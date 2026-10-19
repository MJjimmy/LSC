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
ENV = dict(os.environ, MARIADB_ROOT_PASSWORD=secrets.token_urlsafe(32),
           MARIADB_PASSWORD=secrets.token_urlsafe(32), MARIADB_USER='provider_check',
           MARIADB_DATABASE='provider_check')
ENV['MYSQLPASSWORD'] = ENV['MARIADB_PASSWORD']


def docker(*args, check=True):
    p = subprocess.run(['docker', *args], env=ENV, capture_output=True, text=True, timeout=90)
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
    for command in [('rm', '-f', NAME), ('volume', 'rm', VOLUME), ('network', 'rm', NETWORK)]:
        try:
            docker(*command, check=False)
        except (subprocess.TimeoutExpired, OSError):
            pass
    assert not docker('volume', 'ls', '--filter', 'name=' + VOLUME, '--format', '{{.Name}}').stdout.strip()
    assert docker('network', 'inspect', NETWORK, check=False).returncode != 0
    print('Verified disposable provider-schema fixture cleanup')
