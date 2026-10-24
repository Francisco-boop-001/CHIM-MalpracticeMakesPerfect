<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/web.php';
brainwash_security_headers();
brainwash_start_session();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new BrainwashHttpFault(405, 'invalid_request');
    brainwash_require_same_origin();
    brainwash_require_csrf(is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
    if ($contentType !== 'application/json') throw new BrainwashHttpFault(415, 'invalid_request');
    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if (!is_string($raw)) throw new BrainwashHttpFault(400, 'invalid_request');
    $request = brainwash_decode_json_object($raw);
    try {
        $wireRequest = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new BrainwashHttpFault(400, 'invalid_request');
    }
    if (array_diff(array_keys($request), ['operation', 'payload']) !== [] || !is_string($request['operation'] ?? null)
        || !isset($wireRequest->payload) || !($wireRequest->payload instanceof stdClass)
        || !is_array($request['payload'] ?? null) || strlen($request['operation']) > 64) {
        throw new BrainwashHttpFault(400, 'invalid_request');
    }
    $operation = $request['operation'];
    $payload = $request['payload'];
    $sessionId = session_id();
    $sessionCsrf = is_string($_SESSION['brainwash_csrf'] ?? null) ? $_SESSION['brainwash_csrf'] : '';
    if ($sessionId === '' || $sessionCsrf === '') throw new BrainwashHttpFault(403, 'forbidden');
    session_write_close();

    require_once __DIR__ . '/runtime.php';
    $context = [
        'assertCurrent' => static function () use ($sessionId, $sessionCsrf): void {
            brainwash_session_fence($sessionId, $sessionCsrf, static fn(): null => null);
        },
        'withCurrentContext' => static fn(callable $callback): mixed => brainwash_session_fence($sessionId, $sessionCsrf, $callback),
        'withLockedCurrentContext' => static fn(callable $callback): mixed => brainwash_session_fence($sessionId, $sessionCsrf, $callback),
    ];
    $data = brainwash_dispatch($operation, $payload, $context);
    brainwash_send_json(200, ['ok' => true, 'data' => $data]);
} catch (BrainwashFault $fault) {
    $status = match ($fault->wireCode) {
        'invalid_request' => 400,
        'not_found' => 404,
        'stale_revision', 'stale_context', 'conflict' => 409,
        'forbidden' => 403,
        default => 503,
    };
    brainwash_send_json($status, ['ok' => false, 'error' => ['code' => $fault->wireCode, 'message' => brainwash_http_message($fault->wireCode)]]);
} catch (BrainwashHttpFault $fault) {
    brainwash_send_json($fault->status, ['ok' => false, 'error' => ['code' => $fault->wireCode, 'message' => brainwash_http_message($fault->wireCode)]]);
} catch (Throwable) {
    brainwash_send_json(503, ['ok' => false, 'error' => ['code' => 'unavailable', 'message' => brainwash_http_message('unavailable')]]);
}
