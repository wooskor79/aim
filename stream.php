<?php
session_start();
$config = include 'config.php'; // 설정 로드

$photoCacheDir = "/volume1/etc/cache/photos/";
$videoCacheDir = "/volume1/etc/cache/videos/";
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

// 원본 보기 및 스트리밍 (기존 코드와 동일)
if ($full || ($type === 'video' && !$isThumb)) {
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $mime = in_array($ext, ['mp4','webm','mov','m4v']) ? "video/mp4" : (@getimagesize($sourcePath)['mime'] ?? 'image/jpeg');

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

// 썸네일 캐시 및 생성 로직 (기존과 동일하지만 sourcePath가 유동적임)
$cachePath = ($type === 'video')
    ? $videoCacheDir . $file . ".jpg"
    : $photoCacheDir . "thumb_" . ($type === 'temp' ? "temp_" : "") . $file . ".jpg";

if (file_exists($cachePath) && filesize($cachePath) > 0) {
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);
    exit;
}

// ... 아래는 기존 썸네일 생성 코드 유지 ...
$created = false;
$ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

// (이하 생략된 기존 썸네일 생성 코드들은 sourcePath 변수를 그대로 쓰므로 잘 작동합니다)
// ...
?>