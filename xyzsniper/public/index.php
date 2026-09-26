<?php

// Timezone Configuration
date_default_timezone_set('Asia/Jakarta');

// Session Setup
if (!session_id()) {
  session_set_cookie_params(['httponly' => true, 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'samesite' => 'Lax', 'path' => '/']);
  session_start();
}

// Bootstrapping
require_once '../app/init.php';

// App Initialization
$app = new App();
