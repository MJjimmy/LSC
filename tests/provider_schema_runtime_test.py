"""Hosted-CI-only schema rehearsal using disposable existing review images.
No public ports, production data, live provider keys, or email delivery.
"""
import os
from pathlib import Path
import re
import secrets
import subprocess
import time
import uuid

NAME = 'lsc-provider-check-' + uuid.uuid4().hex[:10]
VOLUME = NAME + '-data'
NETWORK = NAME + '-net'
WEB = NAME + '-web'
ENV = dict(os.environ, MARIADB_ROOT_PASSWORD=secrets.token_urlsafe(32),
           MARIADB_PASSWORD=secrets.token_urlsafe(32), MARIADB_USER='provider_check',
           MARIADB_DATABASE='provider_check')
ENV['MYSQLPASSWORD'] = ENV['MARIADB_PASSWORD']


def docker(*args, check=True, input_text=None):
    p = subprocess.run(['docker', *args], env=ENV, capture_output=True, text=True, timeout=90, input=input_text)
    if check and p.returncode:
        text = p.stdout + p.stderr
        for key in ['MARIADB_ROOT_PASSWORD', 'MARIADB_PASSWORD']:
            text = text.replace(ENV[key], '[REDACTED]')
        raise RuntimeError(text[-5000:])
    return p


def sql(query, check=True):
    return docker('exec', NAME, '/bin/sh', '-c',
        'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb --protocol=socket --user="$MARIADB_USER" --database="$MARIADB_DATABASE" --batch --skip-column-names -e "$1"',
        'provider-check', query, check=check)


def prepare(check=True):
    return docker('run', '--rm', '--pull', 'never', '--network', NETWORK, '--read-only',
        '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges:true', '--pids-limit', '64',
        '--memory', '256m', '--tmpfs', '/tmp:rw,nosuid,nodev,noexec,size=64m',
        '-e', 'MYSQLHOST=' + NAME, '-e', 'MYSQLDATABASE=provider_check',
        '-e', 'MYSQLUSER=provider_check', '-e', 'MYSQLPORT=3306', '-e', 'MYSQLPASSWORD',
        '--entrypoint', '/bin/php', 'lsc-runtime-fixed:review', '/app/bin/prepare-provider-schema.php', check=check)


try:
    docker('network', 'create', '--internal', NETWORK)
    docker('volume', 'create', VOLUME)
    docker('run', '-d', '--name', NAME, '--pull', 'never', '--network', NETWORK,
        '--user', '0:0', '--memory', '512m', '--pids-limit', '128',
        '--security-opt', 'no-new-privileges:true',
        '--mount', 'type=volume,source=' + VOLUME + ',target=/var/lib/mysql,volume-nocopy',
        '-e', 'MARIADB_ROOT_PASSWORD', '-e', 'MARIADB_PASSWORD', '-e', 'MARIADB_USER',
        '-e', 'MARIADB_DATABASE', 'lsc-database:review')
    for attempt in range(90):
        ready = sql('SELECT 1', check=False)
        process = docker('exec', NAME, '/bin/cat', '/proc/1/comm', check=False)
        if ready.returncode == 0 and process.stdout.strip() == 'mariadbd':
            break
        time.sleep(.5)
    else:
        raise RuntimeError('Disposable provider-schema fixture did not become ready')
    sql("INSERT INTO users(name,email,password_hash,role) VALUES('Fixture','fixture@example.invalid','fixture-not-login-capable','admin')")
    sql("INSERT INTO notification_queue(channel,recipient,subject,body,status) VALUES('email','fixture@example.invalid','Fixture','Unchanged payload','pending')")
    user_query = 'SELECT user_id,name,email,password_hash,role,is_active FROM users ORDER BY user_id'
    queue_query = 'SELECT queue_id,channel,recipient,subject,body,status,attempts,created_at FROM notification_queue ORDER BY queue_id'
    before = (sql(user_query).stdout, sql(queue_query).stdout)
    prepare()
    prepare()
    assert before == (sql(user_query).stdout, sql(queue_query).stdout), 'Users or existing queue data were mutated'
    assert sql("SELECT COUNT(*) FROM oauth_identities").stdout.strip() == '0'
    assert sql("SELECT COUNT(*) FROM notification_queue WHERE provider_message_id IS NOT NULL").stdout.strip() == '0'
    docker('run', '-d', '--name', WEB, '--pull', 'never', '--network', NETWORK,
        '--read-only', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges:true',
        '--memory', '512m', '--pids-limit', '128', '--tmpfs', '/tmp:rw,nosuid,nodev,size=128m',
        '-e', 'PORT=18765', '-e', 'APP_ENV=testing', '-e', 'APP_URL=http://127.0.0.1:18765',
        '-e', 'NOTIFICATION_PROCESSOR_ENABLED=1', '-e', 'NOTIFICATION_WORKER_ENABLED=1',
        '-e', 'EMAIL_DELIVERY_ENABLED=1', '-e', 'NOTIFICATION_MIN_QUEUE_ID=1000000000',
        '-e', 'NOTIFICATION_SEND_AFTER=2000-01-01 00:00:00',
        '-e', 'RESEND_API_KEY=fixture-not-a-real-key', '-e', 'RESEND_FROM_EMAIL=sender@example.invalid',
        '-e', 'GOOGLE_CLIENT_ID=fixture-not-a-real-id', '-e', 'GOOGLE_CLIENT_SECRET=fixture-not-a-real-secret',
        '-e', 'MYSQLHOST=' + NAME, '-e', 'MYSQLDATABASE=provider_check',
        '-e', 'MYSQLUSER=provider_check', '-e', 'MYSQLPORT=3306', '-e', 'MYSQLPASSWORD',
        'lsc-runtime-fixed:review')
    ready_code = "$c=curl_init('http://127.0.0.1:18765/healthz');curl_setopt($c,CURLOPT_RETURNTRANSFER,true);curl_setopt($c,CURLOPT_PROXY,'');curl_exec($c);exit(curl_getinfo($c,CURLINFO_HTTP_CODE)===200?0:1);"
    for attempt in range(60):
        if docker('exec', WEB, '/bin/php', '-r', ready_code, check=False).returncode == 0:
            break
        time.sleep(.2)
    else:
        raise RuntimeError('Disposable fixture web server did not become ready')
    probe = (Path(__file__).resolve().parent / 'app_auth_http_probe.php').read_text()
    result = docker('exec', '-i', WEB, '/bin/php', input_text=probe)
    print(result.stdout)
    # Activation barrier skips fixture backlog; the live daemon must remain supervised.
    audit = docker('exec', WEB, '/bin/php', '/app/bin/audit-notification-queue.php').stdout
    assert '"eligible_email_count":0' in audit and '"processor_enabled":true' in audit, audit
    service_path = '/tmp/lsc/sv/notification-worker'
    previous = docker('exec', WEB, '/bin/s6-svstat', '-o', 'pid', service_path).stdout.strip()
    assert previous.isdigit() and int(previous) > 0, 'Queue worker did not start'
    status = docker('exec', WEB, '/bin/php', '-r', 'echo file_get_contents("/proc/".$argv[1]."/status");', previous).stdout
    assert re.search(r'^Uid:\s+65532\s', status, re.M), 'Queue worker must remain unprivileged'
    docker('exec', WEB, '/bin/s6-svc', '-t', service_path)
    for attempt in range(40):
        current = docker('exec', WEB, '/bin/s6-svstat', '-o', 'pid', service_path, check=False).stdout.strip()
        if current.isdigit() and int(current) > 0 and current != previous:
            break
        time.sleep(.2)
    else:
        raise RuntimeError('Queue supervisor did not restart the processor')
    print('PASS: opt-in queue worker starts unprivileged, skips old fixture mail, and restarts under supervision')

    assert before == (sql(user_query).stdout, sql(queue_query).stdout), 'HTTP fixture changed existing rows'

    # Mismatch tests touch only this disposable fixture, never any deployed database.
    sql('ALTER TABLE oauth_identities DROP INDEX uq_oauth_provider_subject')
    assert prepare(check=False).returncode != 0, 'Wrong unique keys must fail closed'
    sql('ALTER TABLE oauth_identities ADD UNIQUE KEY uq_oauth_provider_subject(provider,subject)')
    sql('ALTER TABLE notification_queue MODIFY provider_message_id VARCHAR(20) NULL')
    assert prepare(check=False).returncode != 0, 'Wrong receipt column must fail closed'
    assert before == (sql(user_query).stdout, sql(queue_query).stdout), 'Mismatch path mutated existing data'
    print('PASS: additive provider schema, repeat execution, exact-key mismatch, receipt-column mismatch, and existing-data preservation')
finally:
    # Independent attempts: a container failure must not skip volume/network cleanup.
    for command in [('rm', '-f', WEB), ('rm', '-f', NAME), ('volume', 'rm', VOLUME), ('network', 'rm', NETWORK)]:
        try:
            docker(*command, check=False)
        except (subprocess.TimeoutExpired, OSError):
            pass
    assert not docker('volume', 'ls', '--filter', 'name=' + VOLUME, '--format', '{{.Name}}').stdout.strip()
    assert docker('network', 'inspect', NETWORK, check=False).returncode != 0
    print('Verified disposable provider-schema fixture cleanup')
