<?php
session_start();
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

$photoDir = "/volume1/ShareFolder/aimyon/Photos/";
$tempDir = "/volume1/etc/aim/photo/";
$page = $_GET['page'] ?? 1;
$per = 150;

$files = glob($photoDir . "*.{jpg,jpeg,png,gif,webp,jfif}", GLOB_BRACE);
usort($files, function($a, $b) {
    if(basename($a) == '1aim.jpg') return -1;
    if(basename($b) == '1aim.jpg') return 1;
    return filemtime($b) - filemtime($a);
});

$tempFiles = array_map('basename', glob($tempDir . "*.{jpg,jpeg,png,gif,webp,jfif}", GLOB_BRACE) ?: []);
$total = count($files);
$pages = ceil($total / $per);
$images = array_slice($files, ($page-1)*$per, $per);

function drawPager($p, $ts) {
    if($ts <= 1) return;
    echo "<div class='pager'>";
    echo "<a href='?page=1'>&laquo;</a>";
    $prev = max(1, $p - 1);
    echo "<a href='?page=$prev'>&lt;</a>";
    $start = max(1, $p - 2);
    $end = min($ts, $p + 2);
    for($i=$start; $i<=$end; $i++) {
        $active = ($i == $p) ? "active" : "";
        echo "<a href='?page=$i' class='$active'>$i</a>";
    }
    $next = min($ts, $p + 1);
    echo "<a href='?page=$next'>&gt;</a>";
    echo "<a href='?page=$ts'>&raquo;</a>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Aimyon Gallery</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .auth-input {
            width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; 
            outline: none; text-align: center; background: var(--bg-color); color: var(--text-color);
            font-size: 15px; margin-bottom: 10px;
        }
    </style>
</head>
<body class="dark-mode">
    <div id="sidebar">
        <h1>Aimyon Gallery</h1>
        
        <div class="switch-wrap">
            <span style="font-size: 14px; font-weight: bold;">다크 모드</span>
            <label class="switch">
                <input type="checkbox" id="theme-checkbox" checked onchange="toggleTheme()">
                <span class="slider"></span>
            </label>
        </div>

        <div class="auth-area" style="margin-bottom: 25px;">
            <?php if(!$isAdmin): ?>
                <input type="password" id="adminPw" class="auth-input" placeholder="Password" onkeypress="if(event.keyCode==13) login()">
            <?php else: ?>
                <button class="css-btn css-btn-pink" onclick="logout()">로그아웃</button>
            <?php endif; ?>
        </div>

        <div class="bgm-box" style="background: var(--card-bg); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px;">
            <div id="now-title" style="font-weight: bold; font-size: 13px; color: var(--accent-blue); margin-bottom: 10px;">BGM 중지됨</div>
            <div class="bgm-btns" style="display: flex; gap: 5px; margin-bottom: 10px;">
                <button class="css-btn" style="flex: 1; padding: 8px; font-size: 11px; margin-bottom:0;" onclick="playBgm()">랜덤 BGM</button>
                <button class="css-btn css-btn-pink" style="flex: 1; padding: 8px; font-size: 11px; margin-bottom:0;" onclick="stopBgm()">중지</button>
            </div>
            <div class="vol-box" style="display: flex; align-items: center; gap: 10px;">
                <label style="font-size: 11px; font-weight: bold;">Vol</label>
                <input type="range" id="vol-range" min="0" max="1" step="0.01" value="0.3" style="width: 100%;">
            </div>
            <ul id="next-list" style="font-size: 11px; color: #888; padding-left: 15px; margin-top: 10px; list-style: none;"></ul>
        </div>
        
        <div class="menu-list">
            <button class="css-btn" onclick="showGallery()">갤러리 보기</button>
            <button class="css-btn css-btn-pink" onclick="showUploadArea()">사진 업로드</button>
        </div>
    </div>

    <div id="main-content">
        <div id="gallery-view">
            <?php drawPager($page, $pages); ?>
            <div class="action-bar" style="margin-bottom: 20px; display: flex; gap: 10px;">
                <button class="css-btn" style="width: auto; padding: 8px 20px;" onclick="selectAll('.img-select')">전체선택</button>
                <button class="css-btn css-btn-pink" style="width: auto; padding: 8px 20px;" onclick="downloadSelected()">다운로드</button>
            </div>
            <div class="photo-grid">
                <?php foreach($images as $img): ?>
                    <div class="photo-card">
                        <input type="checkbox" class="img-select" value="<?=basename($img)?>">
                        <img src="stream.php?file=<?=urlencode(basename($img))?>" onclick="openModal('stream.php?file=<?=urlencode(basename($img))?>&full=1')">
                    </div>
                <?php endforeach; ?>
            </div>
            <?php drawPager($page, $pages); ?>
        </div>

        <div id="upload-view" style="display: none;">
            <h2 style="color:var(--accent-blue); margin-bottom:20px;">대기열 관리</h2>
            <div id="upload-input-box" style="background:var(--card-bg); padding:30px; border:2px dashed var(--border-color); border-radius:15px; margin-bottom:20px; text-align: center;">
                <input type="file" id="upFiles" multiple onchange="checkFiles(this)" style="display:none;">
                <label for="upFiles" class="css-btn" style="width: auto; display: inline-block; padding: 10px 30px;">파일 선택</label>
                <div id="file-name-display" style="font-size:13px; color:#888; margin-top:15px;">선택된 파일 없음</div>
                <button id="up-btn" class="css-btn css-btn-pink" style="width:auto; padding: 8px 30px; margin: 15px auto 0;" disabled onclick="upload()">업로드 시작</button>
            </div>
            <div class="photo-grid">
                <?php foreach($tempFiles as $tfile): ?>
                    <div class="photo-card">
                        <input type="checkbox" class="temp-select" value="<?=$tfile?>">
                        <img src="stream.php?type=temp&file=<?=urlencode($tfile)?>" onclick="openModal('stream.php?type=temp&file=<?=urlencode($tfile)?>&full=1')">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="modal" onclick="closeModal()">
        <img id="modal-img">
    </div>
    <script src="script.js"></script>
</body>
</html>