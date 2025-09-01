<?php
/**
 * Google Themed Menu - Core Functionality
 * 
 * 플러그인의 핵심 기능을 담당하는 클래스
 * 데이터 관리, 권한 확인, AJAX 처리 등
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class GTM_Core {
    
    private static $instance = null;
    protected $menu_items = array();
    protected $permissions = array();
    protected $access_settings = array();
    protected $login_settings = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_options();
        $this->init_hooks();
    }
    
    /**
     * 플러그인 활성화
     */
    public static function activate() {
        // 승인자 역할 생성
        if (!get_role('approver')) {
            add_role('approver', '승인자', array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
            ));
        }
        
        // 기본 옵션 설정 - 기존 옵션이 없을 때만 설정
        if (get_option('gtm_menu_items') === false) {
            update_option('gtm_menu_items', self::get_default_menu_items());
        }
        
        if (get_option('gtm_menu_permissions') === false) {
            $default_permissions = array();
            foreach (self::get_default_menu_items() as $item) {
                // 기본값 설정: 챗봇과 공지사항은 모든 사용자(guest), 나머지는 로그인 사용자(all)
                if ($item['id'] === 'chatbot' || $item['id'] === 'notice') {
                    $default_permissions[$item['id']] = array('guest');
                } else {
                    $default_permissions[$item['id']] = array('all');
                }
            }
            update_option('gtm_menu_permissions', $default_permissions);
        }
        
        if (get_option('gtm_access_settings') === false) {
            update_option('gtm_access_settings', self::get_default_access_settings());
        }
        
        if (get_option('gtm_login_settings') === false) {
            update_option('gtm_login_settings', self::get_default_login_settings());
        }
        
        // 플러그인 버전 저장
        update_option('gtm_version', GTM_VERSION);
    }
    
    /**
     * 플러그인 비활성화
     */
    public static function deactivate() {
        // 필요한 정리 작업
    }
    
    /**
     * 옵션 로드
     */
    protected function load_options() {
        // 옵션을 불러올 때 기본값과 병합하지 않고 저장된 값만 사용
        $this->menu_items = get_option('gtm_menu_items', array());
        $this->permissions = get_option('gtm_menu_permissions', array());
        $this->access_settings = get_option('gtm_access_settings', array());
        $this->login_settings = get_option('gtm_login_settings', array());
        
        // 첫 설치 시에만 기본값 설정
        if (empty($this->menu_items)) {
            $this->menu_items = $this->get_default_menu_items();
            update_option('gtm_menu_items', $this->menu_items);
        }
        
        if (empty($this->permissions)) {
            $default_permissions = array();
            foreach ($this->menu_items as $item) {
                if ($item['id'] === 'chatbot' || $item['id'] === 'notice') {
                    $default_permissions[$item['id']] = array('guest');
                } else {
                    $default_permissions[$item['id']] = array('all');
                }
            }
            $this->permissions = $default_permissions;
            update_option('gtm_menu_permissions', $this->permissions);
        }
        
        if (empty($this->access_settings)) {
            $this->access_settings = $this->get_default_access_settings();
            update_option('gtm_access_settings', $this->access_settings);
        }
        
        if (empty($this->login_settings)) {
            $this->login_settings = $this->get_default_login_settings();
            update_option('gtm_login_settings', $this->login_settings);
        }
        
        // 권한 데이터 검증 (수정하지 않고 검증만)
        $this->validate_permissions(false);
    }
    
    /**
     * 권한 데이터 검증
     */
    protected function validate_permissions($save = true) {
        $updated = false;
        
        foreach ($this->permissions as $menu_id => &$perms) {
            // 배열이 아닌 경우 배열로 변환
            if (!is_array($perms)) {
                $perms = array($perms);
                $updated = true;
            }
            
            // guest가 있으면 guest만 남기기 (중복 제거)
            if (in_array('guest', $perms) && count($perms) > 1) {
                $perms = array('guest');
                $updated = true;
            }
        }
        
        if ($updated && $save) {
            update_option('gtm_menu_permissions', $this->permissions);
        }
    }
    
    /**
     * 훅 초기화
     */
    protected function init_hooks() {
        add_action('init', array($this, 'init'));
        
        // 승인자 역할 관련
        add_action('after_setup_theme', array($this, 'hide_admin_bar_for_approver'));
        
        // 로그아웃 처리
        add_action('init', array($this, 'handle_logout'));
        add_action('wp_logout', array($this, 'redirect_after_logout'));
        
        // 로그인 리다이렉트
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
        
        // AJAX 처리
        add_action('wp_ajax_gtm_get_menu_html', array($this, 'ajax_get_menu_html'));
        add_action('wp_ajax_nopriv_gtm_get_menu_html', array($this, 'ajax_get_menu_html'));
    }
    
    /**
     * 초기화
     */
    public function init() {
        // 승인자 역할 확인 및 생성
        if (!get_role('approver')) {
            add_role('approver', '승인자', array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
            ));
        }
    }
    
    /**
     * 기본 메뉴 항목
     */
    public static function get_default_menu_items() {
        return array(
            array(
                'id' => 'chatbot',
                'icon' => '🔍',
                'text' => '챗봇',
                'url' => '/',
                'order' => 1,
                'guest_action' => 'allow',
                'no_permission_action' => 'show_message',
                'no_permission_message' => '이 페이지에 접근할 권한이 없습니다.',
                'no_permission_redirect' => '/'
            ),
            array(
                'id' => 'documents',
                'icon' => '📁',
                'text' => '문서관리',
                'url' => '/?page_id=96',
                'order' => 2,
                'guest_action' => 'redirect_login',
                'no_permission_action' => 'show_message',
                'no_permission_message' => '이 페이지에 접근할 권한이 없습니다.',
                'no_permission_redirect' => '/'
            ),
            array(
                'id' => 'facility',
                'icon' => '⚙️',
                'text' => '시설정의',
                'url' => '/?page_id=439',
                'order' => 3,
                'guest_action' => 'redirect_login',
                'no_permission_action' => 'show_message',
                'no_permission_message' => '이 페이지에 접근할 권한이 없습니다.',
                'no_permission_redirect' => '/'
            ),
            array(
                'id' => 'notice',
                'icon' => '📝',
                'text' => '공지사항',
                'url' => '/?page_id=292',
                'order' => 4,
                'guest_action' => 'allow',
                'no_permission_action' => 'show_message',
                'no_permission_message' => '이 페이지에 접근할 권한이 없습니다.',
                'no_permission_redirect' => '/'
            ),
            array(
                'id' => 'approve',
                'icon' => '✅',
                'text' => '계정승인',
                'url' => '/?page_id=578',
                'order' => 5,
                'guest_action' => 'redirect_login',
                'no_permission_action' => 'redirect_home',
                'no_permission_message' => '이 페이지에 접근할 권한이 없습니다.',
                'no_permission_redirect' => '/'
            ),
            array(
                'id' => 'admin',
                'icon' => '⚙️',
                'text' => '메뉴관리',
                'url' => '/?page_id=999', // 메뉴 관리 페이지 ID로 변경 필요
                'order' => 6,
                'guest_action' => 'redirect_login',
                'no_permission_action' => 'redirect_home',
                'no_permission_message' => '이 페이지에 접근할 권한이 없습니다.',
                'no_permission_redirect' => '/'
            )
        );
    }
    
    /**
     * 기본 접근 설정
     */
    public static function get_default_access_settings() {
        return array(
            'guest_action' => 'redirect_login',
            'no_permission_action' => 'show_message',
            'no_permission_message' => '이 페이지에 접근할 권한이 없습니다.',
            'no_permission_redirect' => '/'
        );
    }
    
    /**
     * 기본 로그인 설정
     */
    public static function get_default_login_settings() {
        return array(
            'login_page_id' => '559',
            'logout_redirect' => '/',
            'login_redirect' => 'previous_page',
            'custom_login_redirect' => ''
        );
    }
    
    /**
     * 사용자가 메뉴를 볼 수 있는지 확인
     */
    public function user_can_see_menu($menu_id) {
        // 권한 설정이 없으면 로그인 사용자에게만 허용 (보안상 안전한 기본값)
        if (!isset($this->permissions[$menu_id]) || empty($this->permissions[$menu_id])) {
            error_log('GTM Debug - No permissions set for menu: ' . $menu_id . ', defaulting to logged in users only');
            return is_user_logged_in();
        }
        
        $allowed_roles = $this->permissions[$menu_id];
        if (!is_array($allowed_roles)) {
            error_log('GTM Debug - Invalid permissions format for menu: ' . $menu_id);
            return is_user_logged_in();
        }
        
        error_log('GTM Debug - Checking permissions for menu: ' . $menu_id . ', allowed roles: ' . print_r($allowed_roles, true));
        
        // 'guest'가 포함되어 있으면 모든 사용자(로그인/비로그인) 허용
        if (in_array('guest', $allowed_roles)) {
            error_log('GTM Debug - Menu allows all users (guest permission)');
            return true;
        }
        
        // 로그인한 사용자인 경우
        if (is_user_logged_in()) {
            // 'all'이 포함되어 있으면 로그인한 모든 사용자 허용
            if (in_array('all', $allowed_roles)) {
                error_log('GTM Debug - Menu allows all logged in users');
                return true;
            }
            
            // 특정 역할 체크
            $user = wp_get_current_user();
            if ($user && isset($user->roles) && is_array($user->roles)) {
                foreach ($user->roles as $role) {
                    if (in_array($role, $allowed_roles)) {
                        error_log('GTM Debug - User role ' . $role . ' is allowed');
                        return true;
                    }
                }
                error_log('GTM Debug - User roles (' . implode(', ', $user->roles) . ') not in allowed list');
            }
        } else {
            error_log('GTM Debug - Guest user and no guest permission');
        }
        
        return false;
    }
    
    /**
     * 로그인 URL 가져오기
     */
    public function get_login_url() {
        $login_page_id = isset($this->login_settings['login_page_id']) ? $this->login_settings['login_page_id'] : '559';
        return get_permalink($login_page_id) ?: '/?page_id=' . $login_page_id;
    }
    
    /**
     * 로그아웃 URL 가져오기
     */
    public function get_logout_url() {
        return add_query_arg('do_custom_logout', '1', home_url('/'));
    }
    
    /**
     * 로그아웃 처리
     */
    public function handle_logout() {
        if (isset($_GET['do_custom_logout']) && $_GET['do_custom_logout'] == '1') {
            wp_logout();
            $redirect = isset($this->login_settings['logout_redirect']) ? $this->login_settings['logout_redirect'] : '/';
            wp_redirect(home_url($redirect));
            exit;
        }
    }
    
    /**
     * 로그아웃 후 리다이렉트
     */
    public function redirect_after_logout() {
        $redirect = isset($this->login_settings['logout_redirect']) ? $this->login_settings['logout_redirect'] : '/';
        wp_redirect(home_url($redirect));
        exit;
    }
    
    /**
     * 로그인 리다이렉트 커스터마이징
     */
    public function custom_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            $redirect_type = isset($this->login_settings['login_redirect']) ? $this->login_settings['login_redirect'] : 'previous_page';
            
            if ($redirect_type === 'previous_page' && !empty($request)) {
                return $request;
            } elseif ($redirect_type === 'custom' && !empty($this->login_settings['custom_login_redirect'])) {
                return home_url($this->login_settings['custom_login_redirect']);
            }
        }
        
        return $redirect_to;
    }
    
    /**
     * 승인자 관리 바 숨기기
     */
    public function hide_admin_bar_for_approver() {
        if (current_user_can('approver') && !current_user_can('manage_options')) {
            show_admin_bar(false);
        }
    }
    
    /**
     * AJAX: 메뉴 HTML 가져오기
     */
    public function ajax_get_menu_html() {
        $frontend = GTM_Frontend::get_instance();
        echo $frontend->get_menu_items_html();
        wp_die();
    }
    
    /**
     * 메뉴 항목 저장 (내부 사용)
     */
    public function save_menu_items($menu_items) {
        update_option('gtm_menu_items', $menu_items);
        $this->menu_items = $menu_items;
    }
    
    /**
     * 권한 저장 (내부 사용)
     */
    public function save_permissions($permissions) {
        update_option('gtm_menu_permissions', $permissions);
        $this->permissions = $permissions;
    }
    
    // Getter 메서드들
    public function get_menu_items() {
        return $this->menu_items;
    }
    
    public function get_permissions() {
        return $this->permissions;
    }
    
    public function get_access_settings() {
        return $this->access_settings;
    }
    
    public function get_login_settings() {
        return $this->login_settings;
    }
}