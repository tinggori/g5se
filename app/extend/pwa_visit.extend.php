<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 직접 접근 방지 (그누보드 필수 보안)

// 1. AJAX 통신일 경우 무시 (백그라운드 통신에서 불필요한 로그가 쌓이거나 에러가 나는 것을 방지)
$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// 2. 세션이 없고, AJAX 통신이 아닐 때만 실행 (방문당 1회 기록)
if (!$is_ajax && !get_session('pwa_visit_logged')) {

    // [핵심 안전장치] 테이블이 없으면 자동으로 생성합니다. (500 에러 원천 차단)
    $sql_table = "
    CREATE TABLE IF NOT EXISTS `g5_pwa_visit_log` (
        `pv_id` INT(11) NOT NULL AUTO_INCREMENT,
        `mb_id` VARCHAR(20) NOT NULL DEFAULT '',
        `pv_referer` VARCHAR(255) NOT NULL DEFAULT '',
        `pv_current_url` VARCHAR(255) NOT NULL DEFAULT '',
        `pv_is_app` TINYINT(4) NOT NULL DEFAULT '0',
        `pv_os` VARCHAR(20) NOT NULL DEFAULT '',
        `pv_browser` VARCHAR(20) NOT NULL DEFAULT '',
        `pv_ip` VARCHAR(45) NOT NULL DEFAULT '',
        `pv_datetime` DATETIME NOT NULL,
        PRIMARY KEY (`pv_id`),
        KEY `idx_visit_env` (`pv_is_app`, `pv_datetime`),
        KEY `idx_mb_visit` (`mb_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    // sql_query의 두 번째 파라미터를 false로 주어 에러를 화면에 뿜지 않고 넘깁니다.
    sql_query($sql_table, false);

    // 3. 앱 접속 여부 확인 (manifest.json의 start_url에 ?utm_source=pwa 를 붙여두었다고 가정)
    $is_app = (isset($_GET['utm_source']) && $_GET['utm_source'] === 'pwa') ? 1 : 0;
    
// 4. 전역 변수 및 접속 정보 수집
    global $member;
    $mb_id = isset($member['mb_id']) ? $member['mb_id'] : '';
    
    // 무거운 clean_xss_tags 대신 가벼운 strip_tags 사용
    $referer = isset($_SERVER['HTTP_REFERER']) ? addslashes(strip_tags($_SERVER['HTTP_REFERER'])) : '';
    $current_url = isset($_SERVER['REQUEST_URI']) ? addslashes(strip_tags($_SERVER['REQUEST_URI'])) : '';
    
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    // 1. OS 정보 추출 (그누보드 함수 시도 후 실패 시 자체 정규식 판별)
    $os = '';
    if (function_exists('get_os') && get_os($agent)) {
        $os = get_os($agent);
    } else {
        if (preg_match('/windows nt 10/i', $agent)) $os = 'Windows 10/11';
        elseif (preg_match('/windows nt 6\.[23]/i', $agent)) $os = 'Windows 8';
        elseif (preg_match('/windows nt 6\.1/i', $agent)) $os = 'Windows 7';
        elseif (preg_match('/windows/i', $agent)) $os = 'Windows';
        elseif (preg_match('/macintosh|mac os/i', $agent)) $os = 'Mac OS';
        elseif (preg_match('/iphone/i', $agent)) $os = 'iOS (iPhone)';
        elseif (preg_match('/ipad/i', $agent)) $os = 'iOS (iPad)';
        elseif (preg_match('/android/i', $agent)) $os = 'Android';
        elseif (preg_match('/linux/i', $agent)) $os = 'Linux';
        else $os = 'Unknown OS';
    }
    $os = addslashes($os);

    // 2. 브라우저 정보 추출 (그누보드 함수 시도 후 실패 시 자체 정규식 판별)
    $browser = '';
    if (function_exists('get_brow') && get_brow($agent)) {
        $browser = get_brow($agent);
    } else {
        if (preg_match('/SamsungBrowser/i', $agent)) $browser = 'Samsung Internet';
        elseif (preg_match('/Whale/i', $agent)) $browser = 'Whale';
        elseif (preg_match('/Edge|Edg/i', $agent)) $browser = 'Edge';
        elseif (preg_match('/Chrome/i', $agent)) $browser = 'Chrome';
        elseif (preg_match('/Firefox/i', $agent)) $browser = 'Firefox';
        elseif (preg_match('/Safari/i', $agent)) $browser = 'Safari';
        elseif (preg_match('/MSIE|Trident/i', $agent)) $browser = 'IE';
        else $browser = 'Unknown Browser';
    }
    $browser = addslashes($browser);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? addslashes(strip_tags($_SERVER['REMOTE_ADDR'])) : '';

    // 5. 수집된 데이터 DB 저장
    $sql = " INSERT INTO g5_pwa_visit_log
                SET mb_id = '{$mb_id}',
                    pv_referer = '{$referer}',
                    pv_current_url = '{$current_url}',
                    pv_is_app = '{$is_app}',
                    pv_os = '{$os}',
                    pv_browser = '{$browser}',
                    pv_ip = '{$ip}',
                    pv_datetime = '".G5_TIME_YMDHIS."' ";
                    
    sql_query($sql, false); // 데이터 삽입 시에도 에러가 발생하면 사이트를 멈추지 않고 무시

    // 6. 로그 기록 완료 세션 생성 (브라우저를 닫기 전까지 중복 기록 방지)
    set_session('pwa_visit_logged', true);
}
