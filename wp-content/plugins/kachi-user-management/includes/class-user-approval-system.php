<?php
/**
 * 메인 플러그인 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class User_Approval_System {
    
    /**
     * 싱글톤 인스턴스
     */
    private static $instance = null;
    
    /**
     * 플러그인 초기화
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 훅 초기화
     */
    private function init_hooks() {
        // 프론트엔드 관리자 클래스가 존재하는 경우에만 초기화
        if (class_exists('UAS_Frontend_Admin')) {
            $frontend_admin = new UAS_Frontend_Admin();
            // 프론트엔드 관리자 쇼트코드 등록
            add_shortcode('user_approval_admin', array('UAS_Frontend_Admin', 'render_admin_shortcode'));
        }
        
        // 숏코드
        $shortcode = new UAS_Shortcode();
        
        // AJAX
        $ajax = new UAS_Ajax();
        
        // 스타일 및 스크립트 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * 관리자 메뉴 제거
     */
    public function remove_admin_menu() {
        // 프론트엔드로 이동했으므로 관리자 메뉴 제거
        remove_menu_page('user-approval-system');
    }
    
    /**
     * 프론트엔드 스크립트 및 스타일 등록
     */
    public function enqueue_scripts() {
        // 필요한 경우 스크립트나 스타일 추가
    }
    
    /**
     * 플러그인 활성화
     */
    public static function activate() {
        // 활성화 시 필요한 작업
        self::create_pending_role();
        
        // 배경 이미지 옵션 추가
        if (false === get_option('uas_background_image')) {
            add_option('uas_background_image', '');
        }
        
        if (false === get_option('uas_background_position')) {
            add_option('uas_background_position', 'bottom center');
        }
        
        if (false === get_option('uas_background_size')) {
            add_option('uas_background_size', 'cover');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * 플러그인 비활성화
     */
    public static function deactivate() {
        // 비활성화 시 필요한 작업
        flush_rewrite_rules();
    }
    
    /**
     * pending 역할 생성
     */
    private static function create_pending_role() {
        add_role('pending', __('승인 대기', 'user-approval-system'), array(
            'read' => true,
        ));
        
        // approver 역할이 없으면 생성
        if (!get_role('approver')) {
            add_role('approver', __('승인자', 'user-approval-system'), array(
                'read' => true,
                'approve_users' => true,
            ));
        }
    }
}