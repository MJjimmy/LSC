"""Run isolated loopback HTTP controller tests with a supplied PHP executable.
No packages required. Outbound PHP network functions disabled; mail transport is a fixture.
Usage: python tests/auth_invitations_http_test.py /path/to/php.exe /owned/scratch/directory
"""
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = Path(sys.argv[1]).resolve()
SCRATCH = Path(sys.argv[2]).resolve()
assert PHP.is_file() and SCRATCH.is_dir()
assert not (ROOT / '.env').exists(), 'Refusing test run beside a source .env'
work = Path(tempfile.mkdtemp(prefix='auth-http-', dir=SCRATCH))
env = {k: v for k, v in os.environ.items() if k.upper() in {'SYSTEMROOT', 'WINDIR', 'COMSPEC'}}
env.update({
    'PATH': str(PHP.parent), 'TMP': str(work), 'TEMP': str(work),
    'PHP_INI_SCAN_DIR': str(work), 'PHPRC': str(work),
    'LSC_AUTH_TEST_WORKSPACE': str(work), 'APP_ENV': 'test',
    'APP_URL': 'https://lsc.example.invalid', 'EMAIL_DELIVERY_ENABLED': '1',
    'RESEND_API_KEY': 'fixture-not-a-real-key', 'RESEND_FROM_EMAIL': 'sender@example.invalid',
})
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
base = f'http://127.0.0.1:{port}'
if os.name == 'nt':
    php_args = [str(PHP), '-n', '-d', f'extension_dir={PHP.parent / "ext"}',
                '-d', 'extension=php_mbstring.dll', '-d', 'extension=php_pdo_sqlite.dll']
else:
    probe = subprocess.run([str(PHP), '-n', '-r',
                            'echo json_encode(["loaded"=>get_loaded_extensions(),"dir"=>ini_get("extension_dir")]);'],
                           env=env, capture_output=True, text=True, check=True, timeout=10)
    info = json.loads(probe.stdout)
    loaded = {name.lower() for name in info['loaded']}
    php_args = [str(PHP), '-n']
    for name in ['pdo', 'pdo_sqlite', 'mbstring', 'session']:
        if name not in loaded:
            extension = Path(info['dir']) / (name + '.so')
            assert extension.is_file(), 'Missing preinstalled PHP extension: ' + name
            php_args += ['-d', 'extension=' + str(extension)]
args = php_args + [
        '-d', f'session.save_path={work}', '-d', 'session.use_strict_mode=1',
        '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0', '-d', 'memory_limit=128M',
        '-d', 'disable_functions=mail,exec,shell_exec,system,passthru,proc_open,popen,fsockopen,pfsockopen,stream_socket_client,curl_exec',
        '-S', f'127.0.0.1:{port}', '-t', str(work), str(ROOT / 'tests/auth-invitations-http-router.php')]

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def client():
    return urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect(),
                                      urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(browser, path, data=None):
    assert path.startswith('/') and not path.startswith('//')
    body = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response = browser.open(urllib.request.Request(base + path, data=body), timeout=10)
    except urllib.error.HTTPError as exc:
        response = exc
    with response:
        text = response.read().decode()
        assert not re.search(r'(Fatal error|Warning|Parse error):', text), text[:2000]
        return response.status, response.headers, text


checks = []
def check(condition, label):
    assert condition, label
    checks.append(label)


def csrf(text):
    found = re.search(r'name="csrf_token" value="([^"]+)"', text)
    assert found, 'CSRF field missing'
    return html.unescape(found.group(1))


def state(browser):
    status, _, text = request(browser, '/__fixture/state')
    assert status == 200
    return json.loads(text)


log = (work / 'server.log').open('w', encoding='utf-8')
process = subprocess.Popen(args, env=env, cwd=work, stdout=log, stderr=subprocess.STDOUT)
try:
    browser = client()
    for attempt in range(50):
        try:
            current = state(browser)
            break
        except (OSError, urllib.error.URLError):
            if process.poll() is not None:
                raise RuntimeError('PHP test server stopped: ' + (work / 'server.log').read_text())
            time.sleep(0.1)
    else:
        raise RuntimeError('PHP loopback server did not become ready')
    token = current['invite_token']
    invitation = 'invite.php?token=' + token
    continuation = urllib.parse.quote(invitation, safe='')
    status, headers, _ = request(browser, '/' + invitation)
    check(status == 302 and headers['Location'] == 'login.php?next=' + continuation, 'Guest invitation retains continuation to login')
    register = '/register.php?next=' + continuation
    status, _, page = request(browser, register)
    check(status == 200 and '15+' in page, 'Registration displays updated password guidance')
    form = {'csrf_token': csrf(page), 'name': 'HTTP Fixture', 'email': 'new@example.invalid', 'role': 'student',
            'password': '12345678', 'confirm_password': '12345678', 'next': invitation}
    status, _, page = request(browser, register, form)
    check(status == 200 and '15 or more' in page, 'Real registration controller rejects weak password')
    check(len(state(browser)['users']) == 3, 'Rejected registration creates no account')
    form.update(csrf_token=csrf(page), password='Copper owls fly over misty hills7!', confirm_password='Copper owls fly over misty hills7!')
    status, headers, _ = request(browser, register, form)
    check(status == 302 and headers['Location'] == 'verify-email.php?registered=1&next=' + continuation, 'Successful signup redirects to verification with invitation preserved')
    new_user = next(u for u in state(browser)['users'] if u['email'] == 'new@example.invalid')
    check(new_user['email_verified_at'] is None, 'New account remains unverified')
    status, _, page = request(browser, '/login.php?next=' + continuation)
    login_form = {'csrf_token': csrf(page), 'email': 'new@example.invalid', 'password': form['password'], 'next': invitation}
    status, _, page = request(browser, '/login.php?next=' + continuation, login_form)
    check(status == 200 and 'Verify your email before signing in' in page, 'Unverified account denied by real login controller')
    check('Verify email / resend verification' in page, 'Blocked user has a verification recovery link')
    mails = [json.loads(line) for line in (work / 'mail.jsonl').read_text().splitlines()]
    check(len(mails) == 1, 'Registration sends only one fixture verification email')
    proof_match = re.search(r'token=([a-f0-9]{64})', mails[0]['text'])
    assert proof_match is not None, 'Verification token missing from fixture mail'
    proof = proof_match.group(1)
    verify_path = '/verify-email.php?token=' + proof
    status, headers, page = request(browser, verify_path)
    check(status == 200 and headers['Referrer-Policy'] == 'no-referrer', 'Verification GET renders with no-referrer policy')
    check(state(browser)['verification_count'] == 1, 'Opening verification link does not consume it')
    password = 'Silver otters greet the morning7!'
    status, _, _ = request(browser, verify_path, {'token': proof, 'password': password, 'confirm_password': password})
    check(status == 403 and state(browser)['verification_count'] == 1, 'Missing CSRF cannot consume verification token')
    _, _, page = request(browser, verify_path)
    status, _, page = request(browser, verify_path, {'csrf_token': csrf(page), 'token': proof, 'password': password, 'confirm_password': password})
    check(status == 200 and 'Email verified and password saved' in page, 'Mailbox verification succeeds through real controller')
    check('login.php?next=' + continuation in page, 'Verification page preserves invitation sign-in link')
    check(state(browser)['verification_count'] == 0, 'Verification token consumed exactly once')
    _, _, page = request(browser, '/login.php?next=' + continuation)
    status, headers, _ = request(browser, '/login.php?next=' + continuation, {'csrf_token': csrf(page), 'email': 'new@example.invalid', 'password': password, 'next': invitation})
    check(status == 302 and headers['Location'] == invitation, 'Verified login returns to invitation')
    status, _, page = request(browser, '/' + invitation)
    check(status == 200 and 'Accept invitation' in page, 'Verified recipient can render acceptance form')
    status, _, _ = request(browser, '/' + invitation, {'token': token})
    check(status == 403 and not state(browser)['members'], 'Missing CSRF cannot accept invitation')
    _, _, page = request(browser, '/' + invitation)
    status, headers, _ = request(browser, '/' + invitation, {'token': token, 'csrf_token': csrf(page)})
    check(status == 303 and headers['Location'] == 'group.php?id=1', 'Invitation acceptance redirects to group')
    current = state(browser)
    check(len(current['members']) == 1 and current['members'][0]['user_id'] == new_user['user_id'], 'Acceptance persisted exactly one intended membership')
    check(current['invitations'][0]['status'] == 'accepted', 'Invitation persisted accepted status')
    _, _, page = request(browser, '/' + invitation)
    check('Invitation already accepted' in page and 'Open group' in page, 'Reopened invitation offers group instead of unexplained failure')
    other = client()
    _, _, page = request(other, '/login.php')
    status, _, _ = request(other, '/login.php', {'csrf_token': csrf(page), 'email': 'other@example.invalid', 'password': 'Blue lanterns cross quiet rivers7!'})
    check(status == 302, 'Separate verified account can sign in')
    status, _, page = request(other, '/' + invitation)
    check(status == 200 and 'Use the invited account' in page and 'Private fixture description' not in page, 'Wrong account gets clear recovery without private group description')
    status, headers, _ = request(other, '/' + invitation, {'csrf_token': csrf(page), 'token': token, 'action': 'switch_account'})
    check(status == 303 and headers['Location'] == 'login.php?next=' + continuation, 'Switch-account POST preserves invitation')
    status, _, _ = request(other, '/' + invitation)
    check(status == 302, 'Switch-account action actually logs out old account')
    pending = client()
    for i in range(4):
        _, _, page = request(pending, '/verify-email.php')
        status, _, page = request(pending, '/verify-email.php', {'csrf_token': csrf(page), 'email': 'pending@example.invalid'})
        check(status == 200, f'Verification resend attempt {i+1} returns controlled page')
    check('Too many verification attempts' in page, 'Per-address verification resend rate limit enforced')
    mails = [json.loads(line) for line in (work / 'mail.jsonl').read_text().splitlines()]
    check(sum(m['to'] == ['pending@example.invalid'] for m in mails) == 3, 'Throttled resend does not call mail transport')
    status, _, _ = request(client(), '/invite.php?token=' + token + '-')
    check(status == 404, 'Malformed invitation URL is rejected rather than repaired')
    result = {'checks': len(checks), 'failed': 0, 'labels': checks, 'workspace': str(work),
              'scope': 'Real HTTP controllers/views with SQLite SQL adapter; fixture mail only; not production/MySQL/browser proof'}
    (work / 'result.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
    print(json.dumps(result, indent=2))
finally:
    process.terminate()
    try:
        process.wait(timeout=10)
    except subprocess.TimeoutExpired:
        process.kill()
        process.wait(timeout=5)
    log.close()
