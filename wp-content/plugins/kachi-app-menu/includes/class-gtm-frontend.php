<?php
/**
 * Google Themed Menu - Frontend Functionality
 * 
 * 프론트엔드 메뉴 표시 및 접근 제어를 담당하는 클래스
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class GTM_Frontend {
    
    private static $instance = null;
    private $menu_rendered = false;
    private $core;
    private $access_checked = false; // 중복 체크 방지를 위한 플래그 추가
    
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
     * 프론트엔드 훅 초기화
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // 프론트엔드에 메뉴 자동 삽입
        add_action('wp_body_open', array($this, 'render_menu'));
        // wp_body_open이 없는 테마를 위한 대체
        add_action('wp_footer', array($this, 'render_menu_fallback'));
        
        // 접근 제어 - wp 액션으로 통합하여 한 번만 실행
        add_action('wp', array($this, 'check_page_access'), 1);
    }
    
    /**
     * 프론트엔드 스크립트/스타일 추가
     */
    public function enqueue_scripts() {
        if (!is_admin()) {
            // 파일 수정 시간을 버전으로 사용하여 캐시 자동 갱신
            $menu_js_path = GTM_PLUGIN_DIR . 'assets/menu.js';
            $menu_js_version = file_exists($menu_js_path) ? filemtime($menu_js_path) : GTM_VERSION;
            
            wp_enqueue_script('gtm-menu-script', GTM_PLUGIN_URL . 'assets/menu.js', array('jquery'), $menu_js_version, true);
            
            // CSS를 인라인으로 추가
            wp_add_inline_style('wp-block-library', $this->get_menu_styles());
            
            // 로그인 상태 및 사용자 정보 전달
            $localize_data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'is_user_logged_in' => is_user_logged_in(),
                'user_roles' => array(),
                'menu_permissions' => $this->core->get_permissions(),
                'is_front_page' => is_front_page(),
                'nonce' => wp_create_nonce('gtm_nonce'),
                'white_mode_pages' => get_option('gtm_white_mode_pages', array()),
                'current_page_id' => get_queried_object_id(),
                'login_settings' => $this->core->get_login_settings()
            );
            
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $localize_data['user_roles'] = $user->roles;
            }
            
            wp_localize_script('gtm-menu-script', 'gtm_data', $localize_data);
        }
    }
    
    /**
     * 메뉴 렌더링
     */
    public function render_menu() {
        if (is_admin() || $this->menu_rendered) {
            return;
        }
        
        // 메뉴가 렌더링되었음을 표시
        $this->menu_rendered = true;
        
        ?>
        <div class="gtm-menu-container">
            <div class="gtm-menu-wrapper" id="gtmMenuWrapper">
                <div class="gtm-menu-header">
                    <div class="gtm-menu-trigger" onclick="toggleAppMenu()">
                        <div class="gtm-icon-wrapper">
                            <svg class="gtm-plane-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z" fill="currentColor"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="gtm-auth-button">
                        <?php if (is_user_logged_in()) : ?>
                            <a href="<?php echo esc_url($this->core->get_logout_url()); ?>" class="gtm-auth-link" id="logoutBtn" title="로그아웃">
                                <span class="gtm-auth-text">로그아웃</span>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url($this->core->get_login_url()); ?>" class="gtm-auth-link" id="loginBtn" title="로그인">
                                <span class="gtm-auth-text">로그인</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="gtm-menu-expanded">
                    <div class="gtm-menu-items">
                        <?php echo $this->get_menu_items_html(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 메뉴 렌더링 대체 (wp_body_open이 없는 경우)
     */
    public function render_menu_fallback() {
        if (!$this->menu_rendered && !is_admin()) {
            $this->render_menu();
        }
    }
    
    /**
     * 메뉴 아이템 HTML 생성
     */
    public function get_menu_items_html() {
        $html = '';
        $menu_items = $this->core->get_menu_items();
        
        // 메뉴 항목 배열 확인
        if (!is_array($menu_items) || empty($menu_items)) {
            $menu_items = GTM_Core::get_default_menu_items();
        }
        
        // 메뉴 항목을 순서대로 정렬
        usort($menu_items, function($a, $b) {
            $order_a = isset($a['order']) ? intval($a['order']) : 999;
            $order_b = isset($b['order']) ? intval($b['order']) : 999;
            return $order_a - $order_b;
        });
        
        foreach ($menu_items as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            
            if ($this->core->user_can_see_menu($item['id'])) {
                $html .= sprintf(
                    '<a href="%s" class="gtm-menu-item" data-menu-id="%s">
                        <span class="gtm-item-icon">%s</span>
                        <span class="gtm-item-text">%s</span>
                        <span class="gtm-item-arrow">→</span>
                    </a>',
                    esc_url($item['url']),
                    esc_attr($item['id']),
                    esc_html($item['icon']),
                    esc_html($item['text'])
                );
            }
        }
        
        return $html;
    }
    
    /**
     * 페이지 접근 확인
     */
    public function check_page_access() {
        // 이미 체크했으면 재실행 방지
        if ($this->access_checked) {
            return;
        }
        
        // 관리자 페이지는 제외
        if (is_admin()) {
            return;
        }
        
        // AJAX 요청은 제외
        if (wp_doing_ajax()) {
            return;
        }
        
        // 404 페이지는 제외
        if (is_404()) {
            return;
        }
        
        // 체크 완료 플래그 설정
        $this->access_checked = true;
        
        $current_page_id = get_queried_object_id();
        $current_url = $_SERVER['REQUEST_URI'];
        $menu_items = $this->core->get_menu_items();
        
        // 로그인 페이지는 접근 제어에서 제외
        $login_settings = $this->core->get_login_settings();
        $login_page_id = isset($login_settings['login_page_id']) ? $login_settings['login_page_id'] : '559';
        if ($current_page_id == $login_page_id) {
            error_log('GTM Debug - Skip access check for login page');
            return;
        }
        
        // 디버그 로그
        error_log('GTM Debug - === PAGE ACCESS CHECK START ===');
        error_log('GTM Debug - Current Page ID: ' . $current_page_id);
        error_log('GTM Debug - Current URL: ' . $current_url);
        error_log('GTM Debug - Is User Logged In: ' . (is_user_logged_in() ? 'Yes' : 'No'));
        
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            error_log('GTM Debug - User roles: ' . implode(', ', $user->roles));
        }
        
        $page_matched = false;
        $access_granted = false;
        
        // 각 메뉴 항목의 페이지 확인
        foreach ($menu_items as $item) {
            if (!isset($item['url'])) continue;
            
            $match = $this->is_current_page_match($item['url'], $current_url, $current_page_id);
            
            if ($match) {
                $page_matched = true;
                error_log('GTM Debug - >>> Found matching menu: ' . $item['id']);
                
                // 권한 확인
                $can_see = $this->core->user_can_see_menu($item['id']);
                error_log('GTM Debug - Can see menu: ' . ($can_see ? 'Yes' : 'No'));
                
                if ($can_see) {
                    error_log('GTM Debug - >>> ACCESS GRANTED - User has permission');
                    $access_granted = true;
                    break; // 권한이 있으면 즉시 종료
                } else {
                    // 권한이 없는 경우 처리
                    $this->handle_no_access($item);
                    return; // 처리 후 종료
                }
            }
        }
        
        // 매칭되는 메뉴가 없는 페이지는 기본적으로 접근 허용
        if (!$page_matched) {
            error_log('GTM Debug - No matching menu found for this page - ACCESS GRANTED');
            $access_granted = true;
        }
        
        error_log('GTM Debug - === PAGE ACCESS CHECK END ===');
    }
    
    /**
     * 현재 페이지가 메뉴 URL과 일치하는지 확인
     */
    private function is_current_page_match($menu_url, $current_url, $current_page_id) {
        // URL 파싱
        $menu_parsed = parse_url($menu_url);
        $current_parsed = parse_url($current_url);
        
        $menu_path = isset($menu_parsed['path']) ? $menu_parsed['path'] : '/';
        $menu_query = isset($menu_parsed['query']) ? $menu_parsed['query'] : '';
        $current_path = isset($current_parsed['path']) ? $current_parsed['path'] : '/';
        $current_query = isset($current_parsed['query']) ? $current_parsed['query'] : '';
        
        // 홈페이지 확인
        if ($menu_url === '/' && is_front_page()) {
            return true;
        }
        
        // 경로가 같은지 확인
        if ($menu_path === $current_path) {
            // 쿼리 파라미터 확인
            if ($menu_query && $current_query) {
                parse_str($menu_query, $menu_params);
                parse_str($current_query, $current_params);
                
                // page_id가 같은지 확인
                if (isset($menu_params['page_id']) && isset($current_params['page_id']) 
                    && $menu_params['page_id'] == $current_params['page_id']) {
                    return true;
                }
            } elseif (!$menu_query && !$current_query) {
                // 둘 다 쿼리가 없는 경우
                return true;
            }
        }
        
        // page_id로 추가 확인
        if (preg_match('/page_id=(\d+)/', $menu_url, $matches)) {
            $menu_page_id = $matches[1];
            if ($current_page_id == $menu_page_id) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 접근 권한이 없을 때 처리
     */
    private function handle_no_access($item) {
        // 비로그인 사용자 처리
        if (!is_user_logged_in()) {
            $guest_action = isset($item['guest_action']) ? $item['guest_action'] : 'redirect_login';
            error_log('GTM Debug - Guest action: ' . $guest_action);
            
            if ($guest_action === 'allow') {
                error_log('GTM Debug - >>> ACCESS GRANTED - Guest action is allow');
                return; // 접근 허용
            } elseif ($guest_action === 'redirect_login') {
                error_log('GTM Debug - >>> ACCESS DENIED - Redirecting to login');
                $login_url = $this->core->get_login_url();
                $redirect_url = add_query_arg('redirect_to', urlencode($_SERVER['REQUEST_URI']), $login_url);
                wp_redirect($redirect_url);
                exit;
            } else {
                error_log('GTM Debug - >>> ACCESS DENIED - Showing message');
                $message = isset($item['no_permission_message']) ? $item['no_permission_message'] : '이 페이지에 접근할 권한이 없습니다.';
                $this->show_access_denied_message($message);
                exit;
            }
        } 
        // 로그인 사용자 처리
        else {
            $action = isset($item['no_permission_action']) ? $item['no_permission_action'] : 'show_message';
            error_log('GTM Debug - No permission action for logged in user: ' . $action);
            
            if ($action === 'redirect_home') {
                error_log('GTM Debug - >>> ACCESS DENIED - Redirecting to home');
                $redirect = isset($item['no_permission_redirect']) ? $item['no_permission_redirect'] : '/';
                wp_redirect(home_url($redirect));
                exit;
            } else {
                error_log('GTM Debug - >>> ACCESS DENIED - Showing message');
                $message = isset($item['no_permission_message']) ? $item['no_permission_message'] : '이 페이지에 접근할 권한이 없습니다.';
                $this->show_access_denied_message($message);
                exit;
            }
        }
    }
    
    /**
     * 접근 거부 메시지 표시
     */
    private function show_access_denied_message($message) {
        wp_die('
            <div class="gtm-access-denied">
                <h2>접근 거부</h2>
                <p>' . esc_html($message) . '</p>
                <a href="' . esc_url(home_url('/')) . '">홈으로 돌아가기</a>
            </div>
        ', '접근 거부', array('response' => 403));
    }
    
    /**
     * 메뉴 스타일 반환
     */
    private function get_menu_styles() {
        return '
            /* 다크 테마 변수 */
            :root {
                --gtm-bg-primary: #1a1a1a;
                --gtm-bg-secondary: #242424;
                --gtm-bg-hover: #2a2a2a;
                --gtm-text-primary: #ffffff;
                --gtm-text-secondary: #a0a0a0;
                --gtm-accent: #a70638;
                --gtm-border: rgba(255, 255, 255, 0.1);
                --gtm-shadow: rgba(0, 0, 0, 0.5);
            }
            
            /* 화이트 테마 변수 */
            .gtm-white-theme {
                --gtm-bg-primary: #ffffff;
                --gtm-bg-secondary: #f8f9fa;
                --gtm-bg-hover: #e9ecef;
                --gtm-text-primary: #212529;
                --gtm-text-secondary: #6c757d;
                --gtm-accent: #a70638;
                --gtm-border: rgba(0, 0, 0, 0.1);
                --gtm-shadow: rgba(0, 0, 0, 0.1);
            }
            
            /* 스크롤바 숨기기 */
            body {
                overflow: auto !important;
                -ms-overflow-style: none !important;
            }
            
            body::-webkit-scrollbar {
                display: none !important;
                background-color: transparent !important;
            }
            
            /* 메뉴 컨테이너 */
            .gtm-menu-container {
                position: fixed !important;
                right: 10px !important;
                top: 10px !important;
                z-index: 99999 !important;
            }
            
            /* 메뉴 래퍼 - 확장 애니메이션의 핵심 */
            .gtm-menu-wrapper {
                position: relative !important;
                width: 56px !important;
                height: 56px !important;
                background: linear-gradient(135deg, var(--gtm-bg-hover) 0%, var(--gtm-bg-primary) 100%) !important;
                border-radius: 28px !important;
                box-shadow: 0 4px 20px var(--gtm-shadow) !important;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
                overflow: hidden !important;
            }
            
            /* 화이트 테마 메뉴 래퍼 */
            .gtm-white-theme .gtm-menu-wrapper {
                background: linear-gradient(135deg, var(--gtm-bg-secondary) 0%, var(--gtm-bg-primary) 100%) !important;
                border: 1px solid var(--gtm-border) !important;
            }
            
            /* 메뉴 열렸을 때 래퍼 확장 */
            .gtm-menu-wrapper.expanded {
                width: 200px !important;
                height: auto !important;
                max-height: 500px !important;
                border-radius: 20px !important;
                box-shadow: 0 10px 40px var(--gtm-shadow) !important;
                background: linear-gradient(135deg, var(--gtm-bg-primary) 0%, var(--gtm-bg-secondary) 100%) !important;
            }
            
            /* 메뉴 헤더 (비행기 아이콘과 로그인/아웃 버튼 포함) */
            .gtm-menu-header {
                position: relative !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                height: 56px !important;
                padding: 0 !important;
            }
            
            /* 트리거 버튼 */
            .gtm-menu-trigger {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 56px !important;
                height: 56px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                z-index: 2 !important;
                transition: transform 0.3s ease !important;
            }
            
            .gtm-menu-wrapper.expanded .gtm-menu-trigger {
                transform: scale(0.9) !important;
            }
            
            /* 아이콘 래퍼 */
            .gtm-icon-wrapper {
                width: 40px !important;
                height: 40px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: rgba(255, 255, 255, 0.1) !important;
                backdrop-filter: blur(10px) !important;
                border-radius: 50% !important;
                transition: all 0.3s ease !important;
                position: relative !important;
            }
            
            /* 화이트 테마 아이콘 래퍼 */
            .gtm-white-theme .gtm-icon-wrapper {
                background: rgba(0, 0, 0, 0.05) !important;
            }
            
            .gtm-icon-wrapper::before {
                content: "" !important;
                position: absolute !important;
                inset: 0px !important;
                background: linear-gradient(45deg, var(--gtm-accent), transparent) !important;
                border-radius: 50% !important;
                opacity: 0 !important;
                transition: opacity 0.3s ease !important;
            }
            
            .gtm-menu-trigger:hover .gtm-icon-wrapper::before {
                opacity: 1 !important;
            }
            
            .gtm-menu-trigger:hover .gtm-icon-wrapper {
                background: rgba(255, 255, 255, 0.15) !important;
            }
            
            .gtm-white-theme .gtm-menu-trigger:hover .gtm-icon-wrapper {
                background: var(--gtm-accent) !important;
            }
            
            /* 비행기 아이콘 */
            .gtm-plane-icon {
                width: 28px !important;
                height: 28px !important;
                z-index: 1 !important;
                position: relative !important;
                transform: rotate(-90deg) !important;
                transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            
            .gtm-menu-wrapper.expanded .gtm-plane-icon {
                transform: rotate(90deg) !important;
            }
            
            .gtm-plane-icon path {
                fill: var(--gtm-text-primary) !important;
                transition: fill 0.3s ease !important;
            }
            
            /* 화이트 테마에서 호버 시 아이콘 색상 변경 */
            .gtm-white-theme .gtm-menu-trigger:hover .gtm-plane-icon path {
                fill: white !important;
            }
            
            /* 인증 버튼 (로그인/로그아웃) */
            .gtm-auth-button {
                position: absolute !important;
                right: 10px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                opacity: 0 !important;
                transition: opacity 0.3s ease 0.2s !important;
                pointer-events: none !important;
            }
            
            .gtm-menu-wrapper.expanded .gtm-auth-button {
                opacity: 1 !important;
                pointer-events: all !important;
            }
            
            .gtm-auth-link {
                display: inline-block !important;
                padding: 6px 12px !important;
                background: rgba(255, 255, 255, 0.1) !important;
                border-radius: 16px !important;
                text-decoration: none !important;
                color: var(--gtm-text-primary) !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                transition: all 0.2s ease !important;
                white-space: nowrap !important;
            }
            
            .gtm-white-theme .gtm-auth-link {
                background: rgba(0, 0, 0, 0.05) !important;
            }
            
            .gtm-auth-link:hover {
                background: rgba(255, 255, 255, 0.2) !important;
                transform: scale(1.05) !important;
                color: var(--gtm-text-primary) !important;
            }
            
            .gtm-white-theme .gtm-auth-link:hover {
                background: rgba(0, 0, 0, 0.1) !important;
            }
            
            .gtm-auth-text {
                line-height: 1 !important;
            }
            
            #logoutBtn:hover {
                background: rgba(255, 67, 54, 0.2) !important;
            }
            
            #loginBtn:hover {
                background: rgba(76, 175, 80, 0.2) !important;
            }
            
            /* 확장된 메뉴 콘텐츠 */
            .gtm-menu-expanded {
                opacity: 0 !important;
                transform: translateY(20px) !important;
                transition: all 0.3s ease 0.1s !important;
                padding: 10px 10px 10px !important;
                pointer-events: none !important;
            }
            
            .gtm-menu-wrapper.expanded .gtm-menu-expanded {
                opacity: 1 !important;
                transform: translateY(0) !important;
                pointer-events: all !important;
            }
            
            /* 메뉴 아이템 */
            .gtm-menu-items {
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
            }
            
            .gtm-menu-item {
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
                padding: 6px 16px !important;
                background: rgba(255, 255, 255, 0.03) !important;
                border: 1px solid transparent !important;
                border-radius: 12px !important;
                color: var(--gtm-text-primary) !important;
                text-decoration: none !important;
                transition: all 0.2s ease !important;
                position: relative !important;
                overflow: hidden !important;
            }
            
            .gtm-white-theme .gtm-menu-item {
                background: rgba(0, 0, 0, 0.02) !important;
            }
            
            .gtm-menu-item::before {
                content: "" !important;
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 3px !important;
                height: 100% !important;
                background: var(--gtm-accent) !important;
                transform: translateX(-3px) !important;
                transition: transform 0.2s ease !important;
            }
            
            .gtm-menu-item:hover {
                background: rgba(255, 255, 255, 0.08) !important;
                border-color: var(--gtm-border) !important;
                padding-left: 20px !important;
            }
            
            .gtm-white-theme .gtm-menu-item:hover {
                background: rgba(0, 0, 0, 0.05) !important;
            }
            
            .gtm-menu-item:hover::before {
                transform: translateX(0) !important;
            }
            
            .gtm-menu-item:hover .gtm-item-arrow {
                transform: translateX(4px) !important;
                opacity: 1 !important;
            }
            
            .gtm-item-icon {
                font-size: 20px !important;
                width: 24px !important;
                text-align: center !important;
                flex-shrink: 0 !important;
            }
            
            .gtm-item-text {
                flex: 1 !important;
                font-size: 15px !important;
                font-weight: 500 !important;
            }
            
            .gtm-item-arrow {
                color: var(--gtm-text-secondary) !important;
                opacity: 0.5 !important;
                transition: all 0.2s ease !important;
            }
            
            /* 로딩 애니메이션 */
            @keyframes pulseGlow {
                0%, 100% {
                    opacity: 0.5;
                    transform: scale(1);
                }
                50% {
                    opacity: 1;
                    transform: scale(1.1);
                }
            }
            
            .gtm-menu-wrapper.loading .gtm-icon-wrapper {
                animation: pulseGlow 2s ease-in-out infinite !important;
            }
            
            /* 모바일 반응형 */
            @media (max-width: 600px) {
                .gtm-menu-container {
                    right: 10px !important;
                    top: 10px !important;
                }
                
                .gtm-menu-wrapper.expanded {
                    width: calc(100vw - 20px) !important;
                    max-width: 320px !important;
                }
                
                .gtm-menu-item {
                    padding: 10px 14px !important;
                }
                
                .gtm-item-text {
                    font-size: 14px !important;
                }
                
                .gtm-auth-link {
                    padding: 4px 10px !important;
                    font-size: 11px !important;
                }
            }
            
            /* 접근 거부 메시지 스타일 */
            .gtm-access-denied {
                text-align: center;
                padding: 50px 20px;
                max-width: 600px;
                margin: 100px auto;
                background: var(--gtm-bg-secondary);
                border-radius: 20px;
                box-shadow: 0 10px 40px var(--gtm-shadow);
                color: var(--gtm-text-primary);
            }
            
            .gtm-access-denied h2 {
                color: #ff5252;
                margin-bottom: 20px;
                font-size: 28px;
            }
            
            .gtm-access-denied p {
                color: var(--gtm-text-secondary);
                margin-bottom: 30px;
                font-size: 16px;
            }
            
            .gtm-access-denied a {
                display: inline-block;
                padding: 12px 30px;
                background: var(--gtm-accent);
                color: white;
                text-decoration: none;
                border-radius: 10px;
                transition: all 0.3s ease;
                font-weight: 500;
            }
            
            .gtm-access-denied a:hover {
                background: #850429;
                transform: translateY(-2px);
                box-shadow: 0 5px 20px rgba(167, 6, 56, 0.4);
            }
            
            /* 화이트 테마용 접근 거부 스타일 */
            .gtm-white-theme .gtm-access-denied {
                background: var(--gtm-bg-primary);
                border: 1px solid var(--gtm-border);
            }
            
            .gtm-white-theme .gtm-access-denied a:hover {
                background: #850429;
                box-shadow: 0 5px 20px rgba(167, 6, 56, 0.3);
            }
        ';
    }
}