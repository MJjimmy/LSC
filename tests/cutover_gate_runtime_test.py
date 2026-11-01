"""Prove the optional cutover gate blocks DB-backed web requests, not health/CLI."""
from pathlib import Path
import subprocess,time,uuid,urllib.request,urllib.error
ROOT=Path(__file__).resolve().parents[1]
name='lsc-cutover-gate-'+uuid.uuid4().hex[:10]
def run(args):
    p=subprocess.run(args,text=True,capture_output=True,timeout=40)
    if p.returncode:raise RuntimeError(p.stderr)
    return p.stdout.strip()
def main():
    try:
        run(['docker','run','-d','--name',name,'--read-only','--cap-drop','ALL','--tmpfs','/tmp:rw,nosuid,nodev,noexec,size=64m','-e','PORT=18765','-e','LSC_DB_CUTOVER_MAINTENANCE=1','--mount',f'type=bind,source={(ROOT/"config/bootstrap.php").as_posix()},target=/app/config/bootstrap.php,readonly','-p','127.0.0.1::18765','lsc-runtime-fixed:review'])
        port=run(['docker','port',name,'18765/tcp'])
        assert port.startswith('127.0.0.1:')
        opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
        base='http://'+port
        for _ in range(30):
            try:
                with opener.open(base+'/healthz',timeout=2) as r:
                    if r.status==200:break
            except (OSError,urllib.error.URLError):pass
            time.sleep(.3)
        else:raise RuntimeError('Local health check did not become ready')
        for path,data in [('/',None),('/login.php',None),('/register.php',b'email=blocked'),('/groups.php',None)]:
            try:opener.open(urllib.request.Request(base+path,data=data),timeout=4);raise AssertionError('Request was not gated')
            except urllib.error.HTTPError as e:
                assert e.code==503 and e.headers.get('Retry-After')=='60'
                assert b'database connection is updated' in e.read()
        print('PASS: GET/POST cutover gate blocks app traffic before database access; health stays available')
        output=run(['docker','exec','-e','LSC_DB_CUTOVER_MAINTENANCE=1',name,'/bin/php','-r',"require '/app/config/bootstrap.php'; echo 'CLI_OK';"])
        assert output=='CLI_OK'
        print('PASS: CLI migration tasks are not blocked by the web maintenance gate')
    finally:
        subprocess.run(['docker','rm','-fv',name],capture_output=True,timeout=20)
if __name__=='__main__':main()
