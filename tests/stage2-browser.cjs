#!/usr/bin/env node
'use strict';
/* Real Chromium regression test; Node >=22 (native WebSocket), no dependencies.
 * node tests/stage2-browser.cjs --output /absolute/evidence.json
 * Options: --candidate-root DIR --browser EXE --profile-parent DIR --output FILE
 * Exit 0: all assertions pass; 1: security regression; 2: harness/setup error.
 * Only synthetic loopback fixtures execute; PHP is stripped, never interpreted.
 * Offline means the loopback server destroys request sockets, including worker
 * fetches (unlike page-only CDP offline emulation). Browser sandbox stays enabled.
 */
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const os = require('node:os');
const crypto = require('node:crypto');
const {spawn, spawnSync, execFileSync} = require('node:child_process');
const REF = '38f93eb393be14e0371311406f827e693fbca770';
const NATIVE_REF = '8925f16b0c14b91ad0ea13a80502a7f3f593b86e';
const args = process.argv.slice(2), options = {};
for (let i = 0; i < args.length; i += 2) {
  if (!['--candidate-root','--browser','--profile-parent','--output'].includes(args[i]) || !args[i+1])
    throw new Error('Expected --candidate-root DIR --browser EXE --profile-parent DIR --output FILE');
  options[args[i]] = args[i+1];
}
const root = path.resolve(options['--candidate-root'] || path.join(__dirname, '..'));
const id = `${Date.now()}-${crypto.randomBytes(5).toString('hex')}`;
const profile = path.join(path.resolve(options['--profile-parent'] || (process.platform === 'win32' ? path.dirname(root) : os.tmpdir())), `browser-profile-${id}`);
const output = path.resolve(options['--output'] || path.join(path.dirname(root), `stage2-browser-${id}.json`));
const evidence = {startedAt:new Date().toISOString(), candidateRoot:root, baselineRef:REF, output, profile, assertions:[], observations:{}, errors:[]};
let browser, server, socket, cdp, session, online = true, phase = 'baseline', candidate, baseline, nativeBaseline, origin;
let nativeRequests = 0;
const nativeResponses = [];
let stderr = '', posts = 0, workerRequests = [], fatal = false, cleaning;
const pending = new Map(); let seq = 0;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const sha = text => crypto.createHash('sha256').update(text).digest('hex');
function check(name, ok, detail) { evidence.assertions.push({name, pass:!!ok, detail}); console.log(`${ok?'PASS':'FAIL'} ${name}`); }
function stripPHP(text) {
  if (!/^\s*<\?php[\s\S]*?\?>/.test(text)) throw new Error('Worker PHP header not found');
  return text.replace(/^\s*<\?php[\s\S]*?\?>\s*/, '');
}
async function until(fn, label, timeout = 8000) {
  const end = Date.now() + timeout; let last;
  do { try { const result = await fn(); if (result) return result; } catch(e) {last=e;} await sleep(70); } while(Date.now()<end);
  throw new Error(`Timeout: ${label}${last ? ': '+last.message : ''}`);
}
function send(method, params = {}, sid = session) {
  return new Promise((resolve,reject) => {
    const id = ++seq;
    const timer = setTimeout(() => {pending.delete(id); reject(new Error(`CDP timeout: ${method}`));}, 10000);
    pending.set(id, {resolve,reject,timer});
    socket.send(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})}));
  });
}
async function evaluate(expression) {
  const r = await send('Runtime.evaluate', {expression,awaitPromise:true,returnByValue:true});
  if(r.exceptionDetails) throw new Error(`Browser expression failed: ${JSON.stringify(r.exceptionDetails)}`);
  return r.result.value;
}
async function navigate(route) {
  // Loader identity prevents accidentally reading the previous same-URL document.
  const old = await evaluate('performance.timeOrigin');
  const result = await send('Page.navigate', {url:origin+route});
  if(result.errorText) return {error:result.errorText, text:''};
  await until(async () => evaluate(`performance.timeOrigin !== ${old} && document.readyState === 'complete'`), 'navigation '+route);
  return evaluate('({url:location.href,text:document.body.innerText})');
}
async function snapshot() {
  return evaluate(`(async()=>{const out={};for(const n of await caches.keys()){const c=await caches.open(n);out[n]=await Promise.all((await c.keys()).map(async r=>({url:r.url,body:await (await c.match(r)).text()})));}return out;})()`);
}
async function fetchText(route, method='GET') {
  return evaluate(`fetch(${JSON.stringify(route)},{method:${JSON.stringify(method)},cache:'no-store'}).then(async r=>({status:r.status,text:await r.text()})).catch(e=>({error:e.name,text:''}))`);
}
async function updateController(label) {
  const changed=await evaluate(`(async()=>{const r=await navigator.serviceWorker.getRegistration();const changed=new Promise(resolve=>navigator.serviceWorker.addEventListener('controllerchange',()=>resolve(true),{once:true}));await r.update();return await Promise.race([changed,new Promise(resolve=>setTimeout(()=>resolve(false),10000))]);})()`);
  if (!changed) throw new Error('Worker did not update: '+label);
  await until(()=>evaluate(`navigator.serviceWorker.getRegistration().then(r=>r.active?.state==='activated')`),label);
}
function observeNativeResponse(response) {
  const pathname=new URL(response.url).pathname;
  if (['/app-auth.php','/oauth.php'].includes(pathname)) {
    nativeResponses.push({pathname,status:response.status,fromServiceWorker:response.fromServiceWorker===true});
  }
}
function fixture(req,res) {
  if(!online) {req.socket.destroy(); return;}
  const u = new URL(req.url, 'http://127.0.0.1');
  res.setHeader('Cache-Control','no-store');
  if(u.pathname === '/service-worker.php') {
    workerRequests.push({phase,time:new Date().toISOString()});
    res.setHeader('Content-Type','application/javascript'); res.setHeader('Service-Worker-Allowed','/');
    res.end((phase==='baseline'?baseline:phase==='native-baseline'?nativeBaseline:candidate)+`\n// stage2-harness-phase:${phase}\n`); return;
  }
  if(u.pathname==='/__login' && req.method==='POST') {
    const who=u.searchParams.get('account');
    if(!['A','B'].includes(who)){res.writeHead(400);res.end();return;}
    res.setHeader('Set-Cookie',`stage2_session=${who}; HttpOnly; SameSite=Strict; Path=/`); res.end('logged in'); return;
  }
  if(u.pathname==='/__logout' && req.method==='POST') {
    res.setHeader('Set-Cookie','stage2_session=; HttpOnly; SameSite=Strict; Path=/; Max-Age=0');res.end('logged out');return;
  }
  if(req.method!=='GET'){posts++;res.end('POST_NETWORK_ONLY');return;}
  if(u.pathname==='/oauth.php') {
    res.writeHead(303,{Location:'/app-auth.php?action=complete'});res.end();return;
  }
  if(u.pathname==='/app-auth.php') {
    nativeRequests++;
    res.writeHead(303,{Location:'lscapp://auth?code='+('c'.repeat(64))+'&state='+('d'.repeat(64))});res.end();return;
  }
  if(u.pathname==='/offline.php'){res.setHeader('Content-Type','text/html');res.end('<!doctype html><body>GENERIC_OFFLINE_FIXTURE</body>');return;}
  if(['/icons/lsc-icon.svg','/icons/lsc-og.jpg'].includes(u.pathname) && !u.search) {
    res.setHeader('Content-Type',u.pathname.endsWith('.svg')?'image/svg+xml':'image/jpeg');
    res.end(u.pathname.endsWith('.svg')?'<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>':'SYNTHETIC_PUBLIC_JPEG');return;
  }
  const who=(req.headers.cookie||'').match(/(?:^|;\s*)stage2_session=([AB])(?:;|$)/)?.[1];
  if(['/account.php','/private-new.php','/private.png'].includes(u.pathname)) {
    res.setHeader('Content-Type',u.pathname.endsWith('.png')?'image/png':'text/html');
    res.setHeader('Cache-Control','private, no-store');
    res.end(`<!doctype html><body>PRIVATE_ACCOUNT_${who||'ANONYMOUS'} ${u.pathname}</body>`);return;
  }
  res.setHeader('Content-Type','text/html');res.end('<!doctype html><body>PUBLIC_FIXTURE</body>');
}
async function cleanup() {
  if(cleaning) return cleaning;
  cleaning=(async()=>{
    if(socket?.readyState===1) {try {await Promise.race([send('Browser.close',{},null),sleep(1000)]);}catch{} socket.close();}
    if(browser && browser.exitCode===null) {
      await Promise.race([new Promise(r=>browser.once('exit',r)),sleep(1800)]);
      if(browser.exitCode===null) {
        if(process.platform==='win32') spawnSync('taskkill',['/PID',String(browser.pid),'/T','/F'],{windowsHide:true,timeout:5000});
        else {try{process.kill(-browser.pid,'SIGKILL');}catch{browser.kill('SIGKILL');}}
      }
    }
    if(server) {server.closeAllConnections(); await new Promise(r=>server.close(r));}
    for(const p of pending.values()){clearTimeout(p.timer);p.reject(new Error('Harness teardown'));} pending.clear();
    try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:8,retryDelay:150}); evidence.profileRemoved=!fs.existsSync(profile);}catch(e){evidence.errors.push('Profile cleanup: '+e.message);fatal=true;}
    evidence.browserStderr=stderr;
    evidence.finishedAt=new Date().toISOString();
    evidence.result=fatal?'HARNESS_ERROR':evidence.assertions.some(a=>!a.pass)?'FAIL':'PASS';
    fs.mkdirSync(path.dirname(output),{recursive:true}); fs.writeFileSync(output,JSON.stringify(evidence,null,2)+'\n');
    console.log(`${evidence.result}: ${output}`);
  })(); return cleaning;
}
async function run() {
  baseline=stripPHP(execFileSync('git',['-C',root,'show',`${REF}:public/service-worker.php`],{encoding:'utf8',timeout:10000}));
  candidate=stripPHP(fs.readFileSync(path.join(root,'public','service-worker.php'),'utf8'));
  nativeBaseline=stripPHP(execFileSync('git',['-C',root,'show',`${NATIVE_REF}:public/service-worker.php`],{encoding:'utf8',timeout:10000}));
  evidence.nativeBaselineRef=NATIVE_REF;evidence.nativeBaselineHash=sha(nativeBaseline);
  evidence.baselineHash=sha(baseline);evidence.candidateHash=sha(candidate);
  evidence.candidateEqualsBaseline=candidate.replace(/\r\n/g,'\n')===baseline.replace(/\r\n/g,'\n');
  const candidates=[options['--browser'],process.env.CHROME_PATH,
    'C:/Users/Jimmy/AppData/Local/BraveSoftware/Brave-Browser/Application/brave.exe',
    '/usr/bin/google-chrome','/usr/bin/google-chrome-stable','/usr/bin/chromium','/usr/bin/chromium-browser','/opt/google/chrome/chrome'].filter(Boolean);
  const exe=candidates.find(p=>fs.existsSync(p));if(!exe)throw new Error('No Chromium browser found; supply --browser');
  evidence.browser=exe;
  fs.mkdirSync(profile,{recursive:false});
  server=http.createServer(fixture); await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(0,'127.0.0.1',resolve);});
  origin=`http://127.0.0.1:${server.address().port}`;evidence.origin=origin;
  const flags=[`--user-data-dir=${profile}`,'--headless=new','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','about:blank'];
  evidence.flags=flags;
  browser=spawn(exe,flags,{stdio:['ignore','ignore','pipe'],windowsHide:true,detached:process.platform!=='win32'});
  browser.on('error',e=>{evidence.errors.push(e.message);});browser.stderr.on('data',b=>{stderr=(stderr+b).slice(-10000);});
  const portFile=path.join(profile,'DevToolsActivePort');
  const port=await until(()=>fs.existsSync(portFile)&&fs.readFileSync(portFile,'utf8').trim().split(/\r?\n/), 'DevTools startup',15000);
  socket=new WebSocket(`ws://127.0.0.1:${port[0]}${port[1]}`);
  await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
  socket.addEventListener('message',e=>{const m=JSON.parse(e.data);const p=pending.get(m.id);if(p){pending.delete(m.id);clearTimeout(p.timer);m.error?p.reject(new Error(JSON.stringify(m.error))):p.resolve(m.result);}if(m.method==='Network.responseReceived')observeNativeResponse(m.params.response);if(m.method==='Network.requestWillBeSent'&&m.params.redirectResponse)observeNativeResponse(m.params.redirectResponse);});
  evidence.browserVersion=await send('Browser.getVersion',{},null);
  const target=await send('Target.createTarget',{url:'about:blank'},null);
  session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
  await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Network.setCacheDisabled',{cacheDisabled:true});
  await navigate('/__harness');
  await evaluate(`navigator.serviceWorker.register('/service-worker.php',{scope:'/',updateViaCache:'none'}).then(()=>navigator.serviceWorker.ready).then(()=>true)`);
  await until(()=>evaluate('!!navigator.serviceWorker.controller'),'baseline controller');
  await fetchText('/__login?account=A','POST');
  check('session cookie is HttpOnly',!(await evaluate('document.cookie')).includes('stage2_session'));
  const a=await navigate('/account.php');check('baseline account A served',a.text.includes('PRIVATE_ACCOUNT_A'),a);
  await until(async()=>JSON.stringify(await snapshot()).includes('PRIVATE_ACCOUNT_A'),'baseline private cache written');
  await fetchText('/__logout','POST'); await fetchText('/__login?account=B','POST');
  online=false;const stale=await navigate('/account.php');
  evidence.observations.baselineOffline=stale;
  check('baseline witness: B session receives cached A offline',stale.text.includes('PRIVATE_ACCOUNT_A'),stale);
  online=true;
  await evaluate(`caches.open('unrelated-application-cache').then(c=>c.put('/unrelated-sentinel',new Response('UNRELATED_SENTINEL')))`);
  evidence.observations.beforeUpgrade=await snapshot();
  phase='candidate';
  const upgrade=await evaluate(`(async()=>{const r=await navigator.serviceWorker.getRegistration();const prior=r.active;const changed=new Promise(resolve=>navigator.serviceWorker.addEventListener('controllerchange',()=>resolve(true),{once:true}));await r.update();return await Promise.race([changed,new Promise(resolve=>setTimeout(()=>resolve(false),10000))]);})()`);
  check('actual candidate update changes controller',upgrade,{workerRequests});
  // controllerchange can precede completion of activation's waitUntil cleanup.
  await until(()=>evaluate(`navigator.serviceWorker.getRegistration().then(r=>r.active?.state==='activated')`),'candidate activation complete');
  evidence.observations.afterActivation=await snapshot();
  const after=evidence.observations.afterActivation;
  check('activation removes legacy lsc-v1 cache',!Object.hasOwn(after,'lsc-v1'),Object.keys(after));
  check('activation preserves unrelated application cache',after['unrelated-application-cache']?.some(e=>e.body==='UNRELATED_SENTINEL'),Object.keys(after));
  // Inspect legacy URL before an online B navigation could overwrite account A.
  online=false;const legacy=await navigate('/account.php');
  check('upgraded offline old-private navigation is generic',legacy.text.includes('GENERIC_OFFLINE_FIXTURE')&&!/PRIVATE_ACCOUNT_[AB]/.test(legacy.text),legacy);
  online=true;
  const b=await navigate('/private-new.php');check('candidate account B served online',b.text.includes('PRIVATE_ACCOUNT_B'),b);
  const bOld=await navigate('/account.php');check('candidate old-private account B served online',bOld.text.includes('PRIVATE_ACCOUNT_B'),bOld);
  await fetchText('/private.png?account=B');
  await evaluate(`fetch('/icons/lsc-icon.svg',{cache:'no-store'}).then(r=>r.arrayBuffer()).then(b=>b.byteLength)`);
  await fetchText('/__mutation','POST');const beforePosts=posts;
  await sleep(200); // old worker does not attach cache.put to event.waitUntil
  evidence.observations.candidateCaches=await snapshot();
  const all=Object.values(evidence.observations.candidateCaches).flat();
  check('private/account HTML absent from every cache',!all.some(e=>/\/(account|private-new)\.php(?:$|\?)/.test(new URL(e.url).pathname)||/PRIVATE_ACCOUNT_[AB]/.test(e.body)),all.filter(e=>/PRIVATE_ACCOUNT_[AB]/.test(e.body)).map(e=>e.url));
  check('query-bearing private .png is not cached',!all.some(e=>new URL(e.url).pathname==='/private.png'),all.map(e=>e.url));
  check('public icon is cached',all.some(e=>new URL(e.url).pathname==='/icons/lsc-icon.svg'),all.map(e=>e.url));
  online=false;
  const newOffline=await navigate('/private-new.php');
  const oldOffline=await navigate('/account.php');
  check('offline new-private navigation is generic',newOffline.text.includes('GENERIC_OFFLINE_FIXTURE')&&!/PRIVATE_ACCOUNT_[AB]/.test(newOffline.text),newOffline);
  check('offline old-private navigation remains generic',oldOffline.text.includes('GENERIC_OFFLINE_FIXTURE')&&!/PRIVATE_ACCOUNT_[AB]/.test(oldOffline.text),oldOffline);
  const postOffline=await fetchText('/__mutation','POST');
  check('non-GET request is not replayed offline',!!postOffline.error&&posts===beforePosts,{postOffline,posts,beforePosts});
  const iconOffline=await evaluate(`fetch('/icons/lsc-icon.svg').then(async r=>({ok:r.ok,bytes:(await r.arrayBuffer()).byteLength})).catch(e=>({error:e.name}))`);
  check('public cached icon available offline',iconOffline.ok&&iconOffline.bytes>0,iconOffline);
  const privateImageOffline=await fetchText('/private.png?account=B');
  check('private query .png cannot replay offline',!!privateImageOffline.error&&!/PRIVATE_ACCOUNT_[AB]/.test(privateImageOffline.text),privateImageOffline);
  evidence.observations.workerRequests=workerRequests;
  // Reproduce the actual mobile failure on v2, then update that SAME controlled client.
  online=true;phase='native-baseline';
  await navigate('/__harness');await updateController('native baseline activated');
  const falseOffline=await navigate('/app-auth.php?action=complete');
  check('v2 witness: online native return is incorrectly replaced by offline page',falseOffline.text.includes('GENERIC_OFFLINE_FIXTURE'),falseOffline);
  phase='candidate';await updateController('native-return candidate activated');
  for(const route of ['/app-auth.php?action=complete','/oauth.php?provider=google']) {
    await navigate('/__harness');
    const offset=nativeResponses.length,before=nativeRequests;
    const navigation=await send('Page.navigate',{url:origin+route});
    await until(()=>nativeRequests>before,'native redirect endpoint reached');
    await sleep(250);
    const document=await evaluate('({url:location.href,text:document.body.innerText})');
    const responses=nativeResponses.slice(offset);
    const nativeDispatch=responses.some(r=>r.status===303&&!r.fromServiceWorker)||!!navigation.errorText;
    check('native return bypasses worker through '+route,nativeDispatch&&!responses.some(r=>r.fromServiceWorker)&&!document.text.includes('GENERIC_OFFLINE_FIXTURE'),{navigation,document,responses});
  }
  const nativeCaches=await snapshot();
  check('auth callback routes are never cached',!Object.values(nativeCaches).flat().some(r=>['/app-auth.php','/oauth.php'].includes(new URL(r.url).pathname)),Object.keys(nativeCaches));
  online=false;
  const stillOffline=await navigate('/private-new.php');
  check('ordinary offline fallback survives native-route bypass',stillOffline.text.includes('GENERIC_OFFLINE_FIXTURE'),stillOffline);
}
let timer=setTimeout(()=>{fatal=true;evidence.errors.push('Overall 80 second deadline exceeded');cleanup().finally(()=>process.exit(2));},80000);
for(const signal of ['SIGINT','SIGTERM']) process.once(signal,()=>{fatal=true;evidence.errors.push(signal);clearTimeout(timer);cleanup().finally(()=>process.exit(2));});
run().catch(e=>{fatal=true;evidence.errors.push(e.stack||String(e));console.error(e);}).finally(async()=>{clearTimeout(timer);await cleanup();process.exitCode=fatal?2:evidence.assertions.some(a=>!a.pass)?1:0;});
