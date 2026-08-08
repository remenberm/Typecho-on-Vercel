<?php
$rootDir = dirname(__DIR__);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestedPath = '/' . ltrim($requestPath, '/');
$resolvedPath = $rootDir . $requestedPath;

if ($requestedPath === '/' || $requestedPath === '/index.php') {
    require_once $rootDir . '/index.php';
    return;
}

if (preg_match('/\.php$/i', $requestedPath) && file_exists($resolvedPath)) {
    require_once $resolvedPath;
    return;
}

if (file_exists($resolvedPath) && !is_dir($resolvedPath)) {
    $mimeType = mime_content_type($resolvedPath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mimeType);
    readfile($resolvedPath);
    return;
}

require_once $rootDir . '/index.php';
