<?php
session_start();
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">

    <!-- =========================
         [SEO] 검색 결과 제목 (중요)
         - 키워드 나열 ❌
         - 30~60자 권장
    ========================== -->
    <title>아이묭 사진 갤러리 | 고화질 사진 다운로드</title>

    <!-- =========================
         [SEO] 검색 결과 설명 (클릭률 영향)
    ========================== -->
    <meta name="description"
          content="아이묭의 고화질 사진을 감상하고 다운로드할 수 있는 비공식 개인 팬 갤러리 사이트입니다.">

    <!-- =========================
         [SEO/AdSense 필수] 모바일 대응
    ========================== -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- =========================
         [SEO] canonical (중복 URL 방지)
    ========================== -->
    <link rel="canonical" href="https://aim.wooskor.com/">

    <!-- =========================
         [AdSense] 승인 후 계정 ID 입력
         승인 전에는 있어도 문제 없음
         예: ca-pub-1234567890123456
    ========================== -->
    <!--
    <meta name="google-adsense-account" content="ca-pub-XXXXXXXXXXXXXXX">
    -->

    <link rel="stylesheet" href="style.css?v=<?=filemtime('style.css')?>">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="dark-mode">

<!-- ==================================================
     [SEO/AdSense용 설명 콘텐츠]
     - UI에는 영향 없음
     - 검색엔진/애드센스용 텍스트
     - display:none ❌ (완전 숨김은 비추천)
================================================== -->
<section class="seo-intro" style="max-width:1px; max-height:1px; overflow:hidden;">
    <h1>아이묭 사진 갤러리</h1>
    <p>
        이 사이트는 일본 싱어송라이터 아이묭(Aimyon)의 사진을 정리한
        비공식 개인 팬 갤러리입니다.
        공연 사진, 화보 이미지 등을 고화질로 감상하고 다운로드할 수 있습니다.
    </p>
</section>

<div id="sidebar">
    <h1>Aimyon Gallery</h1>
    
    <div class="switch-wrap">
        <span style="font-size:13px;font-weight:bold;color:var(--text-color);">
            다크 모드
        </span>
        <label class="switch">
            <input type="checkbox" id="theme-checkbox" checked onchange="toggleTheme()">
            <span class="slider"></span>
        </label>
    </div>

    <div class="auth-area" style="margin-bottom:25px;">
        <?php if(!$isAdmin): ?>
            <input type="password"
                   id="adminPw"
                   class="auth-input"
                   placeholder="Password"
                   onkeypress="if(event.keyCode==13) login()">
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

        <!-- =========================
             [AdSense 필수 페이지 링크]
             실제 파일 반드시 존재해야 함
        ========================== -->
        <!--
        <a href="/about.php" class="css-btn css-btn-gray">사이트 소개</a>
        <a href="/privacy.php" class="css-btn css-btn-gray">개인정보처리방침</a>
        <a href="/contact.php" class="css-btn css-btn-gray">문의</a>
        -->
    </div>
</div>

<div id="main-content">
    <div id="ajax-content"></div>
</div>

<div id="modal" onclick="closeModal()">
    <!-- [SEO] 이미지 alt 필수 -->
    <img id="modal-img" alt="아이묭 사진 크게 보기">
</div>

<div id="msg-modal" class="msg-modal">
    <div class="msg-content">
        <h3 id="msg-text">알림</h3>
    </div>
</div>

<script src="script.js?v=<?=filemtime('script.js')?>"></script>

<!-- =========================
     [AdSense] 승인 후 광고 스크립트 위치
     (자동 광고 권장)
========================== -->
<!--
<script async
    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXX"
    crossorigin="anonymous"></script>
-->

</body>
</html>
