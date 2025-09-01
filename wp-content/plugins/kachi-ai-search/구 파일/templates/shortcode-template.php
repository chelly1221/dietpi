<?php
/**
 * 까치 쿼리 시스템 전체 페이지 템플릿
 *
 * @package Kachi_Query_System
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <?php wp_head(); ?>
    <style>
        /* 템플릿 레벨 스타일 리셋 */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: hidden;
        }
        
        #kachi-page-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            width: 100vw !important;
            min-height: 100vh !important;
        }
        
        /* 워드프레스 관리자 바 여백 보정 */
        body.admin-bar .kachi-query-container {
            margin-top: -32px;
            padding-top: 72px;
        }
        
        @media screen and (max-width: 782px) {
            body.admin-bar .kachi-query-container {
                margin-top: -46px;
                padding-top: 86px;
            }
        }
    </style>
</head>
<body <?php body_class(); ?>>
    <div id="kachi-page-wrapper">
        <?php
        while (have_posts()) :
            the_post();
            the_content();
        endwhile;
        ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>