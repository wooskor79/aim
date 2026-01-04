<?php
session_start();
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$photoDir = "/volume1/ShareFolder/aimyon/Photos/";
$tempDir = "/volume1/etc/aim/photo/";

$view = $_GET['view'] ?? 'gallery';
$page = $_GET['page'] ?? 1;
$per = 150;

if ($view === 'gallery') {
    $files = glob($photoDir . "*.{jpg,jpeg,png,gif,webp,jfif}", GLOB_BRACE);
    usort($files, function($a, $b) {
        if(basename($a) == '1aim.jpg') return -1;
        if(basename($b) == '1aim.jpg') return 1;
        return filemtime($b) - filemtime($a);
    });
    $total = count($files);
    $pages = ceil($total / $per);
    $images = array_slice($files, ($page-1)*$per, $per);
?>
    <?php drawPager($page, $pages, 'gallery'); ?>
    
    <div class="action-bar" style="margin-bottom: 20px; display: flex; gap: 10px;">
        <button class="css-btn" style="width: auto; padding: 8px 20px;" onclick="selectAll('.img-select')">전체선택</button>
        <button class="css-btn css-btn-gray" style="width: auto; padding: 8px 20px;" onclick="downloadSelected()">다운로드</button>
    </div>

    <div class="photo-grid">
        <?php foreach($images as $img): ?>
            <div class="photo-card">
                <input type="checkbox" class="img-select" value="<?=basename($img)?>">
                <img src="stream.php?file=<?=urlencode(basename($img))?>" onclick="openModal('stream.php?file=<?=urlencode(basename($img))?>&full=1')">
            </div>
        <?php endforeach; ?>
    </div>

    <?php drawPager($page, $pages, 'gallery'); ?>

<?php } else { 
    $tempFiles = array_map('basename', glob($tempDir . "*.{jpg,jpeg,png,gif,webp,jfif}", GLOB_BRACE) ?: []);
?>
    <h2 style="color:var(--accent-blue); margin-bottom:20px;">업로드 대기열</h2>
    <div id="upload-input-box" style="background:var(--card-bg); padding:40px; border:2px dashed var(--border-color); border-radius:15px; text-align: center;">
        <input type="file" id="upFiles" multiple onchange="checkFiles(this)" style="display:none;">
        <label for="upFiles" class="css-btn" style="width: auto; display: inline-block; padding: 12px 40px;">파일 선택하기</label>
        <div id="file-name-display" style="margin-top:15px; font-size:13px; color:#888;">선택된 파일 없음</div>
        <button id="up-btn" class="css-btn css-btn-gray" style="width:auto; padding: 10px 40px; margin: 20px auto 0;" disabled onclick="upload()">대기열로 업로드</button>
    </div>
    
    <div class="photo-grid" style="margin-top:30px;">
        <?php foreach($tempFiles as $tfile): ?>
            <div class="photo-card">
                <img src="stream.php?type=temp&file=<?=urlencode($tfile)?>" onclick="openModal('stream.php?type=temp&file=<?=urlencode($tfile)?>&full=1')">
            </div>
        <?php endforeach; ?>
    </div>
<?php } 

function drawPager($p, $ts, $v) {
    if($ts <= 1) return;
    echo "<div class='pager'>";
    echo "<a href='javascript:void(0)' onclick='loadPage(1, \"$v\")'>&laquo;</a>";
    $prev = max(1, $p - 1);
    echo "<a href='javascript:void(0)' onclick='loadPage($prev, \"$v\")'>&lt;</a>";
    for($i=max(1, $p-2); $i<=min($ts, $p+2); $i++) {
        $active = ($i == $p) ? "active" : "";
        echo "<a href='javascript:void(0)' onclick='loadPage($i, \"$v\")' class='$active'>$i</a>";
    }
    $next = min($ts, $p + 1);
    echo "<a href='javascript:void(0)' onclick='loadPage($next, \"$v\")'>&gt;</a>";
    echo "<a href='javascript:void(0)' onclick='loadPage($ts, \"$v\")'>&raquo;</a>";
    echo "</div>";
}
?>