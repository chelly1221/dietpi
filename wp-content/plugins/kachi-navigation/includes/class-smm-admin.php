<?php
/**
 * Admin functionality
 */
class SMM_Admin {
    
    /**
     * 관리자 초기화
     */
    public static function init() {
        // 관리자 메뉴를 추가하지 않음
    }
    
    /**
     * 설정 저장
     */
    private static function save_settings() {
        $settings = array(
            'default_bg_color' => sanitize_text_field($_POST['default_bg_color']),
            'default_point_color' => sanitize_text_field($_POST['default_point_color'])
        );
        
        update_option('smm_settings', $settings);
        
        echo '<div class="notice notice-success"><p>' . __('설정이 저장되었습니다.', 'sidebar-menu-manager') . '</p></div>';
    }
    
    /**
     * 메뉴 추가
     */
    private static function add_menu() {
        global $wpdb;
        
        $menu_label = sanitize_text_field($_POST['menu_label']);
        $menu_url = sanitize_text_field($_POST['menu_url']);
        $menu_target = sanitize_text_field($_POST['menu_target']);
        
        $wpdb->insert(
            $wpdb->prefix . 'smm_menus',
            array(
                'menu_label' => $menu_label,
                'menu_url' => $menu_url,
                'menu_target' => $menu_target,
                'menu_order' => self::get_next_order()
            ),
            array('%s', '%s', '%s', '%d')
        );
        
        echo '<div class="notice notice-success"><p>' . __('메뉴가 추가되었습니다.', 'sidebar-menu-manager') . '</p></div>';
    }
    
    /**
     * 메뉴 수정
     */
    private static function edit_menu() {
        global $wpdb;
        
        $menu_id = intval($_POST['menu_id']);
        $menu_label = sanitize_text_field($_POST['menu_label']);
        $menu_url = sanitize_text_field($_POST['menu_url']);
        $menu_target = sanitize_text_field($_POST['menu_target']);
        
        $wpdb->update(
            $wpdb->prefix . 'smm_menus',
            array(
                'menu_label' => $menu_label,
                'menu_url' => $menu_url,
                'menu_target' => $menu_target
            ),
            array('id' => $menu_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        echo '<div class="notice notice-success"><p>' . __('메뉴가 수정되었습니다.', 'sidebar-menu-manager') . '</p></div>';
    }
    
    /**
     * 메뉴 삭제
     */
    private static function delete_menu($menu_id) {
        global $wpdb;
        
        $wpdb->delete(
            $wpdb->prefix . 'smm_menus',
            array('id' => $menu_id),
            array('%d')
        );
        
        echo '<div class="notice notice-success"><p>' . __('메뉴가 삭제되었습니다.', 'sidebar-menu-manager') . '</p></div>';
    }
    
    /**
     * 모든 메뉴 가져오기
     */
    public static function get_all_menus() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}smm_menus ORDER BY menu_order ASC"
        );
    }
    
    /**
     * 다음 순서 번호 가져오기
     */
    private static function get_next_order() {
        global $wpdb;
        
        $max_order = $wpdb->get_var(
            "SELECT MAX(menu_order) FROM {$wpdb->prefix}smm_menus"
        );
        
        return $max_order ? $max_order + 1 : 1;
    }
}