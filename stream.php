<?php
// stream.php - 최종 수정본
session_start();

/* =========================
 * 1. 기본 설정 및 경로
 * ========================= */
// 로그 파일을 캐시 폴더 내부에 생성 (권한 문제 방지)
$photoCacheDir = "/volume1/etc/cache/photos/";
$videoCacheDir = "/volume1/etc/cache/videos/";
$logFile       = $videoCacheDir . "debug.log";

// 캐시 폴더가 없으면 생성 시도
@mkdir($photoCacheDir, 0777, true);
@mkdir($videoCacheDir, 0777, true);

/* =========================
 * 2. 요청 파라미터 처리
 * ========================= */
$type    = $_GET['type']  ?? 'gallery';
$file    = basename($_GET['file'] ?? ''); // 경로 조작 방지
$full    = isset($_GET['full']);
$isThumb = isset($_GET['thumb']);

if (empty($file)) {
    header("HTTP/1.0 400 Bad Request");
    exit;
}

/* =========================
 * 3. 원본 파일 경로 매핑
 * ========================= */
// 경로 끝에 슬래시(/)를 명확히 포함하여 이중 슬래시 문제 방지
$basePhotoDir = "/volume1/ShareFolder/aimyon/Photos/";
$baseVideoDir = "/volume1/ShareFolder/aimyon/묭영상/"; // [중요] 한글 경로 확인
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
 * 4. 스트리밍 및 원본 보기 로직
 * ========================= */
// 동영상 원본 요청(재생)이거나 썸네일 요청이 아닐 때
if ($full || ($type === 'video' && !$isThumb)) {
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'mp4','webm','mov','m4v' => 'video/mp4',
        'png' => 'image/png',
        'gif' => 'image/gif',
        default => 'image/jpeg'
    };

    if ($type === 'video') {
        // 비디오 스트리밍 (구간 탐색 지원)
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
        // 이미지 원본
        header("Content-Type: $mime");
        header("Content-Length: " . filesize($sourcePath));
        readfile($sourcePath);
        exit;
    }
}

/* =========================
 * 5. 썸네일 캐시 확인
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

// [1] WebP 처리 (Imagick)
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

// [2] 일반 이미지 처리 (GD)
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

// [3] 비디오/GIF 썸네일 (FFmpeg)
if (!$created && ($ext === 'gif' || $type === 'video')) {
    
    // [핵심 수정] 패키지 버전의 FFmpeg를 최우선으로 탐색
    $ffmpeg_candidates = [
        '/var/packages/ffmpeg6/target/bin/ffmpeg', // FFmpeg 6 (추천)
        '/var/packages/ffmpeg7/target/bin/ffmpeg', // FFmpeg 7
        '/var/packages/ffmpeg5/target/bin/ffmpeg', 
        '/usr/local/bin/ffmpeg',
        // '/usr/bin/ffmpeg' // 내장 버전은 기능이 막혀있으므로 제외하거나 최후순위
    ];

    $ffmpeg = '';
    foreach ($ffmpeg_candidates as $path) {
        if (file_exists($path)) {
            $ffmpeg = $path;
            // 라이브러리 경로 호환성 해결
            if (strpos($path, 'packages') !== false) {
                 $libPath = dirname(dirname($path)) . '/lib';
                 putenv("LD_LIBRARY_PATH=$libPath");
            }
            break;
        }
    }

    if ($ffmpeg) {
        $seek = ($type === 'video') ? "-ss 00:00:02" : "";
        
        // 명령어 구성 (이중 슬래시 문제 해결된 $sourcePath 사용)
        $cmd = "$ffmpeg -y $seek -i " . escapeshellarg($sourcePath)
             . " -vframes 1 -an -q:v 2 -f image2 " . escapeshellarg($cachePath) . " 2>&1";

        exec($cmd, $out, $ret);

        if (file_exists($cachePath) && filesize($cachePath) > 0) {
            $created = true;
        } else {
            // 실패 시 디버그 로그 기록
            $logContent = "-------------- " . date('Y-m-d H:i:s') . " --------------\n";
            $logContent .= "Used FFmpeg: $ffmpeg\n";
            $logContent .= "Target File: $file\n";
            $logContent .= "Source Path: $sourcePath\n";
            $logContent .= "Return Code: $ret\n";
            $logContent .= "Output:\n" . implode("\n", $out) . "\n\n";
            @file_put_contents($logFile, $logContent, FILE_APPEND);
        }
    } else {
        @file_put_contents($logFile, "Error: 유효한 FFmpeg 패키지를 찾을 수 없습니다. (내장 버전 제외됨)\n", FILE_APPEND);
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

// 실패 시 에러 이미지 출력
$im = imagecreatetruecolor(400, 300);
$bg = imagecolorallocate($im, 30,0,0);
$tc = imagecolorallocate($im, 255,255,255);
imagestring($im, 5, 120, 140, "No Thumbnail", $tc);
header("Content-Type: image/jpeg");
imagejpeg($im);
imagedestroy($im);
?>