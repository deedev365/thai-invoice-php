<?php
require __DIR__ . '/vendor/autoload.php';

// Build a safe, same-app redirect target (defends against open redirects by
// stripping any host and only honouring admin.php with its query string).
function safe_redirect_target($raw)
{
    $raw = (string) $raw;
    $path = (string) (parse_url($raw, PHP_URL_PATH) ?: '');
    $query = parse_url($raw, PHP_URL_QUERY);

    if (basename($path) === 'admin.php') {
        return 'admin.php' . ($query ? '?' . $query : '');
    }

    return 'admin.php';
}

if (is_admin_authenticated()) {
    header('Location: admin.php');
    exit;
}

$redirect = safe_redirect_target($_POST['redirect'] ?? $_GET['redirect'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (admin_login($_POST['password'] ?? '')) {
        header('Location: ' . $redirect);
        exit;
    }
    $error = 'Incorrect password. Please try again.';
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · Invoice Admin</title>
<link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="navbar">
  <h1 class="navbar-title">Invoice</h1>
  <div class="navbar-menu">
    <a href="index.php">Invoice</a>
    <a href="admin.php" class="active">Admin</a>
  </div>
</div>

<div class="login-shell">
  <form class="login-card" method="post" action="login.php">
    <h1>Invoice Admin</h1>
    <p class="login-hint">Sign in to create and edit invoices.</p>

    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px; margin-bottom: 16px; font-size: 13px; color: #856404;">
      <strong>⚠️ Test Project</strong><br>
      Default password: <code style="background: #fdf5e6; padding: 2px 6px; border-radius: 3px; font-weight: 600;">admin</code>
    </div>

    <?php if ($error !== ''): ?>
      <div class="error"><?= h($error) ?></div>
    <?php endif; ?>
    <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" autofocus required>
    <button type="submit">Sign in</button>
  </form>
</div>
</body>
</html>
