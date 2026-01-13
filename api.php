<?php
session_start();
$photoDir = "/volume1/ShareFolder/aimyon/Photos/";
$videoDir = "/volume1/ShareFolder/aimyon/묭영상/";
$tempDir = "/volume1/etc/aim/photo/";
$pwFile = "/volume1/etc/aim/password.txt";
$bgmDir = "./bgm/";

$action = $_REQUEST['action'] ?? '';
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

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

if ($action === 'logout') {
    session_destroy();
    echo "ok";
    exit;
}

if ($action === 'get_bgm') {
    $bgms = glob($bgmDir . "*.mp3");
    header('Content-Type: application/json');
    echo json_encode(array_map('basename', $bgms ?: []));
    exit;
}

if ($action === 'delete_temp' && $isAdmin) {
    $files = $_POST['files'] ?? [];
    foreach($files as $f) {
        $target = $tempDir . basename($f);
        if(file_exists($target)) unlink($target);
    }
    echo "ok"; exit;
}

if ($action === 'move_to_gallery' && $isAdmin) {
    $files = $_POST['files'] ?? [];
    foreach($files as $f) {
        $oldPath = $tempDir . basename($f);
        if(!file_exists($oldPath)) continue;
        $filename = pathinfo($f, PATHINFO_FILENAME);
        $ext = pathinfo($f, PATHINFO_EXTENSION);
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

if ($action === 'upload') {
    foreach($_FILES['files']['tmp_name'] as $k => $tmp) {
        move_uploaded_file($tmp, $tempDir . basename($_FILES['files']['name'][$k]));
    }
    echo "ok"; exit;
}

if ($action === 'download') {
    $files = $_POST['files'] ?? [];
    $fileCount = count($files);

    if ($fileCount === 0) exit;

    if ($fileCount === 1) {
        $filePath = $photoDir . basename($files[0]);
        if (file_exists($filePath)) {
            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    } 
    else {
        $zip = new ZipArchive();
        $zipFileName = "aimyon_photos_" . date("Ymd_His") . ".zip";
        $zipFilePath = $tempDir . $zipFileName;

        if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
            foreach ($files as $f) {
                $filePath = $photoDir . basename($f);
                if (file_exists($filePath)) {
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
?>