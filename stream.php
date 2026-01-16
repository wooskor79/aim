<?php
session_start();
$config = include 'config.php'; // 설정 로드

// [수정] 설정 파일의 캐시 경로 사용
$photoCacheDir = $config['photo_cache'];
$videoCacheDir = $config['video_cache'];
$logFile       = $videoCacheDir . "debug.log";

@mkdir($photoCacheDir, 0777, true);
@mkdir($videoCacheDir, 0777, true);

$type    = $_GET['type']  ?? 'gallery';
$file    = basename($_GET['file'] ?? ''); 
$full    = isset($_GET['full']);
$isThumb = isset($_GET['thumb']);

if (empty($file)) { header("HTTP/1.0 400 Bad Request"); exit; }

// 파일 위치 찾기 함수
function findFileInDirs($filename, $dirs) {
    foreach ($dirs as $dir) {
        if (file_exists($dir . $filename)) return $dir . $filename;
    }
    return null;
}

// 경로 찾기
$sourcePath = null;
if ($type === 'temp') {
    if(file_exists($config['temp_dir'] . $file)) {
        $sourcePath = $config['temp_dir'] . $file;
    }
} elseif ($type === 'video') {
    $sourcePath = findFileInDirs($file, $config['video_dirs']);
} else {
    $sourcePath = findFileInDirs($file, $config['photo_dirs']);
}

if (!$sourcePath || !file_exists($sourcePath)) { 
    header("HTTP/1.0 404 Not Found"); exit; 
}

// 원본 보기 및 스트리밍
if ($full || ($type === 'video' && !$isThumb)) {
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'mp4','webm','mov','m4v' => 'video/mp4',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => 'image/jpeg'
    };

    if ($type === 'video') {
        $size = filesize($sourcePath);
        $fp = @fopen($sourcePath, 'rb');
        $start = 0; $end = $size - 1;
        header("Accept-Ranges: bytes");
        header("Content-Type: $mime");

        if (isset($_SERVER['HTTP_RANGE'])) {
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) { header('HTTP/1.1 416 Requested Range Not Satisfiable'); exit; }
            if ($range == '-') $c_start = $size - substr($range, 1);
            else {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size - 1;
            }
            $c_end = ($c_end > $size - 1) ? $size - 1 : $c_end;
            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) { header('HTTP/1.1 416 Requested Range Not Satisfiable'); exit; }
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

// 썸네일 캐시 및 생성 로직
$cachePath = ($type === 'video')
    ? $videoCacheDir . $file . ".jpg"
    : $photoCacheDir . "thumb_" . ($type === 'temp' ? "temp_" : "") . $file . ".jpg";

if (file_exists($cachePath) && filesize($cachePath) > 0) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

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
        $img->clear(); $img->destroy();
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
    $ffmpeg_candidates = [
        '/var/packages/ffmpeg6/target/bin/ffmpeg',
        '/var/packages/ffmpeg7/target/bin/ffmpeg',
        '/var/packages/ffmpeg5/target/bin/ffmpeg', 
        '/usr/local/bin/ffmpeg',
        '/usr/bin/ffmpeg'
    ];
    $ffmpeg = '';
    foreach ($ffmpeg_candidates as $path) {
        if (file_exists($path)) {
            $ffmpeg = $path;
            if (strpos($path, 'packages') !== false) {
                 $libPath = dirname(dirname($path)) . '/lib';
                 putenv("LD_LIBRARY_PATH=$libPath");
            }
            break;
        }
    }
    if ($ffmpeg) {
        $seek = ($type === 'video') ? "-ss 00:00:02" : "";
        $cmd = "$ffmpeg -y $seek -i " . escapeshellarg($sourcePath)
             . " -vframes 1 -an -q:v 2 -f image2 " . escapeshellarg($cachePath) . " 2>&1";
        exec($cmd, $out, $ret);
        if (file_exists($cachePath) && filesize($cachePath) > 0) $created = true;
    }
}

if ($created) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

$im = imagecreatetruecolor(400, 300);
$bg = imagecolorallocate($im, 30,0,0);
$tc = imagecolorallocate($im, 255,255,255);
imagestring($im, 5, 120, 140, "No Thumbnail", $tc);
header("Content-Type: image/jpeg");
imagejpeg($im);
imagedestroy($im);
?>