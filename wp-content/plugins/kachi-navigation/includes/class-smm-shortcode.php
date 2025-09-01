<?php
/**
 * Shortcode functionality
 */
class SMM_Shortcode {
    
    /**
     * 쇼트코드 초기화
     */
    public static function init() {
        add_shortcode('sidebar_menu', array(__CLASS__, 'render_sidebar_menu'));
    }
    
    /**
     * 사이드바 메뉴 렌더링
     */
    public static function render_sidebar_menu($atts) {
        $settings = get_option('smm_settings', array());
        
        $atts = shortcode_atts(array(
            'class' => '',
            'position' => 'left',
            'width' => '250px',
            'bg_color' => isset($settings['default_bg_color']) ? $settings['default_bg_color'] : '#1a1a1a',
            'point_color' => isset($settings['default_point_color']) ? $settings['default_point_color'] : '#a70638'
        ), $atts);
        
        // 메뉴 가져오기
        $menus = SMM_Admin::get_all_menus();
        
        // 현재 URL 정보 가져오기
        $current_url = self::get_current_url();
        $current_path = self::get_current_path();
        $home_url = home_url();
        
        // 관리자 여부 확인
        $is_admin = is_user_logged_in() && current_user_can('manage_options');
        
        ob_start();
        ?>
        <div class="smm-container <?php echo esc_attr($atts['class']); ?>" 
             data-position="<?php echo esc_attr($atts['position']); ?>"
             style="--smm-width: <?php echo esc_attr($atts['width']); ?>; 
                    --smm-bg-color: <?php echo esc_attr($atts['bg_color']); ?>; 
                    --smm-point-color: <?php echo esc_attr($atts['point_color']); ?>;">
            <div class="smm-sidebar">
                <div class="smm-sidebar-header">
                    <h3><?php _e('개발자 설정', 'sidebar-menu-manager'); ?></h3>
                    <?php if ($is_admin) : ?>
                    <button type="button" class="smm-add-menu-btn" title="<?php _e('메뉴 추가', 'sidebar-menu-manager'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
                <ul class="smm-menu-list">
                    <?php if (empty($menus)) : ?>
                        <li class="smm-no-menu"><?php _e('등록된 메뉴가 없습니다.', 'sidebar-menu-manager'); ?></li>
                    <?php else : ?>
                        <?php foreach ($menus as $menu) : 
                            $is_active = self::is_menu_active($menu->menu_url, $current_url, $current_path, $home_url);
                        ?>
                            <li class="smm-menu-item <?php echo $is_active ? 'active' : ''; ?>" data-menu-id="<?php echo esc_attr($menu->id); ?>">
                                <?php if ($is_admin) : ?>
                                <div class="smm-drag-handle">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M8 6H16M8 12H16M8 18H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($menu->menu_url); ?>" 
                                   target="<?php echo esc_attr($menu->menu_target); ?>"
                                   class="smm-menu-link">
                                    <?php echo esc_html($menu->menu_label); ?>
                                </a>
                                <?php if ($is_admin) : ?>
                                <div class="smm-menu-actions">
                                    <button type="button" class="smm-menu-edit" title="<?php _e('수정', 'sidebar-menu-manager'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M18.5 2.50001C18.8978 2.10219 19.4374 1.87869 20 1.87869C20.5626 1.87869 21.1022 2.10219 21.5 2.50001C21.8978 2.89784 22.1213 3.43741 22.1213 4.00001C22.1213 4.56262 21.8978 5.10219 21.5 5.50001L12 15L8 16L9 12L18.5 2.50001Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="smm-menu-delete" title="<?php _e('삭제', 'sidebar-menu-manager'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <?php if ($is_admin) : ?>
        <!-- 메뉴 추가/수정 모달 -->
        <div class="smm-modal" id="smm-menu-modal" style="display: none;">
            <div class="smm-modal-content">
                <div class="smm-modal-header">
                    <h4 id="smm-modal-title"><?php _e('새 메뉴 추가', 'sidebar-menu-manager'); ?></h4>
                    <button type="button" class="smm-modal-close">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="smm-modal-body">
                    <form id="smm-menu-form">
                        <input type="hidden" id="smm-menu-id" name="menu_id" value="" />
                        <div class="smm-form-group">
                            <label for="smm-menu-label"><?php _e('메뉴 레이블', 'sidebar-menu-manager'); ?></label>
                            <input type="text" id="smm-menu-label" name="menu_label" required />
                        </div>
                        <div class="smm-form-group">
                            <label for="smm-menu-url"><?php _e('링크 URL', 'sidebar-menu-manager'); ?></label>
                            <input type="text" id="smm-menu-url" name="menu_url" required />
                            <p class="description"><?php _e('상대 경로(/page) 또는 전체 URL(https://example.com/page)을 입력하세요.', 'sidebar-menu-manager'); ?></p>
                        </div>
                        <div class="smm-form-group">
                            <label for="smm-menu-target"><?php _e('링크 타겟', 'sidebar-menu-manager'); ?></label>
                            <select id="smm-menu-target" name="menu_target">
                                <option value="_self"><?php _e('같은 창에서 열기', 'sidebar-menu-manager'); ?></option>
                                <option value="_blank"><?php _e('새 창에서 열기', 'sidebar-menu-manager'); ?></option>
                            </select>
                        </div>
                        <div class="smm-form-actions">
                            <button type="submit" class="smm-btn smm-btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px;">
                                    <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span id="smm-submit-text"><?php _e('추가', 'sidebar-menu-manager'); ?></span>
                            </button>
                            <button type="button" class="smm-btn smm-btn-cancel"><?php _e('취소', 'sidebar-menu-manager'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- 삭제 확인 모달 -->
        <div class="smm-modal" id="smm-delete-modal" style="display: none;">
            <div class="smm-modal-content smm-modal-small">
                <div class="smm-modal-header">
                    <h4><?php _e('메뉴 삭제', 'sidebar-menu-manager'); ?></h4>
                    <button type="button" class="smm-modal-close">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="smm-modal-body">
                    <p><?php _e('이 메뉴를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.', 'sidebar-menu-manager'); ?></p>
                    <input type="hidden" id="smm-delete-menu-id" value="" />
                    <div class="smm-form-actions">
                        <button type="button" class="smm-btn smm-btn-danger" id="smm-confirm-delete">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px;">
                                <path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php _e('삭제', 'sidebar-menu-manager'); ?>
                        </button>
                        <button type="button" class="smm-btn smm-btn-cancel"><?php _e('취소', 'sidebar-menu-manager'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 현재 URL 가져오기
     */
    private static function get_current_url() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $request_uri = $_SERVER['REQUEST_URI'];
        
        return $protocol . '://' . $host . $request_uri;
    }
    
    /**
     * 현재 경로 가져오기 (쿼리 스트링 제외)
     */
    private static function get_current_path() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);
        
        return $path;
    }
    
    /**
     * 메뉴가 활성화되어 있는지 확인
     */
    private static function is_menu_active($menu_url, $current_url, $current_path, $home_url) {
        // 메뉴 URL 정규화
        $menu_url = trim($menu_url);
        
        // 1. 정확한 URL 매칭 (쿼리 스트링 포함)
        if ($menu_url === $current_url) {
            return true;
        }
        
        // 2. 상대 경로인 경우
        if (strpos($menu_url, '/') === 0) {
            // 쿼리 스트링 제외하고 경로만 비교
            if ($menu_url === $current_path) {
                return true;
            }
            
            // 전체 URL로 변환하여 비교
            $full_menu_url = $home_url . $menu_url;
            if ($full_menu_url === $current_url) {
                return true;
            }
            
            // 쿼리 스트링 제외하고 비교
            $current_url_without_query = strtok($current_url, '?');
            if ($full_menu_url === $current_url_without_query) {
                return true;
            }
        }
        
        // 3. 전체 URL인 경우
        if (strpos($menu_url, 'http') === 0) {
            // 쿼리 스트링 제외하고 비교
            $menu_url_without_query = strtok($menu_url, '?');
            $current_url_without_query = strtok($current_url, '?');
            
            if ($menu_url_without_query === $current_url_without_query) {
                return true;
            }
            
            // 메뉴 URL의 경로 부분만 추출하여 비교
            $menu_path = parse_url($menu_url, PHP_URL_PATH);
            if ($menu_path && $menu_path === $current_path) {
                return true;
            }
        }
        
        // 4. 홈페이지 특별 처리
        if (($menu_url === '/' || $menu_url === $home_url || $menu_url === $home_url . '/') && 
            ($current_path === '/' || $current_url === $home_url || $current_url === $home_url . '/')) {
            return true;
        }
        
        // 5. 하위 페이지 매칭 (선택적 - 필요한 경우 활성화)
        // 예: /products가 메뉴인 경우 /products/item1도 활성화
        /*
        if (strpos($current_path, $menu_url) === 0 && $menu_url !== '/') {
            return true;
        }
        */
        
        return false;
    }
}