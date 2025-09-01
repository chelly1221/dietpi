<?php
/**
 * Plugin Name: KACHI Update
 * Plugin URI: https://3chan.kr
 * Description: KACHI 버전 업데이트 공지사항을 관리하고 표시하는 플러그인
 * Version: 2.0.0
 * Author: 3chan
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * Text Domain: kachi-update
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('VERSION_NOTICE_VERSION', '2.0.5');
define('VERSION_NOTICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VERSION_NOTICE_PLUGIN_URL', plugin_dir_url(__FILE__));

// 클래스 파일 포함
require_once VERSION_NOTICE_PLUGIN_DIR . 'includes/class-version-notice-frontend-admin.php';

class VersionNoticePlugin {
    
    private static $instance = null;
    private $db_version = '1.0';
    private $table_name;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'version_notices';
        
        // 훅 등록
        add_action('init', array($this, 'init'));
        add_shortcode('version_notice', array($this, 'render_shortcode'));
        add_shortcode('version_notice_admin', array('Version_Notice_Frontend_Admin', 'render_admin_shortcode'));
        
        // 활성화/비활성화 훅
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 스타일 및 스크립트 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // 배경 이미지 스타일 추가
        add_action('wp_head', array($this, 'add_background_styles'));
        
        // 프론트엔드 관리자 초기화
        new Version_Notice_Frontend_Admin();
        
        // 기존 워드프레스 관리자 메뉴 제거
        add_action('admin_menu', array($this, 'remove_admin_menu'), 999);
    }
    
    public function init() {
        // 텍스트 도메인 로드
        load_plugin_textdomain('version-notice', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        // 테이블 생성
        $this->create_database_table();
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_database_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            version_number varchar(20) NOT NULL,
            notice_date varchar(50) NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            year_month varchar(7) NOT NULL,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_year_month (year_month),
            KEY idx_sort_order (sort_order)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // 테이블이 생성되었는지 확인
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") == $this->table_name;
        
        if (!$table_exists) {
            // dbDelta가 실패한 경우 직접 쿼리 실행
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                wp_die('Failed to create database table. Error: ' . $wpdb->last_error . '<br>SQL: <pre>' . $sql . '</pre>');
            }
        }
        
        add_option('version_notice_db_version', $this->db_version);
        
        // 배경 이미지 설정 옵션 추가
        add_option('version_notice_background_image', '');
        add_option('version_notice_background_pages', array());
    }
    
    public function remove_admin_menu() {
        // 기존 관리자 메뉴 제거 (프론트엔드로 이동)
        // remove_menu_page('version-notice');
    }
    
    public function enqueue_frontend_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'version_notice')) {
            wp_enqueue_style('version-notice-frontend', VERSION_NOTICE_PLUGIN_URL . 'assets/frontend.css', array(), VERSION_NOTICE_VERSION);
        }
    }
    
    public function render_shortcode($atts) {
        // 공지사항 데이터 가져오기
        global $wpdb;
        
        // 테이블 이름 확인
        $table_name = $wpdb->prefix . 'version_notices';
        
        $query = "SELECT * FROM `{$table_name}` ORDER BY `year_month` DESC, `sort_order` ASC, `id` DESC";
        $notices = $wpdb->get_results($query);
        
        // 연월별로 그룹화
        $grouped_notices = array();
        if ($notices && is_array($notices)) {
            foreach ($notices as $notice) {
                $year_month = $notice->year_month;
                if (!isset($grouped_notices[$year_month])) {
                    $grouped_notices[$year_month] = array();
                }
                $grouped_notices[$year_month][] = $notice;
            }
        }
        
        ob_start();
        ?>
        <div class="version-log-wrapper-kac">
            <h2 class="version-title">공지사항</h2>
            <p class="version-subtitle">버전 업데이트</p>
            
            <?php if (!empty($grouped_notices)): ?>
                <?php foreach ($grouped_notices as $year_month => $month_notices): ?>
                    <?php
                    $year = substr($year_month, 0, 4);
                    $month = intval(substr($year_month, 5, 2));
                    ?>
                    <div class="version-section">
                        <h3 class="version-month"><?php echo $year; ?>년 <?php echo $month; ?>월</h3>
                        
                        <?php foreach ($month_notices as $notice): ?>
                            <details class="version-entry">
                                <summary>
                                    <span class="version-tag"><?php echo esc_html($notice->version_number); ?></span>
                                    <?php if (!empty($notice->notice_date)): ?>
                                        <?php echo esc_html($notice->notice_date); ?> — 
                                    <?php endif; ?>
                                    <?php echo esc_html($notice->title); ?>
                                </summary>
                                <ul>
                                    <?php echo wp_kses_post($notice->content); ?>
                                </ul>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>공지사항이 없습니다.</p>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    public function add_background_styles() {
        global $post;
        
        // 현재 페이지에 version_notice 숏코드가 있는지 확인
        if (!is_singular() || !is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'version_notice')) {
            return;
        }
        
        // 설정에서 배경 이미지 정보 가져오기
        $background_image = get_option('version_notice_background_image', '');
        $background_pages = get_option('version_notice_background_pages', array());
        
        // 배경 이미지가 설정되어 있지 않으면 리턴
        if (empty($background_image)) {
            return;
        }
        
        $current_page_id = get_the_ID();
        
        // 모든 페이지에 적용하거나, 현재 페이지가 선택된 페이지 목록에 있는 경우
        if (empty($background_pages) || in_array($current_page_id, $background_pages)) {
            ?>
            <style>
                body {
                    background-image: url("<?php echo esc_url($background_image); ?>") !important;
                    background-repeat: no-repeat !important;
                    background-position: center top !important;
                    background-attachment: fixed !important;
                    background-size: cover !important;
                }
                
                /* 콘텐츠 영역에 배경색 추가하여 가독성 향상 */
                .site-content,
                .content-area,
                main {
                    background-color: rgba(255, 255, 255, 0.95);
                    padding: 20px;
                    border-radius: 10px;
                    margin: 20px auto;
                }
            </style>
            <?php
        }
    }
}

// 플러그인 초기화
VersionNoticePlugin::get_instance();