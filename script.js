let audio = new Audio();
let playlist = [];
let cur = 0;
let isStarted = false;

$(document).ready(function() {
    const savedTheme = localStorage.getItem('theme') || 'dark-mode';
    $('body').attr('class', savedTheme);
    $('#theme-checkbox').prop('checked', savedTheme === 'dark-mode');

    const lastPage = localStorage.getItem('lastPage') || 1;
    const lastView = localStorage.getItem('lastView') || 'gallery';
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
    $('#modal-video').hide().attr('src', ''); 
    $('#modal-img').attr('src', src).show(); 
    $('#modal').css('display', 'flex').hide().fadeIn(200); 
    $('body').css('overflow', 'hidden');
}

function openVideoModal(src) {
    stopBgm(); 
    $('#modal-img').hide();
    $('#modal-video').attr('src', src).show();
    $('#modal').css('display', 'flex').hide().fadeIn(200);
    $('body').css('overflow', 'hidden');
    $('#modal-video')[0].play();
}

function closeModal() {
    const videoElement = $('#modal-video')[0];
    if (videoElement) {
        videoElement.pause();
        videoElement.src = "";
        videoElement.load();
    }

    $('#modal').fadeOut(200, function() {
        $('body').css('overflow', 'auto');
        // 모달 닫기 완료 후 배경음악 다시 재생
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