<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
?>

<?php if ($is_admin == 'super') {  ?><!-- <div style='float:left; text-align:center;'>RUN TIME : <?php echo get_microtime()-$begin_time; ?><br></div> --><?php }  ?>

<?php run_event('tail_sub'); ?>

<style>
    /* 상단 미니 버튼 컨테이너 */
.pwa-header-container {
    position: fixed;
    top: 15px;
    right: 110px; /* 우측 상단 메뉴 버튼들과 겹치지 않게 적절히 조절 (itdot.kr 상단바 기준) */
    z-index: 10000;
    display: none; /* 기본적으로는 숨김 */
}

/* 상단 미니 버튼 디자인 */
#pwa-header-install-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background-color: #007bff;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 0px;
    font-size: 12px;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    cursor: pointer;
    transition: background-color 0.2s;
}

#pwa-header-install-btn:hover {
    background-color: #0056b3;
}

/* 미니 버튼 안의 작은 아이콘 */
.pwa-mini-icon {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

/* 우측 상단 X 닫기 버튼 스타일 */
#pwa-close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 28px; /* X 크기 */
    color: #999;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

#pwa-close-btn:hover {
    color: #333; /* 터치/마우스 오버 시 색상 진하게 */
}

/* 설치 버튼이 가로 전체를 채우도록 수정 */
#pwa-install-btn {
    width: 100%; /* 기존 flex: 2 대신 전체 너비 사용 */
    background-color: #007bff;
    color: white;
    border: none;
    padding: 14px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
}

/* 슬라이더 안쪽 여백 조정 (X버튼과 내용이 겹치지 않도록 상단 여백 추가) */
.pwa-slider {
    position: fixed;
    bottom: -150px;
    left: 0;
    width: 100%;
    background-color: #ffffff;
    box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.1);
    padding: 30px 20px 20px; /* 상단 패딩을 30px로 늘림 */
    box-sizing: border-box;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
    z-index: 9999;
    transition: bottom 0.4s ease-in-out;
}

/* 슬라이더가 나타날 때의 클래스 */
.pwa-slider.show {
    bottom: 0;
}

.pwa-content {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.pwa-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    margin-right: 15px;
}

.pwa-text h4 {
    margin: 0 0 5px 0;
    font-size: 16px;
    color: #333;
}

.pwa-text p {
    margin: 0;
    font-size: 13px;
    color: #666;
    word-break: keep-all;
}

.pwa-actions {
    display: flex;
    gap: 10px;
}

#pwa-install-btn {
    flex: 2;
    background-color: #007bff;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

#pwa-close-btn {
    flex: 1;
    background-color: #f1f3f5;
    color: #555;
    border: none;
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
}
</style>

<div id="pwa-header-btn-container" class="pwa-header-container">
    <button id="pwa-header-install-btn">
        <img src="/img/icons/icon-192x192.png" alt="App Icon" class="pwa-mini-icon">
        앱 설치
    </button>
</div>

<div id="pwa-install-slider" class="pwa-slider">
    <button id="pwa-close-btn" aria-label="닫기">&times;</button>
    <div class="pwa-content">
        <img src="/img/icons/icon-192x192.png" alt="App Icon" class="pwa-icon">
        <div class="pwa-text">
            <h4>앱으로 더 편하게!</h4>
            <p id="pwa-desc">홈 화면에 아이콘을 추가하여 언제든 빠르게 접속해 보세요.</p>
        </div>
    </div>
    <div class="pwa-actions">
        <button id="pwa-install-btn">바로 설치하기</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let deferredPrompt;
    const installSlider = document.getElementById('pwa-install-slider');
    const installBtn = document.getElementById('pwa-install-btn');
    const closeBtn = document.getElementById('pwa-close-btn');
    const descText = document.getElementById('pwa-desc');
    
    // 신규 추가된 상단 버튼 요소들
    const headerBtnContainer = document.getElementById('pwa-header-btn-container');
    const headerInstallBtn = document.getElementById('pwa-header-install-btn');

    // 변수 정의: 이번 세션에 하단 슬라이더를 닫았는지 여부 체크
    const isSliderDismissed = sessionStorage.getItem('pwa-slider-dismissed');

    // 공통 설치 함수 (하단 큰 버튼, 상단 작은 버튼 공용)
    const triggerInstall = async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`사용자 설치 선택: ${outcome}`);
            deferredPrompt = null;
        }
        installSlider.classList.remove('show');
        headerBtnContainer.style.display = 'none'; // 설치 시작하면 상단 버튼도 숨김
    };

    // 1. Android / PC 브라우저 대응 이벤트
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // 사용자가 이번 페이지 접속 중에 하단 슬라이더를 끈 적이 없다면 슬라이더 노출
        if (!isSliderDismissed) {
            setTimeout(() => {
                installSlider.classList.add('show');
            }, 1000);
        } else {
            // 이미 하단 슬라이더를 껐었다면 상단 미니 버튼을 바로 노출
            headerBtnContainer.style.display = 'block';
        }
    });

    // 하단 큰 버튼 클릭 시
    installBtn.addEventListener('click', triggerInstall);
    
    // 상단 미니 버튼 클릭 시
    headerInstallBtn.addEventListener('click', triggerInstall);

    // ★ 핵심: X 닫기 버튼 클릭 시 동작
    closeBtn.addEventListener('click', () => {
        // 1. 하단 슬라이더 집어넣기
        installSlider.classList.remove('show');
        
        // 2. 세션 저장소에 "나 슬라이더 껐음" 기록 (새로고침 전까지 유지됨)
        sessionStorage.setItem('pwa-slider-dismissed', 'true');
        
        // 3. 상단 미니 버튼 슬그머니 등장시키기
        headerBtnContainer.style.display = 'block';
    });

    // 2. iOS (Safari) 대응 조건문
    const isIos = () => /iphone|ipad|ipod/.test(navigator.userAgent.toLowerCase());
    const isInStandaloneMode = () => ('standalone' in navigator) && (navigator.standalone);

    if (isIos() && !isInStandaloneMode()) {
        descText.innerHTML = "하단의 <b>[공유]</b> 버튼을 누르고<br><b>[홈 화면에 추가]</b>를 선택해주세요.";
        installBtn.style.display = 'none'; // iOS는 직접 설치 명령이 안 되므로 대형 설치버튼 숨김
        
        if (!isSliderDismissed) {
            setTimeout(() => {
                installSlider.classList.add('show');
            }, 1000);
        } else {
            // iOS의 경우 상단 버튼을 눌러도 강제 프롬프트를 못 띄우므로, 
            // 닫았을 때 상단 버튼을 띄우는 대신 하단 슬라이더 문구만 살짝 바꿔 다시 부르는 유도가 좋습니다.
            // 필요하다면 iOS 전용 상단 안내 툴팁으로 커스텀 가능합니다.
            headerBtnContainer.style.display = 'block';
            headerInstallBtn.innerHTML = "💡 앱 설치 방법";
            headerInstallBtn.addEventListener('click', () => {
                installSlider.classList.add('show'); // 상단 알림 누르면 하단 가이드 다시 등장
            });
        }
    }
});
</script>

</body>
</html>
<?php echo html_end(); // HTML 마지막 처리 함수 : 반드시 넣어주시기 바랍니다.
