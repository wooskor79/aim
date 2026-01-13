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

// 2. 원본 파일 스트리밍 (모달 재생 시)
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

// 3. 캐시 폴더 정의 및 생성
$photoCacheDir = "/volume1/etc/cache/photos/";
$videoCacheDir = "/volume1/etc/cache/videos/";

if (!is_dir($photoCacheDir)) @mkdir($photoCacheDir, 0777, true);
if (!is_dir($videoCacheDir)) @mkdir($videoCacheDir, 0777, true);

// 캐시 파일명 결정
if ($type === 'video') {
    $cachePath = $videoCacheDir . $file . ".jpg";
} else {
    $cachePath = $photoCacheDir . "thumb_" . ($type === 'temp' ? "temp_" : "") . $file;
}

// 4. 캐시가 이미 존재하면 즉시 출력 후 종료
if (file_exists($cachePath)) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

// 5. 캐시가 없을 경우 생성 로직
ini_set('memory_limit', '512M');

if ($type === 'video') {
    // [강화된 영상 캐시 생성]
    // -y: 기존 파일 덮어쓰기
    // -ss 00:00:01: 영상의 1초 지점 추출 (0초는 검은 화면일 확률이 높음)
    $cmd = "/usr/bin/ffmpeg -y -i " . escapeshellarg($sourcePath) . " -ss 00:00:01 -vframes:v 1 -q:v 2 " . escapeshellarg($cachePath) . " 2>&1";
    shell_exec($cmd);
    
    // 생성 성공 여부 확인 후 즉시 출력
    if (file_exists($cachePath)) {
        header("Content-Type: image/jpeg");
        header("Content-Length: " . filesize($cachePath));
        readfile($cachePath);
    } else {
        // 실패 시 404 처리 (이미지 깨짐 방지 위해 투명 이미지 등으로 대체 가능)
        header("HTTP/1.0 404 Not Found");
    }
    exit;
} else {
    // [사진 캐시 생성 로직]
    $imgInfo = @getimagesize($sourcePath);
    if (!$imgInfo) exit;
    $mime = $imgInfo['mime'];

    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $src = @imagecreatefrompng($sourcePath); break;
        case 'image/gif':  $src = @imagecreatefromgif($sourcePath); break;
        case 'image/webp': $src = @imagecreatefromwebp($sourcePath); break;
        default: exit;
    }

    if ($src) {
        $thumbSize = 400; 
        $width = imagesx($src);
        $height = imagesy($src);
        $thumb = imagecreatetruecolor($thumbSize, $thumbSize);
        $minSide = min($width, $height);
        imagecopyresampled($thumb, $src, 0, 0, ($width-$minSide)/2, ($height-$minSide)/2, $thumbSize, $thumbSize, $minSide, $minSide);

        imagejpeg($thumb, $cachePath, 80); // 파일로 저장
        
        header("Content-Type: image/jpeg");
        imagejpeg($thumb); // 브라우저에 출력

        imagedestroy($src);
        imagedestroy($thumb);
    }
}
?>