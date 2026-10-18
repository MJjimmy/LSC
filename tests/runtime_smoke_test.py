"""Local, isolated runtime smoke test. Does not publish ports or access live data."""
import json,subprocess,time,uuid
IMAGE='lsc-runtime-fixed:review'
NAME='lsc-fixed-check-'+uuid.uuid4().hex[:10]
def docker(args,check=True):
 r=subprocess.run(['docker',*args],capture_output=True,text=True,timeout=45)
 if check and r.returncode:raise RuntimeError(r.stdout+r.stderr)
 return r
port='18765'
probe=r'''$out=[];foreach(['/healthz','/','/composer.json','/.env','/database/init.sql','/config/bootstrap.php','/runtime/prepare.php','/unknown-page'] as $p){$c=curl_init('http://127.0.0.1:18765'.$p);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>2,CURLOPT_PROXY=>'',CURLOPT_HTTPHEADER=>['X-Forwarded-Proto: https']]);$body=curl_exec($c);$out[$p]=['status'=>curl_getinfo($c,CURLINFO_HTTP_CODE),'poweredBy'=>stripos((string)$body,'X-Powered-By:')!==false];}echo json_encode($out);'''
try:
 docker(['run','-d','--pull','never','--name',NAME,'--network','none','--read-only','--cap-drop','ALL','--security-opt','no-new-privileges:true','--pids-limit','128','--memory','512m','--tmpfs','/tmp:rw,nosuid,nodev,size=128m','-e','PORT='+port,IMAGE])
 for _ in range(30):
  r=docker(['exec',NAME,'/bin/php','-r',probe],False)
  if r.returncode==0:
   results=json.loads(r.stdout)
   if results['/healthz']['status']==200:break
  time.sleep(.2)
 else:raise RuntimeError(r.stdout+r.stderr+docker(['logs',NAME],False).stdout+docker(['logs',NAME],False).stderr)
 assert results['/']['status']==200,results
 for p,v in results.items():
  assert v['status']==(200 if p in ['/','/healthz'] else 404),(p,v)
  assert not v['poweredBy'],(p,v)
 print('PASS: eight HTTP paths, private paths blocked, non-default PORT, no PHP version header')
 print(json.dumps(results))
 code=r'''foreach(['pdo_mysql','mbstring','curl'] as $e){if(!extension_loaded($e))exit(1);} echo 'PHP '.PHP_VERSION.'; required extensions loaded; libxml extension='.(extension_loaded('libxml')?'present':'absent')."\n"; $s=file_get_contents('/lib/apk/db/installed');foreach(explode("\n\n",$s) as $p){if(preg_match('/^P:(zlib|libxml2)$/m',$p)){preg_match('/^P:(.+)$/m',$p,$n);preg_match('/^V:(.+)$/m',$p,$v);echo $n[1].' '.$v[1]."\n";}}'''
 print(docker(['exec',NAME,'/bin/php','-r',code]).stdout)
 lint=r'''$n=0;foreach(['app','bin','config','public','views','runtime'] as $d){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/app/'.$d,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->getExtension()!=='php')continue;$proc=proc_open(['/bin/php','-l',$f->getPathname()],[1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))exit(1);$out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($proc);if($status){fwrite(STDERR,$out);exit(1);}$n++;}}echo 'PHP files linted: '.$n."\n";'''
 print(docker(['exec',NAME,'/bin/php','-r',lint]).stdout)
 for service in ['nginx', 'php-fpm']:
  service_path='/tmp/lsc/sv/'+service
  previous_pid=docker(['exec',NAME,'/bin/s6-svstat','-o','pid',service_path]).stdout.strip()
  docker(['exec',NAME,'/bin/s6-svc','-t',service_path])
  for _ in range(30):
   r=docker(['exec',NAME,'/bin/php','-r',probe],False)
   current_pid=docker(['exec',NAME,'/bin/s6-svstat','-o','pid',service_path],False).stdout.strip()
   if r.returncode==0 and json.loads(r.stdout)['/healthz']['status']==200 and current_pid.isdigit() and int(current_pid)>0 and current_pid!=previous_pid:break
   time.sleep(.2)
  else:raise RuntimeError(service+' supervision did not recover')
  print('PASS: supervisor restarts '+service+' after service termination')
 docker(['stop','--time','5',NAME])
 state=json.loads(docker(['inspect',NAME,'--format','{{json .State}}']).stdout)
 assert state['ExitCode'] in [0,143],state
 print('PASS: container stop, exit='+str(state['ExitCode']))
finally:
 docker(['rm','-f',NAME],False)
 assert not docker(['ps','-a','--filter','name='+NAME,'--format','{{.Names}}']).stdout.strip()
 print('Verified disposable container cleanup; no existing service or database changed')
for invalid in ['abc', '0', '65536', '-1']:
 r=docker(['run','--rm','--pull','never','--network','none','--read-only','--cap-drop','ALL','--security-opt','no-new-privileges:true','--tmpfs','/tmp:rw,nosuid,nodev,size=32m','-e','PORT='+invalid,IMAGE],False)
 assert r.returncode==1 and 'PORT must be an integer' in r.stderr,(invalid,r.returncode,r.stderr)
print('PASS: invalid PORT values rejected before either server starts')
