<?php
// Front controller — g5se
// 모든 요청을 받아 라우터로 위임한다.
// 주의: gnuboard 의 require 는 반드시 *글로벌 스코프* 에서 호출해야 한다.
//       (메서드/함수 안에서 require 하면 $g5 등 전역 변수가 로컬에 갇혀 DB 연결을 잃는다.)

// PHP 7.4 환경에서 PHP 8.0+ 문자열 함수 폴리필 (배포 서버가 7.4 인 경우 대비).
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
} 
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || (strlen($haystack) >= strlen($needle) && substr_compare($haystack, $needle, -strlen($needle)) === 0);
    }
}

define('_GNUBOARD_', true);
define('G5_PATH', __DIR__.'/app');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = preg_replace('/[^a-zA-Z0-9\.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$_g5se_base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($_g5se_base_path === '/' || $_g5se_base_path === '.') $_g5se_base_path = '';
define('G5SE_BASE_PATH', $_g5se_base_path);
define('G5_URL', $scheme.'://'.$host.G5SE_BASE_PATH);

// gnuboard 의 *_BBS_URL 들이 자동으로 '/bbs' 를 붙이지 못하도록 미리 박는다.
define('G5_BBS_URL',        G5_URL);
define('G5_HTTP_BBS_URL',   G5_URL);
define('G5_HTTPS_BBS_URL',  G5_URL);

// data/ 를 app/ 밖(docroot 직속)으로 분리했으므로 PATH/URL 도 그쪽을 가리키도록 미리 박는다.
define('G5_DATA_PATH',      __DIR__.'/data');
define('G5_DATA_URL',       G5_URL.'/data');

require G5_PATH.'/router.php';

$_g5se_request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (G5SE_BASE_PATH !== '' && str_starts_with($_g5se_request_path, G5SE_BASE_PATH.'/')) {
    $_g5se_request_path = substr($_g5se_request_path, strlen(G5SE_BASE_PATH));
} else if (G5SE_BASE_PATH !== '' && $_g5se_request_path === G5SE_BASE_PATH) {
    $_g5se_request_path = '/';
}

// 출력버퍼 필터: 모던화 완료된 엔드포인트의 `.php` 접미사를 자동 제거 + 게시판 URL 정리.
// gnuboard 내부 코드가 G5_BBS_URL.'/login_check.php' 형태로 URL 을 조립하므로,
// 최종 HTML 송출 직전에 일괄 치환해서 클린 URL 로 노출한다.
if ($_g5se_request_path !== '/install/install_db') {
ob_start(function ($html) {
    static $clean_endpoints = [
        'login', 'login_check', 'logout',
        'register', 'register_form', 'register_form_update', 'register_result',
        'member_confirm', 'member_leave',
        'password', 'password_check',
        'password_lost', 'password_lost_certify', 'password_lost2',
        'password_reset', 'password_reset_update',
        'search', 'new', 'faq', 'connect', 'board_list_update', 'move', 'move_update',
        'memo', 'memo_form', 'memo_form_update', 'memo_view', 'memo_delete',
        'formmail', 'formmail_send', 'profile', 'point',
        'scrap', 'scrap_delete', 'scrap_popin', 'scrap_popin_update',
        'poll_result', 'poll_update', 'poll_etc_update', 'poll_etc_update_mail',
        'sns_send',
    ];
    // 0) 내용관리 URL 정리: /content.php?co_id=X[&...] → /content/X[?...]
    $html = preg_replace_callback(
        '#/content\.php\?([^"\'\s<>]+)#',
        function ($m) {
            $qs = str_replace('&amp;', '&', $m[1]);
            parse_str($qs, $params);
            if (empty($params['co_id']) || !preg_match('/^[a-zA-Z0-9_-]+$/', $params['co_id'])) {
                return $m[0];
            }
            $url = '/content/'.$params['co_id'];
            unset($params['co_id']);
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, '', '&amp;');
            }
            return $url;
        },
        $html
    );

    // 1) 모던 엔드포인트 .php 제거: (/login).php → /login
    $pattern = '#(/(?:'.implode('|', $clean_endpoints).'))\.php(?![a-zA-Z0-9])#';
    $html = preg_replace($pattern, '$1', $html);

    // 1.5) 이름이 다른 레거시 엔드포인트 정리
    $html = preg_replace('#/qalist\.php(?![a-zA-Z0-9])#', '/qa', $html);
    $html = preg_replace('#/current_connect\.php(?![a-zA-Z0-9])#', '/connect', $html);

    // 2) 게시판 URL 정리: /board.php?bo_table=X[&wr_id=N][&page=Y&...] → /board/X[/N][?page=Y&...]
    //    매개변수 순서 무관하게 bo_table, wr_id 추출 후 나머지 query string 은 보존.
    //    HTML 안에선 `&` 가 `&amp;` 로 인코딩되므로 parse_str 호출 전 디코드.
    $html = preg_replace_callback(
        '#/board\.php\?([^"\'\s<>]+)#',
        function ($m) {
            $qs = str_replace('&amp;', '&', $m[1]);
            parse_str($qs, $params);
            if (empty($params['bo_table']) || !preg_match('/^[a-zA-Z0-9_]+$/', $params['bo_table'])) {
                return $m[0];
            }
            $url = '/board/'.$params['bo_table'];
            if (!empty($params['wr_id']) && preg_match('/^\d+$/', $params['wr_id'])) {
                $url .= '/'.$params['wr_id'];
                unset($params['wr_id']);
            }
            unset($params['bo_table']);
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, '', '&amp;');
            }
            return $url;
        },
        $html
    );

    // 3) 게시판 액션 URL 정리: /write.php / delete.php / good.php / download.php / view_image.php / link.php
    //    매개변수 순서 무관하게 bo_table, wr_id, no, w 추출 후 /board/{bo_table}/{action}[/{wr_id}[/{no}]][?w=X&...] 로 재조립
    $html = preg_replace_callback(
        '#/(write|write_update|delete|good|nogood|download|view_image|link)\.php\?([^"\'\s<>]+)#',
        function ($m) {
            $action = $m[1];
            $qs = str_replace('&amp;', '&', $m[2]);
            parse_str($qs, $params);
            if (empty($params['bo_table']) || !preg_match('/^[a-zA-Z0-9_]+$/', $params['bo_table'])) {
                return $m[0];   // bo_table 없거나 이상하면 그대로 둠
            }
            $url = '/board/'.$params['bo_table'].'/'.$action;
            if (!empty($params['wr_id']) && preg_match('/^\d+$/', $params['wr_id'])) {
                $url .= '/'.$params['wr_id'];
                unset($params['wr_id']);
            }
            if (!empty($params['no']) && preg_match('/^\d+$/', $params['no'])) {
                $url .= '/'.$params['no'];
                unset($params['no']);
            }
            unset($params['bo_table']);
            // 남은 query 파라미터 (w, sca, sfl, stx, page 등) 보존
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, '', '&amp;');
            }
            return $url;
        },
        $html
    );

    // 4) 댓글 작성/삭제도 마찬가지: /write_comment_update.php, /delete_comment.php
    $html = preg_replace_callback(
        '#/write_comment_update\.php\?bo_table=([a-zA-Z0-9_]+)#',
        fn($m) => '/board/'.$m[1].'/comment',
        $html
    );
    $html = preg_replace_callback(
        '#/delete_comment\.php\?([^"\'\s<>]+)#',
        function ($m) {
            $qs = str_replace('&amp;', '&', $m[1]);
            parse_str($qs, $params);
            if (empty($params['bo_table']) || !preg_match('/^[a-zA-Z0-9_]+$/', $params['bo_table'])) {
                return $m[0];
            }

            $url = '/board/'.$params['bo_table'].'/comment/delete';
            if (!empty($params['comment_id']) && preg_match('/^\d+$/', $params['comment_id'])) {
                $url .= '/'.$params['comment_id'];
                unset($params['comment_id']);
            }
            unset($params['bo_table']);
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, '', '&amp;');
            }

            return $url;
        },
        $html
    );

    // 5) shop 상품 상세: /shop/item.php?it_id=X[&...] → /shop/item/X[?...]
    //    it_id 외 query 는 보존, &amp; 인코딩 유지
    $html = preg_replace_callback(
        '#/shop/item\.php\?([^"\'\s<>]+)#',
        function ($m) {
            $qs = str_replace('&amp;', '&', $m[1]);
            parse_str($qs, $params);
            if (empty($params['it_id']) || !preg_match('/^[a-zA-Z0-9_-]+$/', $params['it_id'])) {
                return $m[0];
            }
            $url = '/shop/item/'.$params['it_id'];
            unset($params['it_id']);
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, '', '&amp;');
            }
            return $url;
        },
        $html
    );

    // 6) shop 카테고리 리스트: /shop/list.php?ca_id=X[&...] → /shop/category/X[?...]
    $html = preg_replace_callback(
        '#/shop/list\.php\?([^"\'\s<>]+)#',
        function ($m) {
            $qs = str_replace('&amp;', '&', $m[1]);
            parse_str($qs, $params);
            if (empty($params['ca_id']) || !preg_match('/^[a-zA-Z0-9_-]+$/', $params['ca_id'])) {
                return $m[0];
            }
            $url = '/shop/category/'.$params['ca_id'];
            unset($params['ca_id']);
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, '', '&amp;');
            }
            return $url;
        },
        $html
    );

    // 7) shop 리스트 타입: /shop/listtype.php?type=N[&...] → /shop/listtype/N[?...]
    //    type 1~5 외 값은 listtype.php 의 alert 분기로 떨어지므로 router 단에선 \d+ 만 검증
    $html = preg_replace_callback(
        '#/shop/listtype\.php\?([^"\'\s<>]+)#',
        function ($m) {
            $qs = str_replace('&amp;', '&', $m[1]);
            parse_str($qs, $params);
            if (empty($params['type']) || !preg_match('/^\d+$/', $params['type'])) {
                return $m[0];
            }
            $url = '/shop/listtype/'.$params['type'];
            unset($params['type']);
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, '', '&amp;');
            }
            return $url;
        },
        $html
    );

    // 8) shop 위시리스트: /shop/wishlist.php → /shop/wishlist
    $html = preg_replace('#/shop/wishlist\.php(?![a-zA-Z0-9])#', '/shop/wishlist', $html);

    // 9) shop 장바구니: /shop/cart.php → /shop/cart (query 없음, 세션 기반)
    $html = preg_replace('#/shop/cart\.php(?![a-zA-Z0-9])#', '/shop/cart', $html);

    // 10) shop 주문서: /shop/orderform.php → /shop/orderform
    $html = preg_replace('#/shop/orderform\.php(?![a-zA-Z0-9])#', '/shop/orderform', $html);

    // 11) shop 주문조회 (orderinquiry, orderinquiryview, orderinquirycancel) — query 보존
    $html = preg_replace('#/shop/(orderinquiry(?:view|cancel)?)\.php(?![a-zA-Z0-9])#', '/shop/$1', $html);

    // 12) shop 배송지목록: /shop/orderaddress.php → /shop/orderaddress
    $html = preg_replace('#/shop/orderaddress\.php(?![a-zA-Z0-9])#', '/shop/orderaddress', $html);

    // 13) shop 쿠폰: /shop/coupon.php → /shop/coupon
    $html = preg_replace('#/shop/coupon\.php(?![a-zA-Z0-9])#', '/shop/coupon', $html);

    // 14) 설문조사: /bbs/poll_(result|update|etc_update|etc_update_mail).php → /poll_*
    $html = preg_replace('#/bbs/(poll_(?:result|update|etc_update_mail|etc_update))\.php(?![a-zA-Z0-9])#', '/$1', $html);

    // 15) admin: /admin/.../*.php → /admin/.../* (clean URL 정규화, hard-coded .php 링크 자동 정리)
    $html = preg_replace('#(/admin(?:/[a-zA-Z][a-zA-Z0-9_-]*)+)\.php(?![a-zA-Z0-9])#', '$1', $html);

    // 16) shop 이벤트: /shop/event.php?ev_id=N[&...] → /shop/event/N[?...] (잔여 query 보존)
    $html = preg_replace_callback(
        '#/shop/event\.php\?(ev_id=\d+(?:&(?:amp;)?[^"\'<>\s]*)?)#',
        function ($m) {
            parse_str(html_entity_decode($m[1]), $qp);
            $ev_id = $qp['ev_id'] ?? '';
            unset($qp['ev_id']);
            $url = '/shop/event/'.$ev_id;
            if (!empty($qp)) $url .= '?'.http_build_query($qp, '', '&amp;');
            return $url;
        },
        $html
    );

    // 하위 디렉토리 배포(/g5se 등)에서는 테마/관리자 화면에 남아 있는
    // 루트 상대 링크(/search, /admin 등)를 설치 기준 경로 아래로 보정한다.
    if (defined('G5SE_BASE_PATH') && G5SE_BASE_PATH !== '') {
        $base_segment = preg_quote(ltrim(G5SE_BASE_PATH, '/'), '#');

        // HTML attributes. 팝업/관리자/쇼핑 화면은 data-url, formaction 같은 속성도 사용한다.
        $base_boundary = '(?:/|\\?|\\#|["\']|$)';

        $html = preg_replace_callback(
            '#\b(href|src|action|formaction|poster|data-(?:url|href|action|src))=(["\'])/(?!/|'.$base_segment.$base_boundary.')#i',
            function ($m) {
                return $m[1].'='.$m[2].G5SE_BASE_PATH.'/';
            },
            $html
        );

        // Same-origin absolute URLs may be stored in editor content, e.g.
        // https://example.com/data/editor/... from a root install. Keep them
        // inside the current subdirectory install as well.
        $host_pattern = preg_quote($_SERVER['HTTP_HOST'] ?? '', '#');
        if ($host_pattern !== '') {
            $html = preg_replace_callback(
                '#\b(href|src|action|formaction|poster|data-(?:url|href|action|src))=(["\'])https?://'.$host_pattern.'/(?!'.$base_segment.$base_boundary.')#i',
                function ($m) {
                    return $m[1].'='.$m[2].G5_URL.'/';
                },
                $html
            );
        }

        // Inline JavaScript locations and popup opens.
        $html = preg_replace_callback(
            '#((?:window\.)?(?:location(?:\.href)?|location\.(?:replace|assign)|document\.location(?:\.href)?|top\.location(?:\.href)?|opener\.location(?:\.href)?)\s*(?:=|\()\s*(["\']))/(?!/|'.$base_segment.$base_boundary.')#i',
            function ($m) {
                return $m[1].G5SE_BASE_PATH.'/';
            },
            $html
        );

        $html = preg_replace_callback(
            '#(window\.open\(\s*(["\']))/(?!/|'.$base_segment.$base_boundary.')#i',
            function ($m) {
                return $m[1].G5SE_BASE_PATH.'/';
            },
            $html
        );

        // jQuery.ajax({ url: "/..." }) style values.
        $html = preg_replace_callback(
            '#(\burl\s*:\s*(["\']))/(?!/|'.$base_segment.$base_boundary.')#i',
            function ($m) {
                return $m[1].G5SE_BASE_PATH.'/';
            },
            $html
        );

        // Meta refresh fallback: content="0;url=/..."
        $html = preg_replace_callback(
            '#(\bcontent=(["\'])[^"\']*?;\s*url=)/(?!/|'.$base_segment.$base_boundary.')#i',
            function ($m) {
                return $m[1].G5SE_BASE_PATH.'/';
            },
            $html
        );
    }

    return $html;
});
}

$_route_target = (new Router())->resolve($_SERVER['REQUEST_URI']);

if ($_route_target === null) {
    http_response_code(404);
    echo "404 — no route";
    exit;
}

$_route_full = G5_PATH.'/'.$_route_target;
if (!is_file($_route_full)) {
    http_response_code(500);
    echo "Router target not found: $_route_target";
    exit;
}

// gnuboard 의 './_common.php' 같은 상대 include 가 동작하도록 CWD 를 맞춘다.
// app/modules/ 트리 (폴더 기반 자동 라우팅) 의 페이지는 CWD 를 항상 G5_PATH 로 고정해
// 레시피의 include_once('./_common.php') 패턴이 그대로 동작하게 한다.
// 식별자: Router::discoverModulePage() 만이 'modules/.../index.php' 를 반환한다.
// 사용자 정의 routes 가 우연히 'modules/<x>.php' 를 target 으로 잡아도 index.php 가
// 아니면 이 분기에 들어오지 않도록 suffix 까지 검사.
if (str_starts_with($_route_target, 'modules/') && str_ends_with($_route_target, '/index.php')) {
    chdir(G5_PATH);
} else {
    chdir(dirname($_route_full));
}

// gnuboard legacy 코드가 self-URL (페이지네이션, 폼 action 등) 을 만들 때
// $_SERVER['SCRIPT_NAME'] 을 사용. front controller 에선 항상 '/index.php'
// 라서 listtype.php?type=4 페이지네이션 클릭이 /index.php?... 로 빠지는
// 문제 발생 → resolved target 으로 덮어써서 마치 직접 실행된 것처럼 보이게.
$_SERVER['SCRIPT_NAME'] = '/'.$_route_target;
$_SERVER['PHP_SELF']    = '/'.$_route_target;

// 글로벌 스코프 require (반드시!)
require $_route_full;
