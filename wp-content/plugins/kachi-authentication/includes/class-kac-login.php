<?php
/**
 * Main plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KAC_Login {
    
    private static $instance = null;
    
    private function __construct() {
        $this->init_hooks();
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function init_hooks() {
        // 쇼트코드 등록
        add_shortcode('kac_login', array('KAC_Login_Shortcode', 'render'));
        add_shortcode('kac_register', array('KAC_Registration', 'render_form'));
        add_shortcode('kac_register_complete', array('KAC_Registration', 'render_complete'));
        
        // 로그인 처리
        add_action('init', array('KAC_Login_Handler', 'process_login'));
        
        // 회원가입 처리
        add_action('init', array('KAC_Registration', 'process_registration'));
        
        // 승인 대기 역할 추가
        add_action('init', array('KAC_Registration', 'add_pending_role'));
        
        // 스타일과 스크립트 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // 로그인 실패 처리
        add_action('wp_login_failed', array('KAC_Login_Handler', 'handle_login_failed'));
        
        // 페이지 템플릿 필터
        add_filter('template_include', array($this, 'maybe_use_blank_template'));
        add_filter('body_class', array($this, 'add_body_class'));
        
        // 회원가입 URL 필터
        add_filter('register_url', function($url) { return home_url('/?page_id=241'); });
        
        // 승인 대기 사용자 로그인 제한
        add_filter('authenticate', array('KAC_Registration', 'restrict_pending_login'), 30, 3);
        
        // 구독자 관리자 바 숨김
        add_filter('show_admin_bar', '__return_false');
        
        // 관리자 접근 제한
        add_action('admin_init', array('KAC_Registration', 'restrict_admin_access'));
        
        // 프론트엔드 관리자 초기화
        new KAC_Login_Frontend_Admin();
        
        // 사용자 프로필 필드 추가 (다른 플러그인과 중복 방지)
        if (!has_action('show_user_profile', 'kachi_add_profile_fields')) {
            add_action('show_user_profile', array($this, 'add_profile_fields'));
            add_action('edit_user_profile', array($this, 'add_profile_fields'));
            add_action('personal_options_update', array($this, 'save_profile_fields'));
            add_action('edit_user_profile_update', array($this, 'save_profile_fields'));
        }
    }
    
    /**
     * 사용자 프로필 필드 추가
     */
    public function add_profile_fields($user) {
        // 다른 플러그인에서 이미 추가했는지 확인
        if (did_action('kachi_profile_fields_rendered')) {
            return;
        }
        
        $sosok = get_user_meta($user->ID, 'kachi_sosok', true);
        $site = get_user_meta($user->ID, 'kachi_site', true);
        ?>
        <h3>소속 정보</h3>
        <table class="form-table">
            <tr>
                <th><label for="kachi_sosok">소속</label></th>
                <td>
                    <input type="text" name="kachi_sosok" id="kachi_sosok" value="<?php echo esc_attr($sosok); ?>" class="regular-text" />
                    <p class="description">사용자의 소속을 입력하세요.</p>
                </td>
            </tr>
            <tr>
                <th><label for="kachi_site">현장</label></th>
                <td>
                    <input type="text" name="kachi_site" id="kachi_site" value="<?php echo esc_attr($site); ?>" class="regular-text" />
                    <p class="description">사용자의 현장을 입력하세요.</p>
                </td>
            </tr>
        </table>
        <?php
        
        // 중복 렌더링 방지 플래그
        do_action('kachi_profile_fields_rendered');
    }
    
    /**
     * 사용자 프로필 필드 저장
     */
    public function save_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['kachi_sosok'])) {
            update_user_meta($user_id, 'kachi_sosok', sanitize_text_field($_POST['kachi_sosok']));
        }
        
        if (isset($_POST['kachi_site'])) {
            update_user_meta($user_id, 'kachi_site', sanitize_text_field($_POST['kachi_site']));
        }
    }
    
    public function enqueue_scripts() {
        global $post;
        
        // 쇼트코드가 있는 페이지인지 확인
        if (is_a($post, 'WP_Post') && 
            (has_shortcode($post->post_content, 'kac_login') || 
             has_shortcode($post->post_content, 'kac_register') || 
             has_shortcode($post->post_content, 'kac_register_complete'))) {
            
            wp_enqueue_style('dashicons');
            wp_enqueue_style('kac-login-style', KAC_LOGIN_PLUGIN_URL . 'assets/css/login-style.css', array(), KAC_LOGIN_VERSION);
            wp_enqueue_style('kac-registration-style', KAC_LOGIN_PLUGIN_URL . 'assets/css/registration-style.css', array(), KAC_LOGIN_VERSION);
            wp_enqueue_script('kac-login-script', KAC_LOGIN_PLUGIN_URL . 'assets/js/login-script.js', array(), KAC_LOGIN_VERSION, true);
            
            // 프론트엔드 관리자에서 설정 가져오기
            $background_url = KAC_Login_Frontend_Admin::get_option('background_url');
            $complete_background_url = KAC_Login_Frontend_Admin::get_option('complete_background_url');
            
            // 회원가입 완료 페이지의 경우 특별한 배경 처리
            if (has_shortcode($post->post_content, 'kac_register_complete')) {
                // 관리자가 설정한 배경이 있으면 사용, 없으면 기본값
                $bg_url = $complete_background_url ? $complete_background_url : '/wp-content/uploads/2025/05/공항.png';
                $custom_css = '
                    body.kac-register-complete-page {
                        background: url("' . esc_url($bg_url) . '") no-repeat top center fixed;
                        background-size: cover;
                    }
                ';
                wp_add_inline_style('kac-login-style', $custom_css);
            } elseif ($background_url) {
                // 다른 페이지들은 관리자 설정 배경 사용
                $custom_css = '
                    body.kac-login-page {
                        background-image: url("' . esc_url($background_url) . '");
                        background-repeat: no-repeat;
                        background-position: bottom center;
                        background-attachment: fixed;
                        background-size: cover;
                    }
                ';
                wp_add_inline_style('kac-login-style', $custom_css);
            }
        }
    }
    
    public function maybe_use_blank_template($template) {
        global $post;
        
        if (is_a($post, 'WP_Post') && 
            (has_shortcode($post->post_content, 'kac_login') || 
             has_shortcode($post->post_content, 'kac_register') || 
             has_shortcode($post->post_content, 'kac_register_complete'))) {
            // 페이지에 full_page 파라미터가 있으면 빈 템플릿 사용
            if (isset($_GET['full_page']) && $_GET['full_page'] === '1') {
                return KAC_LOGIN_PLUGIN_DIR . 'templates/blank-template.php';
            }
        }
        
        return $template;
    }
    
    public function add_body_class($classes) {
        global $post;
        
        if (is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'kac_login') || 
                has_shortcode($post->post_content, 'kac_register')) {
                $classes[] = 'kac-login-page';
            }
            
            if (has_shortcode($post->post_content, 'kac_register_complete')) {
                $classes[] = 'kac-login-page';
                $classes[] = 'kac-register-complete-page';
            }
        }
        
        return $classes;
    }
    
    public static function activate() {
        // 활성화 시 필요한 작업
        flush_rewrite_rules();
        
        // 기본 옵션 설정
        $default_options = array(
            'logo_url' => '',
            'background_url' => '',
            'complete_background_url' => '',
            'complete_image_url' => '',
            'default_redirect' => home_url('/'),
            'terms_url' => '#',
            'privacy_url' => '#'
        );
        
        if (!get_option('kac_login_settings')) {
            add_option('kac_login_settings', $default_options);
        }
        
        // 기본 조직 데이터 설정
        if (!get_option('kac_organization_data')) {
            $default_org_data = array(
                "관리자" => array(
                    "관리자" => array("관리자")
                ),
                "전략기획본부" => array(
                    "스마트공항추진실" => array("스마트공항추진실장", "스마트기획부", "데이터융합부", "스마트공항부")
                ),
                "김포공항" => array(
                    "레이더관제부" => array("레이더관제송신소", "관제수신소", "관제통신소", "ASDE", "레이더관제부 사무실"),
                    "기술지원부" => array("test1", "test2")
                ),
                "김해공항" => array(
                    "관제부" => array("김해 제1현장", "김해 제2현장")
                ),
                "제주공항" => array(
                    "운영부" => array("제주 A현장", "제주 B현장"),
                    "기술부" => array("제주 C현장")
                ),
                "항공기술훈련원" => array(
                    "훈련지원부" => array("훈련센터 1호관", "훈련센터 2호관")
                )
            );
            add_option('kac_organization_data', $default_org_data);
        }
    }
    
    public static function deactivate() {
        // 비활성화 시 필요한 작업
        flush_rewrite_rules();
    }
}