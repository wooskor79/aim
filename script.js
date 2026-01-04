let audio = new Audio();
let playlist = [];
let cur = 0;
let isStarted = false;

$(document).ready(function() {
    // 테마 설정
    const savedTheme = localStorage.getItem('theme') || 'dark-mode';
    $('body').attr('class', savedTheme);
    $('#theme-checkbox').prop('checked', savedTheme === 'dark-mode');

    // 초기 페이지 로드
    const lastPage = localStorage.getItem('lastPage') || 1;
    const lastView = localStorage.getItem('lastView') || 'gallery';
    loadPage(lastPage, lastView);

    audio.volume = 0.3;
    $('#vol-range').on('input', function() { audio.volume = this.value; });

    loadBgm();

    $(document).one('click', function() {
        if(!isStarted) { playBgm(); isStarted = true; }
    });
});

// 핵심 기능: 콘텐츠만 비동기로 교체 (음악 유지)
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
    $('#modal-img').attr('src', src); 
    $('#modal').css('display', 'flex').hide().fadeIn(200); 
    $('body').css('overflow', 'hidden');
}

function closeModal() {
    $('#modal').fadeOut(200, function() {
        $('body').css('overflow', 'auto');
    });
}

// 갤러리 관련 함수들
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