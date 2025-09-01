<?php
/**
 * Plugin Name: KACHI Analytics
 * Plugin URI: https://3chan.kr
 * Description: KACHI Python 백엔드 API와 통신하여 문서 통계를 시각화하는 대시보드 플러그인
 * Version: 2.2.0
 * Author: 3chan
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kachi-analytics
 * Domain Path: /languages
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('STATISTICS_DASHBOARD_VERSION', '2.4.2');
define('STATISTICS_DASHBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STATISTICS_DASHBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));

// 필요한 클래스 파일 로드
require_once STATISTICS_DASHBOARD_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
require_once STATISTICS_DASHBOARD_PLUGIN_DIR . 'includes/class-settings.php';
require_once STATISTICS_DASHBOARD_PLUGIN_DIR . 'includes/class-dashboard.php';

class StatisticsDashboardPlugin {
    
    private static $instance = null;
    private $ajax_handlers;
    private $settings;
    private $dashboard;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 클래스 인스턴스 생성
        $this->ajax_handlers = new SD_Ajax_Handlers();
        $this->settings = new SD_Settings();
        $this->dashboard = new SD_Dashboard();
        
        // 훅 등록
        add_action('init', array($this, 'init'));
        add_shortcode('statistics_dashboard', array($this->dashboard, 'render_dashboard_shortcode'));
        
        // 활성화/비활성화 훅
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 스타일 및 스크립트 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    public function init() {
        // 텍스트 도메인 로드
        load_plugin_textdomain('statistics-dashboard', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // 설정 옵션 초기화
        $this->init_options();
    }
    
    private function init_options() {
        // 기본 설정값
        $defaults = array(
            'api_base_url' => 'http://localhost:8000',
            'llm_gpu_api_url' => 'http://localhost:8000/gpu',
            'web_server_api_url' => 'https://example.com',
            'api_timeout' => 30,
            'cache_duration' => 300, // 5분
            'enable_cache' => true
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('sd_' . $key) === false) {
                add_option('sd_' . $key, $value);
            }
        }
    }
    
    public function activate() {
        // 캐시 테이블 생성
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'statistics_cache';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiration datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY expiration (expiration)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function enqueue_frontend_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'statistics_dashboard')) {
            // Chart.js - 버전 3.9.1 명시
            wp_enqueue_script('chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1');
            
            // CSS 파일들
            wp_enqueue_style('sd-base', STATISTICS_DASHBOARD_PLUGIN_URL . 'assets/css/base.css', array(), STATISTICS_DASHBOARD_VERSION);
            wp_enqueue_style('sd-components', STATISTICS_DASHBOARD_PLUGIN_URL . 'assets/css/components.css', array('sd-base'), STATISTICS_DASHBOARD_VERSION);
            wp_enqueue_style('sd-sections', STATISTICS_DASHBOARD_PLUGIN_URL . 'assets/css/sections.css', array('sd-components'), STATISTICS_DASHBOARD_VERSION);
            
            // JavaScript 파일들
            wp_enqueue_script('sd-core', STATISTICS_DASHBOARD_PLUGIN_URL . 'assets/js/core.js', array('jquery'), STATISTICS_DASHBOARD_VERSION, true);
            wp_enqueue_script('sd-charts', STATISTICS_DASHBOARD_PLUGIN_URL . 'assets/js/charts.js', array('sd-core', 'chartjs'), STATISTICS_DASHBOARD_VERSION, true);
            wp_enqueue_script('sd-ui-handlers', STATISTICS_DASHBOARD_PLUGIN_URL . 'assets/js/ui-handlers.js', array('sd-charts'), STATISTICS_DASHBOARD_VERSION, true);
            
            // Localization
            wp_localize_script('sd-core', 'sd_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sd_ajax_nonce'),
                'api_base_url' => get_option('sd_api_base_url'),
                'llm_gpu_api_url' => get_option('sd_llm_gpu_api_url'),
                'web_server_api_url' => get_option('sd_web_server_api_url'),
                'messages' => array(
                    'loading' => '데이터를 불러오는 중입니다...',
                    'error' => '데이터를 불러오는 중 오류가 발생했습니다',
                    'no_data' => '데이터가 없습니다',
                    'retry' => '다시 시도'
                )
            ));
        }
    }
}

// Initialize plugin
StatisticsDashboardPlugin::get_instance();