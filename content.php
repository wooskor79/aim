<?php
session_start();
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$photoDir = "/volume1/ShareFolder/aimyon/Photos/";
$videoDir = "/volume1/ShareFolder/aimyon/묭영상/";
$tempDir = "/volume1/etc/aim/photo/";

$view = $_GET['view'] ?? 'gallery';
$page = $_GET['page'] ?? 1;
$per = 150;

if ($view === 'gallery' || $view === 'video') {
    $files = ($view === 'video') 
        ? glob($videoDir . "*.{mp4,webm,mov,m4v,MP4}", GLOB_BRACE)
        : glob($photoDir . "*.{jpg,jpeg,png,gif,webp,jfif}", GLOB_BRACE);

    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $total = count($files);
    $pages = ceil($total / $per);
    $items = array_slice($files, ($page-1)*$per, $per);
?>
    <?php drawPager($page, $pages, $view); ?>
    
    <div class="photo-grid">
        <?php foreach($items as $item): ?>
            <div class="photo-card">
                <?php if($view === 'video'): ?>
                    <div class="video-preview-wrapper" 
                         onclick="openVideoModal('stream.php?type=video&file=<?=urlencode(basename($item))?>')" 
                         style="width:100%; height:100%; position:relative; background:#000; cursor:pointer;">
                        
                        <img src="stream.php?type=video&file=<?=urlencode(basename($item))?>&thumb=1" 
                             style="width:100%; height:100%; object-fit:cover;">
                        
                        <div class="video-info" style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.6); color:#fff; font-size:10px; padding:4px; text-align:center; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?=basename($item)?>
                        </div>
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:rgba(255,255,255,0.7); font-size:30px; pointer-events:none;">▶</div>
                    </div>
                <?php else: ?>
                    <input type="checkbox" class="img-select" value="<?=basename($item)?>">
                    <img src="stream.php?file=<?=urlencode(basename($item))?>" onclick="openModal('stream.php?file=<?=urlencode(basename($item))?>&full=1')">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php drawPager($page, $pages, $view); ?>
<?php } else { 
    // 업로드 대기열 로직 (기존 유지)
}

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