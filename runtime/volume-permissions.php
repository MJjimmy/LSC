<?php
declare(strict_types=1);
// Only the fixed upload mount root may be changed; never recurse over user files.
$uid = fileowner('/proc/self');
if (($argv[1] ?? '') === '--is-root') {
    exit($uid === 0 ? 0 : 1);
}
try {
    if ($uid !== 0 && $uid !== 65532) {
        throw new RuntimeException('Unexpected runtime identity.');
    }
    // A container launched as root may provide root-owned logging pipes.
    // Change only inherited pipe objects, never a redirected regular file.
    if ($uid === 0) {
        foreach ([1, 2] as $number) {
            $fd = '/proc/self/fd/' . $number;
            $target = readlink($fd);
            if (is_string($target) && str_starts_with($target, 'pipe:[')
                && (!chown($fd, 65532) || !chgrp($fd, 65532))) {
                throw new RuntimeException('Logging pipe ownership could not be prepared.');
            }
        }
    }
    $mount = getenv('RAILWAY_VOLUME_MOUNT_PATH');
    if ($mount !== false && $mount !== '') {
        $path = '/app/storage/uploads';
        if ($mount !== $path || is_link($path) || realpath($path) !== $path || !is_dir($path)) {
            throw new RuntimeException('Unexpected upload volume mount.');
        }
        if ($uid === 0) {
            if (!chown($path, 65532) || !chgrp($path, 65532) || !chmod($path, 0700)) {
                throw new RuntimeException('Upload volume permissions could not be prepared.');
            }
        }
        clearstatcache(true, $path);
        if (fileowner($path) !== 65532 || filegroup($path) !== 65532 || ($uid === 65532 && !is_writable($path))) {
            throw new RuntimeException('Upload volume is not writable by the application.');
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'LSC volume setup failed: ' . $error->getMessage() . "\n");
    exit(1);
}
