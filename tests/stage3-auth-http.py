"""Stage3 only: real PHP controllers + disposable MySQL/MariaDB, stdlib only.

Adapted from auth-security-http.py at 678c818b. Run ONLY in approved hosted CI:
  python tests/stage3-auth-http.py --result-json /absolute/outside/source/stage3.json
Requires AUTH_SECURITY_CI=isolated, MYSQLHOST=127.0.0.1,
MYSQLDATABASE=auth_security and explicit disposable MYSQLPORT/USER/PASSWORD.
Refuses .env, nonempty databases, proxies, redirects, and an occupied server port.
No migrations/imports, real email, provider exchange, or production credentials.
The result contains labels/status only. Restricted server log and session files
are outside both source and the empty web root and removed on exit. PHP lint
and behavioral RED/GREEN runs belong to hosted CI, never local project PHP.
"""
import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://127.0.0.1:18971'
FIXTURE = ROOT / 'tests' / 'stage3-auth-fixture.php'
OLD_PASSWORD = 'FixturePass123!'
NEW_PASSWORD = 'ChangedPass123!'
EMAIL = 'session@example.invalid'
RESULTS = []
FIXTURE_KEY = ''


def check(condition, message):
    if not condition:
        raise AssertionError(message)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.ProxyHandler({}), NoRedirect(),
            urllib.request.HTTPCookieProcessor(self.jar))

    def request(self, path, fields=None):
        check(path in ('/fixture', '/login.php', '/register.php', '/reset-password.php'),
              'request outside stage3 allowlist')
        data = urllib.parse.urlencode(fields).encode() if fields is not None else None
        request = urllib.request.Request(BASE + path, data=data,
                                         headers={'X-Stage3-Fixture': FIXTURE_KEY})
        try:
            response = self.opener.open(request, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            check(response.headers.get('X-Stage3-Fixture') == 'isolated',
                  'response did not come from isolated fixture')
            return response.status, response.read().decode('utf-8'), response.headers

    def fixture(self, action='', **fields):
        status, text, _ = self.request('/fixture', {'action': action, **fields})
        check(status == 200, 'fixture request failed')
        return json.loads(text)

    def csrf(self):
        return self.fixture()['csrf']


def test(label, operation):
    try:
        operation()
        RESULTS.append({'test': label, 'status': 'pass'})
        print('PASS: ' + label, flush=True)
    except Exception as error:
        # Never print exception messages, traceback locals, responses or request fields.
        RESULTS.append({'test': label, 'status': 'fail', 'error_type': type(error).__name__})
        print('FAIL: ' + label + ' (' + type(error).__name__ + ')', flush=True)


def login(client, password, email=EMAIL):
    return client.request('/login.php', {'csrf_token': client.csrf(), 'email': email,
                                        'password': password})[0]


def register(client, email):
    return client.request('/register.php', {
        'csrf_token': client.csrf(), 'name': 'Stage3 quota', 'email': email,
        'password': OLD_PASSWORD, 'confirm_password': OLD_PASSWORD,
        'role': 'student'})


def reset(client, token, password):
    return client.request('/reset-password.php', {
        'csrf_token': client.csrf(), 'token': token,
        'password': password, 'confirm_password': password})


def suite():
    setup = Client()

    def first_six():
        for number in range(1, 7):
            # Deliberately new independent cookie jar and fresh CSRF per signup.
            client = Client()
            email = f'quota{number}@example.invalid'
            status, _, _ = register(client, email)
            check(status == 302, 'six valid unique registrations must succeed')
            check(setup.fixture('user-count', email=email)['count'] == 1,
                  'successful registration must persist exactly one user')
        check(setup.fixture('quota')['users'] == 6, 'six unique rows required')
    test('six unique signups persist across independent sessions', first_six)
    test('successful signups retain six IP quota hits', lambda: check(
        setup.fixture('quota')['attempts'] == 6, 'successful hits were cleared'))
    test('signup quota uses a one hour window', lambda: check(
        3500 <= setup.fixture('quota-window')['seconds'] <= 3600,
        'signup quota expiry must be one hour, allowing setup execution time'))

    def seventh():
        before = setup.fixture('quota')['users']
        client = Client()
        status, html, _ = register(client, 'quota7@example.invalid')
        check(status == 200 and 'Too many signup attempts' in html,
              'seventh registration must be rate limited')
        check(setup.fixture('user-count', email='quota7@example.invalid')['count'] == 0,
              'blocked registration must not insert a user')
        check(setup.fixture('quota') == {'attempts': 6, 'users': before},
              'blocked registration must not alter quota or user count')
    test('seventh signup blocked without a new user row', seventh)

    def expiry():
        setup.fixture('expire-quota')
        client = Client()
        status, _, _ = register(client, 'quota-expired@example.invalid')
        check(status == 302, 'expired quota must permit a new registration')
        check(setup.fixture('user-count', email='quota-expired@example.invalid')['count'] == 1,
              'post-expiry registration must persist')
        check(setup.fixture('quota')['attempts'] == 1, 'fresh hour must start at one hit')
    test('quota expiry permits registration in a fresh window', expiry)

    check(setup.fixture('create', email=EMAIL)['ok'] is True, 'synthetic account setup failed')
    sessions = {}
    for kind in ('local', 'oauth', 'native'):
        sessions[kind] = []
        for _ in range(2):
            client = Client()
            if kind == 'local':
                check(login(client, OLD_PASSWORD) == 302, 'local setup login failed')
            else:
                check(client.fixture(kind)['ok'] is True, 'trusted setup login failed')
            check(client.fixture()['logged_in'] is True, 'setup session must authenticate')
            check(client.request('/fixture', {'action': 'protected'})[0] == 200,
                  'setup session must access protected fixture')
            sessions[kind].append(client)

    proof = Client()
    check(proof.fixture('capture-proof')['ok'] is True, 'old proof capture failed')
    check(proof.fixture()['logged_in'] is False, 'capturing proof must not log in')
    ticket_browser = Client()
    ticket = ticket_browser.fixture('native-ticket')['code']
    check(isinstance(ticket, str) and len(ticket) == 64, 'issued ticket setup failed')
    ready = Client()
    check(ready.fixture('native-ready')['ok'] is True, 'ready intent setup failed')
    check(ready.fixture()['logged_in'] is True, 'completed intent browser must start authenticated')
    check(setup.fixture('code-count')['count'] >= 1, 'issued ticket must persist before reset')

    reset_client = Client()
    token = reset_client.fixture('token')['token']
    check(isinstance(token, str) and len(token) == 64, 'reset token setup failed')
    status, html, _ = reset(reset_client, token, NEW_PASSWORD)
    check(status == 200 and 'Password updated.' in html, 'controller reset setup failed')
    # All post-reset checks intentionally use sessions/proofs created BEFORE the reset.
    for kind, clients in sessions.items():
        def state_revoked(client=clients[0]):
            check(client.fixture()['logged_in'] is False,
                  'isLoggedIn must reject the pre-reset fingerprint')
        def protected_revoked(client=clients[1]):
            check(client.request('/fixture', {'action': 'protected'})[0] == 302,
                  'requireLogin must reject the pre-reset session without a prior state check')
            check(client.fixture()['logged_in'] is False, 'revoked session remains authenticated')
        test(kind + ' session revoked by isLoggedIn after reset', state_revoked)
        test(kind + ' independent session revoked by requireLogin after reset', protected_revoked)

    def stale_proof():
        result = proof.fixture('replay-proof')
        # Strict boolean assertion is mandatory: baseline void/null is NOT accepted.
        check(result['strict_false'] is True, 'stale Auth.login proof must return strict false')
        check(result['logged_in'] is False, 'stale proof must not authenticate')
        check(proof.request('/fixture', {'action': 'protected'})[0] == 302,
              'stale proof must not grant protected access')
    test('old password proof cannot race reset through Auth.login fifth argument', stale_proof)

    def pending_ticket():
        native = Client()
        status, _, _ = native.request('/fixture', {'action': 'exchange', 'code': ticket})
        check(status == 400, 'pre-reset native ticket must fail exchange')
        check(native.fixture()['logged_in'] is False, 'rejected ticket must not authenticate')
        check(native.request('/fixture', {'action': 'protected'})[0] == 302,
              'rejected ticket must not grant protected access')
    test('issued native ticket cannot exchange after reset', pending_ticket)

    def ready_intent():
        before = setup.fixture('code-count')['count']
        # No isLoggedIn/requireLogin call on this jar since reset: exercise complete() itself.
        status, _, _ = ready.request('/fixture', {'action': 'complete'})
        check(status == 400, 'pre-reset completed browser intent must fail complete')
        check(setup.fixture('code-count')['count'] == before,
              'stale intent must not persist a fresh native code')
    test('real completeGoogle intent cannot issue a fresh code after reset', ready_intent)

    def reuse_token():
        status, html, _ = reset(reset_client, token, 'ReuseMustNotWin123!')
        check(status == 200 and 'Password updated.' not in html
              and 'already been used' in html, 'used reset token must fail')
    test('used reset token cannot reset again', reuse_token)

    def old_password():
        client = Client()
        check(login(client, OLD_PASSWORD) == 200, 'old password must fail login')
        check(client.fixture()['logged_in'] is False, 'old password must not authenticate')
    test('old password fails after reset', old_password)

    def new_password():
        client = Client()
        check(login(client, NEW_PASSWORD) == 302, 'new password must work after reset')
        check(client.fixture()['logged_in'] is True, 'new password must authenticate')
        check(client.request('/fixture', {'action': 'protected'})[0] == 200,
              'new password session must access protected fixture')
    test('new password works after reset and rejected token reuse', new_password)

    for mode in ('state', 'protected'):
        def legacy(which=mode):
            client = Client()
            check(login(client, NEW_PASSWORD) == 302, 'legacy setup login failed')
            check(client.fixture()['logged_in'] is True, 'legacy setup must start authenticated')
            client.fixture('legacy')
            if which == 'protected':
                check(client.request('/fixture', {'action': 'protected'})[0] == 302,
                      'missing fingerprint must fail requireLogin directly')
            check(client.fixture()['logged_in'] is False, 'missing fingerprint must fail closed')
        test('missing fingerprint fails closed via ' + mode, legacy)

    def inactive():
        email = 'inactive@example.invalid'
        check(setup.fixture('create', email=email)['ok'] is True, 'inactive setup failed')
        first, second = Client(), Client()
        check(login(first, OLD_PASSWORD, email) == 302, 'inactive first setup failed')
        check(login(second, OLD_PASSWORD, email) == 302, 'inactive second setup failed')
        pending = Client().fixture('native-ticket', email=email)['code']
        setup.fixture('deactivate', email=email)
        check(first.fixture()['logged_in'] is False, 'inactive session must fail isLoggedIn')
        check(second.request('/fixture', {'action': 'protected'})[0] == 302,
              'inactive independent session must fail requireLogin')
        local = Client()
        check(login(local, OLD_PASSWORD, email) == 200
              and local.fixture()['logged_in'] is False, 'inactive local login must fail')
        oauth = Client()
        check(oauth.request('/fixture', {'action': 'oauth', 'email': email})[0] == 400
              and oauth.fixture()['logged_in'] is False, 'inactive trusted OAuth resolution must fail')
        native = Client()
        check(native.request('/fixture', {'action': 'exchange', 'code': pending})[0] == 400
              and native.fixture()['logged_in'] is False, 'inactive native exchange must fail')
    test('inactive users remain denied across sessions local OAuth and native', inactive)


def isolated_environment():
    for key, value in [('AUTH_SECURITY_CI', 'isolated'), ('MYSQLHOST', '127.0.0.1'),
                       ('MYSQLDATABASE', 'auth_security')]:
        check(os.environ.get(key) == value, 'isolated environment gate failed')
    check(not (ROOT / '.env').exists(), 'fixture refuses source .env credentials')
    check(re.fullmatch(r'[0-9]{1,5}', os.environ.get('MYSQLPORT', '')) is not None,
          'explicit isolated database port required')
    check(1 <= int(os.environ['MYSQLPORT']) <= 65535, 'invalid isolated database port')
    check(os.environ.get('MYSQLUSER') and os.environ.get('MYSQLPASSWORD'),
          'explicit disposable database credentials required')
    # Do not inherit unrelated secrets, PHP config, proxy or provider environment.
    allowed = ('PATH', 'SystemRoot', 'SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATHEXT',
               'TMP', 'TEMP', 'TMPDIR', 'HOME', 'USERPROFILE', 'LD_LIBRARY_PATH',
               'AUTH_SECURITY_CI', 'MYSQLHOST', 'MYSQLPORT', 'MYSQLDATABASE',
               'MYSQLUSER', 'MYSQLPASSWORD')
    env = {key: os.environ[key] for key in allowed if key in os.environ}
    env.update(APP_ENV='testing', APP_DEBUG='0', APP_URL=BASE,
               RESEND_API_KEY='', GOOGLE_CLIENT_ID='', GOOGLE_CLIENT_SECRET='',
               STAGE3_FIXTURE_KEY=FIXTURE_KEY)
    return env


def run():
    global FIXTURE_KEY
    parser = argparse.ArgumentParser(description='Isolated stage3 regression tests only')
    parser.add_argument('--result-json', type=Path,
                        help='Explicit result path outside source; labels/status only')
    args = parser.parse_args()
    output = args.result_json.resolve() if args.result_json else None
    if output:
        check(not output.is_relative_to(ROOT), 'result path must be outside source')
        check(output.suffix == '.json', 'result path must have .json suffix')
        check(not output.exists(), 'refusing to overwrite existing result')
    FIXTURE_KEY = secrets.token_hex(32)
    env = isolated_environment()
    # Fail before SQL/server startup if another process already owns the fixed port.
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 18971))
    with tempfile.TemporaryDirectory(prefix='stage3-auth-', dir=os.environ.get('TMPDIR')) as temp:
        work = Path(temp).resolve()
        check(not work.is_relative_to(ROOT), 'server artifacts must be outside source')
        web = work / 'web'
        sessions = work / 'sessions'
        web.mkdir()
        sessions.mkdir(mode=0o700)
        log_path = work / 'server.log'  # Sibling of web root, never served.
        log_path.touch(mode=0o600)
        php = ['php', '-d', 'display_errors=0', '-d', 'display_startup_errors=0',
               '-d', 'allow_url_fopen=0', '-d', 'disable_functions=mail,curl_exec,curl_multi_exec',
               '-d', 'session.save_path=' + str(sessions)]
        server = None
        with log_path.open('w+', encoding='utf-8') as log:
            try:
                initialized = subprocess.run(php + [str(FIXTURE)], cwd=ROOT, env=env,
                                             stdout=log, stderr=log, timeout=45)
                check(initialized.returncode == 0, 'isolated schema initialization failed')
                server = subprocess.Popen(php + ['-S', '127.0.0.1:18971', '-t', str(web), str(FIXTURE)],
                                          cwd=ROOT, env=env, stdout=log, stderr=log)
                for _ in range(100):
                    check(server.poll() is None, 'owned PHP server exited during startup')
                    try:
                        check(Client().fixture()['logged_in'] is False, 'fresh server must be anonymous')
                        break
                    except (OSError, urllib.error.URLError):
                        time.sleep(.1)
                else:
                    raise RuntimeError('isolated server readiness timed out')
                suite()
            except Exception as error:
                RESULTS.append({'test': 'isolated setup or suite prerequisite', 'status': 'fail',
                                'error_type': type(error).__name__})
                print('FAIL: isolated setup or suite prerequisite (' + type(error).__name__ + ')', flush=True)
            finally:
                if server is not None:
                    if server.poll() is None:
                        server.terminate()
                    try:
                        server.wait(timeout=10)
                    except subprocess.TimeoutExpired:
                        server.kill()
                        server.wait(timeout=10)
                log.flush()
                log.seek(0)
                server_text = log.read()
                test('no PHP warnings fatal or uncaught errors', lambda: check(
                    re.search(r'PHP (?:Warning|Fatal error|Parse error|Deprecated|Notice)|Uncaught ',
                              server_text) is None, 'restricted server log contains PHP errors'))
    summary = {'suite': 'stage3-auth', 'results': RESULTS,
               'passed': sum(item['status'] == 'pass' for item in RESULTS),
               'failed': sum(item['status'] == 'fail' for item in RESULTS),
               'real_mail_provider_calls': 0}
    if output:
        # No automatic path choice and no sensitive values in persisted diagnostics.
        with output.open('x', encoding='utf-8') as result:
            json.dump(summary, result, indent=2)
            result.write('\n')
    print('Stage3 result: {passed} passed, {failed} failed; real mail/provider calls: 0.'.format(**summary),
          flush=True)
    return int(summary['failed'] != 0)


if __name__ == '__main__':
    try:
        exit_code = run()
    except Exception as error:
        # Includes preflight/cleanup/output errors; do not leak URLs, credentials or paths.
        print('FAIL: isolated runner (' + type(error).__name__ + ')', flush=True)
        exit_code = 1
    raise SystemExit(exit_code)
