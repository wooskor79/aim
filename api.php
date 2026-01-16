<?php
session_start();
// ------------------------------------------------------------------
// [설정] 경로 설정
// ------------------------------------------------------------------
$photoDir = "/volume1/ShareFolder/aimyon/Photos/"; 
$videoDir = "/volume1/ShareFolder/aimyon/묭영상/";
$tempDir  = "/volume1/etc/aim/photo/";             
$pwFile   = "/volume1/etc/aim/password.txt";
$bgmDir   = "./bgm/";

$action  = $_REQUEST['action'] ?? '';
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// 1. 로그인
if ($action === 'login') {
    $inputPw = $_POST['pw'] ?? '';
    $savedPw = trim(@file_get_contents($pwFile));
    if (!empty($savedPw) && $inputPw === $savedPw) {
        $_SESSION['admin'] = true; 
        echo "ok";
    } else {
        echo "no";
    }
    exit;
}

// 2. 로그아웃
if ($action === 'logout') {
    session_destroy();
    echo "ok";
    exit;
}

// 3. BGM 목록
if ($action === 'get_bgm') {
    $bgms = glob($bgmDir . "*.mp3");
    header('Content-Type: application/json');
    echo json_encode(array_map('basename', $bgms ?: []));
    exit;
}

// 4. [관리자] 선택 삭제 (주석 해제됨)
if ($action === 'delete_temp' && $isAdmin) {
    $files = $_POST['files'] ?? [];
    foreach($files as $f) {
        $target = $tempDir . basename($f);
        if(file_exists($target)) unlink($target);
    }
    echo "ok"; exit;
}

// 5. [관리자] 갤러리로 이동 (주석 해제됨)
if ($action === 'move_to_gallery' && $isAdmin) {
    $files = $_POST['files'] ?? [];
    foreach($files as $f) {
        $oldPath = $tempDir . basename($f);
        if(!file_exists($oldPath)) continue;

        $filename = pathinfo($f, PATHINFO_FILENAME);
        $ext = pathinfo($f, PATHINFO_EXTENSION);
        
        // 중복 방지
        $newFileName = $f;
        $counter = 0;
        while(file_exists($photoDir . $newFileName)) {
            $newFileName = $filename . "_" . $counter . "." . $ext;
            $counter++;
        }
        
        rename($oldPath, $photoDir . $newFileName);
    }
    echo "ok"; exit;
}

// 6. 파일 업로드 (주석 해제됨)
if ($action === 'upload') {
    if (!file_exists($tempDir)) {
        if (!@mkdir($tempDir, 0777, true)) {
            echo "폴더 생성 실패"; exit;
        }
    }

    if (isset($_FILES['files']['name'])) {
        foreach($_FILES['files']['tmp_name'] as $k => $tmp) {
            $name = basename($_FILES['files']['name'][$k]);
            move_uploaded_file($tmp, $tempDir . $name);
        }
    }
    echo "ok"; exit;
}

// 7. 다운로드
if ($action === 'download') {
    $files = $_POST['files'] ?? [];
    if (count($files) === 0) exit;

    if (count($files) === 1) {
        $path = file_exists($photoDir.basename($files[0])) ? $photoDir.basename($files[0]) : $videoDir.basename($files[0]);
        if (file_exists($path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($path).'"');
            header('Content-Length: '.filesize($path));
            readfile($path);
            exit;
        }
    } else {
        $zipName = "download_" . date("Ymd_His") . ".zip";
        $zipPath = $tempDir . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($files as $f) {
                $path = file_exists($photoDir.basename($f)) ? $photoDir.basename($f) : $videoDir.basename($f);
                if (file_exists($path)) $zip->addFile($path, basename($f));
            }
            $zip->close();
            if (file_exists($zipPath)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="'.$zipName.'"');
                header('Content-Length: '.filesize($zipPath));
                readfile($zipPath);
                unlink($zipPath);
                exit;
            }
        }
    }
}

// 8. 썸네일 저장
if ($action === 'save_thumb') {
    $file = $_POST['file'] ?? '';
    $data = $_POST['image'] ?? '';
    $videoCacheDir = "/volume1/etc/cache/videos/";
    if (!file_exists($videoCacheDir)) @mkdir($videoCacheDir, 0777, true);

    if ($file && $data) {
        $data = str_replace('data:image/jpeg;base64,', '', $data);
        $data = str_replace(' ', '+', $data);
        file_put_contents($videoCacheDir . basename($file) . ".jpg", base64_decode($data));
        echo "saved";
    }
    exit;
}
?>