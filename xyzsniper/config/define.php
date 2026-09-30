<?php

// Environment File Loader
$env = parse_ini_file(getenv('SNIPER_ENV_FILE') ?: __DIR__ . '/.env');
$mode = strtolower(trim($env['APP_MODE'] ?? 'local'));

if (!in_array($mode, ['local', 'host'], true)) {
  die('APP_MODE harus bernilai local atau host.');
}

$environment = strtoupper($mode);
$canonicalAppUrl = rtrim($env['APP_URL_' . $environment], '/');
$appUrl = $canonicalAppUrl;

// Keep local browser sessions and assets on the local origin even when the
// configured canonical URL is the public Cloudflare tunnel hostname.
if ($mode === 'local') {
  $requestHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
  if (in_array($requestHost, ['localhost:8000', '127.0.0.1:8000', 'localhost', '127.0.0.1'], true)) {
    $appUrl = 'http://' . $requestHost;
  }
}

// Core Application Info
define('app_name', $env['APP_NAME']);
define('APP_MODE', $mode);
define('APP_CANONICAL_URL', $canonicalAppUrl);
define('burl', $appUrl);
define('assets', $appUrl . '/assets');
define('images', $appUrl . '/assets/images');
define('web_icons', $appUrl . '/assets/web_icons');
define('js', $appUrl . '/assets/js');
define('GOOGLE_CLIENT_ID', $env['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $env['GOOGLE_CLIENT_SECRET'] ?? '');
define('GOOGLE_REDIRECT_URI', $appUrl . '/ProcAuth/googleCallback');

// Routing Constants
define('DEFAULT_CONTROLLER', $env['DEFAULT_CONTROLLER']);

// Database Constants
define('DB_HOST', $env['DB_HOST_' . $environment]);
define('DB_PORT', $env['DB_PORT_' . $environment]);
define('DB_USER', $env['DB_USER_' . $environment]);
define('DB_PASS', $env['DB_PASS_' . $environment]);
define('DB_NAME', $env['DB_NAME_' . $environment]);
