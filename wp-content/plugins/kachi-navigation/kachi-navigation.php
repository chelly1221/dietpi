<?php
/**
 * Plugin Name: KACHI Navigation
 * Plugin URI: https://3chan.kr
 * Description: KACHI 특정 페이지에 사이드바 메뉴를 자동으로 적용하는 플러그인
 * Version: 2.0.0
 * Author: 3chan
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * Text Domain: kachi-navigation
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('SMM_VERSION', '2.3.1');
define('SMM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 필요한 클래스 파일들 포함 전에 체크
$required_files = array(
    'includes/class-smm-core.php',
    'includes/class-smm-admin.php',
    'includes/class-smm-shortcode.php',
    'includes/class-smm-ajax.php'
);

foreach ($required_files as $file) {
    $file_path = SMM_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>Sidebar Menu Manager: Required file missing - ' . esc_html($file) . '</p></div>';
        });
        return;
    }
}

// 플러그인 활성화
register_activation_hook(__FILE__, array('SMM_Core', 'activate'));

// 플러그인 비활성화
register_deactivation_hook(__FILE__, array('SMM_Core', 'deactivate'));

// 플러그인 초기화
add_action('plugins_loaded', array('SMM_Core', 'init'));