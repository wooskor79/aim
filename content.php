<?php
session_start();
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$photoDir = "/volume1/ShareFolder/aimyon/Photos/";
$videoDir = "/volume1/ShareFolder/aimyon/묭영상/";
$tempDir  = "/volume1/etc/aim/photo/";

$view = $_GET['view'] ?? 'gallery';
$page = $_GET['page'] ?? 1;
$per = 150;

// ---------------------------------------------------------
// 1. 갤러리 / 비디오 모드
// ---------------------------------------------------------
if ($view === 'gallery' || $view === 'video') {
    $files = ($view === 'video') 
        ? glob($videoDir . "*.{mp4,webm,mov,m4v,MP4}", GLOB_BRACE)
        : glob($photoDir . "*.{jpg,jpeg,png,gif,webp,jfif}", GLOB_BRACE);

    if($files) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
    } else {
        $files = [];
    }

    $total = count($files);
    $pages = ceil($total / $per);
    $items = array_slice($files, ($page-1)*$per, $per);
?>
    <?php drawPager($page, $pages, $view); ?>
    
    <div class="toolbar">
        <button class="css-btn css-btn-gray" onclick="selectAll('.img-select')">전체 선택</button>
        <button class="css-btn" style="background: #f59e0b; color: #fff;" onclick="downloadSelected()">선택 다운로드</button>
    </div>

    <div class="photo-grid">
        <?php foreach($items as $item): ?>
            <div class="photo-card">
                <input type="checkbox" class="img-select" value="<?=basename($item)?>">
                <?php if($view === 'video'): ?>
                    <?php
                        $thumbPath = "/volume1/etc/cache/videos/" . basename($item) . ".jpg";
                        $hasThumb = file_exists($thumbPath) && filesize($thumbPath) > 0;
                    ?>
                    <div class="video-preview-wrapper" 
                         onclick="openVideoModal('stream.php?type=video&file=<?=urlencode(basename($item))?>')" 
                         style="width:100%; height:100%; position:relative; background:#000; cursor:pointer;">
                        <?php if ($hasThumb): ?>
                            <img src="stream.php?type=video&file=<?=urlencode(basename($item))?>&thumb=1" 
                                 style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <video src="stream.php?type=video&file=<?=urlencode(basename($item))?>#t=1.0" 
                                   preload="metadata" muted playsinline
                                   onloadeddata="captureAndSaveThumb(this, '<?=basename($item)?>')"
                                   style="width:100%; height:100%; object-fit:cover; pointer-events: none;">
                            </video>
                        <?php endif; ?>
                        <div class="video-info" style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.6); color:#fff; font-size:10px; padding:4px; text-align:center;">
                            <?=basename($item)?>
                        </div>
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:rgba(255,255,255,0.7); font-size:30px; pointer-events:none;">▶</div>
                    </div>
                <?php else: ?>
                    <img src="stream.php?file=<?=urlencode(basename($item))?>&thumb=1" onclick="openModal('stream.php?file=<?=urlencode(basename($item))?>&full=1')">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php drawPager($page, $pages, $view); ?>

<?php 
// ---------------------------------------------------------
// 2. 업로드 페이지 (비활성화 - 주석 처리)
// ---------------------------------------------------------
} /* else { 
?>
    <div class="upload-container">
        <h2 class="upload-title">사진 업로드</h2>
        
        <div id="drop-zone">
            <div class="icon">☁️</div>
            <p>여기로 사진을 드래그하거나 클릭하세요</p>
            <p style="font-size:12px; color:#64748b;">(여러 장 선택 가능)</p>
        </div>
        
        <input type="file" id="upFiles" multiple accept="image/*,video/*" style="display:none;">

        <div id="preview-area" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap:10px; margin-bottom:20px;"></div>

        <button id="up-btn" class="css-btn disabled" style="background:#10b981; width:100%; margin-top:10px;" onclick="uploadNewFiles()" disabled>
            파일을 선택해주세요
        </button>

        <?php
            // 임시 폴더 파일 목록 스캔
            $tempFiles = glob($tempDir . "*");
            
            if(count($tempFiles) > 0):
        ?>
        <div class="staging-area">
            <h3 style="color:var(--text-color); margin-bottom:15px; margin-top:30px; border-top:1px solid var(--border-color); padding-top:20px;">
                업로드 대기 중인 사진 (<?=count($tempFiles)?>장)
            </h3>
            
            <div class="toolbar">
                <button class="css-btn css-btn-gray" onclick="selectAll('.temp-select')">전체 선택</button>
                
                <button id="btn-move-ask" class="css-btn" style="background:#3b82f6;" onclick="askMove()">갤러리로 이동</button>
                <div id="box-move-confirm" style="display:none; gap:5px;">
                    <button class="css-btn" style="background:#10b981;" onclick="confirmMove()">확인</button>
                    <button class="css-btn css-btn-gray" onclick="cancelMove()">취소</button>
                </div>
                
                <button id="btn-del-ask" class="css-btn" style="background:#ef4444;" onclick="askDelete()">삭제</button>
                <div id="box-del-confirm" style="display:none; gap:5px;">
                    <button class="css-btn" style="background:#ef4444;" onclick="confirmDelete()">확인</button>
                    <button class="css-btn css-btn-gray" onclick="cancelDelete()">취소</button>
                </div>
            </div>

            <div id="move-area"></div>
            <div id="del-area"></div>

            <div class="photo-grid">
                <?php foreach($tempFiles as $tf): ?>
                    <div class="photo-card">
                        <input type="checkbox" class="temp-select" value="<?=basename($tf)?>">
                        <img src="stream.php?type=temp&file=<?=urlencode(basename($tf))?>&thumb=1" 
                             onclick="openModal('stream.php?type=temp&file=<?=urlencode(basename($tf))?>&full=1')">
                        <div style="position:absolute; bottom:0; width:100%; background:rgba(0,0,0,0.5); color:white; font-size:10px; text-align:center;">
                            <?=basename($tf)?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php } */ 

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