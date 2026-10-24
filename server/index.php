<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/web.php';
brainwash_security_headers();
brainwash_start_session();
$csrf = brainwash_ensure_csrf();

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
  <title>Malpractice Makes Perfect</title>
  <link rel="stylesheet" href="assets/editor.css">
</head>
<body>
<main class="brainwash-page">
  <header class="brainwash-header">
    <div><h1>Malpractice Makes Perfect</h1><p>Unlicensed character development</p></div>
  </header>
  <div id="brainwash-root"></div>
  <script src="assets/editor.js" defer></script>
  <script src="assets/web.js" defer></script>
</main>
</body>
</html>
