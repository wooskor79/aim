<?php
session_start();
$config = include 'config.php'; // 설정 로드

$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$photoDirs = $config['photo_dirs'];
$videoDirs = $config['video_dirs'];
$tempDir   = $config['temp_dir'];

$view = $_GET['view'] ?? 'gallery';
$page = $_GET['page'] ?? 1;
$per = 150;

if ($view === 'gallery' || $view === 'video') {
    $files = [];
    
    // 비디오 모드면 비디오 폴더들, 갤러리 모드면 사진 폴더들을 모두 스캔
    $targetDirs = ($view === 'video') ? $videoDirs : $photoDirs;
    $pattern = ($view === 'video') ? "*.{mp4,webm,mov,m4v,MP4}" : "*.{jpg,jpeg,png,gif,webp,jfif}";

    foreach ($targetDirs as $dir) {
        $found = glob($dir . $pattern, GLOB_BRACE);
        if ($found) {
            $files = array_merge($files, $found);
        }
    }

    if($files) {
        // 날짜순 정렬
        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
        
        // 갤러리 모드일 때만 AIM1.jpg 맨 앞으로 고정 로직 (선택사항)
        if ($view === 'gallery') {
            // 여러 폴더 중 어디에 AIM1.jpg가 있는지 모르므로 검색
            $pinnedKey = false;
            foreach($files as $k => $f) {
                if(basename($f) === "AIM1.jpg") {
                    $pinnedKey = $k;
                    break;
                }
            }
            if ($pinnedKey !== false) {
                $pinnedFile = $files[$pinnedKey];
                unset($files[$pinnedKey]);
                array_unshift($files, $pinnedFile);
            }
        }
    } else { $files = []; }

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
                    <div class="video-preview-wrapper" onclick="openVideoModal('stream.php?type=video&file=<?=urlencode(basename($item))?>')" style="width:100%; height:100%; position:relative; background:#000; cursor:pointer;">
                         <video src="stream.php?type=video&file=<?=urlencode(basename($item))?>#t=1.0" preload="metadata" muted playsinline style="width:100%; height:100%; object-fit:cover; pointer-events: none;"></video>
                         <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; font-size:30px;">▶</div>
                    </div>
                <?php else: ?>
                    <img src="stream.php?file=<?=urlencode(basename($item))?>&thumb=1" onclick="openModal('stream.php?file=<?=urlencode(basename($item))?>&full=1')">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php drawPager($page, $pages, $view); ?>

<?php } else { ?>
    <div class="upload-wrapper">
        <h2 class="upload-title">사진 업로드</h2>
        <div id="drop-zone">
            <div class="icon">☁️</div>
            <p class="main-text">여기로 사진을 드래그하거나 클릭하세요</p>
            <p class="sub-text">(여러 장 선택 가능)</p>
        </div>
        <input type="file" id="upFiles" multiple accept="image/*,video/*" style="display:none;">
        <div id="preview-area"></div>
        <button id="up-btn" class="css-btn disabled" onclick="uploadNewFiles()" disabled>파일을 선택해주세요</button>

        <?php
            $tempFiles = glob($tempDir . "*");
            if($tempFiles):
        ?>
        <div class="staging-area">
            <div class="staging-toolbar">
                <div class="left-tools" style="display:flex; gap:10px; width:100%; align-items:center;">
                    <button class="css-btn css-btn-gray" onclick="selectAll('.temp-select')" style="width:auto; padding:8px 16px; margin:0;">전체 선택</button>
                    <?php if($isAdmin): ?>
                        <div id="del-group" style="display:flex; gap:5px;">
                            <button id="btn-del-ask" class="css-btn" style="background:#ef4444; width:auto; padding:8px 16px; margin:0;" onclick="askDelete()">선택 삭제</button>
                            <div id="box-del-confirm" style="display:none; gap:5px; align-items:center;">
                                <span style="font-size:12px; font-weight:bold; color:#ef4444;">삭제?</span>
                                <button class="css-btn" style="background:#ef4444; width:auto; padding:8px 16px; margin:0;" onclick="confirmDelete()">예</button>
                                <button class="css-btn" style="background:#3b82f6; width:auto; padding:8px 16px; margin:0;" onclick="cancelDelete()">아니오</button>
                            </div>
                        </div>
                        <div id="move-group" style="display:flex; gap:5px;">
                            <button id="btn-move-ask" class="css-btn" style="background:#3b82f6; width:auto; padding:8px 16px; margin:0;" onclick="askMove()">선택 업로드</button>
                            <div id="box-move-confirm" style="display:none; gap:5px; align-items:center;">
                                <span style="font-size:12px; font-weight:bold; color:#3b82f6;">이동?</span>
                                <button class="css-btn" style="background:#3b82f6; width:auto; padding:8px 16px; margin:0;" onclick="confirmMove()">예</button>
                                <button class="css-btn" style="background:#64748b; width:auto; padding:8px 16px; margin:0;" onclick="cancelMove()">아니오</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="photo-grid">
                <?php foreach($tempFiles as $tf): ?>
                    <div class="photo-card">
                        <input type="checkbox" class="temp-select" value="<?=basename($tf)?>">
                        <img src="stream.php?type=temp&file=<?=urlencode(basename($tf))?>&thumb=1" 
                             onclick="openModal('stream.php?type=temp&file=<?=urlencode(basename($tf))?>&full=1')">
                        <div style="position:absolute; bottom:0; width:100%; background:rgba(0,0,0,0.5); color:white; font-size:10px; text-align:center;"><?=basename($tf)?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php } 

function drawPager($p, $ts, $v) {
    if($ts <= 1) return;
    echo "<div class='pager'>";
    // ... 페이징 로직 (생략, 기존과 동일) ...
    echo "</div>";
}
?>