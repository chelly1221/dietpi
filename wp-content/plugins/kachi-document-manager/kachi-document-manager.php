<?php
/**
 * Plugin Name: KACHI Document Manager - AI Document System (Internal Network Version)
 * Plugin URI: https://3chan.kr/plugins/kachi-document-manager
 * Description: KACHI 내부망 전용 AI 기반 PDF/DOCX/PPTX/HWPX 문서 업로드, 분할 저장 및 태그 관리 시스템
 * Version: 3.0.3
 * Author: 3chan Development Team
 * Author URI: https://3chan.kr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kachi-document-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.2
 * Update URI: false
 * 
 * @package ThreeChan_PDF_Manager
 * @category Document_Management
 * @since 1.0.0
 * 
 * X-Plugin-Identifier: threechan-ai-docmanager-internal-2024
 * X-Internal-Network-Only: true
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('THREECHAN_PDF_VERSION', '3.0.3');
define('THREECHAN_PDF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('THREECHAN_PDF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('THREECHAN_PDF_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Internal network configuration
if (!defined('THREECHAN_API_INTERNAL_ONLY')) {
    define('THREECHAN_API_INTERNAL_ONLY', true);
}

// Include frontend admin class
require_once THREECHAN_PDF_PLUGIN_DIR . 'includes/class-frontend-admin.php';

/**
 * Main Plugin Class - Internal Network Version
 */
class ThreeChan_PDF_Manager {
    
    private static $instance = null;
    private $allowed_internal_ips = array();
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->set_internal_network_config();
        $this->init_hooks();
    }
    
    /**
     * Set internal network configuration
     */
    private function set_internal_network_config() {
        // Define allowed internal IP ranges
        $this->allowed_internal_ips = array(
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.1/32'
        );
        
        // Get internal API URL from wp-config.php or use default
        if (!defined('THREECHAN_API_INTERNAL_URL')) {
            define('THREECHAN_API_INTERNAL_URL', get_option('threechan_internal_api_url', 'http://10.0.0.100:8000'));
        }
        
        // WebSocket URL (for documentation purposes - actual WebSocket is proxied)
        if (!defined('THREECHAN_WS_INTERNAL_URL')) {
            $ws_host = str_replace('http://', 'ws://', THREECHAN_API_INTERNAL_URL);
            $ws_host = str_replace('https://', 'wss://', $ws_host);
            define('THREECHAN_WS_INTERNAL_URL', $ws_host);
        }
    }
    
    /**
     * Check if IP is from internal network
     */
    private function is_internal_ip($ip) {
        foreach ($this->allowed_internal_ips as $range) {
            if ($this->ip_in_range($ip, $range)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     */
    private function ip_in_range($ip, $cidr) {
        list($subnet, $bits) = explode('/', $cidr);
        if ($bits === null) {
            $bits = 32;
        }
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Action hooks
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_user_data_script'));
        
        // Remove admin menu
        add_action('admin_menu', array($this, 'remove_admin_menu'), 999);
        
        // Disable update checks for this plugin
        add_filter('site_transient_update_plugins', array($this, 'disable_plugin_updates'));
        add_filter('pre_set_site_transient_update_plugins', array($this, 'disable_plugin_updates'));
        
        // Force proxy mode for internal network
        add_filter('3chan_pdf_settings', array($this, 'force_proxy_mode'));
        
        // Shortcodes
        add_shortcode('3chan_pdf_manager', array($this, 'render_shortcode'));
        add_shortcode('3chan_pdf_admin', array('ThreeChan_PDF_Frontend_Admin', 'render_admin_shortcode'));
        
        // Release session lock for AJAX requests
        add_action('wp_ajax_3chan_proxy_api', array($this, 'release_session_lock'), 1);
        add_action('wp_ajax_nopriv_3chan_proxy_api', array($this, 'release_session_lock'), 1);
        add_action('wp_ajax_3chan_check_duplicate', array($this, 'release_session_lock'), 1);
        add_action('wp_ajax_3chan_update_tags', array($this, 'release_session_lock'), 1);
        add_action('wp_ajax_3chan_proxy_image', array($this, 'release_session_lock'), 1);
        add_action('wp_ajax_nopriv_3chan_proxy_image', array($this, 'release_session_lock'), 1);
        add_action('wp_ajax_3chan_proxy_websocket', array($this, 'release_session_lock'), 1);
        add_action('wp_ajax_nopriv_3chan_proxy_websocket', array($this, 'release_session_lock'), 1);
        
        // AJAX handlers
        add_action('wp_ajax_3chan_pdf_upload', array($this, 'handle_ajax_upload'));
        add_action('wp_ajax_nopriv_3chan_pdf_upload', array($this, 'handle_ajax_upload'));
        add_action('wp_ajax_3chan_get_settings', array($this, 'ajax_get_settings'));
        add_action('wp_ajax_3chan_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_3chan_proxy_api', array($this, 'handle_api_proxy'));
        add_action('wp_ajax_nopriv_3chan_proxy_api', array($this, 'handle_api_proxy'));
        add_action('wp_ajax_3chan_proxy_image', array($this, 'handle_image_proxy'));
        add_action('wp_ajax_nopriv_3chan_proxy_image', array($this, 'handle_image_proxy'));
        add_action('wp_ajax_3chan_proxy_websocket', array($this, 'handle_websocket_proxy'));
        add_action('wp_ajax_nopriv_3chan_proxy_websocket', array($this, 'handle_websocket_proxy'));
        
        // Additional AJAX handlers
        add_action('wp_ajax_3chan_delete_file', array($this, 'ajax_delete_file'));
        add_action('wp_ajax_3chan_check_duplicate', array($this, 'ajax_check_duplicate'));
        add_action('wp_ajax_3chan_update_tags', array($this, 'ajax_update_tags'));
        
        // Add PDF.js module support
        add_filter('script_loader_tag', array($this, 'add_module_type_to_scripts'), 10, 3);
        
        // Initialize frontend admin
        new ThreeChan_PDF_Frontend_Admin();
    }
    
    /**
     * Force proxy mode for internal network
     */
    public function force_proxy_mode($settings) {
        // Always use proxy in internal network mode
        $settings['use_proxy'] = true;
        $settings['api_url'] = THREECHAN_API_INTERNAL_URL;
        $settings['internal_network_only'] = true;
        return $settings;
    }
    
    /**
     * Release session lock to allow parallel AJAX requests
     */
    public function release_session_lock() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
    
    /**
     * Handle WebSocket Proxy - Real WebSocket implementation
     * This creates a true WebSocket connection to the backend
     */
    public function handle_websocket_proxy() {
        // Check nonce for security
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', '3chan-pdf-nonce')) {
            header('HTTP/1.1 403 Forbidden');
            exit('Security check failed');
        }
        
        // Verify internal network access
        $client_ip = $_SERVER['REMOTE_ADDR'];
        if (!$this->is_internal_ip($client_ip)) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied - External network');
        }
        
        // Get parameters
        $sosok = $_REQUEST['sosok'] ?? '';
        $site = $_REQUEST['site'] ?? '';
        
        // Check if this is a WebSocket upgrade request
        $upgrade = strtolower($_SERVER['HTTP_UPGRADE'] ?? '');
        $connection = strtolower($_SERVER['HTTP_CONNECTION'] ?? '');
        
        if ($upgrade === 'websocket' && strpos($connection, 'upgrade') !== false) {
            // This is a WebSocket upgrade request
            // We need to proxy it to the backend WebSocket server
            $this->proxy_websocket_connection($sosok, $site);
        } else {
            // This is a regular HTTP request - use Server-Sent Events as fallback
            $this->handle_sse_fallback($sosok, $site);
        }
    }
    
    /**
     * Proxy WebSocket connection to backend
     */
    private function proxy_websocket_connection($sosok, $site) {
        // WebSocket proxy is complex in PHP, use Server-Sent Events as fallback
        // For true WebSocket support, consider using a reverse proxy like nginx
        $this->handle_sse_fallback($sosok, $site);
    }
    
    /**
     * Handle Server-Sent Events as WebSocket fallback
     */
    private function handle_sse_fallback($sosok, $site) {
        // Set headers for Server-Sent Events
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering
        
        // Disable output buffering
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        while (@ob_end_flush());
        @apache_setenv('no-gzip', 1);
        
        // Send initial connection event
        echo "event: connected\n";
        echo "data: " . json_encode(['type' => 'connected']) . "\n\n";
        flush();
        
        // Keep connection alive and poll for updates
        $last_check = 0;
        $check_interval = 2; // Check every 2 seconds
        
        while (true) {
            // Check if client is still connected
            if (connection_aborted()) {
                break;
            }
            
            $current_time = time();
            
            // Check for updates every interval
            if ($current_time - $last_check >= $check_interval) {
                $last_check = $current_time;
                
                // Fetch tasks from API
                $api_url = THREECHAN_API_INTERNAL_URL;
                $url = $api_url . '/tasks/?';
                if ($sosok) $url .= 'sosok=' . urlencode($sosok) . '&';
                if ($site) $url .= 'site=' . urlencode($site);
                
                $context = stream_context_create(array(
                    'http' => array(
                        'timeout' => 5,
                        'ignore_errors' => true,
                        'header' => "X-Internal-Request: true\r\n"
                    ),
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    )
                ));
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (isset($data['tasks'])) {
                        // Send task update event
                        echo "event: task_update\n";
                        echo "data: " . json_encode([
                            'type' => 'task_update',
                            'tasks' => $data['tasks']
                        ]) . "\n\n";
                        flush();
                    }
                }
            }
            
            // Send heartbeat
            echo "event: ping\n";
            echo "data: " . json_encode(['type' => 'ping', 'time' => $current_time]) . "\n\n";
            flush();
            
            // Sleep for a bit
            sleep(1);
            
            // Timeout after 30 seconds of no activity
            if ($current_time - $_SERVER['REQUEST_TIME'] > 30) {
                break;
            }
        }
        
        exit;
    }
    
    /**
     * Handle Image Proxy Requests
     */
    public function handle_image_proxy() {
        // Check nonce for security
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', '3chan-pdf-nonce')) {
            header('HTTP/1.1 403 Forbidden');
            exit('Security check failed');
        }
        
        // Verify internal network access
        $client_ip = $_SERVER['REMOTE_ADDR'];
        if (!$this->is_internal_ip($client_ip)) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied - External network');
        }
        
        // Get the image URL
        $image_url = $_REQUEST['image_url'] ?? '';
        
        if (empty($image_url)) {
            header('HTTP/1.1 400 Bad Request');
            exit('Image URL required');
        }
        
        // Validate that the URL contains :8001/image pattern
        if (!preg_match('/:8001\/image/', $image_url)) {
            header('HTTP/1.1 400 Bad Request');
            exit('Invalid image URL');
        }
        
        // Log the request for debugging
        error_log('3chan PDF Manager (Internal) - Image Proxy Request: ' . $image_url);
        
        // Set appropriate headers for caching
        $cache_duration = 86400; // 24 hours
        header('Cache-Control: public, max-age=' . $cache_duration);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_duration) . ' GMT');
        
        // Fetch the image from the internal API
        $response = wp_remote_get($image_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'X-Internal-Request' => 'true',
                'X-Forwarded-For' => $client_ip
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('3chan PDF Manager (Internal) - Image Proxy Error: ' . $response->get_error_message());
            header('HTTP/1.1 502 Bad Gateway');
            exit('Failed to fetch image');
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        $body = wp_remote_retrieve_body($response);
        
        // Set content type header
        $content_type = $headers['content-type'] ?? 'image/jpeg';
        header('Content-Type: ' . $content_type);
        
        // Set content length if available
        if (isset($headers['content-length'])) {
            header('Content-Length: ' . $headers['content-length']);
        }
        
        // Output the image
        if ($status_code === 200) {
            echo $body;
        } else {
            header('HTTP/1.1 ' . $status_code);
            echo 'Image not found';
        }
        
        exit;
    }
    
    /**
     * Add module type to PDF.js scripts
     */
    public function add_module_type_to_scripts($tag, $handle, $src) {
        $module_scripts = array(
            '3chan-pdf-js',
            '3chan-pdf-worker'
        );
        
        if (in_array($handle, $module_scripts)) {
            $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        
        return $tag;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Add default options for internal network
        add_option('3chan_pdf_manager_version', THREECHAN_PDF_VERSION);
        add_option('3chan_pdf_manager_settings', array(
            'api_url' => THREECHAN_API_INTERNAL_URL,
            'max_file_size' => 50, // MB
            'allowed_file_types' => array('pdf', 'docx', 'pptx', 'hwpx'),
            'enable_notifications' => true,
            'default_page_size' => 10,
            'use_proxy' => true, // Always true for internal network
            'internal_network_only' => true,
            'primary_color' => '#a70638',
            'enable_auto_save' => true,
            'cache_duration' => 3600,
            'enable_duplicate_check' => true,
            'enable_websocket' => true // Enable WebSocket support
        ));
        
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/3chan-pdf-manager';
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary data
        delete_transient('3chan_pdf_manager_temp_data');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('3chan-pdf-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Register custom post type if needed
        $this->register_post_types();
    }
    
    /**
     * Register custom post types
     */
    private function register_post_types() {
        // Optional: Register a custom post type for documents
        register_post_type('3chan_document', array(
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => array('title', 'custom-fields'),
        ));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, '3chan_pdf_manager')) {
            
            // Get settings (already forced to proxy mode)
            $settings = get_option('3chan_pdf_manager_settings');
            $settings = apply_filters('3chan_pdf_settings', $settings);
            
            // Enqueue CSS
            wp_enqueue_style(
                '3chan-pdf-manager-style',
                THREECHAN_PDF_PLUGIN_URL . 'assets/css/style.css',
                array(),
                THREECHAN_PDF_VERSION . '.3'
            );
            
            // Enqueue PDF.js
            wp_enqueue_script(
                '3chan-pdf-js',
                THREECHAN_PDF_PLUGIN_URL . 'assets/js/pdf.mjs',
                array(),
                THREECHAN_PDF_VERSION,
                true
            );
            
            // Enqueue JavaScript
            wp_enqueue_script(
                '3chan-pdf-manager-core',
                THREECHAN_PDF_PLUGIN_URL . 'assets/js/pdf-manager-core-internal.js',
                array('jquery'),
                THREECHAN_PDF_VERSION . '.3',
                true
            );
            
            wp_enqueue_script(
                '3chan-pdf-manager-upload',
                THREECHAN_PDF_PLUGIN_URL . 'assets/js/pdf-manager-upload.js',
                array('3chan-pdf-manager-core'),
                THREECHAN_PDF_VERSION . '.5',
                true
            );
            
            wp_enqueue_script(
                '3chan-pdf-manager-documents',
                THREECHAN_PDF_PLUGIN_URL . 'assets/js/pdf-manager-documents.js',
                array('3chan-pdf-manager-core'),
                THREECHAN_PDF_VERSION . '.3',
                true
            );
            
            // Legacy script reference for backward compatibility
            wp_enqueue_script(
                '3chan-pdf-manager-script',
                THREECHAN_PDF_PLUGIN_URL . 'assets/js/script.js',
                array('jquery'),
                '0.0.1',
                true
            );
            
            // Enqueue UI enhancements
            wp_enqueue_script(
                '3chan-pdf-manager-ui',
                THREECHAN_PDF_PLUGIN_URL . 'assets/js/ui-enhancements.js',
                array('3chan-pdf-manager-core'),
                THREECHAN_PDF_VERSION,
                true
            );
            
            // Localize script - only proxy mode
            wp_localize_script('3chan-pdf-manager-core', 'threechan_pdf_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('3chan-pdf-nonce'),
                'plugin_url' => THREECHAN_PDF_PLUGIN_URL,
                'use_proxy' => true, // Always true for internal network
                'internal_network_only' => true,
                'enable_duplicate_check' => $settings['enable_duplicate_check'] ?? true,
                'enable_websocket' => $settings['enable_websocket'] ?? true,
                'max_file_size' => $settings['max_file_size'] * 1024 * 1024, // Convert to bytes
                'allowed_file_types' => $settings['allowed_file_types'],
                'websocket_proxy_url' => admin_url('admin-ajax.php') . '?action=3chan_proxy_websocket', // WebSocket proxy endpoint
                'strings' => array(
                    'upload_error' => __('업로드 중 오류가 발생했습니다.', '3chan-pdf-manager'),
                    'delete_confirm' => __('정말로 삭제하시겠습니까?', '3chan-pdf-manager'),
                    'loading' => __('로딩 중...', '3chan-pdf-manager'),
                    'duplicate_found' => __('동일한 이름의 파일이 존재합니다.', '3chan-pdf-manager'),
                    'overwrite_confirm' => __('기존 파일을 덮어쓰시겠습니까?', '3chan-pdf-manager'),
                    'keep_both' => __('두 파일 모두 유지', '3chan-pdf-manager'),
                    'overwrite' => __('덮어쓰기', '3chan-pdf-manager'),
                    'cancel' => __('취소', '3chan-pdf-manager'),
                    'internal_network_mode' => __('내부망 전용 모드', '3chan-pdf-manager'),
                    'websocket_connected' => __('실시간 연결됨', '3chan-pdf-manager'),
                    'websocket_disconnected' => __('연결 끊김', '3chan-pdf-manager')
                )
            ));
            
            // Add inline CSS for dynamic styles
            $custom_css = $this->get_custom_css($settings);
            if ($custom_css) {
                wp_add_inline_style('3chan-pdf-manager-style', $custom_css);
            }
        }
    }

    /**
     * Get custom CSS based on settings
     */
    private function get_custom_css($settings) {
        $css = '';
        
        // Primary color
        if (!empty($settings['primary_color'])) {
            $css .= ':root { --primary-color: ' . $settings['primary_color'] . '; }';
        }
        
        return $css;
    }
    
    /**
     * Add user data to page head
     */
    public function add_user_data_script() {
        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $user_data = array(
                'id' => $current_user_id,
                'name' => wp_get_current_user()->display_name,
                'email' => wp_get_current_user()->user_email,
                'role' => wp_get_current_user()->roles[0] ?? 'subscriber'
            );
            
            // Get user meta with unified keys
            $user_data['sosok'] = get_user_meta($current_user_id, 'kachi_sosok', true) ?: '';
            $user_data['site'] = get_user_meta($current_user_id, 'kachi_site', true) ?: '';
            
            echo '<script>';
            echo 'window.threechanUserData = ' . json_encode($user_data) . ';';
            echo 'window.userSosok = ' . json_encode($user_data['sosok'] ?? '') . ';';
            echo 'window.userSite = ' . json_encode($user_data['site'] ?? '') . ';';
            echo 'window.internalNetworkMode = true;'; // Flag for internal network mode
            echo '</script>';
        }
    }
    
    /**
     * AJAX: Check for duplicate files (Internal Network Version)
     */
    public function ajax_check_duplicate() {
        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Check nonce for security
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', '3chan-pdf-nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Verify internal network access
        $client_ip = $_SERVER['REMOTE_ADDR'];
        if (!$this->is_internal_ip($client_ip)) {
            wp_send_json_error(array('message' => 'Access denied - External network'));
            return;
        }
        
        $filenames = $_POST['filenames'] ?? array();
        $sosok = $_POST['sosok'] ?? '';
        $site = $_POST['site'] ?? '';
        
        if (empty($filenames)) {
            wp_send_json_error(array('message' => 'No filenames provided'));
            return;
        }
        
        // Use internal API URL
        $api_url = THREECHAN_API_INTERNAL_URL;
        
        $url = $api_url . '/check-duplicate/?';
        if ($sosok) $url .= 'sosok=' . urlencode($sosok) . '&';
        if ($site) $url .= 'site=' . urlencode($site) . '&';
        foreach ($filenames as $filename) {
            $url .= 'filenames=' . urlencode($filename) . '&';
        }
        $url = rtrim($url, '&');
        
        error_log('3chan PDF Manager (Internal) - Checking duplicates at: ' . $url);
        
        $response = wp_remote_post($url, array(
            'method' => 'POST',
            'timeout' => 5,
            'blocking' => true,
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Internal-Request' => 'true'
            ),
            'body' => json_encode(array(
                'filenames' => $filenames,
                'sosok' => $sosok,
                'site' => $site
            ))
        ));
        
        if (is_wp_error($response)) {
            error_log('3chan PDF Manager (Internal) - Duplicate check error: ' . $response->get_error_message());
            wp_send_json_success(array('duplicates' => array()));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['duplicates'])) {
            wp_send_json_success(array('duplicates' => $data['duplicates']));
        } else {
            wp_send_json_success(array('duplicates' => array()));
        }
    }

    /**
     * AJAX: Update document tags (Internal Network Version)
     */
    public function ajax_update_tags() {
        // Check nonce for security
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', '3chan-pdf-nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Verify internal network access
        $client_ip = $_SERVER['REMOTE_ADDR'];
        if (!$this->is_internal_ip($client_ip)) {
            wp_send_json_error(array('message' => 'Access denied - External network'));
            return;
        }
        
        $file_id = sanitize_text_field($_POST['file_id'] ?? '');
        $tags = sanitize_text_field($_POST['tags'] ?? '');
        
        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID is required'));
            return;
        }
        
        // Use internal API URL
        $api_url = THREECHAN_API_INTERNAL_URL;
        $url = $api_url . '/update-document-tags/?file_id=' . urlencode($file_id);
        
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Internal-Request' => 'true'
            ),
            'body' => array(
                'tags' => $tags
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('3chan PDF Manager (Internal) - Tag update error: ' . $response->get_error_message());
            wp_send_json_error(array('message' => 'Failed to update tags: ' . $response->get_error_message()));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['status']) && $data['status'] === 'success') {
            // Also update in local database if you have one
            global $wpdb;
            $table_name = $wpdb->prefix . '3chan_pdf_uploads';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $wpdb->update(
                    $table_name,
                    array('tags' => $tags),
                    array('file_id' => $file_id),
                    array('%s'),
                    array('%s')
                );
            }
            
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array('message' => $data['message'] ?? 'Failed to update tags'));
        }
    }
    
    /**
     * Handle API Proxy Requests - Internal Network Only (FIXED VERSION)
     */
    public function handle_api_proxy() {
        // Check nonce for security
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', '3chan-pdf-nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Verify internal network access
        $client_ip = $_SERVER['REMOTE_ADDR'];
        if (!$this->is_internal_ip($client_ip)) {
            error_log('3chan PDF Manager - External access attempt from IP: ' . $client_ip);
            wp_send_json_error(array('message' => 'Access denied - External network access not allowed'));
            return;
        }
        
        // Use internal API URL
        $api_url = THREECHAN_API_INTERNAL_URL;
        
        // Get the endpoint and method
        $endpoint = $_REQUEST['endpoint'] ?? '';
        $method = !empty($_REQUEST['method']) ? strtoupper($_REQUEST['method']) : 'GET';
        
        // For backward compatibility
        if ($method === 'GET') {
            $post_endpoints = array('upload-pdf/', 'create-document/', 'check-duplicate/', 'upload-async/');
            $delete_endpoints = array('delete-document/');
            $put_endpoints = array('update-document/', 'update-document-tags/');
            
            foreach ($post_endpoints as $pattern) {
                if (strpos($endpoint, $pattern) !== false) {
                    $method = 'POST';
                    break;
                }
            }
            
            foreach ($delete_endpoints as $pattern) {
                if (strpos($endpoint, $pattern) !== false) {
                    $method = 'DELETE';
                    break;
                }
            }
            
            foreach ($put_endpoints as $pattern) {
                if (strpos($endpoint, $pattern) !== false) {
                    $method = 'PUT';
                    break;
                }
            }
        }
        
        // Build the full URL
        $url = rtrim($api_url, '/') . '/' . ltrim($endpoint, '/');
        
        // Log the request for debugging
        error_log('3chan PDF Manager (Internal) - API Request: ' . $method . ' ' . $url);
        
        // Standard timeout for all requests
        $timeout = 30; // Default timeout
        
        // For async upload, server responds immediately after saving files
        if (strpos($endpoint, 'upload-async/') !== false) {
            error_log('3chan PDF Manager - Async upload endpoint detected');
        }
        // For synchronous upload (old endpoint), use longer timeout
        elseif (strpos($endpoint, 'upload-pdf/') !== false) {
            $timeout = 120; // Keep long timeout for old sync upload
        }
        
        $args = array(
            'method' => $method,
            'timeout' => $timeout,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Internal-Request' => 'true',
                'X-Forwarded-For' => $client_ip
            ),
            'sslverify' => false,
            'blocking' => true,
            'stream' => false,
            'decompress' => true
        );
        
        // Handle different request methods and data
        if ($method === 'POST' && !empty($_FILES)) {
            // Handle file upload with multipart/form-data
            $boundary = wp_generate_password(24);
            $args['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
            
            $body = '';
            
            // Add form fields first
            foreach ($_POST as $key => $value) {
                if ($key !== 'action' && $key !== 'nonce' && $key !== 'endpoint' && $key !== 'method') {
                    $body .= '--' . $boundary . "\r\n";
                    $body .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
                    $body .= $value . "\r\n";
                }
            }
            
            // Handle files
            $file_count = 0;
            
            if (!empty($_FILES['files'])) {
                if (isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
                    $file_count = count($_FILES['files']['name']);
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                            $body .= '--' . $boundary . "\r\n";
                            $body .= 'Content-Disposition: form-data; name="files"; filename="' . $_FILES['files']['name'][$i] . '"' . "\r\n";
                            $body .= 'Content-Type: ' . $_FILES['files']['type'][$i] . "\r\n\r\n";
                            
                            $file_content = file_get_contents($_FILES['files']['tmp_name'][$i]);
                            $body .= $file_content . "\r\n";
                        }
                    }
                } else {
                    if ($_FILES['files']['error'] === UPLOAD_ERR_OK) {
                        $body .= '--' . $boundary . "\r\n";
                        $body .= 'Content-Disposition: form-data; name="files"; filename="' . $_FILES['files']['name'] . '"' . "\r\n";
                        $body .= 'Content-Type: ' . $_FILES['files']['type'] . "\r\n\r\n";
                        
                        $file_content = file_get_contents($_FILES['files']['tmp_name']);
                        $body .= $file_content . "\r\n";
                        
                        $file_count = 1;
                    }
                }
            }
            
            foreach ($_FILES as $key => $file) {
                if ($key !== 'files' && strpos($key, 'file') !== false) {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $body .= '--' . $boundary . "\r\n";
                        $body .= 'Content-Disposition: form-data; name="files"; filename="' . $file['name'] . '"' . "\r\n";
                        $body .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";
                        
                        $file_content = file_get_contents($file['tmp_name']);
                        $body .= $file_content . "\r\n";
                        
                        $file_count++;
                    }
                }
            }
            
            error_log('3chan PDF Manager (Internal) - Total files processed: ' . $file_count);
            
            $body .= '--' . $boundary . '--';
            $args['body'] = $body;
            
        } elseif ($method === 'POST' && strpos($endpoint, 'dismiss-completed-tasks') !== false) {
            // Special handling for dismiss-completed-tasks endpoint
            $args['headers']['Content-Type'] = 'multipart/form-data; boundary=' . wp_generate_password(24);
            
            $boundary = $args['headers']['Content-Type'];
            $boundary = substr($boundary, strpos($boundary, 'boundary=') + 9);
            
            // Get parameters from POST data first, then query string as fallback
            $sosok = isset($_POST['sosok']) ? $_POST['sosok'] : ($_REQUEST['sosok'] ?? '');
            $site = isset($_POST['site']) ? $_POST['site'] : ($_REQUEST['site'] ?? '');
            
            // Build multipart form data
            $body = '';
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="sosok"' . "\r\n\r\n";
            $body .= $sosok . "\r\n";
            
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="site"' . "\r\n\r\n";
            $body .= $site . "\r\n";
            
            $body .= '--' . $boundary . '--';
            
            $args['body'] = $body;
            
            error_log('3chan PDF Manager (Internal) - Dismiss tasks for: sosok=' . $sosok . ', site=' . $site);
            
        } elseif ($method === 'PUT' && strpos($endpoint, 'update-document-tags') !== false) {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            
            $form_data = array();
            foreach ($_POST as $key => $value) {
                if ($key !== 'action' && $key !== 'nonce' && $key !== 'endpoint' && $key !== 'method') {
                    $form_data[$key] = $value;
                }
            }
            
            $args['body'] = http_build_query($form_data);
            
        } elseif (($method === 'POST' || $method === 'PUT') && empty($_FILES)) {
            $all_params = $_POST;
            unset($all_params['action'], $all_params['nonce'], $all_params['endpoint'], $all_params['method']);
            
            if (!empty($all_params)) {
                $args['body'] = json_encode($all_params);
            }
        }
        
        // Make the request
        $response = wp_remote_request($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('3chan PDF Manager (Internal) - API Error: ' . $response->get_error_message());
            
            wp_send_json_error(array(
                'message' => 'API request failed',
                'error' => $response->get_error_message()
            ));
            return;
        }
        
        // Get response body and headers
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Log the response for debugging
        error_log('3chan PDF Manager (Internal) - API Response: ' . $status_code . ' - Length: ' . strlen($body));
        
        // For async upload, handle response specially
        if (strpos($endpoint, 'upload-async/') !== false) {
            error_log('3chan PDF Manager - Processing async upload response');
            error_log('3chan PDF Manager - Response status code: ' . $status_code);
            
            // Check if response is successful
            if ($status_code === 200) {
                // Try to parse JSON response
                $data = json_decode($body, true);
                
                if (json_last_error() === JSON_ERROR_NONE && $data) {
                    error_log('3chan PDF Manager - Parsed async upload response: ' . json_encode($data));
                    
                    // Return the response as WordPress AJAX success
                    wp_send_json_success($data);
                    return;
                } else {
                    // JSON parsing failed
                    error_log('3chan PDF Manager - JSON parse error: ' . json_last_error_msg());
                    wp_send_json_error(array(
                        'message' => 'Invalid JSON response from server',
                        'parse_error' => json_last_error_msg(),
                        'raw_response' => substr($body, 0, 200)
                    ));
                    return;
                }
            } else {
                // Non-200 status code
                wp_send_json_error(array(
                    'message' => 'Upload failed with status ' . $status_code,
                    'status_code' => $status_code,
                    'response' => substr($body, 0, 200)
                ));
                return;
            }
        }
        
        // For all other endpoints, return response as-is
        if (isset($headers['content-type'])) {
            header('Content-Type: ' . $headers['content-type']);
        }
        
        http_response_code($status_code);
        echo $body;
        wp_die();
    }
    
    /**
     * Remove admin menu (프론트엔드로 이동)
     */
    public function remove_admin_menu() {
        // 기존 관리자 메뉴 제거
        remove_menu_page('3chan-pdf-manager');
    }
    
    /**
     * Disable plugin updates
     */
    public function disable_plugin_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_file = THREECHAN_PDF_PLUGIN_BASENAME;
        
        // Remove this plugin from update list
        if (isset($transient->response[$plugin_file])) {
            unset($transient->response[$plugin_file]);
        }
        
        // Also add to no_update list to prevent false positives
        if (!isset($transient->no_update)) {
            $transient->no_update = array();
        }
        
        $plugin_data = get_plugin_data(__FILE__);
        
        $transient->no_update[$plugin_file] = (object) array(
            'id' => $plugin_file,
            'slug' => dirname($plugin_file),
            'plugin' => $plugin_file,
            'new_version' => $plugin_data['Version'],
            'url' => $plugin_data['PluginURI'],
            'package' => false,
            'icons' => array(),
            'banners' => array(),
            'banners_rtl' => array(),
            'tested' => get_bloginfo('version'),
            'requires_php' => $plugin_data['RequiresPHP'],
            'compatibility' => new stdClass(),
        );
        
        return $transient;
    }
    
    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $settings = get_option('3chan_pdf_manager_settings');
        $settings = apply_filters('3chan_pdf_settings', $settings);
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'fullpage' => 'yes',
            'theme' => 'default'
        ), $atts, '3chan_pdf_manager');
        
        // Start output buffering
        ob_start();
        
        // Add wrapper class based on attributes
        $wrapper_class = 'threechan-pdf-wrapper';
        if ($atts['fullpage'] === 'yes') {
            $wrapper_class .= ' fullpage-mode';
        }
        if ($atts['theme'] !== 'default') {
            $wrapper_class .= ' theme-' . sanitize_html_class($atts['theme']);
        }
        $wrapper_class .= ' internal-network-mode';
        
        echo '<div class="' . esc_attr($wrapper_class) . '">';
        
        // Include template
        include THREECHAN_PDF_PLUGIN_DIR . 'templates/pdf-manager.php';
        
        echo '</div>';
        
        // Return buffered content
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX upload
     */
    public function handle_ajax_upload() {
        // This is now handled by the proxy
        wp_send_json_error(array('message' => 'Use proxy endpoint'));
    }
    
    /**
     * AJAX: Get settings
     */
    public function ajax_get_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $settings = get_option('3chan_pdf_manager_settings');
        $settings = apply_filters('3chan_pdf_settings', $settings);
        wp_send_json_success($settings);
    }
    
    /**
     * AJAX: Save settings (Internal Network Version)
     */
    public function ajax_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], '3chan-pdf-nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Parse the form data
        parse_str($_POST['form'], $form_data);
        
        $settings = array(
            'api_url' => THREECHAN_API_INTERNAL_URL, // Always use internal URL
            'max_file_size' => intval($form_data['max_file_size'] ?? 50),
            'allowed_file_types' => array_map('sanitize_text_field', $form_data['allowed_file_types'] ?? array()),
            'enable_notifications' => isset($form_data['enable_notifications']),
            'default_page_size' => intval($form_data['default_page_size'] ?? 10),
            'primary_color' => sanitize_hex_color($form_data['primary_color'] ?? '#a70638'),
            'enable_auto_save' => isset($form_data['enable_auto_save']),
            'cache_duration' => intval($form_data['cache_duration'] ?? 3600),
            'use_proxy' => true, // Always true for internal network
            'internal_network_only' => true,
            'enable_duplicate_check' => isset($form_data['enable_duplicate_check']),
            'enable_websocket' => isset($form_data['enable_websocket'])
        );
        
        update_option('3chan_pdf_manager_settings', $settings);
        wp_send_json_success(array('message' => __('설정이 저장되었습니다.', '3chan-pdf-manager')));
    }
    
    /**
     * AJAX: Delete file (Internal Network Version)
     */
    public function ajax_delete_file() {
        if (!wp_verify_nonce($_POST['nonce'], '3chan-pdf-nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Verify internal network access
        $client_ip = $_SERVER['REMOTE_ADDR'];
        if (!$this->is_internal_ip($client_ip)) {
            wp_send_json_error('Access denied - External network');
            return;
        }
        
        $file_id = sanitize_text_field($_POST['file_id']);
        
        // Call API to delete file
        $api_url = THREECHAN_API_INTERNAL_URL;
        
        $response = wp_remote_request($api_url . '/delete-document/?file_id=' . $file_id, array(
            'method' => 'DELETE',
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'X-Internal-Request' => 'true'
            )
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            // Also delete from local database if exists
            global $wpdb;
            $table_name = $wpdb->prefix . '3chan_pdf_uploads';
            $wpdb->update(
                $table_name,
                array('status' => 'deleted'),
                array('file_id' => $file_id),
                array('%s'),
                array('%s')
            );
            
            wp_send_json_success(array('message' => __('파일이 삭제되었습니다.', '3chan-pdf-manager')));
        } else {
            wp_send_json_error(__('파일 삭제 실패', '3chan-pdf-manager'));
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // PDF uploads table
        $table_name = $wpdb->prefix . '3chan_pdf_uploads';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            file_id varchar(255) NOT NULL,
            filename varchar(255) NOT NULL,
            original_filename varchar(255) NOT NULL,
            file_size bigint(20) NOT NULL,
            file_type varchar(50) NOT NULL,
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            tags text,
            sosok varchar(255),
            site varchar(255),
            status varchar(50) DEFAULT 'active',
            meta_data text,
            upload_ip varchar(45),
            internal_access tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY file_id (file_id),
            KEY status (status),
            KEY upload_date (upload_date),
            KEY sosok_site_filename (sosok, site, original_filename)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add version option
        add_option('3chan_pdf_db_version', '1.2.0');
    }
}

/**
 * Initialize plugin
 */
function threechan_pdf_manager_init() {
    return ThreeChan_PDF_Manager::get_instance();
}

// Start the plugin
threechan_pdf_manager_init();

/**
 * Template functions for developers
 */

/**
 * Display PDF manager
 */
function threechan_pdf_manager_display($args = array()) {
    $defaults = array(
        'fullpage' => 'yes',
        'theme' => 'default'
    );
    $args = wp_parse_args($args, $defaults);
    
    echo do_shortcode('[3chan_pdf_manager fullpage="' . esc_attr($args['fullpage']) . '" theme="' . esc_attr($args['theme']) . '"]');
}

/**
 * Get user's uploaded files
 */
function threechan_pdf_get_user_files($user_id = null) {
    global $wpdb;
    
    if (null === $user_id) {
        $user_id = get_current_user_id();
    }
    
    $table_name = $wpdb->prefix . '3chan_pdf_uploads';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d AND status = 'active' ORDER BY upload_date DESC",
        $user_id
    ));
    
    return $results;
}

/**
 * Get file by ID
 */
function threechan_pdf_get_file($file_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . '3chan_pdf_uploads';
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE file_id = %s",
        $file_id
    ));
    
    return $result;
}

/**
 * Delete file
 */
function threechan_pdf_delete_file($file_id, $user_id = null) {
    global $wpdb;
    
    if (null === $user_id) {
        $user_id = get_current_user_id();
    }
    
    $table_name = $wpdb->prefix . '3chan_pdf_uploads';
    
    // Soft delete
    $result = $wpdb->update(
        $table_name,
        array('status' => 'deleted'),
        array('file_id' => $file_id, 'user_id' => $user_id),
        array('%s'),
        array('%s', '%d')
    );
    
    return $result !== false;
}

/**
 * Helper function to add/update user meta for sosok and site
 */
function threechan_pdf_update_user_info($user_id, $sosok = '', $site = '') {
    if ($sosok !== '') {
        update_user_meta($user_id, 'kachi_sosok', sanitize_text_field($sosok));
    }
    
    if ($site !== '') {
        update_user_meta($user_id, 'kachi_site', sanitize_text_field($site));
    }
}

/**
 * Helper function to get user's sosok and site information
 */
function threechan_pdf_get_user_info($user_id = null) {
    if (null === $user_id) {
        $user_id = get_current_user_id();
    }
    
    return array(
        'sosok' => get_user_meta($user_id, 'kachi_sosok', true) ?: '',
        'site' => get_user_meta($user_id, 'kachi_site', true) ?: ''
    );
}

/**
 * Get internal network status
 */
function threechan_pdf_is_internal_network() {
    return defined('THREECHAN_API_INTERNAL_ONLY') && THREECHAN_API_INTERNAL_ONLY;
}