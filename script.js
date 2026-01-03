let audio = new Audio();
let playlist = [];
let cur = 0;

$(document).ready(function() {
    // 볼륨 조절 슬라이더 리스너
    const volRange = document.getElementById('vol-range');
    if(volRange) {
        volRange.addEventListener('input', function() {
            audio.volume = this.value;
        });
    }
    
    // BGM 목록 불러오기
    loadBgm();
});

// 로그인 함수: 비번이 맞으면 페이지를 새로고침해서 로그보기 버튼을 노출시킴
function login() {
    const pwVal = $('#adminPw').val();
    if(!pwVal) return;
    
    $.post('api.php?action=login', {pw: pwVal}, function(res) {
        if(res.trim() === 'ok') {
            // 성공 시 페이지 새로고침 -> PHP가 세션을 확인하고 버튼을 그려줌
            location.reload(); 
        } else {
            alert('비밀번호가 틀렸습니다.');
            $('#adminPw').val('').focus();
        }
    });
}

function loadBgm() {
    $.getJSON('api.php?action=get_bgm', function(data) {
        if(data && data.length > 0) {
            playlist = data.sort(() => Math.random() - 0.5);
            renderNext();
        } else {
            $('#now-title').text("BGM 없음");
        }
    });
}

function playBgm() {
    if(playlist.length === 0) return;
    audio.src = 'bgm/' + playlist[cur];
    audio.volume = document.getElementById('vol-range').value;
    
    audio.play().then(() => {
        $('#now-title').text("♬ " + playlist[cur]);
        cur = (cur + 1) % playlist.length;
        renderNext();
    }).catch(err => console.error("재생 오류:", err));
}

audio.onended = function() { playBgm(); };

function stopBgm() {
    audio.pause();
    $('#now-title').text("BGM 중지됨");
}

function renderNext() {
    let h = "";
    for(let i=0; i<5; i++) {
        let idx = (cur + i) % playlist.length;
        if(playlist[idx]) h += `<li>${playlist[idx]}</li>`;
    }
    $('#next-list').html(h);
}

// 갤러리 제어
function selectAll() { $('.img-select').prop('checked', true); }

function downloadSelected() {
    let checked = $('.img-select:checked');
    if(checked.length === 0) return alert('사진을 선택하세요.');
    
    let form = $('<form method="POST" action="api.php?action=download"></form>');
    checked.each(function(){
        form.append(`<input type="hidden" name="files[]" value="${$(this).val()}">`);
    });
    $('body').append(form);
    form.submit(); 
    form.remove();
}

function checkFiles(input) {
    for(let f of input.files) {
        if(f.size > 10*1024*1024) {
            alert(f.name + " 파일이 10MB를 넘습니다.");
            input.value = "";
            $('#up-btn').prop('disabled', true);
            return;
        }
    }
    $('#up-btn').prop('disabled', input.files.length === 0);
}

function upload() {
    let fd = new FormData();
    for(let f of $('#upFiles')[0].files) fd.append('files[]', f);
    $.ajax({
        url: 'api.php?action=upload',
        data: fd,
        type: 'POST',
        processData: false,
        contentType: false,
        success: () => {
            alert('업로드 완료');
            location.reload();
        }
    });
}

function openModal(src) {
    $('#modal-img').attr('src', src);
    $('#modal').css('display', 'flex');
}