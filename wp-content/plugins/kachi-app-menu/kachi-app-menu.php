<?php
/**
 * Plugin Name: KACHI App Menu
 * Plugin URI: https://3chan.kr
 * Description: KACHI Google 스타일의 앱 메뉴를 모든 프론트엔드 페이지에 자동으로 표시합니다.
 * Version: 2.0.0
 * Author: 3chan
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * Text Domain: kachi-app-menu
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('GTM_VERSION', '2.1.7');
define('GTM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GTM_PLUGIN_URL', plugin_dir_url(__FILE__));

// 필요한 파일 포함
require_once GTM_PLUGIN_DIR . 'includes/class-gtm-core.php';
require_once GTM_PLUGIN_DIR . 'includes/class-gtm-frontend.php';
require_once GTM_PLUGIN_DIR . 'includes/class-gtm-shortcode.php';
require_once GTM_PLUGIN_DIR . 'includes/class-gtm-frontend-admin-template.php';

// 플러그인 활성화/비활성화 훅
register_activation_hook(__FILE__, array('GTM_Core', 'activate'));
register_deactivation_hook(__FILE__, array('GTM_Core', 'deactivate'));

// 플러그인 초기화
function gtm_init() {
    // 코어 기능 초기화
    GTM_Core::get_instance();
    
    // 프론트엔드 기능 초기화
    if (!is_admin()) {
        GTM_Frontend::get_instance();
    }
    
    // 쇼트코드 기능 초기화
    GTM_Shortcode::get_instance();
}
add_action('plugins_loaded', 'gtm_init');