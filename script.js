let audio = new Audio();
let playlist = [];
let cur = 0;
let isStarted = false;

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
});

function loadPage(page, view) {
    localStorage.setItem('lastPage', page);
    localStorage.setItem('lastView', view);
    
    $.get('content.php', { page: page, view: view }, function(html) {
        $('#ajax-content').html(html);
        window.scrollTo(0, 0);
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

// [수정] 영상 모달 열기 (BGM 정지)
function openVideoModal(src) {
    audio.pause(); 
    $('#now-title').text("BGM 일시정지 (영상 재생중)");
    
    $('#modal-img').hide(); 
    $('#modal-video').attr('src', src).show();
    $('#modal').css('display', 'flex').hide().fadeIn(200);
    $('body').css('overflow', 'hidden');
    
    // 자동재생 시도
    let v = $('#modal-video')[0];
    v.volume = 0.5;
    v.play().catch(function(e){ console.log(e); });
}

// [수정] 모달 닫기 (BGM 다시 재생)
function closeModal() {
    $('#modal').fadeOut(200, function() {
        $('body').css('overflow', 'auto');
        $('#modal-img').attr('src', '');
        
        let v = $('#modal-video')[0];
        v.pause();
        v.src = "";
        $('#modal-video').hide();
        
        // 닫으면 BGM 다시 켜기
        playBgm();
    });
}

function selectAll(cls) { $(cls).prop('checked', true); }

function checkFiles(input) {
    $('#file-name-display').text(input.files.length + "개 선택됨");
    $('#up-btn').prop('disabled', input.files.length === 0);
}

function upload() {
    let fd = new FormData();
    for(let f of $('#upFiles')[0].files) fd.append('files[]', f);
    $.ajax({
        url: 'api.php?action=upload', data: fd, type: 'POST', 
        processData: false, contentType: false,
        success: () => { loadPage(1, 'upload'); }
    });
}

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

// [추가됨] 브라우저 화면 캡처 및 서버 전송
function captureAndSaveThumb(video, filename) {
    if (video.readyState < 2) return; // 로딩 안됐으면 패스

    let canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    let ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    let dataURL = canvas.toDataURL('image/jpeg', 0.7); // 품질 0.7
    
    // 서버로 전송
    $.post('api.php?action=save_thumb', {
        file: filename,
        image: dataURL
    }, function(res) {
        console.log('Thumbnail saved: ' + filename + ' (' + res + ')');
    });
}