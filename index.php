<?php
session_start();
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Aimyon Gallery</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="dark-mode">
    <div id="sidebar">
        <h1>Aimyon Gallery</h1>
        
        <div class="switch-wrap">
            <span style="font-size: 13px; font-weight: bold; color: var(--text-color);">다크 모드</span>
            <label class="switch">
                <input type="checkbox" id="theme-checkbox" checked onchange="toggleTheme()">
                <span class="slider"></span>
            </label>
        </div>

        <div class="auth-area" style="margin-bottom: 25px;">
            <?php if(!$isAdmin): ?>
                <input type="password" id="adminPw" class="auth-input" placeholder="Password" onkeypress="if(event.keyCode==13) login()">
            <?php else: ?>
                <button class="css-btn css-btn-gray" onclick="logout()">로그아웃</button>
            <?php endif; ?>
        </div>

        <div class="bgm-box">
            <div id="now-title">BGM 중지됨</div>
            <div class="bgm-btns">
                <button class="css-btn" onclick="playBgm()">랜덤 BGM</button>
                <button class="css-btn css-btn-gray" onclick="stopBgm()">중지</button>
            </div>
            <div class="vol-box">
                <label>Vol</label>
                <input type="range" id="vol-range" min="0" max="1" step="0.01" value="0.3">
            </div>
            <ul id="next-list"></ul>
        </div>
        
        <div class="menu-list">
            <button class="css-btn" onclick="loadPage(1, 'gallery')">갤러리 보기</button>
            <button class="css-btn css-btn-gray" onclick="loadPage(1, 'upload')">사진 업로드</button>
        </div>
    </div>

    <div id="main-content">
        <div id="ajax-content"></div>
    </div>

    <div id="modal" onclick="closeModal()">
        <img id="modal-img">
    </div>

    <script src="script.js"></script>
</body>
</html>