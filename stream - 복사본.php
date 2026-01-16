<?php
// stream.php

/* =========================
 * 1. 기본 설정
 * ========================= */
$photoCacheDir = "/volume1/etc/cache/photos/";
$videoCacheDir = "/volume1/etc/cache/videos/";

// [수정] 로그 파일을 캐시 폴더 안에 생성 (찾기 쉽고 권한 문제 예방)
$logFile       = $videoCacheDir . "debug.log";

// 캐시 폴더 생성
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
 * 3. 원본 경로 설정
 * ========================= */
$basePhotoDir = "/volume1/ShareFolder/aimyon/Photos/";
$baseVideoDir = "/volume1/ShareFolder/aimyon/묭영상/";
$baseTempDir  = "/volume1/etc/aim/photo/";

if ($type === 'temp') {
    $sourcePath = $baseTempDir . $file;
} elseif ($type === 'video') {
    $sourcePath = $baseVideoDir . $file;
} else {
    $sourcePath = $basePhotoDir . $file;
}

if (!file_exists($sourcePath)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

/* =========================
 * 4. 전체 보기 및 스트리밍
 * ========================= */
if ($full || ($type === 'video' && !$isThumb)) {
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $mime = in_array($ext, ['mp4','webm','mov','m4v'])
        ? "video/mp4"
        : (@getimagesize($sourcePath)['mime'] ?? 'image/jpeg');

    if ($type === 'video') {
        $size = filesize($sourcePath);
        $fp = @fopen($sourcePath, 'rb');
        $start = 0; $end = $size - 1;
        
        header("Accept-Ranges: bytes");
        header("Content-Type: $mime");

        if (isset($_SERVER['HTTP_RANGE'])) {
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable'); exit;
            }
            if ($range == '-') {
                $c_start = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size - 1;
            }
            $c_end = ($c_end > $size - 1) ? $size - 1 : $c_end;
            
            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable'); exit;
            }
            $start = $c_start; $end = $c_end;
            $length = $end - $start + 1;
            fseek($fp, $start);
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$size");
            header("Content-Length: $length");
        } else {
            header("Content-Length: $size");
        }

        $buffer = 1024 * 8;
        while(!feof($fp) && ($p = ftell($fp)) <= $end) {
            if ($p + $buffer > $end) $buffer = $end - $p + 1;
            echo fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit;
    } else {
        header("Content-Type: $mime");
        header("Content-Length: " . filesize($sourcePath));
        readfile($sourcePath);
        exit;
    }
}

/* =========================
 * 5. 썸네일 캐시 경로
 * ========================= */
$cachePath = ($type === 'video')
    ? $videoCacheDir . $file . ".jpg"
    : $photoCacheDir . "thumb_" . ($type === 'temp' ? "temp_" : "") . $file . ".jpg";

if (file_exists($cachePath) && filesize($cachePath) > 0) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

/* =========================
 * 6. 썸네일 생성 로직
 * ========================= */
$created = false;
$ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

// (1) WebP (Imagick)
if ($ext === 'webp' && extension_loaded('imagick')) {
    try {
        $img = new Imagick($sourcePath);
        $img->setIteratorIndex(0);
        $img->thumbnailImage(400, 0);
        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality(80);
        $img->writeImage($cachePath);
        $img->clear();
        $img->destroy();
        $created = true;
    } catch (Exception $e) { $created = false; }
}

// (2) JPG/PNG (GD)
if (!$created && in_array($ext, ['jpg','jpeg','png']) && function_exists('imagecreatefromstring')) {
    $data = @file_get_contents($sourcePath);
    if ($data !== false) {
        $src = @imagecreatefromstring($data);
        if ($src) {
            $w = imagesx($src); $h = imagesy($src);
            $tw = 400; $th = intval($h * ($tw / $w));
            $dst = imagecreatetruecolor($tw, $th);
            $bg = imagecolorallocate($dst, 255,255,255);
            imagefilledrectangle($dst, 0,0,$tw,$th,$bg);
            imagecopyresampled($dst, $src, 0,0,0,0, $tw,$th, $w,$h);
            if (imagejpeg($dst, $cachePath, 80)) $created = true;
            imagedestroy($src); imagedestroy($dst);
        }
    }
}

// (3) Video/GIF (FFmpeg)
if (!$created && ($ext === 'gif' || $type === 'video')) {
    // 1. FFmpeg 경로 찾기
    $ffmpeg = "/usr/bin/ffmpeg";
    if (!file_exists($ffmpeg)) $ffmpeg = "/opt/bin/ffmpeg";
    if (!file_exists($ffmpeg)) {
        $ffmpeg = "/var/packages/ffmpeg7/target/bin/ffmpeg";
        putenv("LD_LIBRARY_PATH=/var/packages/ffmpeg7/target/lib");
    }

    $seek = ($type === 'video') ? "-ss 00:00:02" : "";
    
    // 명령어
    $cmd = "$ffmpeg -y $seek -i " . escapeshellarg($sourcePath)
         . " -vframes 1 -an -q:v 2 -f image2 " . escapeshellarg($cachePath) . " 2>&1";

    exec($cmd, $out, $ret);

    if (file_exists($cachePath) && filesize($cachePath) > 0) {
        $created = true;
    } else {
        // [중요] 디버그 로그 기록
        $logContent = "-------------- " . date('Y-m-d H:i:s') . " --------------\n";
        $logContent .= "FFmpeg Found At: $ffmpeg\n";
        $logContent .= "Target File: $file\n";
        $logContent .= "Source Path: $sourcePath\n";
        $logContent .= "Command: $cmd\n";
        $logContent .= "Result Code: $ret\n";
        $logContent .= "Output:\n" . implode("\n", $out) . "\n\n";
        @file_put_contents($logFile, $logContent, FILE_APPEND);
    }
}

/* =========================
 * 7. 결과 출력
 * ========================= */
if ($created) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

// 실패 시 이미지
$im = imagecreatetruecolor(400, 300);
$bg = imagecolorallocate($im, 30,0,0);
$tc = imagecolorallocate($im, 255,255,255);
imagestring($im, 5, 120, 140, "No Thumbnail", $tc);
header("Content-Type: image/jpeg");
imagejpeg($im);
imagedestroy($im);
?>