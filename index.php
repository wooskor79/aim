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
    for($i=1; $i<=$ts; $i++) {
        $active = ($i == $p) ? "active" : "";
        echo "<a href='?page=$i' class='$active'>$i</a> ";
    }
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
            width: 100%; padding: 12px; border: 2px solid #ffafcc; border-radius: 12px; 
            outline: none; text-align: center; background: rgba(255,255,255,0.7);
            font-size: 16px; transition: border-color 0.3s;
        }
        .auth-input:focus { border-color: #ff8fa3; }
        
        /* 파일 선택 버튼 커스텀 */
        .file-label {
            display: inline-block; width: auto; padding: 10px 25px; 
            background: #fff; color: #ffafcc; border: 2px solid #ffafcc; 
            border-radius: 12px; font-weight: bold; cursor: pointer; 
            transition: all 0.2s; margin-bottom: 10px;
        }
        .file-label:hover { background: #fff5f7; transform: translateY(-1px); }
    </style>
</head>
<body>
    <div id="sidebar">
        <h1>Aimyon Gallery</h1>
        
        <div class="auth-area" style="margin-bottom: 25px;">
            <?php if(!$isAdmin): ?>
                <input type="password" id="adminPw" class="auth-input" onkeypress="if(event.keyCode==13) login()">
            <?php else: ?>
                <button class="css-btn" style="background: #ff8fa3; margin-bottom: 0;" onclick="logout()">로그아웃하기</button>
            <?php endif; ?>
        </div>

        <div class="bgm-box" style="background: #f9f9f9; padding: 15px; border-radius: 15px; border: 1px solid #eee; margin-bottom: 20px;">
            <div id="now-title" style="font-weight: bold; font-size: 13px; color: #ffafcc; margin-bottom: 10px;">BGM 중지됨</div>
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
            <button class="css-btn" onclick="showGallery()">아이묭 사진보기</button>
            <button class="css-btn css-btn-pink" onclick="showUploadArea()">아이묭 사진 업로드</button>
        </div>
    </div>

    <div id="main-content">
        <div id="gallery-view">
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
            <div class="pager-wrap" style="margin-top:20px;"><?php drawPager($page, $pages); ?></div>
        </div>

        <div id="upload-view" style="display: none;">
            <h2 style="color:#ffafcc; margin-bottom:20px;">사진 업로드 관리 (대기열)</h2>
            <div id="upload-input-box" style="background:#fff; padding:30px; border:2px dashed #ffafcc; border-radius:15px; margin-bottom:20px; text-align: center;">
                <input type="file" id="upFiles" multiple onchange="checkFiles(this)" style="display:none;">
                <label for="upFiles" class="file-label">파일 선택하기</label>
                <div id="file-name-display" style="font-size:13px; color:#888; margin-bottom:15px;">선택된 파일 없음</div>
                <button id="up-btn" class="css-btn css-btn-pink" style="width:auto; padding: 8px 30px;" disabled onclick="upload()">대기열로 업로드</button>
            </div>
            <div class="action-bar" style="margin-bottom:20px; display:flex; gap:10px;">
                <button class="css-btn" style="width:auto; padding: 8px 20px;" onclick="selectAll('.temp-select')">전체선택</button>
                <?php if($isAdmin): ?>
                    <button class="css-btn" style="width:auto; padding: 8px 20px; background:#ff4d4d;" onclick="deleteSelectedTemp()">선택 삭제</button>
                    <button class="css-btn css-btn-pink" style="width:auto; padding: 8px 20px; background:#4CAF50;" onclick="moveSelectedToGallery()">선택 전송</button>
                <?php endif; ?>
            </div>
            <div class="photo-grid">
                <?php foreach($tempFiles as $tfile): ?>
                    <div class="photo-card" style="border: 2px solid #ddd;">
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