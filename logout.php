<?php
require __DIR__ . '/vendor/autoload.php';

admin_logout();

header('Location: login.php');
exit;
