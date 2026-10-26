<?php
declare(strict_types=1);

// Run the actual bootstrap in a separate CLI process with synthetic globals.
$bootstrap = dirname(__DIR__) . '/config/bootstrap.php';
$child = 'register_shutdown_function(static function (): void { echo "\n__HTTP_STATUS__=" . (http_response_code() ?: 200); });'
    . 'putenv("APP_ENV=test"); putenv("APP_DEBUG=0");'
    . '$_SERVER["REQUEST_METHOD"]="GET"; $_GET=["next"=>["fixture"]]; $_POST=[];'
    . 'require ' . var_export($bootstrap, true) . '; echo "BOOTSTRAP_CONTINUED";';
$pipes = [];
$process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $child], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Could not start isolated bootstrap fixture.'); }
$output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
if ($exit!==0 || $errors!=='') { throw new RuntimeException('Fixture execution failed; not a valid security result.'); }
if (!str_contains($output,'__HTTP_STATUS__=400') || str_contains($output,'BOOTSTRAP_CONTINUED')) {
    throw new RuntimeException('REGRESSION: array-shaped query input reached application code instead of a controlled HTTP 400.');
}
fwrite(STDOUT,"PASS: malformed query parameters are rejected before application/session/database work.\n");
