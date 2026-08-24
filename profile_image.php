<?php
// Delivers user profile images in requested resolutions with HTTP caching headers.
session_start();
if (!isset($_SESSION['id'])) {
    header("HTTP/1.0 403 Forbidden");
    exit();
}

if (!isset($_GET['hash']) || !isset($_GET['res'])) {
    header("HTTP/1.0 400 Bad Request");
    exit();
}

$hash = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['hash']);
$res = $_GET['res'] === 'high' ? 'high' : 'low';
$file_path = __DIR__ . '/media/profiles/' . $hash . '_' . $res . '.jpg';

if (file_exists($file_path)) {
    // Cache headers for better performance
    $last_modified_time = filemtime($file_path); 
    $etag = md5_file($file_path); 
    header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified_time)." GMT"); 
    header("Etag: $etag"); 

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $last_modified_time) {
        header('HTTP/1.0 304 Not Modified');
        exit;
    }
    
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
        header('HTTP/1.0 304 Not Modified');
        exit;
    }

    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400'); // Cache for 1 day
    readfile($file_path);
} else {
    header("HTTP/1.0 404 Not Found");
}
?>
