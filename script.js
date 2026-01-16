let audio = new Audio();
let playlist = [];
let cur = 0;
let isStarted = false;
let selectedFiles = []; // 업로드 파일 담을 배열

$(document).ready(function() {
    const savedTheme = localStorage.getItem('theme') || 'dark-mode';
    $('body').attr('class', savedTheme);
    $('#theme-checkbox').prop('checked', savedTheme === 'dark-mode');

    let lastPage = localStorage.getItem('lastPage') || 1;
    let lastView = localStorage.getItem('lastView') || 'gallery';
    loadPage(lastPage, lastView);

    audio.volume = 0.3;
    $('#vol-range').on('input', function() { audio.volume = this.value; });

    loadBgm();

    $(document).one('click', function() {
        if(!isStarted) { playBgm(); isStarted = true; }
    });

    setTimeout(function() {
        showMsgModal("media.wooskor.com");
    }, 300000); 

    // [드래그 앤 드롭 이벤트]
    $(document).on('dragover', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    
    $(document).on('dragleave', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });
    
    $(document).on('drop', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        handleFiles(e.originalEvent.dataTransfer.files);
    });
    
    // [클릭 이벤트] drop-zone 클릭 시 input 클릭 유도
    $(document).on('click', '#drop-zone', function() {
        $('#upFiles').click();
    });
    
    // [파일 선택 완료 시]
    $(document).on('change', '#upFiles', function() {
        handleFiles(this.files);
        // 같은 파일을 다시 선택할 수 있도록 값 초기화는 하지 않음 (multiple이므로)
    });
});

// 파일 처리 함수
function handleFiles(files) {
    if (!files || files.length === 0) return;
    
    // 기존 파일 목록에 추가
    for (let i = 0; i < files.length; i++) {
        let file = files[i];
        selectedFiles.push(file);
        
        // 미리보기 생성 (이미지인 경우만)
        if (file.type.startsWith('image/')) {
            let reader = new FileReader();
            reader.onload = function(e) {
                let html = `
                    <div class="preview-item">
                        <img src="${e.target.result}">
                        <button class="preview-remove" onclick="removeFile(${selectedFiles.length - 1}, this)">×</button>
                    </div>
                `;
                $('#preview-area').append(html);
            };
            reader.readAsDataURL(file);
        } else {
            // 이미지가 아닌 경우 아이콘 등으로 표시
            let html = `
                <div class="preview-item" style="display:flex; justify-content:center; align-items:center; background:#334155;">
                    <span style="font-size:12px; text-align:center; word-break:break-all; padding:5px;">${file.name}</span>
                    <button class="preview-remove" onclick="removeFile(${selectedFiles.length - 1}, this)">×</button>
                </div>
            `;
            $('#preview-area').append(html);
        }
    }
    updateUploadBtn();
}

// 개별 파일 취소 (배열에서 null 처리)
function removeFile(index, btn) {
    $(btn).parent().remove();
    selectedFiles[index] = null;
    updateUploadBtn();
}

// 업로드 버튼 상태 업데이트
function updateUploadBtn() {
    let validCount = selectedFiles.filter(f => f !== null).length;
    
    if(validCount > 0) {
        $('#up-btn').prop('disabled', false);
        $('#up-btn').text(`선택한 사진 ${validCount}장 업로드`);
    } else {
        $('#up-btn').prop('disabled', true);
        $('#up-btn').text('선택한 사진 업로드');
    }
}

// 서버로 업로드 전송
function uploadNewFiles() {
    let validFiles = selectedFiles.filter(f => f !== null);
    if(validFiles.length === 0) return;

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
            selectedFiles = []; // 배열 초기화
            loadPage(1, 'upload'); // 페이지 새로고침
        },
        error: () => {
            alert('업로드 실패');
            $('#up-btn').text('다시 시도').prop('disabled', false);
        }
    });
}

function loadPage(page, view) {
    localStorage.setItem('lastPage', page);
    localStorage.setItem('lastView', view);
    
    $.get('content.php', { page: page, view: view }, function(html) {
        $('#ajax-content').html(html);
        window.scrollTo(0, 0);
        // 페이지 변경 시 선택된 파일 배열 초기화
        selectedFiles = []; 
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
        v.pause();
        v.src = "";
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

let scrollTimer = null;
$(window).on('scroll', function() {
    $('#main-content').addClass('is-scrolling');
    if(scrollTimer) clearTimeout(scrollTimer);
    scrollTimer = setTimeout(function() {
        $('#main-content').removeClass('is-scrolling');
    }, 250);
});

function showMsgModal(text) {
    $('#msg-text').text(text);
    $('#msg-modal').addClass('show').css('display', 'flex');
    setTimeout(function() {
        $('#msg-modal').removeClass('show');
        setTimeout(() => $('#msg-modal').css('display', 'none'), 500); 
    }, 5000); 
}

// [썸네일 생성 함수]
function captureAndSaveThumb(video, filename) {
    if (video.readyState < 2) return;
    let canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    let ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    let dataURL = canvas.toDataURL('image/jpeg', 0.7);
    $.post('api.php?action=save_thumb', {
        file: filename,
        image: dataURL
    }, function(res) {
        console.log('Thumbnail saved: ' + filename + ' (' + res + ')');
    });
}