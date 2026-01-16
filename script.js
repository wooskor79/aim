let audio = new Audio();
let playlist = [];
let cur = 0;
let isStarted = false;
let selectedFiles = []; // 업로드할 파일 객체들을 담는 배열

$(document).ready(function() {
    // 테마 설정
    const savedTheme = localStorage.getItem('theme') || 'dark-mode';
    $('body').attr('class', savedTheme);
    $('#theme-checkbox').prop('checked', savedTheme === 'dark-mode');

    // 페이지 로드
    let lastPage = localStorage.getItem('lastPage') || 1;
    let lastView = localStorage.getItem('lastView') || 'gallery';
    loadPage(lastPage, lastView);

    // 오디오 설정
    audio.volume = 0.3;
    $('#vol-range').on('input', function() { audio.volume = this.value; });
    loadBgm();

    // 첫 클릭 시 BGM 재생
    $(document).one('click', function() {
        if(!isStarted) { playBgm(); isStarted = true; }
    });

    setTimeout(function() { showMsgModal("media.wooskor.com"); }, 300000); 

    /* =========================================
     * [이벤트 바인딩] 드래그 앤 드롭 및 파일 선택
     * ========================================= */
    
    // 드래그 진입/이탈 효과
    $(document).on('dragover', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    $(document).on('dragleave', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });
    
    // 드롭 시 파일 처리
    $(document).on('drop', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        // e.originalEvent.dataTransfer가 존재하는지 확인
        if(e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length > 0) {
            handleFiles(e.originalEvent.dataTransfer.files);
        }
    });
    
    // 영역 클릭 시 파일창 열기
    $(document).on('click', '#drop-zone', function() {
        $('#upFiles').click();
    });
    
    // 파일창에서 선택 완료 시
    $(document).on('change', '#upFiles', function() {
        if(this.files && this.files.length > 0) {
            handleFiles(this.files);
        }
    });
});

/* =========================================
 * [핵심 기능] 파일 미리보기 및 처리 함수
 * ========================================= */
function handleFiles(files) {
    if (!files || files.length === 0) return;
    
    // 선택된 파일들을 배열에 추가하고 화면에 그리기
    Array.from(files).forEach((file) => {
        selectedFiles.push(file);
        let index = selectedFiles.length - 1;

        // 1. 이미지 파일인 경우 (썸네일 표시)
        if (file.type.startsWith('image/')) {
            let reader = new FileReader();
            reader.onload = function(e) {
                let html = `
                    <div class="preview-item" id="file-${index}">
                        <img src="${e.target.result}">
                        <div class="file-name">${file.name}</div>
                        <button class="preview-remove" onclick="removeFile(${index})">×</button>
                    </div>
                `;
                $('#preview-area').append(html);
            };
            reader.readAsDataURL(file);
        } 
        // 2. 이미지가 아닌 경우 (파일명만 표시)
        else {
            let html = `
                <div class="preview-item" id="file-${index}" style="display:flex; flex-direction:column; justify-content:center; align-items:center; background:#334155; color:#fff;">
                    <span style="font-size:24px;">📄</span>
                    <div class="file-name" style="font-size:11px; margin-top:5px; padding:0 5px; word-break:break-all;">${file.name}</div>
                    <button class="preview-remove" onclick="removeFile(${index})">×</button>
                </div>
            `;
            $('#preview-area').append(html);
        }
    });

    updateUploadBtn();
}

// 개별 파일 취소 (배열에서는 null 처리하고 화면에서 제거)
function removeFile(index) {
    $(`#file-${index}`).remove();
    selectedFiles[index] = null; 
    updateUploadBtn();
}

// 업로드 버튼 텍스트 및 활성화 상태 변경
function updateUploadBtn() {
    let validCount = selectedFiles.filter(f => f !== null).length;
    
    if(validCount > 0) {
        $('#up-btn').prop('disabled', false).removeClass('disabled');
        $('#up-btn').text(`선택한 사진 ${validCount}장 업로드 시작`);
        $('#preview-area').css('display', 'grid'); // 파일이 있으면 그리드 보이기
    } else {
        $('#up-btn').prop('disabled', true).addClass('disabled');
        $('#up-btn').text('파일을 선택해주세요');
    }
}

// [서버 전송]
function uploadNewFiles() {
    let validFiles = selectedFiles.filter(f => f !== null);
    if(validFiles.length === 0) return alert('업로드할 파일이 없습니다.');

    let fd = new FormData();
    validFiles.forEach(f => fd.append('files[]', f));

    $('#up-btn').text('업로드 중...').prop('disabled', true);

    $.ajax({
        url: 'api.php?action=upload', 
        data: fd, 
        type: 'POST', 
        processData: false, 
        contentType: false,
        success: () => { 
            // 성공 시 배열 초기화 후 페이지 새로고침
            selectedFiles = []; 
            $('#preview-area').empty();
            loadPage(1, 'upload'); 
        },
        error: (e) => {
            console.error(e);
            alert('업로드 실패! 로그를 확인하세요.');
            $('#up-btn').text('다시 시도').prop('disabled', false);
        }
    });
}

/* =========================================
 * [기타 페이지 로직]
 * ========================================= */

function loadPage(page, view) {
    localStorage.setItem('lastPage', page);
    localStorage.setItem('lastView', view);
    
    $.get('content.php', { page: page, view: view }, function(html) {
        $('#ajax-content').html(html);
        window.scrollTo(0, 0);
        
        // 페이지가 바뀌면 선택된 파일 목록 초기화
        selectedFiles = []; 
        $('#preview-area').empty(); 
    });
}

// 이동/삭제/BGM 등 나머지 함수들은 기존 유지
function askMove() {
    let checked = $('.temp-select:checked');
    if(checked.length === 0) return alert('이동할 사진을 선택해주세요.');
    $('#btn-move-ask').hide();
    $('#box-move-confirm').css('display', 'flex');
}
function cancelMove() {
    $('#box-move-confirm').hide();
    $('#btn-move-ask').show();
}
function confirmMove() {
    let checked = $('.temp-select:checked');
    let files = [];
    checked.each(function() { files.push($(this).val()); });
    $.post('api.php?action=move_to_gallery', { files: files }, function(res) {
        if(res.trim() === 'ok') {
            $('#move-area').html('<span style="color:#10b981; font-weight:bold; padding: 12px;">이동 완료!</span>');
            setTimeout(function() { loadPage(1, 'upload'); }, 800);
        } else {
            alert('오류 발생: ' + res);
            cancelMove();
        }
    });
}
function askDelete() {
    let checked = $('.temp-select:checked');
    if(checked.length === 0) return alert('삭제할 사진을 선택해주세요.');
    $('#btn-del-ask').hide();
    $('#box-del-confirm').css('display', 'flex');
}
function cancelDelete() {
    $('#box-del-confirm').hide();
    $('#btn-del-ask').show();
}
function confirmDelete() {
    let checked = $('.temp-select:checked');
    let files = [];
    checked.each(function() { files.push($(this).val()); });
    $.post('api.php?action=delete_temp', { files: files }, function(res) {
        if(res.trim() === 'ok') {
            $('#del-area').html('<span style="color:#ef4444; font-weight:bold; padding: 12px;">삭제 완료!</span>');
            setTimeout(function() { loadPage(1, 'upload'); }, 800);
        } else {
            alert('오류 발생: ' + res);
            cancelDelete();
        }
    });
}

function toggleTheme() {
    const isDark = $('#theme-checkbox').is(':checked');
    const theme = isDark ? 'dark-mode' : 'light-mode';
    $('body').attr('class', theme);
    localStorage.setItem('theme', theme);
}
function loadBgm() {
    $.getJSON('api.php?action=get_bgm', function(data) {
        if(data && data.length > 0) {
            playlist = data.sort(() => Math.random() - 0.5);
            renderNext();
        }
    });
}
function playBgm() {
    if(playlist.length === 0) return;
    audio.src = 'bgm/' + playlist[cur];
    audio.play().then(() => {
        $('#now-title').text("♬ " + playlist[cur]);
        cur = (cur + 1) % playlist.length;
        renderNext();
    }).catch(() => {});
}
function stopBgm() { audio.pause(); $('#now-title').text("BGM 중지됨"); }
function renderNext() {
    let h = "";
    for(let i=0; i<5; i++) {
        let idx = (cur + i) % playlist.length;
        if(playlist[idx]) h += `<li>${playlist[idx]}</li>`;
    }
    $('#next-list').html(h);
}
audio.onended = function() { playBgm(); };

function login() {
    const pwVal = $('#adminPw').val();
    $.post('api.php?action=login', {pw: pwVal}, function(res) {
        if(res.trim() === 'ok') location.reload();
        else $('#adminPw').val('').focus();
    });
}
function logout() { $.post('api.php?action=logout', () => location.reload()); }
function openModal(src) { 
    $('#modal-video').hide(); 
    $('#modal-img').attr('src', src).show(); 
    $('#modal').css('display', 'flex').hide().fadeIn(200); 
    $('body').css('overflow', 'hidden');
}
function openVideoModal(src) {
    audio.pause(); 
    $('#now-title').text("BGM 일시정지 (영상 재생중)");
    $('#modal-img').hide(); 
    $('#modal-video').attr('src', src).show();
    $('#modal').css('display', 'flex').hide().fadeIn(200);
    $('body').css('overflow', 'hidden');
    let v = $('#modal-video')[0];
    v.volume = 0.5;
    v.play().catch(function(e){ console.log(e); });
}
function closeModal() {
    $('#modal').fadeOut(200, function() {
        $('body').css('overflow', 'auto');
        $('#modal-img').attr('src', '');
        let v = $('#modal-video')[0];
        v.pause(); v.src = "";
        $('#modal-video').hide();
        playBgm();
    });
}
function selectAll(cls) { $(cls).prop('checked', true); }
function downloadSelected() {
    let checked = $('.img-select:checked');
    if(checked.length === 0) return;
    let form = $('<form method="POST" action="api.php?action=download"></form>');
    checked.each(function(){ form.append(`<input type="hidden" name="files[]" value="${$(this).val()}">`); });
    $('body').append(form); form.submit(); form.remove();
}
function showMsgModal(text) {
    $('#msg-text').text(text);
    $('#msg-modal').addClass('show').css('display', 'flex');
    setTimeout(function() {
        $('#msg-modal').removeClass('show');
        setTimeout(() => $('#msg-modal').css('display', 'none'), 500); 
    }, 5000); 
}
function captureAndSaveThumb(video, filename) {
    if (video.readyState < 2) return;
    let canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    let ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    let dataURL = canvas.toDataURL('image/jpeg', 0.7);
    $.post('api.php?action=save_thumb', { file: filename, image: dataURL }, function(res) {
        console.log('Thumbnail saved: ' + filename);
    });
}