<?php

$publicDirectory = __DIR__ . '/public';
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestedFile = realpath($publicDirectory . rawurldecode($requestPath));

if ($requestedFile !== false && str_starts_with($requestedFile, $publicDirectory . DIRECTORY_SEPARATOR) && is_file($requestedFile)) {
    return false;
}

$route = trim($requestPath, '/');
if ($route === '') {
    unset($_GET['url']);
} else {
    $_GET['url'] = $route;
}
chdir($publicDirectory);
require $publicDirectory . '/index.php';
