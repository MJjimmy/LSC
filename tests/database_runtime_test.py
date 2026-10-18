"""Disposable fresh-schema and restart checks; no real database or credentials."""
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import time
import uuid

NAME = 'lsc-db-check-' + uuid.uuid4().hex[:10]
VOLUME = NAME + '-data'
ENV = dict(os.environ, MARIADB_ROOT_PASSWORD=secrets.token_urlsafe(32),
           MARIADB_PASSWORD=secrets.token_urlsafe(32), MARIADB_USER='lsc_check', MARIADB_DATABASE='lsc_check')

def docker(*args, check=True):
    result = subprocess.run(['docker', *args], env=ENV, capture_output=True, text=True, timeout=60)
    if check and result.returncode:
        raise RuntimeError('Docker database check failed: ' + result.stdout + result.stderr)
    return result

def sql(query, check=True):
    return docker('exec', NAME, '/bin/sh', '-c',
                  'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb --protocol=socket --user="$MARIADB_USER" --database="$MARIADB_DATABASE" --batch --skip-column-names -e "$1"',
                  'lsc-check', query, check=check)

def wait():
    result = sql('SELECT 1', check=False)
    for _ in range(90):
        result = sql('SELECT 1', check=False)
        # The vendor's temporary initializer also accepts SQL; wait for PID 1
        # to exec the real server so initialization cannot race this test.
        server = docker('exec', NAME, '/bin/cat', '/proc/1/comm', check=False)
        if result.returncode == 0 and server.stdout.strip() == 'mariadbd':
            return
        if docker('inspect', NAME, '--format', '{{.State.Running}}', check=False).stdout.strip() != 'true':
            break
        time.sleep(.5)
    logs = docker('logs', NAME, check=False)
    message = logs.stdout + logs.stderr + result.stderr
    for name in ['MARIADB_ROOT_PASSWORD', 'MARIADB_PASSWORD']:
        message = message.replace(ENV[name], '[REDACTED]')
    raise RuntimeError('Fresh database did not become ready: ' + message[-6000:])

try:
    docker('volume', 'create', VOLUME)
    docker('run', '-d', '--name', NAME, '--pull', 'never', '--user', '0:0', '--network', 'none',
           '--memory', '512m', '--pids-limit', '128', '--security-opt', 'no-new-privileges:true',
           '--mount', 'type=volume,source=' + VOLUME + ',target=/var/lib/mysql,volume-nocopy',
           '-e', 'MARIADB_ROOT_PASSWORD', '-e', 'MARIADB_PASSWORD', '-e', 'MARIADB_USER', '-e', 'MARIADB_DATABASE',
           'lsc-database:review')
    wait()
    expected = set(re.findall(r'CREATE TABLE `([^`]+)`', (Path(__file__).resolve().parents[1] / 'database/init.sql').read_text()))
    actual = set(sql('SHOW TABLES').stdout.splitlines())
    assert actual == expected, (sorted(actual), sorted(expected))
    status = docker('exec', NAME, '/bin/cat', '/proc/1/status').stdout
    assert re.search(r'^Uid:\s+65532\s', status, re.M), 'Database must drop root'
    capabilities = re.search(r'^CapEff:\s+(\w+)', status, re.M)
    assert capabilities is not None and int(capabilities[1], 16) == 0
    sql('CREATE TABLE runtime_persistence_check (id INT PRIMARY KEY); INSERT INTO runtime_persistence_check VALUES (1)')
    docker('restart', '--time', '10', NAME)
    wait()
    assert sql('SELECT id FROM runtime_persistence_check').stdout.strip() == '1'
    print('PASS: fresh schema has ' + str(len(expected)) + ' tables; application DB user works; database drops root and preserves data across restart')
finally:
    docker('rm', '-f', NAME, check=False)
    docker('volume', 'rm', VOLUME, check=False)
    assert not docker('volume', 'ls', '--filter', 'name=' + VOLUME, '--format', '{{.Name}}').stdout.strip()
    print('Verified disposable database/volume cleanup')
