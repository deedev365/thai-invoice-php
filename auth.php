<?php
// auth.php — minimal session-based single-password gate for the admin editor
// and the write/enumeration API actions. The public print view (index.php +
// api.php?action=get) stays open so invoice links can be shared with customers.
//
// The password is stored only as a hash. Resolution order:
//   1. env INVOICE_ADMIN_PASSWORD_HASH  (best for shared hosting / CI)
//   2. auth_config.php returning ['password_hash' => '...']
//   3. built-in default below (password: "invoice2026" — CHANGE THIS)
//
// Generate a new hash:
//   php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
// then drop it into auth_config.php (copy auth_config.sample.php).

// Fallback so the admin is never locked out with no configured password.
// Default password: "admin"
const INVOICE_DEFAULT_PASSWORD_HASH = '$2y$12$oWaVqGgVzAOiX3iQNkibhOkTjw2lfK465ZzeW2/ny.D.prW5nazii';

function auth_start_session()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
}

function admin_password_hash()
{
    $envHash = getenv('INVOICE_ADMIN_PASSWORD_HASH');
    if (is_string($envHash) && $envHash !== '') {
        return $envHash;
    }

    $config = __DIR__ . '/auth_config.php';
    if (is_file($config)) {
        $data = require $config;
        if (is_array($data) && !empty($data['password_hash'])) {
            return (string) $data['password_hash'];
        }
    }

    return INVOICE_DEFAULT_PASSWORD_HASH;
}

function is_admin_authenticated()
{
    auth_start_session();

    return !empty($_SESSION['invoice_admin']);
}

// Verify a password attempt and, on success, start an authenticated session.
function admin_login($password)
{
    if (!password_verify((string) $password, admin_password_hash())) {
        return false;
    }

    auth_start_session();
    session_regenerate_id(true);
    $_SESSION['invoice_admin'] = true;

    return true;
}

function admin_logout()
{
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// Page guard: redirect unauthenticated visitors to the login form.
function require_admin_page()
{
    if (is_admin_authenticated()) {
        return;
    }

    $target = $_SERVER['REQUEST_URI'] ?? 'admin.php';
    header('Location: login.php?redirect=' . urlencode($target));
    exit;
}

// API guard: respond 401 JSON for unauthenticated write/enumeration calls.
function require_admin_api()
{
    if (is_admin_authenticated()) {
        return;
    }

    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Authentication required. Please sign in.']);
    exit;
}
