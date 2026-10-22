"""Real PHP HTTP/controllers + disposable MariaDB. No production/provider traffic."""
import http.cookiejar
import json
import os
from pathlib import Path
import re
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

assert os.environ.get('AUTH_SECURITY_CI') == 'isolated'
assert os.environ.get('MYSQLDATABASE') == 'auth_security'
ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://127.0.0.1:18971'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, fields=None):
        data = urllib.parse.urlencode(fields).encode() if fields is not None else None
        try:
            response = self.opener.open(BASE + path, data=data, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode(), response.headers

    def fixture(self, action='', **fields):
        status, text, _ = self.request('/fixture', {'action': action, **fields})
        assert status == 200, f'fixture {action} returned {status}'
        return json.loads(text)

    def csrf(self):
        return self.fixture()['csrf']

failures = []
passed = 0

def test(label, operation):
    global passed
    try:
        operation()
        passed += 1
        print('PASS:', label, flush=True)
    except Exception as error:
        failures.append(label)
        # Do not print bodies, tokens, cookies, password hashes or request fields.
        print('FAIL:', label, '-', type(error).__name__, str(error), flush=True)

def check(condition, message):
    if not condition:
        raise AssertionError(message)

subprocess.run(['php', 'tests/auth-security-fixture.php'], cwd=ROOT, check=True)
log = open(ROOT / 'auth-security-server.log', 'w+')
server = subprocess.Popen(['php', '-d', 'display_errors=0', '-S', '127.0.0.1:18971', 'tests/auth-security-fixture.php'], cwd=ROOT, stdout=log, stderr=log)
try:
    for attempt in range(60):
        try:
            Client().request('/fixture')
            break
        except OSError:
            time.sleep(.1)
    else:
        raise RuntimeError('Isolated PHP server did not start')

    def signup_quota():
        client = Client()
        csrf = client.csrf()
        for number in range(1, 8):
            status, html, _ = client.request('/register.php', {'csrf_token': csrf, 'name':'Quota fixture', 'email':f'quota{number}@example.invalid', 'password':'FixturePass123!', 'confirm_password':'FixturePass123!', 'role':'student'})
            if number <= 6:
                check(status == 302, 'first six unique registrations must succeed')
            else:
                check(status == 200 and 'Too many signup attempts' in html, 'seventh successful-signup attempt must be limited')
        check(client.fixture('quota') == 6, 'successful registrations must retain six quota hits')
        client.fixture('expire-quota')
        status, _, _ = client.request('/register.php', {'csrf_token':csrf, 'name':'After expiry','email':'quota-expired@example.invalid','password':'FixturePass123!', 'confirm_password':'FixturePass123!', 'role':'student'})
        check(status == 302 and client.fixture('quota') == 1, 'expired quota permits registration in new window')
    test('successful signup quota and expiry', signup_quota)

    setup = Client()
    setup.fixture('create', email='session@example.invalid')
    old = {}
    for kind in ('login', 'oauth', 'native'):
        client = Client()
        client.fixture(kind)
        check(client.fixture()['logged_in'], 'fixture login must authenticate')
        old[kind] = client
    pending = Client().fixture('native-ticket')['code']
    reset_client = Client()
    token = reset_client.fixture('token')['token']
    reset_ok = reset_client.fixture('reset-direct', token=token, password='ChangedPass123!')['ok']
    check(reset_ok, 'valid fresh reset must succeed')
    for kind, client in old.items():
        test(kind + ' session revoked after reset', lambda c=client: check(c.request('/fixture', {'action':'protected'})[0] == 302 and not c.fixture()['logged_in'], 'old authenticated session must be rejected'))
    test('pending native handoff revoked after reset', lambda: check(Client().request('/fixture', {'action':'exchange','code':pending})[0] == 400, 'pre-reset code must not grant a fresh session'))
    test('reset token one use', lambda: check(not reset_client.fixture('reset-direct', token=token, password='OtherPass123!')['ok'], 'used token must fail'))

    def fresh_password():
        client = Client()
        csrf = client.csrf()
        status, _, _ = client.request('/login.php', {'csrf_token':csrf,'email':'session@example.invalid','password':'ChangedPass123!'})
        check(status == 302 and client.fixture()['logged_in'], 'new password grants valid fresh session')
    test('fresh password login after reset', fresh_password)

    def legacy():
        client = Client()
        client.fixture('login')
        client.fixture('legacy')
        check(not client.fixture()['logged_in'], 'sessions without a fingerprint must fail closed')
    test('legacy sessions require re-login', legacy)

    def inactive():
        client = Client()
        client.fixture('create', email='inactive@example.invalid')
        client.fixture('login', email='inactive@example.invalid')
        client.fixture('deactivate', email='inactive@example.invalid')
        check(client.request('/fixture', {'action':'protected'})[0] == 302, 'inactive account must be rejected')
    test('deactivation still revokes sessions', inactive)

    for path, field in [('/login.php','next'),('/register.php','next')]:
        test(path + ' rejects array query ' + field, lambda p=path,f=field: check(Client().request(p+'?'+f+'%5B%5D=x')[0] == 400, 'malformed array must return 400'))
    for path, fields in [('/login.php',['email','password','next']),('/register.php',['name','email','password','confirm_password','role','next']),('/forgot-password.php',['email']),('/reset-password.php',['password','confirm_password','token'])]:
        for field in fields:
            def malformed(p=path, f=field):
                client = Client()
                values = {'csrf_token':client.csrf(), f+'[]':'x'}
                if p == '/reset-password.php' and f != 'token':
                    values['token'] = 'a' * 64
                status, _, _ = client.request(p, values)
                check(status == 400, 'malformed array must return controlled 400')
            test(path + ' rejects array ' + field, malformed)
    test('invalid UTF-8 query rejected', lambda: check(Client().request('/register.php?next=%FF')[0] == 400, 'invalid encoding must return 400'))

    for kind, name, email, password in [('name', 'x'*101,'bound-name@example.invalid','FixturePass123!'), ('email','Bounds', 'x'*140+'@example.invalid','FixturePass123!'), ('password','Bounds','bound-pass@example.invalid','x'*73),('NUL password','Bounds','bound-nul@example.invalid','fixture\0pass')]:
        def bounds(n=name, e=email, p=password):
            client = Client()
            status, _, _ = client.request('/register.php', {'csrf_token':client.csrf(),'name':n,'email':e,'password':p,'confirm_password':p,'role':'student'})
            check(status == 400, 'out-of-bounds registration must return controlled 400')
        test('registration bounds ' + kind, bounds)

    def malformed_reset_token():
        client = Client()
        token = client.fixture('token')['token']
        status, html, _ = client.request('/reset-password.php', {'csrf_token':client.csrf(),'token':token+'!','password':'TamperedPass123!','confirm_password':'TamperedPass123!'})
        check(status == 400 and 'Password updated.' not in html, 'token suffix must not be stripped into a valid token')
        check(client.fixture('reset-direct',token=token,password='AnotherPass123!')['ok'], 'malformed token must not consume original')
    test('exact reset token validation', malformed_reset_token)
    test('reset service enforces password maximum', lambda: check(not setup.fixture('reset-direct',token=setup.fixture('token')['token'],password='x'*73)['ok'], 'service must reject overlong password'))

    def logout(method, token_kind, expected):
        client = Client()
        client.fixture('login')
        csrf = client.csrf()
        fields = None if method == 'GET' else ({} if token_kind == 'missing' else {'csrf_token':csrf if token_kind == 'valid' else ('wrong' if token_kind == 'wrong' else ['bad'])})
        status, _, headers = client.request('/logout.php', fields)
        check(status == expected, f'expected {expected}, received {status}')
        if method == 'GET':
            check(headers.get('Allow') == 'POST', '405 must advertise POST')
        check(client.fixture()['logged_in'] == (expected != 302), 'only valid POST may destroy session')
    for method, kind, expected in [('GET','missing',405),('POST','missing',403),('POST','wrong',403),('POST','valid',302)]:
        test('logout '+method+' '+kind, lambda m=method,k=kind,e=expected: logout(m,k,e))

    # No diagnostic values are printed; warnings/uncaught failures are test failures.
    log.flush()
    log.seek(0)
    server_text = log.read()
    test('no PHP warnings or uncaught request errors', lambda: check(not re.search(r'PHP (Warning|Fatal error)|Uncaught ', server_text), 'server emitted warning/fatal; inspect restricted fixture log'))
finally:
    server.terminate()
    server.wait(timeout=10)
    log.close()
print(f'Auth-security result: {passed} passed, {len(failures)} failed; real mail/provider calls: 0.', flush=True)
raise SystemExit(bool(failures))
