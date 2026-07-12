<?php
// auth_config.php — local admin password configuration (not tracked by design).
//
// 1. Copy this file to auth_config.php in the same folder.
// 2. Generate a password hash:
//        php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
// 3. Paste the hash below.
//
// If this file is absent, auth.php falls back to the built-in default password
// ("invoice2026") — fine for first run, but set your own before going live.

return [
    'password_hash' => '$2y$12$replace-with-your-own-generated-hash',
];
