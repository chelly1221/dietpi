<?php
/**
 * AJAX functionality
 */
class SMM_Ajax {
    
    /**
     * AJAX 초기화
     */
    public static function init() {
        // 프론트엔드 AJAX 액션
        add_action('wp_ajax_smm_add_menu_frontend', array(__CLASS__, 'add_menu_frontend'));
        add_action('wp_ajax_nopriv_smm_add_menu_frontend', array(__CLASS__, 'add_menu_frontend'));
        
        add_action('wp_ajax_smm_edit_menu_frontend', array(__CLASS__, 'edit_menu_frontend'));
        add_action('wp_ajax_nopriv_smm_edit_menu_frontend', array(__CLASS__, 'edit_menu_frontend'));
        
        add_action('wp_ajax_smm_delete_menu_frontend', array(__CLASS__, 'delete_menu_frontend'));
        add_action('wp_ajax_nopriv_smm_delete_menu_frontend', array(__CLASS__, 'delete_menu_frontend'));
        
        add_action('wp_ajax_smm_get_menu_data', array(__CLASS__, 'get_menu_data'));
        add_action('wp_ajax_nopriv_smm_get_menu_data', array(__CLASS__, 'get_menu_data'));
        
        // 관리자 AJAX 액션
        add_action('wp_ajax_smm_update_menu_order', array(__CLASS__, 'update_menu_order'));
    }
    
    /**
     * 프론트엔드에서 메뉴 추가
     */
    public static function add_menu_frontend() {
        // nonce 확인
        if (!wp_verify_nonce($_POST['nonce'], 'smm_nonce')) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 로그인 사용자만 가능
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        
        $menu_label = sanitize_text_field($_POST['menu_label']);
        $menu_url = sanitize_text_field($_POST['menu_url']); // 상대 경로도 허용
        $menu_target = sanitize_text_field($_POST['menu_target']);
        
        if (empty($menu_label) || empty($menu_url)) {
            wp_send_json_error('필수 항목을 입력해주세요.');
        }
        
        // 다음 순서 번호 가져오기
        $max_order = $wpdb->get_var(
            "SELECT MAX(menu_order) FROM {$wpdb->prefix}smm_menus"
        );
        $next_order = $max_order ? $max_order + 1 : 1;
        
        // 메뉴 추가
        $result = $wpdb->insert(
            $wpdb->prefix . 'smm_menus',
            array(
                'menu_label' => $menu_label,
                'menu_url' => $menu_url,
                'menu_target' => $menu_target,
                'menu_order' => $next_order
            ),
            array('%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            wp_send_json_error('메뉴 추가에 실패했습니다.');
        }
        
        wp_send_json_success(array(
            'message' => '메뉴가 추가되었습니다.',
            'menu' => array(
                'id' => $wpdb->insert_id,
                'label' => $menu_label,
                'url' => $menu_url,
                'target' => $menu_target
            )
        ));
    }
    
    /**
     * 프론트엔드에서 메뉴 수정
     */
    public static function edit_menu_frontend() {
        // nonce 확인
        if (!wp_verify_nonce($_POST['nonce'], 'smm_nonce')) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 로그인 사용자만 가능
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        
        $menu_id = intval($_POST['menu_id']);
        $menu_label = sanitize_text_field($_POST['menu_label']);
        $menu_url = sanitize_text_field($_POST['menu_url']); // 상대 경로도 허용
        $menu_target = sanitize_text_field($_POST['menu_target']);
        
        if (empty($menu_id) || empty($menu_label) || empty($menu_url)) {
            wp_send_json_error('필수 항목을 입력해주세요.');
        }
        
        // 메뉴 업데이트
        $result = $wpdb->update(
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
        
        if ($result === false) {
            wp_send_json_error('메뉴 수정에 실패했습니다.');
        }
        
        wp_send_json_success(array(
            'message' => '메뉴가 수정되었습니다.',
            'menu' => array(
                'id' => $menu_id,
                'label' => $menu_label,
                'url' => $menu_url,
                'target' => $menu_target
            )
        ));
    }
    
    /**
     * 프론트엔드에서 메뉴 삭제
     */
    public static function delete_menu_frontend() {
        // nonce 확인
        if (!wp_verify_nonce($_POST['nonce'], 'smm_nonce')) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 로그인 사용자만 가능
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        
        $menu_id = intval($_POST['menu_id']);
        
        if (empty($menu_id)) {
            wp_send_json_error('잘못된 요청입니다.');
        }
        
        // 메뉴 삭제
        $result = $wpdb->delete(
            $wpdb->prefix . 'smm_menus',
            array('id' => $menu_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('메뉴 삭제에 실패했습니다.');
        }
        
        wp_send_json_success(array(
            'message' => '메뉴가 삭제되었습니다.'
        ));
    }
    
    /**
     * 메뉴 데이터 가져오기
     */
    public static function get_menu_data() {
        // nonce 확인
        if (!wp_verify_nonce($_POST['nonce'], 'smm_nonce')) {
            wp_send_json_error('보안 검증 실패');
        }
        
        global $wpdb;
        
        $menu_id = intval($_POST['menu_id']);
        
        if (empty($menu_id)) {
            wp_send_json_error('잘못된 요청입니다.');
        }
        
        $menu = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}smm_menus WHERE id = %d",
                $menu_id
            )
        );
        
        if (!$menu) {
            wp_send_json_error('메뉴를 찾을 수 없습니다.');
        }
        
        wp_send_json_success(array(
            'menu' => array(
                'id' => $menu->id,
                'label' => $menu->menu_label,
                'url' => $menu->menu_url,
                'target' => $menu->menu_target
            )
        ));
    }
    
    /**
     * 메뉴 순서 업데이트
     */
    public static function update_menu_order() {
        // nonce 확인 - 프론트엔드와 관리자 모두 지원
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $admin_nonce = isset($_POST['nonce']) && $_POST['nonce'] === wp_create_nonce('smm_admin_nonce');
        $frontend_nonce = isset($_POST['nonce']) && $_POST['nonce'] === wp_create_nonce('smm_nonce');
        
        if (!$admin_nonce && !$frontend_nonce) {
            wp_send_json_error('보안 검증 실패');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        
        $menu_order = $_POST['menu_order'];
        
        if (!is_array($menu_order)) {
            wp_send_json_error('잘못된 요청입니다.');
        }
        
        foreach ($menu_order as $order => $menu_id) {
            $wpdb->update(
                $wpdb->prefix . 'smm_menus',
                array('menu_order' => $order),
                array('id' => intval($menu_id)),
                array('%d'),
                array('%d')
            );
        }
        
        wp_send_json_success(array(
            'message' => '메뉴 순서가 업데이트되었습니다.'
        ));
    }
}