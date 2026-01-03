<?php
// 반드시 첫 줄에 있어야 합니다.
session_start();

$photoDir = "/volume1/ShareFolder/aimyon/Photos/";
$tempDir = "/volume1/etc/aim/photo/";
$pwFile = "/volume1/etc/aim/password.txt";
$bgmDir = "./bgm/";

$action = $_REQUEST['action'] ?? '';

// [로그인 처리]
if ($action === 'login') {
    $inputPw = $_POST['pw'] ?? '';
    // 파일에서 비번을 읽어와 앞뒤 공백을 제거합니다.
    $savedPw = trim(@file_get_contents($pwFile));

    if (!empty($savedPw) && $inputPw === $savedPw) {
        // 성공 시 세션에 기록 (index.php에서 버튼을 보여주는 기준)
        $_SESSION['admin'] = true; 
        echo "ok";
    } else {
        // 실패 시 세션 파괴 (보안)
        unset($_SESSION['admin']);
        echo "no";
    }
    exit;
}

// [BGM 목록]
if ($action === 'get_bgm') {
    $bgms = glob($bgmDir . "*.mp3");
    header('Content-Type: application/json');
    echo json_encode(array_map('basename', $bgms ?: []));
    exit;
}

// [다운로드] 1개면 원본, 여러개면 zip
if ($action === 'download') {
    $files = $_POST['files'] ?? [];
    if (count($files) === 1) {
        $filePath = $photoDir . basename($files[0]);
        if (file_exists($filePath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            readfile($filePath);
            exit;
        }
    } elseif (count($files) > 1) {
        $zipName = "aim_down_" . time() . ".zip";
        $zipPath = "/tmp/" . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE)) {
            foreach ($files as $f) {
                $p = $photoDir . basename($f);
                if (file_exists($p)) $zip->addFile($p, basename($f));
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="Aimyon_Gallery.zip"');
            readfile($zipPath); unlink($zipPath);
            exit;
        }
    }
}

// [업로드]
if ($action === 'upload') {
    foreach($_FILES['files']['tmp_name'] as $k => $tmp) {
        move_uploaded_file($tmp, $tempDir . basename($_FILES['files']['name'][$k]));
    }
    echo "ok"; exit;
}
?>