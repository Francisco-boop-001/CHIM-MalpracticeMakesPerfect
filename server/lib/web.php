<?php
declare(strict_types=1);

final class BrainwashHttpFault extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $wireCode)
    {
        parent::__construct($wireCode);
    }
}

function brainwash_path_is_within(string $base, string $candidate): bool
{
    $base = rtrim(str_replace('\\', '/', $base), '/');
    $candidate = str_replace('\\', '/', $candidate);
    return $candidate === $base || str_starts_with($candidate, $base . '/');
}

/** Resolve the private hash file without allowing it under the deployed plugin or document root. */
function brainwash_auth_config_path(bool $mustExist = true): string
{
    $configured = getenv('BRAINWASH_PRIVATE_CONFIG');
    $path = is_string($configured) && $configured !== '' ? $configured : '/etc/chim/brainwash-auth.php';
    if (!str_starts_with($path, '/') || str_contains($path, "\0") || basename($path) === '' || basename($path) === '.' || basename($path) === '..') {
        throw new BrainwashHttpFault(503, 'unavailable');
    }
    $directory = realpath(dirname($path));
    if (!is_string($directory) || !is_dir($directory) || is_link($path)) throw new BrainwashHttpFault(503, 'unavailable');
    $candidate = $directory . DIRECTORY_SEPARATOR . basename($path);
    $pluginRoot = realpath(dirname(__DIR__));
    if (is_string($pluginRoot) && brainwash_path_is_within($pluginRoot, $candidate)) throw new BrainwashHttpFault(503, 'unavailable');
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
    if (is_string($documentRoot) && $documentRoot !== '') {
        $documentRoot = realpath($documentRoot);
        if (is_string($documentRoot) && brainwash_path_is_within($documentRoot, $candidate)) throw new BrainwashHttpFault(503, 'unavailable');
    }
    if ($mustExist) {
        $resolved = realpath($candidate);
        if (!is_string($resolved) || $resolved !== $candidate || !is_file($resolved) || !is_readable($resolved)) {
            throw new BrainwashHttpFault(503, 'unavailable');
        }
        $stat = @lstat($resolved);
        if ($stat === false || (($stat['mode'] ?? 0) & 0170000) !== 0100000 || (($stat['mode'] ?? 0) & 0022) !== 0) {
            throw new BrainwashHttpFault(503, 'unavailable');
        }
    }
    return $candidate;
}

function brainwash_auth_config(): array
{
    $path = brainwash_auth_config_path();
    $config = require $path;
    if (!is_array($config) || array_keys($config) !== ['password_hash'] || !is_string($config['password_hash'])) {
        throw new BrainwashHttpFault(503, 'unavailable');
    }
    return $config;
}

function brainwash_auth_hash_valid(string $passphrase, array $config): bool
{
    $hash = $config['password_hash'] ?? null;
    if ($passphrase === '' || strlen($passphrase) > 4096 || !is_string($hash) || strlen($hash) > 512) return false;
    $info = password_get_info($hash);
    return ($info['algo'] ?? null) !== null && password_verify($passphrase, $hash);
}

function brainwash_csrf_valid(string $expected, string $provided): bool
{
    return preg_match('/\A[a-f0-9]{64}\z/D', $expected) === 1
        && preg_match('/\A[a-f0-9]{64}\z/D', $provided) === 1
        && hash_equals($expected, $provided);
}

function brainwash_origin_matches(string $origin, string $scheme, string $host): bool
{
    if (str_contains($origin, "\r") || str_contains($origin, "\n") || str_contains($host, "\r") || str_contains($host, "\n")) return false;
    $originParts = parse_url($origin);
    $hostParts = parse_url('http://' . $host);
    if (!is_array($originParts) || !is_array($hostParts)
        || isset($originParts['user']) || isset($originParts['pass']) || isset($originParts['path'])
        || isset($originParts['query']) || isset($originParts['fragment'])
        || !isset($originParts['scheme'], $originParts['host'], $hostParts['host'])) return false;
    $scheme = strtolower($scheme);
    if (!in_array($scheme, ['http', 'https'], true) || strtolower($originParts['scheme']) !== $scheme) return false;
    $originPort = $originParts['port'] ?? ($scheme === 'https' ? 443 : 80);
    $requestPort = $hostParts['port'] ?? ($scheme === 'https' ? 443 : 80);
    return strtolower($originParts['host']) === strtolower($hostParts['host']) && $originPort === $requestPort;
}

function brainwash_request_origin_matches(array $server): bool
{
    $origin = $server['HTTP_ORIGIN'] ?? null;
    $host = $server['HTTP_HOST'] ?? null;
    if (!is_string($origin) || !is_string($host) || $origin === '' || $host === '') return false;
    $scheme = (!empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off') ? 'https' : 'http';
    return brainwash_origin_matches($origin, $scheme, $host);
}

function brainwash_decode_json_object(string $body): array
{
    if (strlen($body) > 32768) throw new BrainwashHttpFault(413, 'invalid_request');
    try {
        $decoded = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new BrainwashHttpFault(400, 'invalid_request');
    }
    if (!$decoded instanceof stdClass) throw new BrainwashHttpFault(400, 'invalid_request');
    return json_decode($body, true, 32, JSON_THROW_ON_ERROR);
}

function brainwash_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('BRAINWASHSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!session_start()) throw new BrainwashHttpFault(503, 'unavailable');
}

function brainwash_session_authenticated(): bool
{
    if (($_SESSION['brainwash_authenticated'] ?? false) !== true || !is_int($_SESSION['brainwash_authenticated_at'] ?? null)) return false;
    if (time() - $_SESSION['brainwash_authenticated_at'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        return false;
    }
    return true;
}

function brainwash_ensure_csrf(): string
{
    $token = $_SESSION['brainwash_csrf'] ?? null;
    if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['brainwash_csrf'] = $token;
    }
    return $token;
}

function brainwash_require_same_origin(): void
{
    if (!brainwash_request_origin_matches($_SERVER)) throw new BrainwashHttpFault(403, 'forbidden');
    $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? null;
    if (is_string($fetchSite) && $fetchSite !== '' && !in_array(strtolower($fetchSite), ['same-origin', 'none'], true)) {
        throw new BrainwashHttpFault(403, 'forbidden');
    }
}

function brainwash_require_csrf(string $provided): void
{
    if (!brainwash_csrf_valid(brainwash_ensure_csrf(), $provided)) throw new BrainwashHttpFault(403, 'forbidden');
}

/** Reopen a single authenticated session only around a short read or final transaction. */
function brainwash_session_fence(string $sessionId, string $csrf, callable $callback): mixed
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_name('BRAINWASHSESSID');
    session_id($sessionId);
    if (!session_start()) throw new BrainwashFault('forbidden');
    try {
        if (!brainwash_session_authenticated() || !brainwash_csrf_valid(
            is_string($_SESSION['brainwash_csrf'] ?? null) ? $_SESSION['brainwash_csrf'] : '',
            $csrf
        )) throw new BrainwashFault('forbidden');
        return $callback();
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    }
}

function brainwash_http_message(string $wireCode): string
{
    return match ($wireCode) {
        'invalid_request' => 'The request is invalid.',
        'not_found' => 'The selected NPC is unavailable.',
        'stale_revision' => 'The card changed. Read it again before drafting.',
        'stale_context' => 'The active playthrough changed. Reload the editor.',
        'conflict' => 'This operation conflicts with current NPC state.',
        'forbidden' => 'Authentication or request verification failed.',
        default => 'Brainwash is unavailable right now.',
    };
}

function brainwash_send_json(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function brainwash_security_header_values(): array
{
    return [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        'Cache-Control' => 'no-store',
    ];
}

function brainwash_security_headers(): void
{
    foreach (brainwash_security_header_values() as $name => $value) header($name . ': ' . $value);
}
