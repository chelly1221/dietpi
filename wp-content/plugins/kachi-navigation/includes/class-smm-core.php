<?php
/**
 * Core functionality
 */
class SMM_Core {
    
    /**
     * 플러그인 초기화
     */
    public static function init() {
        // 관리자 기능 초기화
        if (is_admin()) {
            SMM_Admin::init();
        }
        
        // 쇼트코드 초기화
        SMM_Shortcode::init();
        
        // AJAX 핸들러 초기화
        SMM_Ajax::init();
        
        // 스타일 및 스크립트 등록
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        
        // 데이터베이스 업그레이드 체크
        add_action('admin_init', array(__CLASS__, 'check_db_upgrade'));
    }
    
    /**
     * 플러그인 활성화
     */
    public static function activate() {
        // 데이터베이스 테이블 생성
        self::create_tables();
        
        // 기본 옵션 설정
        add_option('smm_settings', array(
            'version' => SMM_VERSION,
            'default_bg_color' => '#1a1a1a',
            'default_point_color' => '#a70638'
        ));
        
        // DB 버전 저장
        update_option('smm_db_version', '3.0');
        
        // 재작성 규칙 플러시
        flush_rewrite_rules();
    }
    
    /**
     * 플러그인 비활성화
     */
    public static function deactivate() {
        // 재작성 규칙 플러시
        flush_rewrite_rules();
    }
    
    /**
     * 데이터베이스 업그레이드 체크
     */
    public static function check_db_upgrade() {
        $current_db_version = get_option('smm_db_version', '1.0');
        
        if (version_compare($current_db_version, '3.0', '<')) {
            self::upgrade_database();
        }
    }
    
    /**
     * 데이터베이스 업그레이드
     */
    private static function upgrade_database() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smm_menus';
        $charset_collate = $wpdb->get_charset_collate();
        
        // 새로운 테이블 구조
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            menu_label varchar(255) NOT NULL,
            menu_url varchar(255) NOT NULL,
            menu_target varchar(10) DEFAULT '_self',
            menu_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // DB 버전 업데이트
        update_option('smm_db_version', '3.0');
    }
    
    /**
     * 데이터베이스 테이블 생성
     */
    private static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smm_menus';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            menu_label varchar(255) NOT NULL,
            menu_url varchar(255) NOT NULL,
            menu_target varchar(10) DEFAULT '_self',
            menu_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 프론트엔드 스타일 및 스크립트 등록
     */
    public static function enqueue_frontend_assets() {
        // CSS 파일이 존재하는지 확인
        $css_file = SMM_PLUGIN_DIR . 'assets/css/frontend.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'smm-frontend',
                SMM_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                SMM_VERSION
            );
        }
        
        // JS 파일이 존재하는지 확인
        $js_file = SMM_PLUGIN_DIR . 'assets/js/frontend.js';
        if (file_exists($js_file)) {
            // 관리자인 경우 jQuery UI 종속성 추가
            $deps = array('jquery');
            if (is_user_logged_in() && current_user_can('manage_options')) {
                $deps[] = 'jquery-ui-core';
                $deps[] = 'jquery-ui-widget';
                $deps[] = 'jquery-ui-mouse';
                $deps[] = 'jquery-ui-sortable';
            }
            
            wp_enqueue_script(
                'smm-frontend',
                SMM_PLUGIN_URL . 'assets/js/frontend.js',
                $deps,
                SMM_VERSION,
                true
            );
            
            wp_localize_script('smm-frontend', 'smm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('smm_nonce'),
                'is_admin' => is_user_logged_in() && current_user_can('manage_options')
            ));
        }
    }
    
    /**
     * 관리자 스타일 및 스크립트 등록
     */
    public static function enqueue_admin_assets($hook) {
        // 플러그인 관리 페이지에서만 로드
        if ($hook !== 'toplevel_page_sidebar-menu-manager') {
            return;
        }
        
        // CSS 파일이 존재하는지 확인
        $admin_css = SMM_PLUGIN_DIR . 'assets/css/admin.css';
        if (file_exists($admin_css)) {
            wp_enqueue_style(
                'smm-admin',
                SMM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                SMM_VERSION
            );
        }
        
        // JS 파일이 존재하는지 확인
        $admin_js = SMM_PLUGIN_DIR . 'assets/js/admin.js';
        if (file_exists($admin_js)) {
            wp_enqueue_script(
                'smm-admin',
                SMM_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-sortable'),
                SMM_VERSION,
                true
            );
            
            wp_localize_script('smm_admin', 'smm_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('smm_admin_nonce')
            ));
        }
    }
}