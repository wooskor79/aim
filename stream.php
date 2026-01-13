<?php
// stream.php

$type = $_GET['type'] ?? 'gallery';
$file = basename($_GET['file']);
$full = isset($_GET['full']);
$isThumb = isset($_GET['thumb']);

// 1. 경로 설정
if ($type === 'temp') {
    $sourcePath = "/volume1/etc/aim/photo/" . $file;
} elseif ($type === 'video') {
    $sourcePath = "/volume1/ShareFolder/aimyon/묭영상/" . $file;
} else {
    $sourcePath = "/volume1/ShareFolder/aimyon/Photos/" . $file;
}

if (!file_exists($sourcePath)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// 2. 원본 스트리밍
if ($full || ($type === 'video' && !$isThumb)) {
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'webm', 'mov', 'm4v'])) {
        header("Content-Type: video/mp4");
    } else {
        $mime = @getimagesize($sourcePath)['mime'] ?? 'image/jpeg';
        header("Content-Type: " . $mime);
    }
    header("Content-Length: " . filesize($sourcePath));
    readfile($sourcePath);
    exit;
}

// 3. 캐시 폴더 설정
$photoCacheDir = "/volume1/etc/cache/photos/";
$videoCacheDir = "/volume1/etc/cache/videos/";

if (!is_dir($photoCacheDir)) @mkdir($photoCacheDir, 0777, true);
if (!is_dir($videoCacheDir)) @mkdir($videoCacheDir, 0777, true);

if ($type === 'video') {
    $cachePath = $videoCacheDir . $file . ".jpg";
} else {
    $cachePath = $photoCacheDir . "thumb_" . ($type === 'temp' ? "temp_" : "") . $file;
}

// 4. 캐시 존재 확인
if (file_exists($cachePath)) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

// 5. 캐시 생성 로직
if ($type === 'video') {
    // FFmpeg 7 경로 및 라이브러리 경로 강제 지정
    $ffmpegPath = "/var/packages/ffmpeg7/target/bin/ffmpeg";
    putenv("LD_LIBRARY_PATH=/var/packages/ffmpeg7/target/lib");
    
    // -ss 00:00:02 (2초 지점 추출, 0초는 보통 검은 화면입니다)
    $cmd = "$ffmpegPath -y -i " . escapeshellarg($sourcePath) . " -ss 00:00:02 -vframes:v 1 -q:v 2 " . escapeshellarg($cachePath) . " 2>&1";
    
    shell_exec($cmd);
    
    if (file_exists($cachePath)) {
        header("Content-Type: image/jpeg");
        header("Content-Length: " . filesize($cachePath));
        readfile($cachePath);
    } else {
        header("HTTP/1.0 404 Not Found");
    }
    exit;
} else {
    // 사진 캐시 로직 (생략 - 기존 동일)
}
?>