<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/web.php';
brainwash_security_headers();
brainwash_start_session();
$authenticated = brainwash_session_authenticated();
$csrf = brainwash_ensure_csrf();

$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        brainwash_require_same_origin();
        brainwash_require_csrf(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : '');
        $action = $_POST['action'] ?? 'login';
        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict']);
            }
            session_destroy();
            header('Location: index.php', true, 303);
            exit;
        }
        if ($action !== 'login' || brainwash_session_authenticated()) throw new BrainwashHttpFault(400, 'invalid_request');
        $passphrase = $_POST['passphrase'] ?? null;
        if (!is_string($passphrase) || strlen($passphrase) > 4096) {
            $message = 'Passphrase was not accepted.';
        } else {
            $attempts = (int)($_SESSION['brainwash_login_attempts'] ?? 0);
            $lastAttempt = (int)($_SESSION['brainwash_login_attempt_at'] ?? 0);
            if ($lastAttempt > 0 && time() - $lastAttempt > 60) $attempts = 0;
            if ($attempts >= 5) {
                $message = 'Too many attempts. Wait one minute and try again.';
            } else {
                try {
                    $valid = brainwash_auth_hash_valid($passphrase, brainwash_auth_config());
                } catch (BrainwashHttpFault) {
                    $valid = false;
                }
                if (!$valid) {
                    $_SESSION['brainwash_login_attempts'] = $attempts + 1;
                    $_SESSION['brainwash_login_attempt_at'] = time();
                    $message = 'Passphrase was not accepted.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION = [
                        'brainwash_authenticated' => true,
                        'brainwash_authenticated_at' => time(),
                        'brainwash_csrf' => bin2hex(random_bytes(32)),
                    ];
                    header('Location: index.php', true, 303);
                    exit;
                }
            }
        }
    } catch (BrainwashHttpFault) {
        http_response_code(403);
        $message = 'Request verification failed. Reload this page and try again.';
    }
}

$authenticated = brainwash_session_authenticated();
header('Content-Type: text/html; charset=utf-8');
function brainwash_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= brainwash_html($csrf) ?>">
  <title>Brainwash</title>
  <link rel="stylesheet" href="assets/editor.css">
</head>
<body>
<main class="brainwash-page">
  <header class="brainwash-header">
    <div><h1>Brainwash</h1><p>Draft, review, and selectively apply NPC profile edits.</p></div>
    <?php if ($authenticated): ?>
      <form method="post" action="index.php">
        <input type="hidden" name="_csrf" value="<?= brainwash_html($csrf) ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit">Log out</button>
      </form>
    <?php endif; ?>
  </header>
  <?php if ($authenticated): ?>
    <div id="brainwash-root"></div>
    <script src="assets/editor.js" defer></script>
    <script src="assets/web.js" defer></script>
  <?php else: ?>
    <section class="brainwash-panel brainwash-login">
      <h2>Sign in</h2>
      <p>Use the server's configured Brainwash passphrase.</p>
      <?php if ($message !== ''): ?><p class="brainwash-status" role="status"><?= brainwash_html($message) ?></p><?php endif; ?>
      <form method="post" action="index.php">
        <input type="hidden" name="_csrf" value="<?= brainwash_html($csrf) ?>">
        <input type="hidden" name="action" value="login">
        <label for="brainwash-passphrase">Passphrase</label>
        <input id="brainwash-passphrase" name="passphrase" type="password" autocomplete="current-password" required maxlength="4096">
        <button type="submit">Sign in</button>
      </form>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
