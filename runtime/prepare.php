<?php
declare(strict_types=1);
// One-time setup only. s6 becomes PID 1 and supervises both production servers.
umask(0077);
try {
    $rawPort = getenv('PORT');
    $port = filter_var($rawPort === false ? '8080' : $rawPort, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false) {
        throw new RuntimeException('PORT must be an integer between 1 and 65535.');
    }
    foreach (['pdo_mysql', 'mbstring', 'curl'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('A required PHP extension is unavailable.');
        }
    }
    foreach (['/tmp/lsc/client-body', '/tmp/lsc/fastcgi', '/tmp/lsc/sessions',
              '/tmp/lsc/proxy', '/tmp/lsc/uwsgi', '/tmp/lsc/scgi',
              '/tmp/lsc/sv/php-fpm', '/tmp/lsc/sv/nginx'] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Runtime temporary storage is unavailable.');
        }
    }
    $template = file_get_contents(__DIR__ . '/nginx.conf.template');
    if ($template === false) {
        throw new RuntimeException('The web-server configuration is unavailable.');
    }
    if (file_put_contents('/tmp/lsc/nginx.conf',
        str_replace('__PORT__', (string)$port, $template), LOCK_EX) === false) {
        throw new RuntimeException('Runtime configuration could not be written.');
    }
    // Keep executables on the immutable image, not on a noexec temp mount.
    foreach (['php-fpm', 'nginx'] as $service) {
        $target = __DIR__ . '/services/' . $service;
        $link = '/tmp/lsc/sv/' . $service . '/run';
        if (is_link($link) && readlink($link) === $target) {
            continue;
        }
        if (!symlink($target, $link)) {
            throw new RuntimeException('Runtime service links could not be created.');
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, "LSC runtime setup failed: " . $error->getMessage() . "\n");
    exit(1);
}
