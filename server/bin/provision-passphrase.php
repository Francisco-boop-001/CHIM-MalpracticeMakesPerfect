<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/lib/web.php';

function brainwash_read_secret(string $prompt): string
{
    $tty = function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($tty) {
        fwrite(STDERR, $prompt);
        exec('stty -echo 2>/dev/null', $ignored, $status);
        if ($status !== 0) throw new RuntimeException('Could not disable terminal echo.');
    }
    try {
        $line = fgets(STDIN, 4098);
        if (!is_string($line)) throw new RuntimeException('Passphrase input was unavailable.');
        return rtrim($line, "\r\n");
    } finally {
        if ($tty) {
            exec('stty echo 2>/dev/null', $ignored, $status);
            fwrite(STDERR, "\n");
            if ($status !== 0) throw new RuntimeException('Could not restore terminal echo.');
        }
    }
}

try {
    $first = brainwash_read_secret('New Brainwash passphrase: ');
    $second = brainwash_read_secret('Confirm Brainwash passphrase: ');
    if (strlen($first) < 12 || strlen($first) > 4096 || str_contains($first, "\0") || !hash_equals($first, $second)) {
        throw new RuntimeException('Passphrases must match and contain 12 to 4096 bytes.');
    }

    $target = brainwash_auth_config_path(false);
    $directory = dirname($target);
    $dirStat = @stat($directory);
    if ($dirStat === false || (($dirStat['mode'] ?? 0) & 0022) !== 0 || !is_writable($directory)) {
        throw new RuntimeException('The private configuration directory must be writable and not group/other writable.');
    }
    if (file_exists($target)) {
        $stat = @lstat($target);
        if ($stat === false || (($stat['mode'] ?? 0) & 0170000) !== 0100000 || (($stat['mode'] ?? 0) & 0022) !== 0) {
            throw new RuntimeException('Existing private configuration is not a safe regular file.');
        }
    }

    $hash = password_hash($first, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') throw new RuntimeException('Password hashing failed.');
    $contents = "<?php\ndeclare(strict_types=1);\nreturn ['password_hash' => " . var_export($hash, true) . "];\n";
    unset($first, $second, $hash);
    $temporary = tempnam($directory, '.brainwash-auth-');
    if (!is_string($temporary)) throw new RuntimeException('Could not create the private configuration file.');
    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || !chmod($temporary, 0600)) {
            throw new RuntimeException('Could not write the private configuration file.');
        }
        if (!rename($temporary, $target)) throw new RuntimeException('Could not install the private configuration file.');
        @chmod($target, 0600);
    } finally {
        if (is_file($temporary)) @unlink($temporary);
        unset($contents);
    }
    fwrite(STDOUT, "Brainwash passphrase hash provisioned.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Brainwash provisioning failed: " . $error->getMessage() . "\n");
    exit(1);
}
