<?php
/**
 * 메인 플러그인 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facility_Definition {
    
    private $ajax_handler;
    private $shortcode_handler;
    private $frontend_admin_handler;
    
    public function __construct() {
        $this->ajax_handler = new Facility_Ajax();
        $this->shortcode_handler = new Facility_Shortcode();
        $this->frontend_admin_handler = new Facility_Frontend_Admin();
    }
    
    public function init() {
        // AJAX 핸들러 초기화
        $this->ajax_handler->init();
        
        // 쇼트코드 핸들러 초기화
        $this->shortcode_handler->init();
        
        // 프론트엔드 관리자 쇼트코드 등록
        add_shortcode('facility_definition_admin', array('Facility_Frontend_Admin', 'render_admin_shortcode'));
        
        // 기존 워드프레스 관리자 메뉴 제거
        add_action('admin_menu', array($this, 'remove_admin_menu'), 999);
        
        // 배경 이미지 스타일 추가
        add_action('wp_enqueue_scripts', array($this, 'add_background_style'));
    }
    
    /**
     * 관리자 메뉴 제거
     */
    public function remove_admin_menu() {
        // 프론트엔드로 이동했으므로 관리자 메뉴 제거
        // 필요시 여기에 메뉴 제거 코드 추가
    }
    
    /**
     * 배경 이미지 스타일 추가
     */
    public function add_background_style() {
        // 쇼트코드가 있는 페이지에서만 배경 이미지 적용
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'facility_definition')) {
            // 저장된 배경 설정 가져오기
            $background_image = get_option('facility_background_image', '');
            $background_position = get_option('facility_background_position', 'bottom center');
            $background_size = get_option('facility_background_size', 'cover');
            
            // 배경 이미지가 설정되어 있을 때만 스타일 추가
            if ($background_image) {
                $custom_css = sprintf(
                    'body {
                        background: url("%s") no-repeat %s fixed;
                        background-size: %s;
                    }',
                    esc_url($background_image),
                    esc_attr($background_position),
                    esc_attr($background_size)
                );
                wp_add_inline_style('wp-block-library', $custom_css);
            }
        }
    }
}