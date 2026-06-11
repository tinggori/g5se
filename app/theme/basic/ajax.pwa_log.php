<?php
// ajax.pwa_log.php
include_once('../../_common.php'); // 경로에 맞게 수정 필요 (예: ../../_common.php)

// JSON 페이로드 읽기
$data = json_decode(file_get_contents('php://input'), true);
$action = isset($data['action']) ? clean_xss_tags($data['action']) : '';

// 허용된 액션값만 처리
$allowed_actions = array('impression', 'click', 'dismissed', 'installed');
if(!in_array($action, $allowed_actions)) {
    exit;
}

$mb_id = isset($member['mb_id']) ? $member['mb_id'] : '';
$agent = $_SERVER['HTTP_USER_AGENT'];

// 그누보드 내장 함수를 활용하여 OS와 브라우저 정보 추출
$os = get_os($agent); 
$browser = get_brow($agent);
$device = G5_IS_MOBILE ? 'Mobile' : 'Desktop'; // 그누보드 모바일 체크 상수
$ip = $_SERVER['REMOTE_ADDR'];

// DB 입력
$sql = " INSERT INTO g5_pwa_action_log
            SET mb_id = '{$mb_id}',
                pa_action = '{$action}',
                pa_os = '{$os}',
                pa_browser = '{$browser}',
                pa_device = '{$device}',
                pa_ip = '{$ip}',
                pa_datetime = '".G5_TIME_YMDHIS."' ";
sql_query($sql);
?>