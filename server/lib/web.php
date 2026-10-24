<?php
declare(strict_types=1);

final class BrainwashHttpFault extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $wireCode)
    {
        parent::__construct($wireCode);
    }
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

/** Reopen the editor session only around a short read or final transaction. */
function brainwash_session_fence(string $sessionId, string $csrf, callable $callback): mixed
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_name('BRAINWASHSESSID');
    session_id($sessionId);
    if (!session_start()) throw new BrainwashFault('forbidden');
    try {
        if (!brainwash_csrf_valid(
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
        'forbidden' => 'Request verification failed. Reload the editor and try again.',
        default => 'Malpractice Makes Perfect is unavailable right now.',
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
