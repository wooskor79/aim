let audio = new Audio();
let playlist = [];
let cur = 0;
let isStarted = false;

$(document).ready(function() {
    const lastView = localStorage.getItem('lastView');
    if(lastView === 'upload') showUploadArea();
    else showGallery();

    audio.volume = 0.3;
    const volRange = document.getElementById('vol-range');
    if(volRange) {
        volRange.value = 0.3;
        volRange.addEventListener('input', function() {
            audio.volume = this.value;
        });
    }

    loadBgm();

    $(document).one('click', function() {
        if(!isStarted) {
            playBgm();
            isStarted = true;
        }
    });

    $('#modal').on('click', function() {
        $(this).fadeOut(200, function() {
            $('#modal-img').attr('src', '');
            $('body').css('overflow', 'auto');
        });
    });
});

function login() {
    const pwVal = $('#adminPw').val();
    if(!pwVal) return;
    $.post('api.php?action=login', {pw: pwVal}, function(res) {
        if(res.trim() === 'ok') location.reload();
        else $('#adminPw').val('').focus();
    });
}

function logout() {
    $.post('api.php?action=logout', function(res) {
        if(res.trim() === 'ok') location.reload();
    });
}

function showGallery() { 
    localStorage.setItem('lastView', 'gallery');
    $('#gallery-view').show(); 
    $('#upload-view').hide(); 
}

function showUploadArea() { 
    localStorage.setItem('lastView', 'upload');
    $('#gallery-view').hide(); 
    $('#upload-view').show(); 
}

function checkFiles(input) {
    const display = document.getElementById('file-name-display');
    if (input.files.length > 0) {
        display.innerText = input.files.length === 1 ? input.files[0].name : input.files.length + "개의 파일 선택됨";
    } else {
        display.innerText = "선택된 파일 없음";
    }

    for(let f of input.files) {
        if(f.size > 10*1024*1024) {
            display.innerText = "10MB 초과 파일 포함됨";
            display.style.color = "red";
            input.value = ""; $('#up-btn').prop('disabled', true); return;
        }
    }
    display.style.color = "#888";
    $('#up-btn').prop('disabled', input.files.length === 0);
}

function upload() {
    let fd = new FormData();
    for(let f of $('#upFiles')[0].files) fd.append('files[]', f);
    $.ajax({
        url: 'api.php?action=upload', 
        data: fd, type: 'POST', processData: false, contentType: false,
        success: () => { location.reload(); }
    });
}

function deleteSelectedTemp() {
    let checked = $('.temp-select:checked');
    if(checked.length === 0) return;
    let files = [];
    checked.each(function(){ files.push($(this).val()); });
    $.post('api.php?action=delete_temp', {files: files}, () => location.reload());
}

function moveSelectedToGallery() {
    let checked = $('.temp-select:checked');
    if(checked.length === 0) return;
    let files = [];
    checked.each(function(){ files.push($(this).val()); });
    $.post('api.php?action=move_to_gallery', {files: files}, () => { location.reload(); });
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
        isStarted = true;
    }).catch(e => console.log("Waiting interaction..."));
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
function selectAll(cls) { $(cls).prop('checked', true); }

function openModal(src) { 
    $('#modal-img').attr('src', src); 
    $('#modal').css('display', 'flex').hide().fadeIn(200); 
    $('body').css('overflow', 'hidden');
}

function closeModal() {
    $('#modal').fadeOut(200, function() {
        $('#modal-img').attr('src', '');
        $('body').css('overflow', 'auto');
    });
}

function downloadSelected() {
    let checked = $('.img-select:checked');
    if(checked.length === 0) return;
    let form = $('<form method="POST" action="api.php?action=download"></form>');
    checked.each(function(){ form.append(`<input type="hidden" name="files[]" value="${$(this).val()}">`); });
    $('body').append(form); form.submit(); form.remove();
}