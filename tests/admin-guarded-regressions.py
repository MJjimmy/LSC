"""Bounded offline regression batch using only existing PHP/Python runtimes.
No containers, package installs, credentials, providers, or real databases.
"""
import json
import os
from pathlib import Path
import resource
import shutil
import signal
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = [
    'admin-boundaries.php', 'security_csrf_regression.php', 'security_task_membership.php',
    'security_scalar_boundary.php', 'stage2-php.php', 'help-profile.php', 'app-sign-in.php',
    'google-sign-in.php', 'resend-password-reset.php', 'group_wiring_test.py',
]
for entry in (ROOT / '.env', ROOT / 'tests' / '.env'):
    if os.path.lexists(entry):
        raise RuntimeError('BLOCKED: source environment entry present; refusing to read it')
for name in SCRIPTS:
    path = ROOT / 'tests' / name
    if path.is_symlink() or not path.is_file() or path.resolve().parent != ROOT / 'tests':
        raise RuntimeError('BLOCKED: unexpected regression input path')
runner_temp = os.environ.get('RUNNER_TEMP')
if not runner_temp or not Path(runner_temp).is_dir():
    raise RuntimeError('BLOCKED: designated runner scratch unavailable')
php = shutil.which('php')
if not php:
    raise RuntimeError('BLOCKED: preinstalled PHP missing; do not install')
base_env = {'PATH': os.environ.get('PATH', '')}
# -n prevents inherited php.ini and auto-prepend code even for this metadata probe.
probe = subprocess.run([php, '-n', '-r',
    'echo json_encode(["version"=>PHP_VERSION,"extensions"=>get_loaded_extensions(),"extension_dir"=>ini_get("extension_dir")]);'],
    env=base_env, capture_output=True, text=True, timeout=10, check=True)
if probe.stderr:
    raise RuntimeError('BLOCKED: clean PHP metadata probe produced stderr')
info = json.loads(probe.stdout)
if tuple(int(x) for x in info['version'].split('.')[:2]) < (8, 1):
    raise RuntimeError('BLOCKED: preinstalled PHP is too old')

with tempfile.TemporaryDirectory(prefix='lsc-offline-', dir=runner_temp) as owned:
    scratch = Path(owned)
    for name in ('tmp', 'sessions', 'logs'):
        (scratch / name).mkdir(mode=0o700)
    directives = [
        'memory_limit=128M', 'display_errors=0', 'display_startup_errors=0',
        'log_errors=1', 'error_reporting=32767',
        'allow_url_fopen=0', 'allow_url_include=0',
        'disable_functions=mail,curl_exec,curl_multi_exec',
        'auto_prepend_file=', 'auto_append_file=',
        'session.auto_start=0', 'session.save_handler=files', 'session.use_cookies=0',
        'session.use_strict_mode=1', 'date.timezone=UTC',
        'sys_temp_dir="' + str(scratch / 'tmp') + '"',
        'session.save_path="' + str(scratch / 'sessions') + '"',
        'error_log="' + str(scratch / 'logs' / 'php-errors.log') + '"',
    ]
    builtins = {x.lower() for x in info['extensions']}
    extension_dir = Path(info['extension_dir']).resolve()
    # Load only named, already installed extensions into a fresh inherited ini.
    # This also applies to PHP children started by the existing test scripts.
    for name in ('pdo', 'pdo_sqlite', 'mbstring', 'curl', 'session', 'openssl'):
        if name in builtins:
            continue
        shared = extension_dir / (name + '.so')
        if not shared.is_file():
            raise RuntimeError('BLOCKED: preinstalled required extension missing: ' + name)
        directives.append('extension="' + str(shared) + '"')
    ini = scratch / 'php.ini'
    ini.write_text('\n'.join(directives) + '\n', encoding='utf-8')
    env = dict(base_env, PHPRC=str(ini), PHP_INI_SCAN_DIR='',
        RUNNER_TEMP=str(scratch / 'tmp'), TMPDIR=str(scratch / 'tmp'),
        TMP=str(scratch / 'tmp'), TEMP=str(scratch / 'tmp'),
        APP_ENV='testing', APP_DEBUG='0', APP_URL='https://lsc.example.invalid',
        EMAIL_DELIVERY_ENABLED='0', NOTIFICATION_PROCESSOR_ENABLED='0', NOTIFICATION_WORKER_ENABLED='0')
    verified = subprocess.run([php, '-r',
        'foreach (["pdo_sqlite","mbstring","curl","session","openssl"] as $e) { if (!extension_loaded($e)) throw new RuntimeException("Missing ".$e); } if (!function_exists("proc_open")) throw new RuntimeException("proc_open unavailable"); echo "Clean isolated PHP runtime ready";'],
        env=env, capture_output=True, text=True, timeout=10, check=True)
    if verified.stderr:
        raise RuntimeError('BLOCKED: isolated PHP configuration produced stderr')
    print(verified.stdout, flush=True)
    results = []
    def child_limits():
        getattr(resource, 'setrlimit')(getattr(resource, 'RLIMIT_FSIZE'), (2 * 1024 * 1024, 2 * 1024 * 1024))

    for name in SCRIPTS:
        command = [sys.executable, '-I', str(ROOT / 'tests' / name)] if name.endswith('.py') else [php, str(ROOT / 'tests' / name)]
        output_path = scratch / 'logs' / (name + '.stdout')
        error_path = scratch / 'logs' / (name + '.stderr')
        reason = None
        with output_path.open('wb') as output, error_path.open('wb') as error:
            process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=output, stderr=error,
                start_new_session=True, preexec_fn=child_limits)
            deadline = time.monotonic() + 45
            try:
                while process.poll() is None:
                    if time.monotonic() > deadline:
                        reason = 'process-tree deadline exceeded'
                        break
                    if output_path.stat().st_size + error_path.stat().st_size > 2 * 1024 * 1024:
                        reason = 'output bound exceeded'
                        break
                    time.sleep(0.05)
            finally:
                try:
                    os.killpg(process.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
                process.wait(timeout=5)
        if output_path.stat().st_size + error_path.stat().st_size > 2 * 1024 * 1024:
            reason = 'output bound exceeded'
        with output_path.open(errors='replace') as stream:
            output = stream.read(16000)
        with error_path.open(errors='replace') as stream:
            error = stream.read(16000)
        # unittest writes its normal successful summary to stderr; PHP does not.
        ok = reason is None and process.returncode == 0 and (name.endswith('.py') or not error)
        results.append({'script': name, 'passed': ok, 'returncode': process.returncode, 'bound_failure': reason})
        print(('PASS: ' if ok else 'FAIL: ') + name, flush=True)
        if output:
            print(output[:16000], flush=True)
        if error:
            print(error[:16000], flush=True)
        if not ok:
            break
    php_log = scratch / 'logs' / 'php-errors.log'
    if php_log.exists() and php_log.stat().st_size:
        print('FAIL: PHP emitted warnings/errors into the isolated error log', flush=True)
        with php_log.open(errors='replace') as stream:
            print(stream.read(16000), flush=True)
        results.append({'script': 'isolated-php-error-log', 'passed': False})
    complete = len(results) == len(SCRIPTS) and all(row['passed'] for row in results)
    print(json.dumps({'offline_regressions': results, 'all_requested_executed': len(results) == len(SCRIPTS),
        'passed': complete, 'mysql_integration': 'NOT RUN', 'production_changed': False}), flush=True)
    raise SystemExit(0 if complete else 1)
