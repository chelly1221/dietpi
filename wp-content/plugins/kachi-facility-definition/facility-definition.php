<?php
/**
 * Plugin Name: KACHI Facility Definition
 * Plugin URI: https://3chan.kr
 * Description: KACHI 시설 정의를 관리하는 플러그인입니다.
 * Version: 1.2.0
 * Author: 3chan
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * Text Domain: kachi-facility-definition
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의 - KACHI Facility Definition
define('KACHI_FACILITY_DEFINITION_VERSION', '1.2.0');
define('KACHI_FACILITY_DEFINITION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KACHI_FACILITY_DEFINITION_PLUGIN_URL', plugin_dir_url(__FILE__));

// Legacy constants for backward compatibility
define('FACILITY_DEFINITION_VERSION', '1.2.0');
define('FACILITY_DEFINITION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FACILITY_DEFINITION_PLUGIN_URL', plugin_dir_url(__FILE__));

// 필수 파일 포함
require_once FACILITY_DEFINITION_PLUGIN_DIR . 'includes/class-facility-definition.php';
require_once FACILITY_DEFINITION_PLUGIN_DIR . 'includes/class-facility-ajax.php';
require_once FACILITY_DEFINITION_PLUGIN_DIR . 'includes/class-facility-shortcode.php';
require_once FACILITY_DEFINITION_PLUGIN_DIR . 'includes/class-facility-frontend-admin.php';

// 플러그인 활성화
register_activation_hook(__FILE__, 'facility_definition_activate');
function facility_definition_activate() {
    // 기본 옵션 추가
    if (false === get_option('facility_definitions_data')) {
        add_option('facility_definitions_data', array());
    }
    
    // 배경 이미지 옵션 추가
    if (false === get_option('facility_background_image')) {
        add_option('facility_background_image', '');
    }
    
    if (false === get_option('facility_background_position')) {
        add_option('facility_background_position', 'bottom center');
    }
    
    if (false === get_option('facility_background_size')) {
        add_option('facility_background_size', 'cover');
    }
    
    // 재작성 규칙 플러시
    flush_rewrite_rules();
}

// 플러그인 비활성화
register_deactivation_hook(__FILE__, 'facility_definition_deactivate');
function facility_definition_deactivate() {
    flush_rewrite_rules();
}

// 플러그인 삭제
register_uninstall_hook(__FILE__, 'facility_definition_uninstall');
function facility_definition_uninstall() {
    // 옵션 삭제
    delete_option('facility_definitions_data');
    delete_option('facility_background_image');
    delete_option('facility_background_position');
    delete_option('facility_background_size');
    
    // 사용자 메타 삭제 (선택적)
    // global $wpdb;
    // $wpdb->delete($wpdb->usermeta, array('meta_key' => 'facility_sosok'));
    // $wpdb->delete($wpdb->usermeta, array('meta_key' => 'facility_site'));
}

// 플러그인 초기화
add_action('plugins_loaded', 'facility_definition_init');
function facility_definition_init() {
    // 텍스트 도메인 로드
    load_plugin_textdomain('facility-definition', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // 메인 클래스 인스턴스 생성
    $facility_definition = new Facility_Definition();
    $facility_definition->init();
    
    // 사용자 프로필 필드 추가
    add_action('show_user_profile', 'facility_add_profile_fields');
    add_action('edit_user_profile', 'facility_add_profile_fields');
    add_action('personal_options_update', 'facility_save_profile_fields');
    add_action('edit_user_profile_update', 'facility_save_profile_fields');
}

/**
 * 사용자 프로필 필드 추가
 */
function facility_add_profile_fields($user) {
    // 까치 쿼리 시스템과 동일한 메타 키 사용
    $sosok = get_user_meta($user->ID, 'kachi_sosok', true);
    $site = get_user_meta($user->ID, 'kachi_site', true);
    ?>
    <h3>시설 정의 관리자 정보</h3>
    <table class="form-table">
        <tr>
            <th><label for="kachi_sosok">소속</label></th>
            <td>
                <input type="text" name="kachi_sosok" id="kachi_sosok" value="<?php echo esc_attr($sosok); ?>" class="regular-text" />
                <p class="description">사용자의 소속을 입력하세요.</p>
            </td>
        </tr>
        <tr>
            <th><label for="kachi_site">현장</label></th>
            <td>
                <input type="text" name="kachi_site" id="kachi_site" value="<?php echo esc_attr($site); ?>" class="regular-text" />
                <p class="description">사용자의 현장을 입력하세요.</p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * 사용자 프로필 필드 저장
 */
function facility_save_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    
    if (isset($_POST['kachi_sosok'])) {
        update_user_meta($user_id, 'kachi_sosok', sanitize_text_field($_POST['kachi_sosok']));
    }
    
    if (isset($_POST['kachi_site'])) {
        update_user_meta($user_id, 'kachi_site', sanitize_text_field($_POST['kachi_site']));
    }
}