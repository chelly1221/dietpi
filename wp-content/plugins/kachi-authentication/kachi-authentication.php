<?php
/**
 * Plugin Name: KACHI Authentication
 * Plugin URI: https://3chan.kr
 * Description: KACHI 커스텀 로그인 시스템 플러그인 - 쇼트코드를 통해 독립적인 로그인 페이지를 생성합니다.
 * Version: 1.3.0
 * Author: 3chan
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * Text Domain: kachi-authentication
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('KAC_LOGIN_VERSION', '1.4.7');
define('KAC_LOGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KAC_LOGIN_PLUGIN_URL', plugin_dir_url(__FILE__));

// 클래스 파일 포함
require_once KAC_LOGIN_PLUGIN_DIR . 'includes/class-kac-login.php';
require_once KAC_LOGIN_PLUGIN_DIR . 'includes/class-kac-login-shortcode.php';
require_once KAC_LOGIN_PLUGIN_DIR . 'includes/class-kac-login-handler.php';
require_once KAC_LOGIN_PLUGIN_DIR . 'includes/class-kac-registration.php';
require_once KAC_LOGIN_PLUGIN_DIR . 'includes/class-kac-login-frontend-admin.php';

// 플러그인 활성화
register_activation_hook(__FILE__, array('KAC_Login', 'activate'));

// 플러그인 비활성화
register_deactivation_hook(__FILE__, array('KAC_Login', 'deactivate'));

// 플러그인 삭제
register_uninstall_hook(__FILE__, 'kac_login_uninstall');

function kac_login_uninstall() {
    // 옵션 삭제
    delete_option('kac_login_settings');
    delete_option('kac_organization_data');
    
    // 사용자 메타 삭제 (선택적 - 주의해서 사용)
    // global $wpdb;
    // $wpdb->delete($wpdb->usermeta, array('meta_key' => 'kachi_sosok'));
    // $wpdb->delete($wpdb->usermeta, array('meta_key' => 'kachi_site'));
}

// 플러그인 초기화
add_action('plugins_loaded', array('KAC_Login', 'get_instance'));