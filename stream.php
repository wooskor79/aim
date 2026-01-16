<?php
// stream.php

/* =========================
 * 1. 기본 설정
 * ========================= */
$photoCacheDir = "/volume1/etc/cache/photos/";
$videoCacheDir = "/volume1/etc/cache/videos/";
$logFile       = "/volume1/etc/aim/ffmpeg_error.log";

@mkdir($photoCacheDir, 0777, true);
@mkdir($videoCacheDir, 0777, true);

/* =========================
 * 2. 요청 파라미터
 * ========================= */
$type    = $_GET['type']  ?? 'gallery';
$file    = basename($_GET['file'] ?? '');
$full    = isset($_GET['full']);
$isThumb = isset($_GET['thumb']);

/* =========================
 * 3. 원본 경로
 * ========================= */
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

/* =========================
 * 4. 전체 보기 (원본 전달)
 * ========================= */
if ($full || ($type === 'video' && !$isThumb)) {
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $mime = in_array($ext, ['mp4','webm','mov','m4v'])
        ? "video/mp4"
        : (@getimagesize($sourcePath)['mime'] ?? 'image/jpeg');

    header("Content-Type: $mime");
    header("Content-Length: " . filesize($sourcePath));
    readfile($sourcePath);
    exit;
}

/* =========================
 * 5. 캐시 경로
 * ========================= */
$cachePath = ($type === 'video')
    ? $videoCacheDir . $file . ".jpg"
    : $photoCacheDir . "thumb_" . ($type === 'temp' ? "temp_" : "") . $file . ".jpg";

/* =========================
 * 6. 캐시 있으면 즉시 반환
 * ========================= */
if (file_exists($cachePath) && filesize($cachePath) > 0) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

/* =========================
 * 7. 썸네일 생성
 * ========================= */
$created = false;
$ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

/* ---------- WebP : Imagick ---------- */
if ($ext === 'webp' && extension_loaded('imagick')) {
    try {
        $img = new Imagick($sourcePath);
        $img->setIteratorIndex(0); // 애니메이션 WebP 첫 프레임
        $img->thumbnailImage(400, 0);
        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality(80);
        $img->writeImage($cachePath);
        $img->clear();
        $img->destroy();
        $created = true;
    } catch (Exception $e) {
        $created = false;
    }
}

/* ---------- JPG / PNG : GD ---------- */
if (!$created && in_array($ext, ['jpg','jpeg','png']) && function_exists('imagecreatefromstring')) {
    $data = @file_get_contents($sourcePath);
    if ($data !== false) {
        $src = @imagecreatefromstring($data);
        if ($src !== false) {
            $w = imagesx($src);
            $h = imagesy($src);
            $tw = 400;
            $th = intval($h * ($tw / $w));

            $dst = imagecreatetruecolor($tw, $th);
            $bg  = imagecolorallocate($dst, 255,255,255);
            imagefilledrectangle($dst, 0,0,$tw,$th,$bg);
            imagecopyresampled($dst, $src, 0,0,0,0, $tw,$th, $w,$h);

            if (imagejpeg($dst, $cachePath, 80)) {
                $created = true;
            }
            imagedestroy($src);
            imagedestroy($dst);
        }
    }
}

/* ---------- GIF / VIDEO : FFmpeg ---------- */
if (!$created && ($ext === 'gif' || $type === 'video')) {
    $ffmpeg = "/opt/bin/ffmpeg";
    if (!file_exists($ffmpeg)) {
        $ffmpeg = "/var/packages/ffmpeg7/target/bin/ffmpeg";
        putenv("LD_LIBRARY_PATH=/var/packages/ffmpeg7/target/lib");
    }

    $seek = ($type === 'video') ? "-ss 00:00:02" : "";
    $cmd  = "$ffmpeg -y $seek -i " . escapeshellarg($sourcePath)
          . " -vframes 1 -an -q:v 2 " . escapeshellarg($cachePath) . " 2>&1";

    exec($cmd, $out, $ret);
    if (file_exists($cachePath) && filesize($cachePath) > 0) {
        $created = true;
    } else {
        @file_put_contents(
            $logFile,
            "[".date('Y-m-d H:i:s')."] FFmpeg fail: $file\n$cmd\n".implode(" ",$out)."\n\n",
            FILE_APPEND
        );
    }
}

/* =========================
 * 8. 결과 출력
 * ========================= */
if ($created) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

/* =========================
 * 9. 실패 시 에러 썸네일
 * ========================= */
$im = imagecreatetruecolor(400, 300);
$bg = imagecolorallocate($im, 0,0,0);
$tc = imagecolorallocate($im, 255,50,50);
imagestring($im, 5, 120, 140, "Thumbnail Error", $tc);
header("Content-Type: image/jpeg");
imagejpeg($im);
imagedestroy($im);
