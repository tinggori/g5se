<?php
if (!defined('_GNUBOARD_')) exit;

global $member;

if (isset($member['mb_id']) && $member['mb_id']) {
    $current_mb_id = addslashes($member['mb_id']);
    
    $device_id = isset($_COOKIE['pwa_device_id']) ? preg_replace('/[^a-zA-Z0-9-]/', '', $_COOKIE['pwa_device_id']) : '';
    $sync_session_name = 'pwa_synced_' . $current_mb_id;

    if ($device_id && !get_session($sync_session_name)) {
        $safe_device_id = addslashes($device_id);

        $sql_visit = " UPDATE g5_pwa_visit_log 
                          SET mb_id = '{$current_mb_id}',
                              pv_datetime = '".G5_TIME_YMDHIS."'
                        WHERE pv_device_id = '{$safe_device_id}' 
                          AND mb_id = '' ";
        sql_query($sql_visit, false);

        $sql_action = " UPDATE g5_pwa_action_log 
                           SET mb_id = '{$current_mb_id}' 
                         WHERE pa_device_id = '{$safe_device_id}' 
                           AND mb_id = '' ";
        sql_query($sql_action, false);

        set_session($sync_session_name, true);
    }
} else {
    // 비회원 방문자는 device_id 기반으로 세션 동기화 (선택 사항)
    $device_id = isset($_COOKIE['pwa_device_id']) ? preg_replace('/[^a-zA-Z0-9-]/', '', $_COOKIE['pwa_device_id']) : '';
    $sync_session_name = 'pwa_synced_guest_' . $device_id;

    if ($device_id && !get_session($sync_session_name)) {
        // 이미 mb_id 가 없는 기록은 device_id 로 묶여 있으므로, 추가 조치 없이 세션만 설정
        set_session($sync_session_name, true);
    }
}