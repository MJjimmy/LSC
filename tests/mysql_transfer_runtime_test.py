"""Disposable MariaDB -> MySQL preservation test; never targets Railway."""
from pathlib import Path
import json, os, secrets, subprocess, time, uuid
ROOT=Path(__file__).resolve().parents[1]
PHP='cgr.dev/chainguard/php@sha256:0da2195607be7420a4d048d0f6bd6e3e8d9dc13c441a09afc30890ed0ec2a218'
MYSQL='mysql@sha256:24989179f58856db763f418010c1169fecc944e3ec1ea7e921572d83eb070f70'

def main():
    assert (ROOT/'bin/transfer-to-mysql.php').exists(), 'Missing safe transfer implementation'
    key='lsc-transfer-'+uuid.uuid4().hex[:10]
    src=key+'-source'; dst=key+'-target'
    password=secrets.token_hex(24); rootpass=secrets.token_hex(24)
    env=os.environ.copy(); env.update({'MARIADB_DATABASE':'lsc_db','MARIADB_USER':'lsc_user','MARIADB_PASSWORD':password,'MARIADB_ROOT_PASSWORD':rootpass,'MYSQL_DATABASE':'railway','MYSQL_ROOT_PASSWORD':rootpass,'LSC_TRANSFER_CONFIRM':'copy-preserve-source','LSC_TRANSFER_SRC_HOST':src,'LSC_TRANSFER_SRC_DATABASE':'lsc_db','LSC_TRANSFER_SRC_USER':'lsc_user','LSC_TRANSFER_SRC_PASSWORD':password,'LSC_TRANSFER_DST_HOST':dst,'LSC_TRANSFER_DST_DATABASE':'railway','LSC_TRANSFER_DST_USER':'root','LSC_TRANSFER_DST_PASSWORD':rootpass,'LSC_TRANSFER_APP_USER':'lsc_user','LSC_TRANSFER_APP_PASSWORD':password})
    transfer_keys=[k for k in env if k.startswith('LSC_TRANSFER_')]
    def run(args, check=True, input=None):
        p=subprocess.run(args,env=env,input=input,text=True,capture_output=True,timeout=180)
        if check and p.returncode:
            # Fixture secrets never enter failure output.
            detail=(p.stdout+p.stderr).replace(password,'[fixture]').replace(rootpass,'[fixture]')
            raise RuntimeError(detail[-4000:])
        return p
    def php(code=None, script=None, check=True):
        cmd=['docker','run','--rm','-i','--network',key,'--cap-drop','ALL','--read-only','--tmpfs','/tmp:rw,nosuid,nodev,noexec,size=16m','--mount',f'type=bind,source={ROOT.as_posix()},target=/work,readonly','--entrypoint','/bin/php']
        for k in transfer_keys: cmd+=['-e',k]
        cmd += [PHP]
        cmd += ['-r',code] if code is not None else [script]
        return run(cmd, check=check)
    connect="function db($p){return new PDO('mysql:host='.getenv('LSC_TRANSFER_'.$p.'_HOST').';dbname='.getenv('LSC_TRANSFER_'.$p.'_DATABASE').';charset=utf8mb4',getenv('LSC_TRANSFER_'.$p.'_USER'),getenv('LSC_TRANSFER_'.$p.'_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}"
    try:
        run(['docker','pull',MYSQL])
        run(['docker','network','create','--internal',key])
        cmd=['docker','run','-d','--name',src,'--network',key,'--user','0:0']
        for k in ['MARIADB_DATABASE','MARIADB_USER','MARIADB_PASSWORD','MARIADB_ROOT_PASSWORD']:cmd+=['-e',k]
        run(cmd+['lsc-database:review'])
        cmd=['docker','run','-d','--name',dst,'--network',key]
        for k in ['MYSQL_DATABASE','MYSQL_ROOT_PASSWORD']:cmd+=['-e',k]
        run(cmd+[MYSQL,'--performance_schema=0','--innodb-buffer-pool-size=64M','--disable-log-bin'])
        for _ in range(80):
            p=php(connect+"try{db('SRC'); db('DST'); echo 'ready';}catch(Throwable $e){exit(1);}",check=False)
            if p.returncode==0:break
            time.sleep(1)
        else:raise RuntimeError('Disposable database readiness failed')
        # Use IDs, hashes, nulls, unicode, FK relationships and deleted high IDs.
        php(connect+"$p=db('SRC'); $p->exec(\"INSERT INTO users(user_id,name,email,password_hash,role,phone_number,bio) VALUES (7,'Fixture Ω','fixture@example.invalid','fixture-hash-preserve','lecturer','+27000000000',NULL),(99,'Deleted','deleted@example.invalid','hash','student',NULL,NULL)\");$p->exec('DELETE FROM users WHERE user_id=99');$p->exec(\"INSERT INTO study_groups(group_id,group_name,created_by) VALUES(4,'Fixture group',7)\");$p->exec(\"INSERT INTO group_members(member_id,group_id,user_id) VALUES(5,4,7)\");$p->exec(\"INSERT INTO messages(id,group_id,user_id,message) VALUES(6,4,7,'quoted \\\"message\\\" Ω')\");")
        env['LSC_TRANSFER_CONFIRM']='inspect-only'
        inspected=php(script='/work/bin/transfer-to-mysql.php')
        summary=json.loads(inspected.stdout.split('LSC_MYSQL_TRANSFER_INSPECTED ',1)[1])
        assert summary['target']=={} or summary['target']==[]
        php(connect+"if(db('DST')->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn()!=0)exit(2);")
        print('PASS: inspection mode makes no target changes')
        env['LSC_TRANSFER_CONFIRM']='copy-preserve-source'
        result=php(script='/work/bin/transfer-to-mysql.php')
        assert 'LSC_MYSQL_TRANSFER_VERIFIED' in result.stdout, result.stdout
        print(result.stdout.strip())
        php(connect+"$a=db('SRC');$b=db('DST');foreach(['users','study_groups','group_members','messages'] as $t){if($a->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC)!=$b->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC))exit(2);}if($b->query(\"SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA='railway' AND TABLE_NAME='users'\")->fetchColumn()<100)exit(3);echo 'Preservation including auto-increment passed';")
        again=php(script='/work/bin/transfer-to-mysql.php')
        assert 'LSC_MYSQL_TRANSFER_VERIFIED' in again.stdout
        print('PASS: identical completed transfer is safely verified on retry')
        php(connect+"db('DST')->exec(\"UPDATE users SET name='Target-only change' WHERE user_id=7\");")
        refused=php(script='/work/bin/transfer-to-mysql.php',check=False)
        assert refused.returncode!=0 and 'LSC_MYSQL_TRANSFER_VERIFIED' not in refused.stdout
        php(connect+"if(db('DST')->query('SELECT name FROM users WHERE user_id=7')->fetchColumn()!=='Target-only change')exit(2);if(db('SRC')->query('SELECT name FROM users WHERE user_id=7')->fetchColumn()!=='Fixture Ω')exit(3);")
        print('PASS: differing non-empty target is refused without changing either database')
        env['LSC_TRANSFER_CONFIRM']=''
        denied=php(script='/work/bin/transfer-to-mysql.php',check=False)
        assert denied.returncode!=0
        print('PASS: absent explicit transfer confirmation fails closed')
        print('All isolated MariaDB-to-MySQL transfer checks passed.')
    finally:
        for name in [src,dst]:run(['docker','rm','-fv',name],check=False)
        run(['docker','network','rm',key],check=False)

if __name__=='__main__':main()
