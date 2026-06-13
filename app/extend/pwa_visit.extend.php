<?php
if (!defined('_GNUBOARD_')) exit;

$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

$device_id = isset($_COOKIE['pwa_device_id']) ? preg_replace('/[^a-zA-Z0-9-]/', '', $_COOKIE['pwa_device_id']) : '';
$screen_res = isset($_COOKIE['pwa_screen_res']) ? preg_replace('/[^0-9x]/i', '', $_COOKIE['pwa_screen_res']) : '';

if ($device_id && !$is_ajax && !get_session('pwa_visit_logged')) {

    $sql_table = "
    CREATE TABLE IF NOT EXISTS `g5_pwa_visit_log` (
        `pv_id` INT(11) NOT NULL AUTO_INCREMENT,
        `mb_id` VARCHAR(20) NOT NULL DEFAULT '',
        `pv_device_id` VARCHAR(50) NOT NULL DEFAULT '',
        `pv_referer` VARCHAR(255) NOT NULL DEFAULT '',
        `pv_current_url` VARCHAR(255) NOT NULL DEFAULT '',
        `pv_is_app` TINYINT(4) NOT NULL DEFAULT '0',
        `pv_os` VARCHAR(20) NOT NULL DEFAULT '',
        `pv_browser` VARCHAR(20) NOT NULL DEFAULT '',
        `pv_screen_res` VARCHAR(20) NOT NULL DEFAULT '',
        `pv_ip` VARCHAR(45) NOT NULL DEFAULT '',
        `pv_datetime` DATETIME NOT NULL,
        PRIMARY KEY (`pv_id`),
        KEY `idx_visit_env` (`pv_is_app`, `pv_datetime`),
        KEY `idx_device_id` (`pv_device_id`),
        KEY `idx_mb_visit` (`mb_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    sql_query($sql_table, false);
    
    sql_query("ALTER TABLE `g5_pwa_visit_log` ADD `pv_device_id` VARCHAR(50) NOT NULL DEFAULT '' AFTER `mb_id`", false);
    sql_query("ALTER TABLE `g5_pwa_visit_log` ADD `pv_screen_res` VARCHAR(20) NOT NULL DEFAULT '' AFTER `pv_browser`", false);

    $is_app = (isset($_GET['utm_source']) && $_GET['utm_source'] === 'pwa') ? 1 : 0;
    
    global $member;
    $mb_id = isset($member['mb_id']) ? $member['mb_id'] : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? addslashes(strip_tags($_SERVER['HTTP_REFERER'])) : '';
    $current_url = isset($_SERVER['REQUEST_URI']) ? addslashes(strip_tags($_SERVER['REQUEST_URI'])) : '';
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    $os = '';
    if (function_exists('get_os') && get_os($agent)) { $os = get_os($agent); } else {
        if (preg_match('/windows nt 10/i', $agent)) $os = 'Windows 10/11';
        elseif (preg_match('/windows/i', $agent)) $os = 'Windows';
        elseif (preg_match('/macintosh|mac os/i', $agent)) $os = 'Mac OS';
        elseif (preg_match('/iphone/i', $agent)) $os = 'iOS (iPhone)';
        elseif (preg_match('/ipad/i', $agent)) $os = 'iOS (iPad)';
        elseif (preg_match('/android/i', $agent)) $os = 'Android';
        else $os = 'Unknown OS';
    }
    $os = addslashes($os);

    $browser = '';
    if (function_exists('get_brow') && get_brow($agent)) { $browser = get_brow($agent); } else {
        if (preg_match('/SamsungBrowser/i', $agent)) $browser = 'Samsung Internet';
        elseif (preg_match('/Whale/i', $agent)) $browser = 'Whale';
        elseif (preg_match('/Edge|Edg/i', $agent)) $browser = 'Edge';
        elseif (preg_match('/Chrome/i', $agent)) $browser = 'Chrome';
        elseif (preg_match('/Safari/i', $agent)) $browser = 'Safari';
        else $browser = 'Unknown Browser';
    }
    $browser = addslashes($browser);

    $ip = isset($_SERVER['REMOTE_ADDR']) ? addslashes(strip_tags($_SERVER['REMOTE_ADDR'])) : '';

    $sql = " INSERT INTO g5_pwa_visit_log
                SET mb_id = '{$mb_id}',
                    pv_device_id = '{$device_id}',
                    pv_referer = '{$referer}',
                    pv_current_url = '{$current_url}',
                    pv_is_app = '{$is_app}',
                    pv_os = '{$os}',
                    pv_browser = '{$browser}',
                    pv_screen_res = '{$screen_res}',
                    pv_ip = '{$ip}',
                    pv_datetime = '".G5_TIME_YMDHIS."' ";
                    
    sql_query($sql, false); 
    set_session('pwa_visit_logged', true);
}