<?php
// stream.php

$type = $_GET['type'] ?? 'gallery';
$file = basename($_GET['file']);
$full = isset($_GET['full']); // 크게 보기 요청 여부

if ($type === 'temp') {
    $sourcePath = "/volume1/etc/aim/photo/" . $file;
} else {
    $sourcePath = "/volume1/ShareFolder/aimyon/Photos/" . $file;
}

if (!file_exists($sourcePath)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// 모달에서 크게 보거나 원본이 필요한 경우 리사이즈 없이 출력
if ($full) {
    $mime = @getimagesize($sourcePath)['mime'] ?? 'image/jpeg';
    header("Content-Type: " . $mime);
    readfile($sourcePath);
    exit;
}

// 썸네일 캐시 로직 (그리드 뷰 용)
$cacheDir = "/volume1/etc/cache/";
$cachePath = $cacheDir . "thumb_" . ($type === 'temp' ? "temp_" : "") . $file;

if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);

if (file_exists($cachePath)) {
    header("Content-Type: image/jpeg");
    readfile($cachePath);
    exit;
}

// 썸네일 생성
ini_set('memory_limit', '512M');
$imgInfo = getimagesize($sourcePath);
$mime = $imgInfo['mime'];

switch ($mime) {
    case 'image/jpeg': $src = @imagecreatefromjpeg($sourcePath); break;
    case 'image/png':  $src = @imagecreatefrompng($sourcePath); break;
    case 'image/gif':  $src = @imagecreatefromgif($sourcePath); break;
    case 'image/webp': $src = @imagecreatefromwebp($sourcePath); break;
    default: header("Content-Type: ".$mime); readfile($sourcePath); exit;
}

if (!$src) { header("Content-Type: ".$mime); readfile($sourcePath); exit; }

$thumbSize = 400; 
$width = imagesx($src);
$height = imagesy($src);
$thumb = imagecreatetruecolor($thumbSize, $thumbSize);

$minSide = min($width, $height);
imagecopyresampled($thumb, $src, 0, 0, ($width-$minSide)/2, ($height-$minSide)/2, $thumbSize, $thumbSize, $minSide, $minSide);

imagejpeg($thumb, $cachePath, 80);
header("Content-Type: image/jpeg");
imagejpeg($thumb);

imagedestroy($src);
imagedestroy($thumb);