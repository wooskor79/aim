<?php
session_start();
// ------------------------------------------------------------------
// [설정] 경로 설정 (환경에 맞게 수정됨)
// ------------------------------------------------------------------
$photoDir = "/volume1/ShareFolder/aimyon/Photos/"; // 최종 이동될 갤러리 경로
$videoDir = "/volume1/ShareFolder/aimyon/묭영상/";
$tempDir  = "/volume1/etc/aim/photo/";             // 업로드 대기소 (임시 경로)
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

/* [기능 비활성화] 4. [관리자] 임시 파일 삭제
if ($action === 'delete_temp' && $isAdmin) {
    $files = $_POST['files'] ?? [];
    foreach($files as $f) {
        $target = $tempDir . basename($f);
        if(file_exists($target)) unlink($target);
    }
    echo "ok"; exit;
}
*/

/* [기능 비활성화] 5. [관리자] 갤러리로 이동
if ($action === 'move_to_gallery' && $isAdmin) {
    $files = $_POST['files'] ?? [];
    foreach($files as $f) {
        $oldPath = $tempDir . basename($f);
        if(!file_exists($oldPath)) continue;

        $filename = pathinfo($f, PATHINFO_FILENAME);
        $ext = pathinfo($f, PATHINFO_EXTENSION);
        
        // 이동할 대상 파일명 설정
        $newFileName = $f;
        $counter = 0;

        // [핵심] 중복된 파일명이 있으면 _0, _1 순으로 번호를 붙임
        while(file_exists($photoDir . $newFileName)) {
            $newFileName = $filename . "_" . $counter . "." . $ext;
            $counter++;
        }
        
        rename($oldPath, $photoDir . $newFileName);
    }
    echo "ok"; exit;
}
*/

/* [기능 비활성화] 6. 파일 업로드
if ($action === 'upload') {
    // 폴더 없으면 생성
    if (!file_exists($tempDir)) @mkdir($tempDir, 0777, true);

    if (isset($_FILES['files']['name'])) {
        foreach($_FILES['files']['tmp_name'] as $k => $tmp) {
            $name = basename($_FILES['files']['name'][$k]);
            move_uploaded_file($tmp, $tempDir . $name);
        }
    }
    echo "ok"; exit;
}
*/

// 7. 다운로드 (단일/압축)
if ($action === 'download') {
    $files = $_POST['files'] ?? [];
    $fileCount = count($files);
    if ($fileCount === 0) exit;

    function getFilePath($fname, $pDir, $vDir) {
        $p = $pDir . basename($fname);
        if (file_exists($p)) return $p;
        $v = $vDir . basename($fname);
        if (file_exists($v)) return $v;
        return null;
    }

    if ($fileCount === 1) {
        $filePath = getFilePath($files[0], $photoDir, $videoDir);
        if ($filePath && file_exists($filePath)) {
            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    } else {
        $zip = new ZipArchive();
        $zipFileName = "aimyon_files_" . date("Ymd_His") . ".zip";
        $zipFilePath = $tempDir . $zipFileName;

        if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
            foreach ($files as $f) {
                $filePath = getFilePath($f, $photoDir, $videoDir);
                if ($filePath && file_exists($filePath)) {
                    $zip->addFile($filePath, basename($f));
                }
            }
            $zip->close();
            if (file_exists($zipFilePath)) {
                if (ob_get_level()) ob_end_clean();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                header('Content-Length: ' . filesize($zipFilePath));
                readfile($zipFilePath);
                unlink($zipFilePath);
                exit;
            }
        }
    }
}

// 8. 썸네일 저장 (브라우저 생성 분)
if ($action === 'save_thumb') {
    $file = $_POST['file'] ?? '';
    $data = $_POST['image'] ?? '';
    $videoCacheDir = "/volume1/etc/cache/videos/";
    if (!file_exists($videoCacheDir)) @mkdir($videoCacheDir, 0777, true);

    if ($file && $data) {
        $data = str_replace('data:image/jpeg;base64,', '', $data);
        $data = str_replace(' ', '+', $data);
        $imgData = base64_decode($data);
        file_put_contents($videoCacheDir . basename($file) . ".jpg", $imgData);
        echo "saved";
    }
    exit;
}
?>