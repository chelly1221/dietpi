<?php
/**
 * 쇼트코드 처리 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facility_Shortcode {
    
    public function init() {
        // 쇼트코드 등록
        add_shortcode('facility_definition', array($this, 'render_facility_definition'));
        
        // 스크립트 및 스타일 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * 쇼트코드 렌더링
     */
    public function render_facility_definition($atts) {
        // 사용자 정보 확인
        $user_id = get_current_user_id();
        $sosok = 'default';
        $site = 'default';
        
        if ($user_id) {
            $user_sosok = get_user_meta($user_id, 'kachi_sosok', true);
            $user_site = get_user_meta($user_id, 'kachi_site', true);
            
            if ($user_sosok) $sosok = $user_sosok;
            if ($user_site) $site = $user_site;
        }
        
        ob_start();
        ?>
        <div class="facility-definition-wrapper">
            <h2>📌 시설 정의</h2>
            
            <form id="facilityForm">
                <label for="facilityKey">용어 <small>(예: 1레이더)</small></label>
                <input type="text" id="facilityKey" class="modern-input" required />

                <label for="facilityValue1">정의 1 <small>(예: NPG-1460E)</small></label>
                <input type="text" id="facilityValue1" class="modern-input" required />

                <label for="facilityValue2">정의 2 <small>(선택, 예: NPG-1323F)</small></label>
                <input type="text" id="facilityValue2" class="modern-input" />

                <div class="button-container">
                    <button type="submit" class="add-button">✚ 추가</button>
                </div>
            </form>

            <div id="loadingIndicator">
                <div class="loading-spinner"></div>
                <p>처리 중...</p>
            </div>

            <ul id="facilityList"></ul>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 스크립트 및 스타일 등록
     */
    public function enqueue_scripts() {
        global $post;
        
        // 쇼트코드가 있는 페이지에서만 로드
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'facility_definition')) {
            // CSS 파일 등록
            wp_enqueue_style(
                'facility-definition-style',
                KACHI_FACILITY_DEFINITION_PLUGIN_URL . 'assets/css/facility-definition.css',
                array(),
                KACHI_FACILITY_DEFINITION_VERSION
            );
            
            // JavaScript 파일 등록
            wp_enqueue_script(
                'facility-definition-script',
                KACHI_FACILITY_DEFINITION_PLUGIN_URL . 'assets/js/facility-definition.js',
                array('jquery'),
                KACHI_FACILITY_DEFINITION_VERSION,
                true
            );
            
            // AJAX URL 및 nonce 전달
            wp_localize_script('facility-definition-script', 'facility_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('facility_ajax_nonce')
            ));
        }
    }
}