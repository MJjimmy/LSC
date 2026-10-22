<?php
declare(strict_types=1);

// Isolated CLI regression: no database, network, credentials or real sessions.
$csrfFile = dirname(__DIR__) . '/app/Core/Csrf.php';
$child = 'register_shutdown_function(static function (): void { echo "\\n__HTTP_STATUS__=" . (http_response_code() ?: 200); });'
    . 'require ' . var_export($csrfFile, true) . ';'
    . '$_SERVER["REQUEST_METHOD"] = "POST"; $_SESSION = []; $_POST = ["csrf_token" => ""];'
    . '\LSC\Core\Csrf::verify();';
$pipes = [];
$process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $child], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Could not start isolated PHP test process.'); }
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
$exit = proc_close($process);
if ($exit !== 0 || $error !== '') { throw new RuntimeException('Isolated fixture execution failed; this is not a valid security result.'); }
if (!preg_match('/__HTTP_STATUS__=(\\d+)/', $output, $match)) { throw new RuntimeException('Fixture status marker missing.'); }
if ((int)$match[1] !== 403) { throw new RuntimeException('REGRESSION: POST with an empty token and no session token was not rejected with HTTP 403.'); }
fwrite(STDOUT, "PASS: absent session token plus empty POST token is rejected.\\n");
