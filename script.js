let audio = new Audio();
let playlist = [];
let cur = 0;
let isStarted = false;
let selectedFiles = []; 

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

    setTimeout(function() { showMsgModal("media.wooskor.com"); }, 300000); 

    // 드래그 앤 드롭
    $(document).on('dragover', '#drop-zone', function(e) { e.preventDefault(); $(this).addClass('dragover'); });
    $(document).on('dragleave', '#drop-zone', function(e) { e.preventDefault(); $(this).removeClass('dragover'); });
    $(document).on('drop', '#drop-zone', function(e) {
        e.preventDefault(); $(this).removeClass('dragover');
        if(e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length > 0) {
            handleFiles(e.originalEvent.dataTransfer.files);
        }
    });
    $(document).on('click', '#drop-zone', function() { $('#upFiles').click(); });
    $(document).on('change', '#upFiles', function() {
        if(this.files && this.files.length > 0) handleFiles(this.files);
    });
});

// [삭제 버튼 토글 로직]
function askDelete() {
    let checked = $('.temp-select:checked');
    if(checked.length === 0) return showMsgModal('삭제할 사진을 선택해주세요.');
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
            showMsgModal('삭제되었습니다.');
            loadPage(1, 'upload');
        } else {
            alert('삭제 실패: ' + res);
            cancelDelete();
        }
    });
}

// [이동 버튼 토글 로직]
function askMove() {
    let checked = $('.temp-select:checked');
    if(checked.length === 0) return showMsgModal('이동할 사진을 선택해주세요.');
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
            showMsgModal('전송 완료!');
            loadPage(1, 'upload');
        } else {
            alert('이동 실패: ' + res);
            cancelMove();
        }
    });
}

// [기본 기능들]
function handleFiles(files) {
    if (!files || files.length === 0) return;
    Array.from(files).forEach((file) => {
        selectedFiles.push(file);
        let index = selectedFiles.length - 1;
        let reader = new FileReader();
        reader.onload = function(e) {
            let src = file.type.startsWith('image/') ? e.target.result : '';
            let content = src ? `<img src="${src}">` : `<span style="color:#fff;font-size:24px;">📄</span><div style="font-size:10px;color:#fff;">${file.name}</div>`;
            let html = `
                <div class="preview-item" id="file-${index}" ${!src ? 'style="display:flex;flex-direction:column;justify-content:center;align-items:center;"' : ''}>
                    ${content}
                    <button class="preview-remove" onclick="removeFile(${index})">×</button>
                </div>`;
            $('#preview-area').append(html);
        };
        reader.readAsDataURL(file);
    });
    updateUploadBtn();
}

function removeFile(index) {
    $(`#file-${index}`).remove();
    selectedFiles[index] = null; 
    updateUploadBtn();
}

function updateUploadBtn() {
    let validCount = selectedFiles.filter(f => f !== null).length;
    if(validCount > 0) {
        $('#up-btn').prop('disabled', false).removeClass('disabled');
        $('#up-btn').text(`선택한 사진 ${validCount}장 업로드 시작`);
    } else {
        $('#up-btn').prop('disabled', true).addClass('disabled').text('파일을 선택해주세요');
    }
}

function uploadNewFiles() {
    let validFiles = selectedFiles.filter(f => f !== null);
    if(validFiles.length === 0) return alert('업로드할 파일이 없습니다.');

    let fd = new FormData();
    validFiles.forEach(f => fd.append('files[]', f));

    $('#up-btn').text('업로드 중...').prop('disabled', true);

    $.ajax({
        url: 'api.php?action=upload', 
        data: fd, type: 'POST', processData: false, contentType: false,
        success: (res) => { 
            if(res.trim() === 'ok') {
                selectedFiles = []; $('#preview-area').empty();
                loadPage(1, 'upload'); 
            } else {
                alert('업로드 실패: ' + res);
                $('#up-btn').text('다시 시도').prop('disabled', false);
            }
        },
        error: (e) => {
            console.error(e); alert('서버 오류');
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
        selectedFiles = []; $('#preview-area').empty(); 
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
    $('#modal-video').hide(); $('#modal-img').attr('src', src).show(); 
    $('#modal').css('display', 'flex').hide().fadeIn(200); 
    $('body').css('overflow', 'hidden');
}
function openVideoModal(src) {
    audio.pause(); $('#now-title').text("BGM 일시정지 (영상 재생중)");
    $('#modal-img').hide(); $('#modal-video').attr('src', src).show();
    $('#modal').css('display', 'flex').hide().fadeIn(200);
    $('body').css('overflow', 'hidden');
    let v = $('#modal-video')[0]; v.volume = 0.5; v.play().catch((e)=>console.log(e));
}
function closeModal() {
    $('#modal').fadeOut(200, function() {
        $('body').css('overflow', 'auto');
        $('#modal-img').attr('src', '');
        let v = $('#modal-video')[0]; v.pause(); v.src = ""; $('#modal-video').hide();
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
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    let ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    let dataURL = canvas.toDataURL('image/jpeg', 0.7);
    $.post('api.php?action=save_thumb', { file: filename, image: dataURL });
}