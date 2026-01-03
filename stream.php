<?php
// /volume1/web/webs/aim/stream.php

$file = basename($_GET['file']);
$sourcePath = "/volume1/ShareFolder/aimyon/Photos/" . $file;
$cacheDir = "/volume1/etc/cache/"; // 사장님이 지정하신 경로
$cachePath = $cacheDir . "thumb_" . $file;

// 캐시 디렉토리가 없으면 생성 (권한 주의)
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

if (file_exists($sourcePath)) {
    // 1. 캐시가 이미 있으면 즉시 출력
    if (file_exists($cachePath)) {
        header("Content-Type: image/jpeg");
        header("Cache-Control: max-age=86400"); // 브라우저 캐시 1일 권장
        readfile($cachePath);
        exit;
    }

    // 2. 캐시가 없으면 썸네일 생성 (원본이 크면 메모리 제한을 위해 일시 상향)
    ini_set('memory_limit', '512M');
    $imgInfo = getimagesize($sourcePath);
    $mime = $imgInfo['mime'];

    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $src = @imagecreatefrompng($sourcePath); break;
        case 'image/gif':  $src = @imagecreatefromgif($sourcePath); break;
        case 'image/webp': $src = @imagecreatefromwebp($sourcePath); break;
        default: readfile($sourcePath); exit;
    }

    if (!$src) { readfile($sourcePath); exit; }

    $thumbSize = 400; // 썸네일 해상도
    $width = imagesx($src);
    $height = imagesy($src);
    $thumb = imagecreatetruecolor($thumbSize, $thumbSize);

    // 정사각형 크롭 리사이즈
    $minSide = min($width, $height);
    imagecopyresampled($thumb, $src, 0, 0, ($width-$minSide)/2, ($height-$minSide)/2, $thumbSize, $thumbSize, $minSide, $minSide);

    // /volume1/etc/cache에 저장
    imagejpeg($thumb, $cachePath, 80);

    // 출력
    header("Content-Type: image/jpeg");
    imagejpeg($thumb);

    imagedestroy($src);
    imagedestroy($thumb);
}