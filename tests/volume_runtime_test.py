"""Disposable Railway-style root-owned volume test; never touches existing data."""
import json
import subprocess
import time
import uuid

IMAGE = 'lsc-runtime-fixed:review'
NAME = 'lsc-volume-check-' + uuid.uuid4().hex[:10]
VOLUME = NAME + '-uploads'

def docker(*args, check=True):
    result = subprocess.run(['docker', *args], capture_output=True, text=True, timeout=45)
    if check and result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    return result

def start():
    docker('run', '-d', '--name', NAME, '--pull', 'never', '--network', 'none',
           '--read-only', '--user', '0:0', '--cap-drop', 'ALL',
           '--cap-add', 'CHOWN', '--cap-add', 'FOWNER', '--cap-add', 'SETUID', '--cap-add', 'SETGID',
           '--security-opt', 'no-new-privileges:true', '--pids-limit', '128', '--memory', '512m',
           '--tmpfs', '/tmp:rw,nosuid,nodev,size=128m',
           '--mount', 'type=volume,source=' + VOLUME + ',target=/app/storage/uploads,volume-nocopy',
           '-e', 'PORT=18765', '-e', 'RAILWAY_VOLUME_MOUNT_PATH=/app/storage/uploads', IMAGE)
    for _ in range(30):
        result = docker('exec', '--user', '65532:65532', NAME, '/bin/php', '-r',
                        '$c=curl_init("http://127.0.0.1:18765/healthz");curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>1,CURLOPT_PROXY=>""]);curl_exec($c);exit(curl_getinfo($c,CURLINFO_HTTP_CODE)===200?0:1);', check=False)
        if result.returncode == 0:
            return
        time.sleep(.2)
    raise RuntimeError('Root-owned-volume startup failed: ' + docker('logs', NAME, check=False).stderr)

try:
    docker('volume', 'create', VOLUME)
    start()
    code = '$s=file_get_contents("/proc/1/status");preg_match("/^Uid:\\s+(\\d+)/m",$s,$u);preg_match("/^Gid:\\s+(\\d+)/m",$s,$g);preg_match("/^CapEff:\\s+(\\w+)/m",$s,$c);$p="/app/storage/uploads";echo json_encode(["uid"=>(int)$u[1],"gid"=>(int)$g[1],"caps"=>$c[1],"owner"=>fileowner($p),"group"=>filegroup($p),"writable"=>is_writable($p)]);'
    values = json.loads(docker('exec', '--user', '65532:65532', NAME, '/bin/php', '-r', code).stdout)
    assert values['uid'] == values['gid'] == values['owner'] == values['group'] == 65532, values
    assert int(values['caps'], 16) == 0 and values['writable'], values
    docker('exec', '--user', '65532:65532', NAME, '/bin/php', '-r',
           'if(file_put_contents("/app/storage/uploads/disposable-check.txt","persistent test")===false)exit(1);')
    docker('stop', '--time', '5', NAME)
    docker('rm', NAME)
    start()
    docker('exec', '--user', '65532:65532', NAME, '/bin/php', '-r',
           'exit(file_get_contents("/app/storage/uploads/disposable-check.txt")==="persistent test"?0:1);')
    print('PASS: root-owned mount becomes writable; supervisor UID/GID 65532, no effective capabilities; file survives replacement container')
finally:
    docker('rm', '-f', NAME, check=False)
    docker('volume', 'rm', VOLUME, check=False)
    assert not docker('volume', 'ls', '--filter', 'name=' + VOLUME, '--format', '{{.Name}}').stdout.strip()
    print('Verified disposable volume/container cleanup')
