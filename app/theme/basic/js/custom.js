<script>
document.addEventListener('DOMContentLoaded', function() {
    // 버튼 요소 선택 (실제 사이트의 클래스/ID에 맞게 변경 필수)
    const installBtn = document.querySelector('.pwa-install-btn'); // [바로 설치하기] 버튼
    const closeBtn = document.querySelector('.pwa-close-btn');     // [x] 닫기 버튼

    // 1. 팝업 노출 기록 (Impression)
    // 팝업이 화면에 렌더링되었을 때 호출 (조건에 따라 제어 필요)
    sendPwaLog('impression');

    // 2. 설치 버튼 클릭 기록 (Click)
    if (installBtn) {
        installBtn.addEventListener('click', () => {
            sendPwaLog('click');
        });
    }

    // 3. 닫기 버튼 클릭 기록 (Dismissed)
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            sendPwaLog('dismissed');
        });
    }

    // 4. 실제 설치 완료 기록 (Installed)
    window.addEventListener('appinstalled', (evt) => {
        sendPwaLog('installed');
        console.log('PWA 설치가 완료되어 로그가 전송되었습니다.');
    });

    // AJAX 통신 함수
    function sendPwaLog(actionType) {
        // g5_url은 그누보드에서 기본 제공하는 전역 변수
        fetch(g5_url + '/ajax.pwa_log.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: actionType })
        }).catch(error => console.error('Error logging PWA action:', error));
    }
});
</script>