<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/lib/web.php';
require_once __DIR__ . '/../server/lib/service.php';

function webExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$cases = [
    'fresh browser opens the editor without a configured passphrase' => static function (): void {
        putenv('BRAINWASH_PRIVATE_CONFIG=/nonexistent/brainwash-no-login-test.php');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        require __DIR__ . '/../server/index.php';
        $html = ob_get_clean();
        webExpect(str_contains($html, 'id="brainwash-root"'), 'Editor must open without credentials or provisioning');
        webExpect(!str_contains($html, 'type="password"') && !str_contains($html, 'Sign in'), 'No plugin login should be shown');
        webExpect(preg_match('/name="csrf-token" content="[a-f0-9]{64}"/', $html) === 1, 'Direct editor still needs a CSRF token');
        $sessionId = session_id();
        $token = brainwash_ensure_csrf();
        session_write_close();
        webExpect(brainwash_session_fence($sessionId, $token, static fn(): string => 'allowed') === 'allowed', 'New editor session must work without authentication flags');
        try {
            brainwash_session_fence($sessionId, str_repeat('00', 32), static fn(): string => 'unexpected');
            throw new RuntimeException('Wrong session token was accepted');
        } catch (BrainwashFault $fault) {
            webExpect($fault->wireCode === 'forbidden', 'Wrong token must fail closed');
        }
        brainwash_session_fence($sessionId, $token, static function (): void { $_SESSION = []; });
        try {
            brainwash_session_fence($sessionId, $token, static fn(): string => 'unexpected');
            throw new RuntimeException('Cleared editor session was accepted');
        } catch (BrainwashFault $fault) {
            webExpect($fault->wireCode === 'forbidden', 'Cleared session must fail closed');
        }
        session_id($sessionId);
        session_start();
        session_destroy();
    },
    'same-origin accepts only exact scheme host and port' => static function (): void {
        webExpect(brainwash_origin_matches('https://chim.example:8443', 'https', 'chim.example:8443'), 'Exact origin should pass');
        webExpect(!brainwash_origin_matches('http://chim.example:8443', 'https', 'chim.example:8443'), 'Scheme mismatch must fail');
        webExpect(!brainwash_origin_matches('https://chim.example', 'https', 'chim.example:8443'), 'Port mismatch must fail');
        webExpect(!brainwash_origin_matches('https://chim.example.attacker.invalid:8443', 'https', 'chim.example:8443'), 'Host suffix must fail');
        webExpect(!brainwash_origin_matches('null', 'https', 'chim.example:8443'), 'Opaque origins must fail');
    },
    'security headers preserve browser same-origin form provenance without accepting opaque origins' => static function (): void {
        $headers = brainwash_security_header_values();
        webExpect(($headers['Referrer-Policy'] ?? null) === 'same-origin', 'Same-origin referrer policy must be sent');
        webExpect(!brainwash_origin_matches('null', 'http', '127.0.0.1:18086'), 'Opaque Origin must remain forbidden');
    },
    'CSRF comparison requires a nonempty constant-time token match' => static function (): void {
        $token = str_repeat('ab', 32);
        webExpect(brainwash_csrf_valid($token, $token), 'Exact token should pass');
        webExpect(!brainwash_csrf_valid($token, ''), 'Empty token must fail');
        webExpect(!brainwash_csrf_valid($token, str_repeat('ab', 31) . 'ac'), 'Mismatched token must fail');
    },
    'bounded JSON accepts one object only' => static function (): void {
        webExpect(brainwash_decode_json_object('{"operation":"brainwash.card.read","payload":{}}') === [
            'operation' => 'brainwash.card.read', 'payload' => [],
        ], 'JSON object should decode');
        foreach (['[]', 'null', '"text"', '{bad}', str_repeat(' ', 32769)] as $bad) {
            try { brainwash_decode_json_object($bad); } catch (BrainwashHttpFault) { continue; }
            throw new RuntimeException('Invalid or oversized JSON was accepted');
        }
    },
];

$failed = 0;
foreach ($cases as $name => $case) {
    try {
        $case();
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n");
    }
}
if ($failed > 0) exit(1);
echo count($cases) . " web auth tests passed.\n";
