<?php
if (!defined('_INDEX_')) define('_INDEX_', true);
if (!defined('_GNUBOARD_')) exit;

if (G5_IS_MOBILE) {
    include_once(G5_THEME_MOBILE_PATH.'/index.php');
    return;
}

if(G5_COMMUNITY_USE === false) {
    include_once(G5_THEME_SHOP_PATH.'/index.php');
    return;
}

// 공통 모던 디자인 head 주입
require_once(G5_THEME_PATH.'/modern/_head.inc.php');

// gnuboard 의 head/tail 은 sub 버전으로 — 게시판(bbs/board.php)도 head.sub.php 사용하므로
// 동일하게 맞춰 default.css 의 chrome cascade 영향을 제거 (자간 등 미세한 차이 발생 원인 차단).
include_once(G5_PATH.'/head.sub.php');
?>

<div class="m-shell">

    <?php require G5_THEME_PATH.'/modern/_nav.inc.php'; ?>

    <?php
    // 팝업레이어 — admin/newwinform 에서 등록한 newwin 을 메인에서 노출 (legacy head.php 에서
    // _INDEX_ 가드로 include 하던 것을 modern shell 에 직접 이식).
    include G5_BBS_PATH.'/newwin.inc.php';
    ?>

    <main class="m-container m-with-sidebar" style="padding: 32px 20px 48px;">
        <!-- 좌측: 메인 콘텐츠 -->
        <div class="m-main-col">
            <section style="text-align: center; padding: 32px 16px 48px;">
                <h1 style="font-size: var(--m-text-display); margin-bottom: 14px;">
                    <?php if ($is_member) { ?>
                        안녕하세요, <span style="color: var(--m-primary);"><?php echo get_text($member['mb_nick']) ?></span> 님
                    <?php } else { ?>
                        환영합니다...
                    <?php } ?>
                </h1>
                <p style="font-size: var(--m-text-lg); color: var(--m-text-muted); max-width: 560px; margin: 0 auto;">
                    gnuboard5 위에 모던 디자인 시스템을 얹어 점진적으로 새로 빚어가는 사이트입니다.
                </p>
            </section>

            <section>
                <?php
                // gnuboard latest 함수 — theme 스킨 사용
                if (!function_exists('latest')) {
                    @include_once(G5_LIB_PATH.'/latest.lib.php');
                }

                // 메인 노출 게시판 목록 — bo_skin 으로 갤러리/게시판 분기
                $sql = "select bo_table, bo_subject, bo_skin
                        from `{$g5['board_table']}`
                        where bo_device <> 'mobile'
                        ".($is_admin ? '' : "and bo_use_cert = ''")."
                        order by bo_order, bo_table limit 6";
                $rs = sql_query($sql);
                $_widgets = [];
                while ($row = sql_fetch_array($rs)) {
                    // bo_skin 에 'gallery' 또는 'pic' 들어가면 갤러리형, 그 외는 게시판형
                    $bo_skin = strtolower($row['bo_skin'] ?? '');
                    $is_gallery = (strpos($bo_skin, 'gallery') !== false || strpos($bo_skin, 'pic') !== false);
                    $_widgets[] = [
                        'bo_table' => $row['bo_table'],
                        'widget'   => $is_gallery ? 'theme/m_gallery' : 'theme/m_board',
                        'rows'     => $is_gallery ? 4 : 5,
                        'sublen'   => 36,
                    ];
                }
                ?>

                <div class="m-latest-grid">
                    <?php foreach ($_widgets as $w) { ?>
                    <div class="m-latest-cell">
                        <?php echo latest($w['widget'], $w['bo_table'], $w['rows'], $w['sublen']); ?>
                    </div>
                    <?php } ?>
                </div>
            </section>
        </div>

        <!-- 우측: 사이드바 (outlogin + 설문) -->
        <aside class="m-side-col">
            <?php require G5_THEME_PATH.'/modern/_outlogin.inc.php'; ?>
            <?php
            // 설문 (poll) — 활성 설문이 있을 때만 카드 노출. poll.lib 명시적 로드 (modern 흐름이 자동 로드 안 함).
            if (!function_exists('poll')) {
                @include_once(G5_LIB_PATH.'/poll.lib.php');
            }
            $_poll_html = function_exists('poll') ? poll('theme/basic') : '';
            if ($_poll_html) {
                echo '<div class="m-side-card">'.$_poll_html.'</div>';
            }
            ?>
        </aside>
    </main>

    <?php require G5_THEME_PATH.'/modern/_footer.inc.php'; ?>

</div>

<?php
// 게시판(bbs/board.php)과 동일하게 tail.sub.php 사용
include_once(G5_PATH.'/tail.sub.php');
