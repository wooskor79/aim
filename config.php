<?php
// [설정 파일] 여기서 모든 경로를 관리합니다.
return [
    // 1. 사진 폴더 목록 (여러 개 가능)
    'photo_dirs' => [
        '/volume1/ShareFolder/aimyon/Photos/',
        // '/volume1/ShareFolder/Another/Photos/', // 콤마(,) 찍고 이렇게 추가하면 됩니다.
    ],

    // 2. 영상 폴더 목록 (여러 개 가능)
    'video_dirs' => [
        '/volume1/ShareFolder/aimyon/묭영상/',
        // '/volume1/ShareFolder/Another/Videos/',
    ],

    // 3. 업로드 임시 폴더
    'temp_dir' => '/volume1/etc/aim/photo/',

    // 4. 비밀번호 파일 경로
    'pw_file'  => '/volume1/etc/aim/password.txt',
    
    // 5. BGM 폴더
    'bgm_dir'  => './bgm/',
];
?>