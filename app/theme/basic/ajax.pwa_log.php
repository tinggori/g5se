<?php
include_once('../../_common.php'); 

$data = json_decode(file_get_contents('php://input'), true);
$action = isset($data['action']) ? strip_tags($data['action']) : '';

// 쿠키 우선, 없으면 JS payload 확인
$screen_res = isset($_COOKIE['pwa_screen_res']) ? preg_replace('/[^0-9x]/i', '', $_COOKIE['pwa_screen_res']) : '';
if (!$screen_res && isset($data['screen_res'])) {
    $screen_res = preg_replace('/[^0-9x]/i', '', $data['screen_res']);
}

$device_id = isset($_COOKIE['pwa_device_id']) ? preg_replace('/[^a-zA-Z0-9-]/', '', $_COOKIE['pwa_device_id']) : '';

$mb_id = isset($member['mb_id']) ? $member['mb_id'] : '';
$agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$os = function_exists('get_os') ? addslashes(get_os($agent)) : '';
$browser = function_exists('get_brow') ? addslashes(get_brow($agent)) : '';
$ip = isset($_SERVER['REMOTE_ADDR']) ? addslashes(strip_tags($_SERVER['REMOTE_ADDR'])) : '';

// [1] JS 지문 생성 직후의 방문 로그 처리
if ($action === 'visit') {
    if ($device_id && !get_session('pwa_visit_logged')) {
        $is_app = (isset($_GET['utm_source']) && $_GET['utm_source'] === 'pwa') ? 1 : 0;
        
        // 테이블 및 컬럼 생성 방어 코드
        $sql_table = "CREATE TABLE IF NOT EXISTS `g5_pwa_visit_log` (`pv_id` INT(11) NOT NULL AUTO_INCREMENT, `pv_datetime` DATETIME NOT NULL, PRIMARY KEY (`pv_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        sql_query($sql_table, false);
        sql_query("ALTER TABLE `g5_pwa_visit_log` ADD `pv_device_id` VARCHAR(50) NOT NULL DEFAULT '' AFTER `mb_id`", false);
        sql_query("ALTER TABLE `g5_pwa_visit_log` ADD `pv_screen_res` VARCHAR(20) NOT NULL DEFAULT '' AFTER `pv_browser`", false);

        $sql = " INSERT INTO g5_pwa_visit_log
                    SET mb_id = '{$mb_id}',
                        pv_device_id = '{$device_id}',
                        pv_referer = '', 
                        pv_current_url = '/',
                        pv_is_app = '{$is_app}',
                        pv_os = '{$os}',
                        pv_browser = '{$browser}',
                        pv_screen_res = '{$screen_res}',
                        pv_ip = '{$ip}',
                        pv_datetime = '".G5_TIME_YMDHIS."' ";
        sql_query($sql, false); 
        set_session('pwa_visit_logged', true);
    }
} 
// [2] 팝업 노출, 클릭, 설치 등 행동 로그 처리
else {
    $allowed_actions = array('impression', 'click', 'dismissed', 'installed');
    if(!in_array($action, $allowed_actions)) { exit; }

    $sql_table = "
    CREATE TABLE IF NOT EXISTS `g5_pwa_action_log` (
        `pa_id` INT(11) NOT NULL AUTO_INCREMENT,
        `mb_id` VARCHAR(20) NOT NULL DEFAULT '',
        `pa_device_id` VARCHAR(50) NOT NULL DEFAULT '',
        `pa_action` VARCHAR(20) NOT NULL,
        `pa_os` VARCHAR(20) NOT NULL DEFAULT '',
        `pa_browser` VARCHAR(20) NOT NULL DEFAULT '',
        `pa_screen_res` VARCHAR(20) NOT NULL DEFAULT '',
        `pa_device` VARCHAR(20) NOT NULL DEFAULT '',
        `pa_ip` VARCHAR(45) NOT NULL DEFAULT '',
        `pa_datetime` DATETIME NOT NULL,
        PRIMARY KEY (`pa_id`),
        KEY `idx_action_date` (`pa_action`, `pa_datetime`),
        KEY `idx_device_id` (`pa_device_id`),
        KEY `idx_mb_id` (`mb_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    sql_query($sql_table, false);

    sql_query("ALTER TABLE `g5_pwa_action_log` ADD `pa_device_id` VARCHAR(50) NOT NULL DEFAULT '' AFTER `mb_id`", false);
    sql_query("ALTER TABLE `g5_pwa_action_log` ADD `pa_screen_res` VARCHAR(20) NOT NULL DEFAULT '' AFTER `pa_browser`", false);

    $device = G5_IS_MOBILE ? 'Mobile' : 'Desktop'; 

    $sql = " INSERT INTO g5_pwa_action_log
                SET mb_id = '{$mb_id}',
                    pa_device_id = '{$device_id}',
                    pa_action = '{$action}',
                    pa_os = '{$os}',
                    pa_browser = '{$browser}',
                    pa_screen_res = '{$screen_res}',
                    pa_device = '{$device}',
                    pa_ip = '{$ip}',
                    pa_datetime = '".G5_TIME_YMDHIS."' ";
    sql_query($sql, false);
}
?>