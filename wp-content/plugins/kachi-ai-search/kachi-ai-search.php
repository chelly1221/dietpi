<?php
/**
 * Plugin Name: KACHI AI Search
 * Plugin URI: https://3chan.kr
 * Description: KACHI AI 기반 문서 검색 및 질의응답 시스템 - 프록시 API 지원
 * Version: 3.2.1
 * Author: 3chan
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * Text Domain: kachi-ai-search
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의 - KACHI AI Search
define('KACHI_AI_SEARCH_VERSION', '3.2.1');
define('KACHI_AI_SEARCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KACHI_AI_SEARCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KACHI_AI_SEARCH_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KACHI_AI_SEARCH_DB_VERSION', '1.0');

// Legacy constants for backward compatibility
define('KACHI_VERSION', '3.2.1');
define('KACHI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KACHI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KACHI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KACHI_DB_VERSION', '1.0');

/**
 * 플러그인 메인 클래스
 */
class Kachi_Query_System {
    
    /**
     * 플러그인 인스턴스
     */
    private static $instance = null;
    
    /**
     * 로더 인스턴스
     */
    private $loader;
    
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
     * 생성자
     */
    private function __construct() {
        // 필요한 파일만 로드
        $this->load_dependencies();
        
        // 초기화
        $this->loader = new Kachi_Loader();
        
        // 공개 훅은 필요할 때만
        $this->define_public_hooks();
        
        // 사용자 프로필 필드 추가
        $this->add_user_profile_fields();
        
        // 프론트엔드 관리자 초기화
        $this->init_frontend_admin();
    }
    
    /**
     * 의존성 파일 로드
     */
    private function load_dependencies() {
        require_once KACHI_PLUGIN_DIR . 'includes/class-kachi-loader.php';
        
        // 프론트엔드 관리자 클래스 추가
        require_once KACHI_PLUGIN_DIR . 'includes/class-kachi-frontend-admin.php';
        
        // AJAX 요청이거나 쇼트코드가 있을 때만 로드
        if (wp_doing_ajax() || $this->should_load_frontend()) {
            require_once KACHI_PLUGIN_DIR . 'includes/class-kachi-shortcode.php';
            require_once KACHI_PLUGIN_DIR . 'includes/class-kachi-ajax.php';
        }
    }
    
    /**
     * 프론트엔드 로드 여부 확인
     */
    private function should_load_frontend() {
        // 페이지 로드 시에만 체크
        if (!is_singular()) {
            return false;
        }
        
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return false;
        }
        
        // 쇼트코드 존재 여부 확인 (관리자 쇼트코드 포함)
        return has_shortcode($post->post_content, 'kachi_query') || 
               has_shortcode($post->post_content, 'kachi_admin');
    }
    
    /**
     * 공개 훅 정의
     */
    private function define_public_hooks() {
        // 쇼트코드는 항상 등록
        add_action('init', array($this, 'register_shortcodes_early'));
        
        
        // AJAX 핸들러는 wp_doing_ajax일 때만
        if (wp_doing_ajax()) {
            $ajax = new Kachi_Ajax();
            
            // 프록시 쿼리 핸들러
            $this->loader->add_action('wp_ajax_kachi_query', $ajax, 'handle_query');
            $this->loader->add_action('wp_ajax_nopriv_kachi_query', $ajax, 'handle_query');
            
            // 문서 정보 가져오기
            $this->loader->add_action('wp_ajax_kachi_get_query_documents', $ajax, 'get_query_documents');
            $this->loader->add_action('wp_ajax_nopriv_kachi_get_query_documents', $ajax, 'get_query_documents');
            
            $this->loader->add_action('wp_ajax_kachi_get_tags', $ajax, 'get_tags');
            $this->loader->add_action('wp_ajax_nopriv_kachi_get_tags', $ajax, 'get_tags');
            
            $this->loader->add_action('wp_ajax_kachi_get_documents', $ajax, 'get_documents');
            $this->loader->add_action('wp_ajax_nopriv_kachi_get_documents', $ajax, 'get_documents');
            
            // 이미지 프록시
            $this->loader->add_action('wp_ajax_kachi_proxy_image', $ajax, 'proxy_image');
            $this->loader->add_action('wp_ajax_nopriv_kachi_proxy_image', $ajax, 'proxy_image');
            
            // 대화 관련 AJAX 핸들러 추가
            $this->loader->add_action('wp_ajax_kachi_load_conversations', $ajax, 'load_conversations');
            $this->loader->add_action('wp_ajax_kachi_save_conversation', $ajax, 'save_conversation');
            $this->loader->add_action('wp_ajax_kachi_delete_conversation', $ajax, 'delete_conversation');
            
            // 디버깅 및 모니터링 AJAX 핸들러
            $this->loader->add_action('wp_ajax_kachi_health_check', $ajax, 'health_check');
            $this->loader->add_action('wp_ajax_kachi_system_info', $ajax, 'get_system_info');
        }
        
        // 스크립트는 필요할 때만 로드
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_scripts');
    }
    
    /**
     * 프론트엔드 관리자 초기화
     */
    private function init_frontend_admin() {
        new Kachi_Frontend_Admin();
    }
    
    /**
     * 사용자 프로필 필드 추가
     */
    private function add_user_profile_fields() {
        add_action('show_user_profile', array($this, 'render_profile_fields'));
        add_action('edit_user_profile', array($this, 'render_profile_fields'));
        add_action('personal_options_update', array($this, 'save_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_profile_fields'));
    }
    
    /**
     * 프로필 필드 렌더링
     */
    public function render_profile_fields($user) {
        $sosok = get_user_meta($user->ID, 'kachi_sosok', true);
        $site = get_user_meta($user->ID, 'kachi_site', true);
        ?>
        <h3>까치 쿼리 시스템 정보</h3>
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
    }
    
    /**
     * 프로필 필드 저장
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
    
    /**
     * 쇼트코드 조기 등록
     */
    public function register_shortcodes_early() {
        // 쇼트코드만 등록하고 실제 처리는 나중에
        add_shortcode('kachi_query', array($this, 'handle_shortcode'));
        add_shortcode('kachi_admin', array('Kachi_Frontend_Admin', 'render_admin_shortcode'));
    }
    
    /**
     * 쇼트코드 처리
     */
    public function handle_shortcode($atts) {
        // 쇼트코드가 실제로 사용될 때만 클래스 로드
        if (!class_exists('Kachi_Shortcode')) {
            require_once KACHI_PLUGIN_DIR . 'includes/class-kachi-shortcode.php';
        }
        
        $shortcode = new Kachi_Shortcode();
        $shortcode->register_shortcode();
        
        return $shortcode->render_shortcode($atts);
    }
    
    /**
     * 스크립트 및 스타일 등록 (프록시 버전)
     */
    public function enqueue_scripts() {
        // 쇼트코드가 있는 페이지에서만 로드
        if (!$this->should_load_frontend()) {
            return;
        }
        
        // 메인 CSS 로드
        wp_enqueue_style(
            'kachi-style',
            KACHI_PLUGIN_URL . 'assets/css/kachi-style.css',
            array(),
            KACHI_VERSION
        );
        
        // 사이드바 CSS 로드
        wp_enqueue_style(
            'kachi-sidebar-style',
            KACHI_PLUGIN_URL . 'assets/css/kachi-sidebar.css',
            array('kachi-style'),
            KACHI_VERSION
        );
        
        // jQuery 의존성 확인
        wp_enqueue_script('jquery');
        
        // JavaScript 파일들을 올바른 순서로 로드
        // 1. Core 모듈 (상태 관리)
        wp_enqueue_script(
            'kachi-core',
            KACHI_PLUGIN_URL . 'assets/js/kachi-core.js',
            array('jquery'),
            KACHI_VERSION,
            true
        );
        
        // 2. UI 모듈 (화면 렌더링)
        wp_enqueue_script(
            'kachi-ui',
            KACHI_PLUGIN_URL . 'assets/js/kachi-ui.js',
            array('jquery', 'kachi-core'),
            KACHI_VERSION,
            true
        );
        
        // 3. API 모듈 (데이터 통신) - 프록시 버전
        wp_enqueue_script(
            'kachi-api',
            KACHI_PLUGIN_URL . 'assets/js/kachi-api.js',
            array('jquery', 'kachi-core', 'kachi-ui'),
            KACHI_VERSION,
            true
        );
        
        // 4. 메인 진입점
        wp_enqueue_script(
            'kachi-main',
            KACHI_PLUGIN_URL . 'assets/js/kachi-main.js',
            array('jquery', 'kachi-core', 'kachi-ui', 'kachi-api'),
            KACHI_VERSION,
            true
        );
        
        // JavaScript 변수 전달 (메인 스크립트에 연결)
        $options = get_option('kachi_settings', array());
        wp_localize_script('kachi-main', 'kachi_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'site_url' => home_url(),
            'nonce' => wp_create_nonce('kachi_ajax_nonce'),
            'plugin_url' => KACHI_PLUGIN_URL,
            'is_user_logged_in' => is_user_logged_in(),
            'use_proxy' => true,
            'strings' => array(
                'loading' => __('까치가 AI에게 물어보러 가는 중...', 'kachi-query-system'),
                'error' => __('오류가 발생했습니다.', 'kachi-query-system'),
                'login_required' => __('로그인이 필요합니다.', 'kachi-query-system'),
                'stopped' => __('사용자에 의해 중지되었습니다.', 'kachi-query-system')
            )
        ));
    }
    
    /**
     * 플러그인 실행
     */
    public function run() {
        $this->loader->run();
    }
    
}

/**
 * 플러그인 초기화
 */
function kachi_query_system_init() {
    $plugin = Kachi_Query_System::get_instance();
    $plugin->run();
}

// 플러그인 로드
add_action('plugins_loaded', 'kachi_query_system_init', 20);

/**
 * 플러그인 활성화
 */
function kachi_query_system_activate() {
    // 기본 설정만 추가 (API URL은 프론트엔드 관리자에서 설정)
    $default_settings = array(
        'api_url' => '',
        'require_login' => 1,
        'enable_streaming' => 1,
        'max_query_length' => 500
    );
    
    // 기존 설정이 없을 때만 추가
    if (false === get_option('kachi_settings')) {
        add_option('kachi_settings', $default_settings);
    }
    
    // 시설물 정의 기본값 추가
    if (false === get_option('kachi_facility_definitions')) {
        add_option('kachi_facility_definitions', array());
    }
    
    // 데이터베이스 테이블 생성
    kachi_create_tables();
    
    // DB 버전 저장
    add_option('kachi_db_version', KACHI_DB_VERSION);
}
register_activation_hook(__FILE__, 'kachi_query_system_activate');

/**
 * 데이터베이스 테이블 생성
 */
function kachi_create_tables() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'kachi_conversations';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        conversation_id varchar(100) NOT NULL,
        title varchar(255) NOT NULL,
        messages longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY conversation_id (conversation_id),
        KEY user_conversation (user_id, conversation_id),
        KEY updated_at (updated_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * 플러그인 업데이트 체크
 */
function kachi_update_db_check() {
    if (get_site_option('kachi_db_version') != KACHI_DB_VERSION) {
        kachi_create_tables();
        update_option('kachi_db_version', KACHI_DB_VERSION);
    }
}
add_action('plugins_loaded', 'kachi_update_db_check');

/**
 * 플러그인 비활성화
 */
function kachi_query_system_deactivate() {
    // 비활성화 시 특별한 작업 없음
}
register_deactivation_hook(__FILE__, 'kachi_query_system_deactivate');

/**
 * 플러그인 삭제
 */
function kachi_query_system_uninstall() {
    global $wpdb;
    
    // 옵션 삭제
    delete_option('kachi_settings');
    delete_option('kachi_facility_definitions');
    delete_option('kachi_db_version');
    
    // 테이블 삭제
    $table_name = $wpdb->prefix . 'kachi_conversations';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'kachi_query_system_uninstall');