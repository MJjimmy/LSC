<?php
declare(strict_types=1);

// Isolated CLI regression: no database, network, session or credentials.
// Proves that forwarded headers are only honoured when the direct peer is a
// configured proxy, and that a proxy deployment does not collapse every
// visitor into one shared rate-limit bucket.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) {
        require_once dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

use LSC\Core\RateLimiter;

$failures = [];
$checks = 0;
function clientIpCase(string $name, string $expected, string $actual): void
{
    global $failures, $checks;
    $checks++;
    if ($expected !== $actual) {
        $failures[] = $name;
        fwrite(STDOUT, "FAIL: {$name}: expected {$expected}, got {$actual}\n");
        return;
    }
    fwrite(STDOUT, "PASS: {$name}\n");
}

function clientIpEnvironment(string $remote, ?string $forwarded, ?string $cloudflare, ?string $trusted, ?string $legacy = null): void
{
    foreach (['TRUSTED_PROXIES', 'TRUSTED_PROXY'] as $name) {
        putenv($name);
    }
    if ($trusted !== null) {
        putenv('TRUSTED_PROXIES=' . $trusted);
    }
    if ($legacy !== null) {
        putenv('TRUSTED_PROXY=' . $legacy);
    }

    unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP']);
    $_SERVER['REMOTE_ADDR'] = $remote;
    if ($forwarded !== null) {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded;
    }
    if ($cloudflare !== null) {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = $cloudflare;
    }
}

// --- Default: the peer address is the client, headers are ignored -----------
clientIpEnvironment('198.51.100.7', '1.2.3.4', null, null);
clientIpCase('no trusted proxy configured ignores X-Forwarded-For', '198.51.100.7', RateLimiter::clientIp());

clientIpEnvironment('198.51.100.7', '1.2.3.4', '203.0.113.42', null);
clientIpCase('Cloudflare header is ignored unless opted in', '198.51.100.7', RateLimiter::clientIp());

clientIpEnvironment('not-an-ip', '1.2.3.4', null, null);
clientIpCase('invalid peer address falls back to a stable placeholder', '0.0.0.0', RateLimiter::clientIp());

// --- Proxy deployments resolve the real client -----------------------------
clientIpEnvironment('10.0.0.5', '203.0.113.9', null, '10.0.0.0/8');
clientIpCase('trusted proxy resolves the forwarded client', '203.0.113.9', RateLimiter::clientIp());

clientIpEnvironment('10.0.0.5', '203.0.113.9', null, '10.0.0.0/8');
$first = RateLimiter::clientIp();
clientIpEnvironment('10.0.0.6', '203.0.113.10', null, '10.0.0.0/8');
$second = RateLimiter::clientIp();
$checks++;
if ($first === $second) {
    $failures[] = 'distinct clients behind one proxy share a bucket';
    fwrite(STDOUT, "FAIL: distinct clients behind one proxy share a bucket\n");
} else {
    fwrite(STDOUT, "PASS: distinct clients behind one proxy do not share a bucket\n");
}

clientIpEnvironment('10.0.0.5', '6.6.6.6, 203.0.113.9, 10.0.0.5', null, '10.0.0.0/8');
clientIpCase('right-most untrusted hop wins over a spoofed left hop', '203.0.113.9', RateLimiter::clientIp());

clientIpEnvironment('10.0.0.5', '10.1.1.1, 10.2.2.2', null, '10.0.0.0/8');
clientIpCase('all-forwarded-hops-trusted falls back to the peer', '10.0.0.5', RateLimiter::clientIp());

clientIpEnvironment('10.0.0.5', 'not-an-ip, 203.0.113.9', null, '10.0.0.0/8');
clientIpCase('malformed hops are skipped', '203.0.113.9', RateLimiter::clientIp());

clientIpEnvironment('192.168.1.9', '203.0.113.9', null, '192.168.1.9');
clientIpCase('a bare address entry is matched exactly', '203.0.113.9', RateLimiter::clientIp());

clientIpEnvironment('192.168.1.10', '203.0.113.9', null, '192.168.1.9');
clientIpCase('a bare address entry does not match a neighbour', '192.168.1.10', RateLimiter::clientIp());

clientIpEnvironment('fd00::1', '2001:db8::1', null, 'fd00::/8');
clientIpCase('IPv6 proxy ranges are supported', '2001:db8::1', RateLimiter::clientIp());

clientIpEnvironment('10.0.0.5', '203.0.113.9', null, 'loopback, 10.0.0.0/8');
clientIpCase('proxy list accepts several entries', '203.0.113.9', RateLimiter::clientIp());

clientIpEnvironment('127.0.0.1', '203.0.113.9', null, 'loopback');
clientIpCase('loopback keyword trusts the local reverse proxy', '203.0.113.9', RateLimiter::clientIp());

// --- Cloudflare opt-in (and the legacy variable name) ----------------------
clientIpEnvironment('172.68.1.1', '', '203.0.113.42', 'cloudflare');
clientIpCase('cloudflare keyword uses the connecting-IP header', '203.0.113.42', RateLimiter::clientIp());

clientIpEnvironment('172.68.1.1', '', 'not-an-ip', 'cloudflare');
clientIpCase('invalid connecting-IP falls back to the peer', '172.68.1.1', RateLimiter::clientIp());

clientIpEnvironment('172.68.1.1', '', '203.0.113.42', null, 'cloudflare');
clientIpCase('legacy TRUSTED_PROXY=cloudflare still works', '203.0.113.42', RateLimiter::clientIp());

fwrite(STDOUT, json_encode(['checks' => $checks, 'failed' => count($failures), 'failures' => $failures]) . "\n");
exit($failures === [] ? 0 : 1);
