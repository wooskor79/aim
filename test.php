<?php
// test.php : 권한 및 FFmpeg 작동 테스트용
header('Content-Type: text/html; charset=utf-8');

echo "<h1>시스템 진단 (System Check)</h1>";

// 1. open_basedir 확인
$basedir = ini_get('open_basedir');
echo "<h3>1. open_basedir 설정 확인</h3>";
if (empty($basedir)) {
    echo "<p style='color:blue'>[알림] open_basedir 제한이 없습니다. (Good)</p>";
} else {
    echo "<p>현재 설정값: <code>$basedir</code></p>";
    if (strpos($basedir, '/volume1') !== false && strpos($basedir, '/usr/bin') !== false) {
         echo "<p style='color:green'>[성공] /volume1 및 /usr/bin 경로가 포함되어 있습니다.</p>";
    } else {
         echo "<p style='color:red; font-weight:bold'>[실패] 설정값에 '/volume1' 또는 '/usr/bin'이 없습니다.<br>
         Web Station 설정에서 open_basedir 끝에 <code>:/volume1:/usr/bin</code>을 추가해야 합니다.</p>";
    }
}

// 2. 폴더 쓰기 권한 확인
echo "<h3>2. 캐시 폴더 쓰기 권한 확인</h3>";
$cacheDir = "/volume1/etc/cache/videos/";
if (!file_exists($cacheDir)) {
    echo "<p style='color:red'>[실패] 폴더가 없습니다: $cacheDir <br>mkdir로 생성을 시도합니다...</p>";
    @mkdir($cacheDir, 0777, true);
}

if (is_writable($cacheDir)) {
    echo "<p style='color:green'>[성공] PHP가 캐시 폴더에 파일을 쓸 수 있습니다.</p>";
} else {
    echo "<p style='color:red; font-weight:bold'>[실패] 캐시 폴더에 쓰기 권한이 없습니다.<br>
    File Station에서 $cacheDir 우클릭 -> 속성 -> 권한 -> http 사용자에게 읽기/쓰기 허용해주세요.</p>";
}

// 3. FFmpeg 실행 테스트
echo "<h3>3. FFmpeg 실행 테스트</h3>";
$ffmpeg = '/usr/bin/ffmpeg';
if (!file_exists($ffmpeg)) $ffmpeg = '/var/packages/ffmpeg7/target/bin/ffmpeg';

echo "<p>감지된 FFmpeg 경로: $ffmpeg</p>";

// 실제 영상 파일 하나를 지정해서 테스트 (경로가 맞는지 확인 필요)
// [중요] 테스트를 위해 존재하는 파일 경로로 수정해서 쓰셔도 됩니다.
$videoFile = "/volume1/ShareFolder/aimyon/묭영상/mymyon3636-13012026-0003.mp4"; 

if (!file_exists($videoFile)) {
    echo "<p style='color:orange'>[경고] 테스트용 영상 파일을 찾을 수 없습니다. ($videoFile)<br>경로를 확인해주세요. 하지만 FFmpeg 버전 확인은 진행합니다.</p>";
}

// 버전 확인 명령
$cmd_ver = "$ffmpeg -version 2>&1";
exec($cmd_ver, $out_ver, $ret_ver);

if ($ret_ver === 0) {
    echo "<p style='color:green'>[성공] FFmpeg가 정상 실행됩니다.</p>";
    echo "<pre style='background:#eee; padding:10px; font-size:11px'>" . implode("\n", array_slice($out_ver, 0, 3)) . "...</pre>";
} else {
    echo "<p style='color:red; font-weight:bold'>[실패] FFmpeg 실행 불가. (리턴코드: $ret_ver)</p>";
    echo "<p>이유: open_basedir에 /usr/bin이 없거나, exec() 함수가 차단되었을 수 있습니다.</p>";
}

// 4. exec 함수 차단 여부
echo "<h3>4. exec() 함수 활성화 여부</h3>";
if(function_exists('exec')) {
    echo "<p style='color:green'>[성공] exec() 함수를 사용할 수 있습니다.</p>";
} else {
    echo "<p style='color:red'>[실패] exec() 함수가 차단되어 있습니다. Web Station > PHP 설정 > 확장 > exec 체크 해제 필요.</p>";
}
?>