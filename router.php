<?php
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// 1. Serve static files/real paths if they exist
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// 2. Map pretty URLs to the public directory (e.g. /login.php -> /public/login.php)
$publicPath = __DIR__ . '/public' . $uri;
if ($uri !== '/' && file_exists($publicPath) && !is_dir($publicPath)) {
    if (pathinfo($publicPath, PATHINFO_EXTENSION) === 'php') {
        require_once $publicPath;
        return true;
    }

    // Serve static files from public/
    $mime = mime_content_type($publicPath);
    header("Content-Type: $mime");
    readfile($publicPath);
    return true;
}

// 3. Fallback to index.php
require_once __DIR__ . '/public/index.php';

