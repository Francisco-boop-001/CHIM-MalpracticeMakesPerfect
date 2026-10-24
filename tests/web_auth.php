<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/lib/web.php';

function webExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$cases = [
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
    'passphrase authentication accepts only a password hash match' => static function (): void {
        $hash = password_hash('test-only secret phrase', PASSWORD_DEFAULT);
        webExpect(brainwash_auth_hash_valid('test-only secret phrase', ['password_hash' => $hash]), 'Correct passphrase should pass');
        webExpect(!brainwash_auth_hash_valid('wrong', ['password_hash' => $hash]), 'Wrong passphrase should fail');
        webExpect(!brainwash_auth_hash_valid('test-only secret phrase', []), 'Missing hash must fail closed');
        webExpect(!brainwash_auth_hash_valid('test-only secret phrase', ['password_hash' => 'not-a-hash']), 'Malformed hash must fail closed');
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
