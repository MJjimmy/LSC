"""Owned Linux hosted supervisor; offline fixtures, not a network sandbox."""
import json
import os
from pathlib import Path
import resource
import re
import shutil
import signal
import subprocess
import sys
import tempfile
import time
ROOT = Path(__file__).resolve().parents[1]
allowed = {'stage3-rehash.php', 'final-auth.php', 'app-sign-in.php', 'security_csrf_regression.php', 'security_scalar_boundary.php', 'google-sign-in.php', 'resend-mailer.php', 'resend-worker.php', 'resend-password-reset.php', 'help-profile.php', 'ui-fix.php', 'final-auth-controllers.php', 'oauth-link-revocation.php'}
names = sys.argv[1:]
assert names and set(names) <= allowed and len(names) == len(set(names))
assert not any(p.is_symlink() or p.name == '.env' for p in ROOT.rglob('*')), 'source symlink/environment refused'
assert not os.path.lexists(ROOT / '.env')
assert all((ROOT/'tests'/name).is_file() for name in names)
php = shutil.which('php')
assert php, 'BLOCKED: preinstalled PHP missing; do not install'
base = {'PATH': os.environ.get('PATH', '')}
probe = subprocess.run([php, '-n', '-r', 'echo json_encode(["version"=>PHP_VERSION,"extensions"=>get_loaded_extensions(),"extension_dir"=>ini_get("extension_dir")]);'], env=base, capture_output=True, text=True, timeout=10, check=True)
assert not probe.stderr
info = json.loads(probe.stdout)
assert tuple(map(int, info['version'].split('.')[:2])) >= (8, 1)
with tempfile.TemporaryDirectory(prefix='lsc-auth-', dir=os.environ['RUNNER_TEMP']) as directory:
    owned = Path(directory).resolve()
    assert not owned.is_relative_to(ROOT)
    (owned/'owned').write_text('lsc-auth-offline')
    for name in ('sessions','tmp','logs'): (owned/name).mkdir(mode=0o700)
    directives = ['memory_limit=128M','display_errors=0','display_startup_errors=0','log_errors=1','error_reporting=32767','allow_url_fopen=0','allow_url_include=0','disable_functions=mail,curl_exec,curl_multi_exec','auto_prepend_file=','auto_append_file=','session.auto_start=0','session.save_handler=files','session.use_cookies=0','session.use_strict_mode=1','date.timezone=UTC','sys_temp_dir="'+str(owned/'tmp')+'"','session.save_path="'+str(owned/'sessions')+'"','error_log="'+str(owned/'logs'/'php-errors.log')+'"']
    for name in ('pdo','pdo_sqlite','mbstring','curl','session','openssl'):
        if name not in {e.lower() for e in info['extensions']}:
            shared = Path(info['extension_dir'])/(name+'.so')
            assert shared.is_file(), 'BLOCKED: missing preinstalled extension '+name
            directives.append('extension="'+str(shared)+'"')
    ini=owned/'php.ini'; ini.write_text('\n'.join(directives)+'\n')
    env=dict(base, PHPRC=str(ini), PHP_INI_SCAN_DIR='', RUNNER_TEMP=str(owned/'tmp'), TMPDIR=str(owned/'tmp'), TMP=str(owned/'tmp'), TEMP=str(owned/'tmp'), FINAL_AUTH_TEMP=str(owned), AUTH_SECURITY_CI='isolated', APP_ENV='testing', APP_DEBUG='0', APP_URL='https://offline.example.invalid', EMAIL_DELIVERY_ENABLED='0', NOTIFICATION_PROCESSOR_ENABLED='0', NOTIFICATION_WORKER_ENABLED='0')
    verified=subprocess.run([php,'-r','foreach (["pdo_sqlite","mbstring","curl","session","openssl"] as $e) if (!extension_loaded($e)) throw new RuntimeException("Missing ".$e); echo "Clean isolated PHP ready";'],env=env,capture_output=True,text=True,timeout=10,check=True)
    assert not verified.stderr
    print(verified.stdout,flush=True)
    def limits():
        getattr(resource,'setrlimit')(getattr(resource,'RLIMIT_FSIZE'),(2097152,2097152))
        getattr(resource,'setrlimit')(getattr(resource,'RLIMIT_CPU'),(90,90))
    results=[]; php_log=owned/'logs'/'php-errors.log'
    assert not php_log.exists() or not php_log.stat().st_size
    linted=0
    for directory in ('app','bin','config','public','views','tests'):
        for path in sorted((ROOT/directory).rglob('*.php')):
            lint=subprocess.run([php,'-l',str(path)],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,timeout=10,preexec_fn=limits)
            assert lint.returncode==0 and not lint.stderr, 'BLOCKED: PHP lint failed: '+str(path.relative_to(ROOT))
            linted+=1
    assert not php_log.exists() or not php_log.stat().st_size, 'BLOCKED: lint emitted PHP errors'
    print('PHP syntax checks passed: '+str(linted),flush=True)
    for name in names:
        out=owned/'logs'/(name+'.out'); err=owned/'logs'/(name+'.err')
        offset=php_log.stat().st_size if php_log.exists() else 0
        reason=None
        with out.open('wb') as stdout, err.open('wb') as stderr:
            process=subprocess.Popen([php,str(ROOT/'tests'/name)],cwd=ROOT,env=env,stdout=stdout,stderr=stderr,start_new_session=True,preexec_fn=limits)
            deadline=time.monotonic()+120
            try:
                while process.poll() is None:
                    if time.monotonic()>deadline or out.stat().st_size+err.stat().st_size>2097152 or (php_log.exists() and php_log.stat().st_size>2097152):
                        reason='process/output bound exceeded'; break
                    time.sleep(.05)
            finally:
                try: getattr(os,'killpg')(process.pid,getattr(signal,'SIGKILL'))
                except ProcessLookupError: pass
                process.wait(timeout=5)
        with out.open(errors='replace') as stream: output=stream.read(32000)
        with err.open(errors='replace') as stream: error=stream.read(16000)
        logged=''
        if php_log.exists():
            with php_log.open(errors='replace') as stream:
                stream.seek(offset); logged=stream.read(16000)
        expected_fake_error=(name=='resend-password-reset.php' and re.fullmatch(r'\[[^\]]+\] LSC reset email not accepted: Resend request failed \(HTTP 503\)\.\s*',logged) is not None)
        unexpected_log=bool(logged) and not expected_fake_error
        ok=reason is None and process.returncode==0 and not error and not unexpected_log
        results.append({'script':name,'passed':ok,'returncode':process.returncode,'bound_failure':reason,'unexpected_php_log':unexpected_log,'expected_fake503_log_count':1 if expected_fake_error else 0})
        print(output,end='',flush=True)
        if error: print(error,flush=True)
        if unexpected_log: print(logged,flush=True)
    print(json.dumps({'results':results,'scripts_executed':len(results),'php_files_linted':linted,'passed':all(x['passed'] for x in results),'mysql':'NOT RUN','http':'NOT RUN','production_changed':False}),flush=True)
    raise SystemExit(0 if all(x['passed'] for x in results) else 1)
