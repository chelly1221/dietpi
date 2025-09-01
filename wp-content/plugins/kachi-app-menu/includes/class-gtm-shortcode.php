<?php
/**
 * Google Themed Menu - Shortcode Functionality
 * 
 * 프론트엔드에서 쇼트코드를 통해 메뉴 관리 기능을 제공하는 클래스
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class GTM_Shortcode {
    
    private static $instance = null;
    private $core;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->core = GTM_Core::get_instance();
        $this->init_hooks();
    }
    
    /**
     * 쇼트코드 훅 초기화
     */
    private function init_hooks() {
        add_shortcode('gtm_admin', array($this, 'render_admin_shortcode'));
        
        // 쇼트코드가 있는지 먼저 확인
        add_action('wp', array($this, 'check_for_shortcode'));
        
        // AJAX 핸들러
        add_action('wp_ajax_gtm_frontend_save_menu', array($this, 'ajax_frontend_save_menu'));
        add_action('wp_ajax_gtm_frontend_delete_menu', array($this, 'ajax_frontend_delete_menu'));
        add_action('wp_ajax_gtm_frontend_update_order', array($this, 'ajax_frontend_update_order'));
        add_action('wp_ajax_gtm_frontend_save_all', array($this, 'ajax_frontend_save_all'));
        add_action('wp_ajax_gtm_frontend_export_settings', array($this, 'ajax_frontend_export_settings'));
        add_action('wp_ajax_gtm_frontend_import_settings', array($this, 'ajax_frontend_import_settings'));
        add_action('wp_ajax_gtm_frontend_reset_settings', array($this, 'ajax_frontend_reset_settings'));
    }
    
    /**
     * 페이지에 쇼트코드가 있는지 확인
     */
    public function check_for_shortcode() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gtm_admin')) {
            // 쇼트코드가 있으면 스크립트/스타일 로드
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));
        }
    }
    
    /**
     * 스크립트와 스타일 로드
     */
    public function enqueue_scripts_and_styles() {
        if (!is_admin()) {
            // CSS 로드
            wp_enqueue_style('gtm-frontend-admin', GTM_PLUGIN_URL . 'assets/frontend-admin.css', array(), GTM_VERSION);
            
            // jQuery와 jQuery UI 의존성
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-widget');
            wp_enqueue_script('jquery-ui-mouse');
            wp_enqueue_script('jquery-ui-sortable');
            
            // 프론트엔드 관리자 스크립트
            wp_enqueue_script('gtm-frontend-admin', GTM_PLUGIN_URL . 'assets/frontend-admin.js', array('jquery', 'jquery-ui-sortable'), GTM_VERSION, true);
            
            // Localization
            wp_localize_script('gtm-frontend-admin', 'gtm_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gtm_frontend_nonce'),
                'is_admin' => current_user_can('manage_options'),
                'messages' => array(
                    'save_success' => '설정이 저장되었습니다.',
                    'save_error' => '저장 중 오류가 발생했습니다.',
                    'delete_confirm' => '이 메뉴를 삭제하시겠습니까?',
                    'delete_success' => '메뉴가 삭제되었습니다.',
                    'required_fields' => '모든 필수 필드를 입력해주세요.',
                    'permission_error' => '권한이 없습니다.',
                    'reset_confirm' => '모든 설정을 초기화하시겠습니까? 이 작업은 되돌릴 수 없습니다.',
                    'import_confirm' => '현재 설정을 덮어쓰시겠습니까?'
                )
            ));
        }
    }
    
    /**
     * 관리자 쇼트코드 렌더링
     */
    public function render_admin_shortcode($atts) {
        // 권한 확인
        if (!current_user_can('manage_options')) {
            return $this->render_access_denied();
        }
        
        // 데이터 가져오기
        $menu_items = $this->core->get_menu_items();
        $permissions = $this->core->get_permissions();
        $login_settings = $this->core->get_login_settings();
        $wp_roles = wp_roles();
        $roles = $wp_roles->get_names();
        
        // 프론트엔드 관리자 템플릿 로드
        require_once GTM_PLUGIN_DIR . 'includes/class-gtm-frontend-admin-template.php';
        $template = new GTM_Frontend_Admin_Template($this->core);
        
        return $template->render($menu_items, $permissions, $login_settings, $roles);
    }
    
    /**
     * 접근 거부 메시지
     */
    private function render_access_denied() {
        return '
        <div class="gtm-fa-access-denied">
            <span class="gtm-fa-icon">🚫</span>
            <h3>접근 권한이 없습니다</h3>
            <p>이 페이지는 관리자만 접근할 수 있습니다.</p>
        </div>';
    }
    
    /**
     * AJAX: 메뉴 저장
     */
    public function ajax_frontend_save_menu() {
        // Nonce 확인
        if (!check_ajax_referer('gtm_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $menu_id = isset($_POST['menu_id']) ? sanitize_text_field($_POST['menu_id']) : '';
        $menu_data = isset($_POST['menu_data']) ? $_POST['menu_data'] : array();
        
        // 데이터 검증
        if (empty($menu_data['icon']) || empty($menu_data['text']) || empty($menu_data['url'])) {
            wp_send_json_error('필수 필드가 누락되었습니다.');
        }
        
        // 현재 메뉴 가져오기
        $menu_items = $this->core->get_menu_items();
        $permissions = $this->core->get_permissions();
        
        // 메뉴 데이터 정리
        $clean_data = array(
            'id' => $menu_id ?: 'menu_' . time(),
            'icon' => sanitize_text_field($menu_data['icon']),
            'text' => sanitize_text_field($menu_data['text']),
            'url' => esc_url_raw($menu_data['url']),
            'order' => isset($menu_data['order']) ? intval($menu_data['order']) : count($menu_items) + 1,
            'guest_action' => sanitize_text_field($menu_data['guest_action'] ?? 'redirect_login'),
            'no_permission_action' => sanitize_text_field($menu_data['no_permission_action'] ?? 'show_message'),
            'no_permission_message' => sanitize_text_field($menu_data['no_permission_message'] ?? '이 페이지에 접근할 권한이 없습니다.'),
            'no_permission_redirect' => esc_url_raw($menu_data['no_permission_redirect'] ?? '/')
        );
        
        // 권한 데이터 정리
        $clean_permissions = array();
        if (isset($menu_data['permissions']) && is_array($menu_data['permissions'])) {
            $clean_permissions = array_map('sanitize_text_field', $menu_data['permissions']);
            
            // guest가 포함되면 guest만 유지
            if (in_array('guest', $clean_permissions)) {
                $clean_permissions = array('guest');
            }
        } else {
            $clean_permissions = array('all');
        }
        
        // 기존 메뉴 업데이트 또는 새 메뉴 추가
        $menu_found = false;
        if ($menu_id) {
            foreach ($menu_items as &$item) {
                if ($item['id'] === $menu_id) {
                    $item = $clean_data;
                    $menu_found = true;
                    break;
                }
            }
        }
        
        if (!$menu_found) {
            $menu_items[] = $clean_data;
        }
        
        // 권한 설정
        $permissions[$clean_data['id']] = $clean_permissions;
        
        // 저장
        update_option('gtm_menu_items', $menu_items);
        update_option('gtm_menu_permissions', $permissions);
        
        // 코어 인스턴스 업데이트
        $this->core->save_menu_items($menu_items);
        $this->core->save_permissions($permissions);
        
        wp_send_json_success(array(
            'message' => $menu_id ? '메뉴가 수정되었습니다.' : '새 메뉴가 추가되었습니다.',
            'menu_id' => $clean_data['id'],
            'menu_data' => $clean_data,
            'permissions' => $clean_permissions
        ));
    }
    
    /**
     * AJAX: 메뉴 삭제
     */
    public function ajax_frontend_delete_menu() {
        // Nonce 확인
        if (!check_ajax_referer('gtm_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $menu_id = isset($_POST['menu_id']) ? sanitize_text_field($_POST['menu_id']) : '';
        
        if (!$menu_id) {
            wp_send_json_error('메뉴 ID가 없습니다.');
        }
        
        // 현재 메뉴 가져오기
        $menu_items = $this->core->get_menu_items();
        $permissions = $this->core->get_permissions();
        
        // 메뉴 제거
        $menu_items = array_filter($menu_items, function($item) use ($menu_id) {
            return $item['id'] !== $menu_id;
        });
        
        // 권한 제거
        unset($permissions[$menu_id]);
        
        // 인덱스 재정렬
        $menu_items = array_values($menu_items);
        
        // 저장
        update_option('gtm_menu_items', $menu_items);
        update_option('gtm_menu_permissions', $permissions);
        
        // 코어 인스턴스 업데이트
        $this->core->save_menu_items($menu_items);
        $this->core->save_permissions($permissions);
        
        wp_send_json_success('메뉴가 삭제되었습니다.');
    }
    
    /**
     * AJAX: 메뉴 순서 업데이트
     */
    public function ajax_frontend_update_order() {
        // Nonce 확인
        if (!check_ajax_referer('gtm_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $order = isset($_POST['order']) ? $_POST['order'] : array();
        
        if (!is_array($order)) {
            wp_send_json_error('잘못된 데이터 형식입니다.');
        }
        
        // 현재 메뉴 가져오기
        $menu_items = $this->core->get_menu_items();
        
        // 새로운 순서로 정렬
        $sorted_items = array();
        foreach ($order as $index => $menu_id) {
            $menu_id = sanitize_text_field($menu_id);
            foreach ($menu_items as $item) {
                if ($item['id'] === $menu_id) {
                    $item['order'] = $index + 1;
                    $sorted_items[] = $item;
                    break;
                }
            }
        }
        
        // 저장
        update_option('gtm_menu_items', $sorted_items);
        
        // 코어 인스턴스 업데이트
        $this->core->save_menu_items($sorted_items);
        
        wp_send_json_success('메뉴 순서가 업데이트되었습니다.');
    }
    
    /**
     * AJAX: 모든 설정 저장
     */
    public function ajax_frontend_save_all() {
        // Nonce 확인
        if (!check_ajax_referer('gtm_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $menu_items = isset($_POST['menu_items']) ? $_POST['menu_items'] : array();
        $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : array();
        $white_mode_pages = isset($_POST['white_mode_pages']) ? $_POST['white_mode_pages'] : array();
        $login_settings = isset($_POST['login_settings']) ? $_POST['login_settings'] : array();
        
        // 데이터 검증 및 정리
        if (!is_array($menu_items)) $menu_items = array();
        if (!is_array($permissions)) $permissions = array();
        if (!is_array($white_mode_pages)) $white_mode_pages = array();
        if (!is_array($login_settings)) $login_settings = array();
        
        // 메뉴 항목 데이터 정리
        $cleaned_menu_items = array();
        foreach ($menu_items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $cleaned_item = array(
                    'id' => sanitize_text_field($item['id']),
                    'icon' => wp_kses_post($item['icon']),
                    'text' => sanitize_text_field($item['text']),
                    'url' => esc_url_raw($item['url']),
                    'order' => intval($item['order']),
                    'guest_action' => sanitize_text_field($item['guest_action']),
                    'no_permission_action' => sanitize_text_field($item['no_permission_action']),
                    'no_permission_message' => sanitize_text_field($item['no_permission_message']),
                    'no_permission_redirect' => esc_url_raw($item['no_permission_redirect'])
                );
                $cleaned_menu_items[] = $cleaned_item;
            }
        }
        
        // 권한 데이터 정리 및 검증
        $cleaned_permissions = array();
        foreach ($permissions as $menu_id => $perms) {
            $menu_id = sanitize_text_field($menu_id);
            if (is_array($perms) && !empty($perms)) {
                $cleaned_perms = array_map('sanitize_text_field', $perms);
                
                // guest가 포함되어 있으면 guest만 남기기
                if (in_array('guest', $cleaned_perms)) {
                    $cleaned_permissions[$menu_id] = array('guest');
                } else {
                    $cleaned_permissions[$menu_id] = $cleaned_perms;
                }
            }
        }
        
        // 화이트모드 페이지 정리
        $cleaned_white_mode_pages = array_map('sanitize_text_field', $white_mode_pages);
        
        // 로그인 설정 정리
        $cleaned_login_settings = array(
            'login_page_id' => sanitize_text_field($login_settings['login_page_id'] ?? '559'),
            'logout_redirect' => esc_url_raw($login_settings['logout_redirect'] ?? '/'),
            'login_redirect' => sanitize_text_field($login_settings['login_redirect'] ?? 'previous_page'),
            'custom_login_redirect' => esc_url_raw($login_settings['custom_login_redirect'] ?? '')
        );
        
        // 설정 저장
        $menu_updated = update_option('gtm_menu_items', $cleaned_menu_items);
        $permissions_updated = update_option('gtm_menu_permissions', $cleaned_permissions);
        $white_mode_updated = update_option('gtm_white_mode_pages', $cleaned_white_mode_pages);
        $login_updated = update_option('gtm_login_settings', $cleaned_login_settings);
        
        // 인스턴스 업데이트
        $this->core->save_menu_items($cleaned_menu_items);
        $this->core->save_permissions($cleaned_permissions);
        
        wp_send_json_success(array(
            'message' => '모든 설정이 저장되었습니다.',
            'menu_updated' => $menu_updated,
            'permissions_updated' => $permissions_updated,
            'white_mode_updated' => $white_mode_updated,
            'login_updated' => $login_updated
        ));
    }
    
    /**
     * AJAX: 설정 내보내기
     */
    public function ajax_frontend_export_settings() {
        // Nonce 확인
        if (!check_ajax_referer('gtm_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $export_data = array(
            'version' => GTM_VERSION,
            'menu_items' => $this->core->get_menu_items(),
            'permissions' => $this->core->get_permissions(),
            'access_settings' => $this->core->get_access_settings(),
            'login_settings' => $this->core->get_login_settings(),
            'white_mode_pages' => get_option('gtm_white_mode_pages', array())
        );
        
        wp_send_json_success($export_data);
    }
    
    /**
     * AJAX: 설정 가져오기
     */
    public function ajax_frontend_import_settings() {
        // Nonce 확인
        if (!check_ajax_referer('gtm_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        if (!is_array($settings)) {
            wp_send_json_error('잘못된 데이터 형식입니다.');
        }
        
        // 설정 가져오기
        if (isset($settings['menu_items'])) {
            update_option('gtm_menu_items', $settings['menu_items']);
        }
        if (isset($settings['permissions'])) {
            update_option('gtm_menu_permissions', $settings['permissions']);
        }
        if (isset($settings['access_settings'])) {
            update_option('gtm_access_settings', $settings['access_settings']);
        }
        if (isset($settings['login_settings'])) {
            update_option('gtm_login_settings', $settings['login_settings']);
        }
        if (isset($settings['white_mode_pages'])) {
            update_option('gtm_white_mode_pages', $settings['white_mode_pages']);
        }
        
        wp_send_json_success('설정을 가져왔습니다.');
    }
    
    /**
     * AJAX: 설정 초기화
     */
    public function ajax_frontend_reset_settings() {
        // Nonce 확인
        if (!check_ajax_referer('gtm_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        // 설정 초기화
        update_option('gtm_menu_items', GTM_Core::get_default_menu_items());
        
        $default_permissions = array(
            'chatbot' => array('guest'),
            'documents' => array('all'),
            'facility' => array('all'),
            'notice' => array('guest'),
            'approve' => array('all'),
            'admin' => array('administrator')
        );
        update_option('gtm_menu_permissions', $default_permissions);
        
        update_option('gtm_access_settings', GTM_Core::get_default_access_settings());
        update_option('gtm_login_settings', GTM_Core::get_default_login_settings());
        update_option('gtm_white_mode_pages', array());
        
        wp_send_json_success('설정이 초기화되었습니다.');
    }
}