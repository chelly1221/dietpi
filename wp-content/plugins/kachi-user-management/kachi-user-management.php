<?php
/**
 * Plugin Name: KACHI User Management
 * Plugin URI: https://3chan.kr
 * Description: KACHI 사용자 계정 승인 시스템을 제공하는 플러그인입니다.
 * Version: 1.2.0
 * Author: 3chan
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * Text Domain: kachi-user-management
 */

// 보안을 위한 직접 접근 차단
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('UAS_PLUGIN_VERSION', '1.5.1');
define('UAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UAS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('UAS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 클래스 파일 로드
require_once UAS_PLUGIN_PATH . 'includes/class-user-approval-system.php';
require_once UAS_PLUGIN_PATH . 'includes/class-uas-shortcode.php';
require_once UAS_PLUGIN_PATH . 'includes/class-uas-ajax.php';

// 프론트엔드 관리자 클래스 파일이 있는 경우에만 로드
if (file_exists(UAS_PLUGIN_PATH . 'includes/class-uas-frontend-admin.php')) {
    require_once UAS_PLUGIN_PATH . 'includes/class-uas-frontend-admin.php';
}

// 플러그인 활성화
register_activation_hook(__FILE__, array('User_Approval_System', 'activate'));

// 플러그인 비활성화
register_deactivation_hook(__FILE__, array('User_Approval_System', 'deactivate'));

// 플러그인 초기화
add_action('plugins_loaded', array('User_Approval_System', 'get_instance'));