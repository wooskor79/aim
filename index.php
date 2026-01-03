<?php
// 반드시 첫 줄!
session_start();

// 로그인 상태 체크 (이 변수가 false면 HTML 코드 자체가 생성 안 됨)
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

$photoDir = "/volume1/ShareFolder/aimyon/Photos/";
$page = $_GET['page'] ?? 1;
$per = 150;

$files = glob($photoDir . "*.{jpg,jpeg,png,gif,webp,jfif}", GLOB_BRACE);
usort($files, function($a, $b) {
    if(basename($a) == '1aim.jpg') return -1;
    if(basename($b) == '1aim.jpg') return 1;
    return filemtime($b) - filemtime($a);
});

$total = count($files);
$pages = ceil($total / $per);
$images = array_slice($files, ($page-1)*$per, $per);

function drawPager($p, $ts) {
    if($ts <= 1) return;
    echo "<div class='pager'>";
    echo "<a href='?page=1'>&laquo;</a> <a href='?page=".max(1, $p-1)."'>&lt;</a> ";
    for($i=1; $i<=$ts; $i++) {
        $active = ($i == $p) ? "active" : "";
        echo "<a href='?page=$i' class='$active'>$i</a> ";
    }
    echo "<a href='?page=".min($ts, $p+1)."'>&gt;</a> <a href='?page=$ts'>&raquo;</a>";
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
</head>
<body>
    <div id="sidebar">
        <h1 style="color: #ffb6c1; text-align: center;">Aimyon Gallery</h1>
        <input type="password" id="adminPw" placeholder="비밀번호 + Enter" onkeypress="if(event.keyCode==13) login()">
        
        <div class="menu-list" style="margin-top: 20px;">
            <button class="css-btn" onclick="location.href='index.php'">아이묭 사진보기</button>
            <button class="css-btn css-btn-pink" onclick="$('#upload-area').toggle()">아이묭 사진 업로드</button>
            
            <?php if($isAdmin): ?>
                <button class="css-btn" style="background: #8e8e8e; box-shadow: 0 5px 0 #6d6d6d; color: #fff;" onclick="location.href='logs.php'">로그보기</button>
            <?php endif; ?>
        </div>

        <div class="bgm-box" style="margin-top: 25px; background: #f9f9f9; padding: 15px; border-radius: 15px;">
            <div id="now-title" style="font-weight: bold; font-size: 13px; color: #ffafcc; margin-bottom: 10px;">BGM 중지됨</div>
            <div class="bgm-btns" style="display: flex; gap: 5px; margin-bottom: 10px;">
                <button class="css-btn" style="flex: 1; padding: 8px; font-size: 12px;" onclick="playBgm()">랜덤 BGM</button>
                <button class="css-btn css-btn-pink" style="flex: 1; padding: 8px; font-size: 12px;" onclick="stopBgm()">중지</button>
            </div>
            <div class="vol-box" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                <label style="font-size: 11px; font-weight: bold;">Vol</label>
                <input type="range" id="vol-range" min="0" max="1" step="0.01" value="0.5" style="width: 100%;">
            </div>
            <div class="next-info" style="font-size: 12px; color: #888;">
                <strong>Next 5:</strong>
                <ul id="next-list" style="padding-left: 15px; margin-top: 5px;"></ul>
            </div>
        </div>
    </div>

    <div id="main-content">
        <div class="pager-wrap"><?php drawPager($page, $pages); ?></div>

        <div class="action-bar" style="margin: 20px 0; display: flex; gap: 10px;">
            <button class="css-btn" style="width: auto; padding: 8px 20px;" onclick="selectAll()">전체선택</button>
            <button class="css-btn css-btn-pink" style="width: auto; padding: 8px 20px;" onclick="downloadSelected()">다운로드</button>
        </div>

        <div id="upload-area" style="display: none; background: #fff; padding: 15px; border: 2px dashed #ffafcc; border-radius: 15px; margin-bottom: 20px;">
            <input type="file" id="upFiles" multiple onchange="checkFiles(this)">
            <button id="up-btn" class="css-btn css-btn-pink" style="width: auto; margin-top: 10px;" disabled onclick="upload()">업로드 시작</button>
        </div>

        <div class="photo-grid">
            <?php foreach($images as $img): ?>
                <div class="photo-card">
                    <input type="checkbox" class="img-select" value="<?=basename($img)?>">
                    <img src="stream.php?file=<?=urlencode(basename($img))?>" onclick="openModal(this.src)">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pager-wrap"><?php drawPager($page, $pages); ?></div>
    </div>

    <div id="modal" onclick="$(this).hide()"><img id="modal-img"></div>

    <script src="script.js"></script>
</body>
</html>