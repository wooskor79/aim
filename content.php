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
    
    <?php if($view === 'gallery' || $view === 'video'): ?>
    <div class="toolbar">
        <button class="css-btn css-btn-gray" onclick="selectAll('.img-select')">전체 선택</button>
        <button class="css-btn" style="background: #f59e0b; color: #fff;" onclick="downloadSelected()">선택 다운로드</button>
    </div>
    <?php endif; ?>

    <div class="photo-grid">
        <?php foreach($items as $item): ?>
            <div class="photo-card">
                <input type="checkbox" class="img-select" value="<?=basename($item)?>">
                
                <?php if($view === 'video'): ?>
                    <?php
                        // 썸네일 파일 존재 여부 확인
                        // (PHP가 직접 파일 시스템을 체크하므로 빠름)
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
                                   preload="metadata" 
                                   muted 
                                   playsinline
                                   onloadeddata="captureAndSaveThumb(this, '<?=basename($item)?>')"
                                   style="width:100%; height:100%; object-fit:cover; pointer-events: none;">
                            </video>
                        <?php endif; ?>

                        <div class="video-info" style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.6); color:#fff; font-size:10px; padding:4px; text-align:center; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
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
<?php } else { 
    // 업로드 페이지 로직 (기존 유지)
    echo '<div style="padding:20px; text-align:center;">';
    echo '<h2>사진 업로드</h2>';
    echo '<input type="file" id="upFiles" multiple accept="image/*" onchange="checkFiles(this)" style="display:none;">';
    echo '<button class="css-btn" onclick="$(\'#upFiles\').click()">파일 선택</button>';
    echo '<div id="file-name-display" style="margin:10px 0; color:#94a3b8;">선택된 파일 없음</div>';
    echo '<button id="up-btn" class="css-btn" style="background:#10b981;" onclick="upload()" disabled>업로드 시작</button>';
    
    // 임시 폴더 파일 리스트
    $tempFiles = glob($tempDir . "*");
    if(count($tempFiles) > 0) {
        echo '<hr style="border-color:#334155; margin:30px 0;">';
        echo '<h3>업로드 대기 중 (' . count($tempFiles) . ')</h3>';
        
        echo '<div class="toolbar" style="justify-content:center;">';
        echo '<button class="css-btn css-btn-gray" onclick="selectAll(\'.temp-select\')">전체 선택</button>';
        echo '<button id="btn-move-ask" class="css-btn" style="background:#3b82f6;" onclick="askMove()">갤러리로 이동</button>';
        echo '<div id="box-move-confirm" style="display:none; gap:5px;">';
        echo '<button class="css-btn" style="background:#10b981;" onclick="confirmMove()">확인</button>';
        echo '<button class="css-btn css-btn-gray" onclick="cancelMove()">취소</button>';
        echo '</div>';
        
        echo '<button id="btn-del-ask" class="css-btn" style="background:#ef4444;" onclick="askDelete()">삭제</button>';
        echo '<div id="box-del-confirm" style="display:none; gap:5px;">';
        echo '<button class="css-btn" style="background:#ef4444;" onclick="confirmDelete()">확인</button>';
        echo '<button class="css-btn css-btn-gray" onclick="cancelDelete()">취소</button>';
        echo '</div>';
        echo '</div>'; // toolbar end

        echo '<div id="move-area"></div><div id="del-area"></div>';
        
        echo '<div class="photo-grid">';
        foreach($tempFiles as $tf) {
            echo '<div class="photo-card">';
            echo '<input type="checkbox" class="temp-select" value="'.basename($tf).'">';
            echo '<img src="stream.php?type=temp&file='.urlencode(basename($tf)).'&thumb=1" onclick="openModal(\'stream.php?type=temp&file='.urlencode(basename($tf)).'&full=1\')">';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
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