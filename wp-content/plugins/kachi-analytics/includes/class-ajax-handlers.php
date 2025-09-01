<?php
/**
 * AJAX Handlers Class
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class SD_Ajax_Handlers {
    
    public function __construct() {
        // AJAX 핸들러 등록
        add_action('wp_ajax_sd_get_statistics', array($this, 'get_statistics'));
        add_action('wp_ajax_sd_get_uploads_by_date', array($this, 'get_uploads_by_date'));
        add_action('wp_ajax_sd_get_storage_statistics', array($this, 'get_storage_statistics'));
        add_action('wp_ajax_sd_get_server_statistics', array($this, 'get_server_statistics'));
        add_action('wp_ajax_sd_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_sd_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_sd_clear_cache', array($this, 'clear_cache'));
        add_action('wp_ajax_sd_get_organization_data', array($this, 'get_organization_data'));
        
        // 추가 시스템 모니터링 핸들러
        add_action('wp_ajax_sd_get_system_info', array($this, 'get_system_info'));
        add_action('wp_ajax_sd_get_cpu_info', array($this, 'get_cpu_info'));
        add_action('wp_ajax_sd_get_memory_info', array($this, 'get_memory_info'));
        add_action('wp_ajax_sd_get_disk_info', array($this, 'get_disk_info'));
        add_action('wp_ajax_sd_get_network_info', array($this, 'get_network_info'));
        add_action('wp_ajax_sd_get_processes', array($this, 'get_processes'));
        add_action('wp_ajax_sd_get_temperature', array($this, 'get_temperature'));
        
        // 비로그인 사용자를 위한 AJAX 핸들러
        add_action('wp_ajax_nopriv_sd_get_statistics', array($this, 'get_statistics'));
        add_action('wp_ajax_nopriv_sd_get_uploads_by_date', array($this, 'get_uploads_by_date'));
        add_action('wp_ajax_nopriv_sd_get_storage_statistics', array($this, 'get_storage_statistics'));
        add_action('wp_ajax_nopriv_sd_get_server_statistics', array($this, 'get_server_statistics'));
        add_action('wp_ajax_nopriv_sd_get_organization_data', array($this, 'get_organization_data'));
    }
    
    /**
     * Convert uptime string to Korean format
     */
    private function convert_uptime_to_korean($uptime_str) {
        if (empty($uptime_str)) {
            return '-';
        }
        
        // If already in Korean format, return as is
        if (strpos($uptime_str, '일') !== false || strpos($uptime_str, '시간') !== false) {
            return $uptime_str;
        }
        
        // Parse "X days, HH:MM:SS" format
        if (preg_match('/(\d+)\s*days?,\s*(\d+):(\d+):(\d+)/', $uptime_str, $matches)) {
            $days = intval($matches[1]);
            $hours = intval($matches[2]);
            $minutes = intval($matches[3]);
            $seconds = intval($matches[4]);
            
            $result = '';
            if ($days > 0) $result .= $days . '일 ';
            if ($hours > 0) $result .= $hours . '시간 ';
            if ($minutes > 0) $result .= $minutes . '분 ';
            if ($seconds > 0 || $result == '') $result .= $seconds . '초';
            
            return trim($result);
        }
        
        // Parse "X hours, Y minutes, Z seconds" format
        if (strpos($uptime_str, 'hour') !== false || strpos($uptime_str, 'minute') !== false || strpos($uptime_str, 'second') !== false) {
            $days = 0;
            $hours = 0;
            $minutes = 0;
            $seconds = 0;
            
            // Extract days
            if (preg_match('/(\d+)\s*days?/', $uptime_str, $day_match)) {
                $days = intval($day_match[1]);
            }
            
            // Extract hours
            if (preg_match('/(\d+)\s*hours?/', $uptime_str, $hour_match)) {
                $hours = intval($hour_match[1]);
            }
            
            // Extract minutes
            if (preg_match('/(\d+)\s*minutes?/', $uptime_str, $minute_match)) {
                $minutes = intval($minute_match[1]);
            }
            
            // Extract seconds
            if (preg_match('/(\d+)\s*seconds?/', $uptime_str, $second_match)) {
                $seconds = intval($second_match[1]);
            }
            
            $result = '';
            if ($days > 0) $result .= $days . '일 ';
            if ($hours > 0) $result .= $hours . '시간 ';
            if ($minutes > 0) $result .= $minutes . '분 ';
            if ($seconds > 0 || $result == '') $result .= $seconds . '초';
            
            return trim($result);
        }
        
        // Parse timedelta format (e.g., "10 days, 5:30:45.123456")
        if (strpos($uptime_str, 'day') !== false) {
            $parts = explode(',', $uptime_str);
            $days = 0;
            $time_part = $uptime_str;
            
            if (count($parts) > 1) {
                $days_part = trim($parts[0]);
                $time_part = trim($parts[1]);
                if (preg_match('/(\d+)/', $days_part, $day_match)) {
                    $days = intval($day_match[1]);
                }
            }
            
            if (preg_match('/(\d+):(\d+):(\d+)/', $time_part, $time_match)) {
                $hours = intval($time_match[1]);
                $minutes = intval($time_match[2]);
                $seconds = intval($time_match[3]);
                
                $result = '';
                if ($days > 0) $result .= $days . '일 ';
                if ($hours > 0) $result .= $hours . '시간 ';
                if ($minutes > 0) $result .= $minutes . '분 ';
                if ($seconds > 0 || $result == '') $result .= $seconds . '초';
                
                return trim($result);
            }
        }
        
        // If parsing fails, return original
        return $uptime_str;
    }
    
    /**
     * 통계 데이터 가져오기
     */
    public function get_statistics() {
        // Verify nonce
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $sosok = sanitize_text_field($_POST['sosok'] ?? '');
        $buseo = sanitize_text_field($_POST['buseo'] ?? '');
        $site = sanitize_text_field($_POST['site'] ?? '');
        
        // 관리자 권한 체크
        $is_admin = ($sosok === '관리자' && $buseo === '관리자' && $site === '관리자');
        
        // Check cache first
        if (get_option('sd_enable_cache')) {
            $cache_key = 'statistics_' . md5($sosok . '_' . $buseo . '_' . $site);
            $cached_data = $this->get_cache($cache_key);
            if ($cached_data !== false) {
                wp_send_json_success($cached_data);
            }
        }
        
        // Make API request
        $api_url = get_option('sd_api_base_url') . '/statistics/';
        
        // Build query parameters
        $params = array();
        
        // 관리자가 아닌 경우 필터 적용
        if (!$is_admin) {
            if ($sosok !== '') {
                $params['sosok'] = $sosok;
            }
            if ($site !== '') {
                $params['site'] = $site;
            }
        }
        
        // Use add_query_arg for proper URL encoding
        if (!empty($params)) {
            $api_url = add_query_arg($params, $api_url);
        }
        
        $response = wp_remote_get($api_url, array(
            'timeout' => get_option('sd_api_timeout', 30),
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('API 연결 오류: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('API 응답 파싱 오류: ' . json_last_error_msg());
        }
        
        // Cache the data
        if (get_option('sd_enable_cache')) {
            $this->set_cache($cache_key, $data, get_option('sd_cache_duration', 300));
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * 날짜별 업로드 데이터 가져오기
     */
    public function get_uploads_by_date() {
        // Verify nonce
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $sosok = sanitize_text_field($_POST['sosok'] ?? '');
        $buseo = sanitize_text_field($_POST['buseo'] ?? '');
        $site = sanitize_text_field($_POST['site'] ?? '');
        $days = intval($_POST['days'] ?? 30);
        
        // 관리자 권한 체크
        $is_admin = ($sosok === '관리자' && $buseo === '관리자' && $site === '관리자');
        
        // Make API request
        $api_url = get_option('sd_api_base_url') . '/statistics/uploads-by-date/';
        
        // Build query parameters
        $params = array('days' => $days);
        
        // 관리자가 아닌 경우 필터 적용
        if (!$is_admin) {
            if ($sosok !== '') {
                $params['sosok'] = $sosok;
            }
            if ($site !== '') {
                $params['site'] = $site;
            }
        }
        
        $api_url = add_query_arg($params, $api_url);
        
        $response = wp_remote_get($api_url, array(
            'timeout' => get_option('sd_api_timeout', 30),
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('API 연결 오류: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('API 응답 파싱 오류: ' . json_last_error_msg());
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * 저장소 통계 가져오기
     */
    public function get_storage_statistics() {
        // Verify nonce
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $sosok = sanitize_text_field($_POST['sosok'] ?? '');
        $buseo = sanitize_text_field($_POST['buseo'] ?? '');
        $site = sanitize_text_field($_POST['site'] ?? '');
        
        // Make API request
        $api_url = get_option('sd_api_base_url') . '/statistics/storage/';
        
        // Add query parameters
        $params = array();
        if ($sosok !== '') {
            $params['sosok'] = $sosok;
        }
        if ($site !== '') {
            $params['site'] = $site;
        }
        
        if (!empty($params)) {
            $api_url = add_query_arg($params, $api_url);
        }
        
        error_log('Storage Stats API URL: ' . $api_url);
        
        $response = wp_remote_get($api_url, array(
            'timeout' => get_option('sd_api_timeout', 30),
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            error_log('Storage Stats API Error: ' . $response->get_error_message());
            wp_send_json_error('API 연결 오류: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON Decode Error: ' . json_last_error_msg());
            wp_send_json_error('API 응답 파싱 오류: ' . json_last_error_msg());
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * 서버 통계 가져오기
     */
    public function get_server_statistics() {
        // Verify nonce
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $sosok = sanitize_text_field($_POST['sosok'] ?? '');
        $buseo = sanitize_text_field($_POST['buseo'] ?? '');
        $site = sanitize_text_field($_POST['site'] ?? '');
        
        // Make API request
        $api_url = get_option('sd_api_base_url') . '/statistics/servers/';
        
        // Add query parameters
        $params = array();
        if ($sosok !== '') {
            $params['sosok'] = $sosok;
        }
        if ($site !== '') {
            $params['site'] = $site;
        }
        
        if (!empty($params)) {
            $api_url = add_query_arg($params, $api_url);
        }
        
        error_log('Server Stats API URL: ' . $api_url);
        
        $response = wp_remote_get($api_url, array(
            'timeout' => get_option('sd_api_timeout', 30),
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            error_log('Server Stats API Error: ' . $response->get_error_message());
            wp_send_json_error('API 연결 오류: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON Decode Error: ' . json_last_error_msg());
            wp_send_json_error('API 응답 파싱 오류: ' . json_last_error_msg());
        }
        
        // 관리자인 경우 추가 정보를 가져와서 보강
        if ($sosok === '관리자' && $buseo === '관리자' && $site === '관리자') {
            
            // AI Server에 GPU 정보 추가
            if (isset($data['ai_server'])) {
                // GPU API 호출 - 설정에서 LLM GPU API URL 가져오기
                $gpu_url = get_option('sd_llm_gpu_api_url');
                
                if ($gpu_url) {
                    error_log('Calling GPU API: ' . $gpu_url);
                    
                    $gpu_response = wp_remote_get($gpu_url, array(
                        'timeout' => get_option('sd_api_timeout', 30),
                        'headers' => array(
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json; charset=utf-8'
                        ),
                        'sslverify' => false
                    ));
                    
                    if (!is_wp_error($gpu_response)) {
                        $gpu_body = wp_remote_retrieve_body($gpu_response);
                        $gpu_data = json_decode($gpu_body, true);
                        
                        error_log('GPU API Response: ' . $gpu_body);
                        
                        // GPU 데이터가 있고 에러가 아닌 경우에만 추가
                        if ($gpu_data !== null && !isset($gpu_data['detail'])) {
                            // main.py의 GPU 정보를 별도 필드로 추가
                            $data['ai_server']['gpu_external'] = $gpu_data;
                            error_log('GPU external data added successfully');
                        } else {
                            error_log('GPU API returned error or invalid data');
                        }
                    } else {
                        error_log('GPU API call failed: ' . $gpu_response->get_error_message());
                    }
                } else {
                    error_log('LLM GPU API URL not configured');
                }
                
                // Convert AI Server uptime to Korean if needed
                if (isset($data['ai_server']['uptime'])) {
                    $data['ai_server']['uptime'] = $this->convert_uptime_to_korean($data['ai_server']['uptime']);
                }
            }
            
            // Web Server 상세 정보 가져오기
            if (isset($data['web_server'])) {
                // 설정된 URL 가져오기
                $web_server_url = get_option('sd_web_server_api_url');
                
                if ($web_server_url) {
                    error_log('Getting Web Server detailed info from: ' . $web_server_url);
                    
                    // 1. /all 엔드포인트로 전체 시스템 정보 가져오기
                    $all_url = rtrim($web_server_url, '/') . '/all';
                    error_log('Calling Web Server /all API: ' . $all_url);
                    
                    $all_response = wp_remote_get($all_url, array(
                        'timeout' => get_option('sd_api_timeout', 30),
                        'headers' => array(
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json; charset=utf-8'
                        ),
                        'sslverify' => false
                    ));
                    
                    if (!is_wp_error($all_response)) {
                        $all_body = wp_remote_retrieve_body($all_response);
                        $all_data = json_decode($all_body, true);
                        
                        if ($all_data !== null) {
                            // 시스템 정보를 Web Server 데이터에 병합
                            if (isset($all_data['system'])) {
                                $data['web_server']['hostname'] = $all_data['system']['hostname'];
                                $data['web_server']['ip_address'] = $all_data['system']['ip_address'];
                                $data['web_server']['platform'] = $all_data['system']['platform'];
                                $data['web_server']['os_info'] = $all_data['system']['platform'] . ' ' . $all_data['system']['platform_release'];
                                
                                // Convert uptime to Korean format
                                if (isset($all_data['system']['uptime'])) {
                                    $data['web_server']['uptime'] = $this->convert_uptime_to_korean($all_data['system']['uptime']);
                                }
                            }
                            
                            if (isset($all_data['cpu'])) {
                                $data['web_server']['cpu'] = array(
                                    'percent' => $all_data['cpu']['percent'],
                                    'count' => $all_data['cpu']['count'],
                                    'freq_current' => $all_data['cpu']['freq_current']
                                );
                            }
                            
                            if (isset($all_data['memory'])) {
                                $data['web_server']['memory'] = array(
                                    'percent' => $all_data['memory']['percent'],
                                    'total_gb' => $all_data['memory']['total_gb'],
                                    'used_gb' => $all_data['memory']['used_gb']
                                );
                            }
                            
                            if (isset($all_data['disk']) && !empty($all_data['disk'])) {
                                // 첫 번째 디스크 (보통 루트 파티션) 정보 사용
                                $main_disk = $all_data['disk'][0];
                                $data['web_server']['disk'] = array(
                                    'percent' => $main_disk['percent'],
                                    'total_gb' => $main_disk['total_gb'],
                                    'used_gb' => $main_disk['used_gb']
                                );
                            }
                            
                            if (isset($all_data['network'])) {
                                $data['web_server']['network'] = array(
                                    'bytes_sent_mb' => $all_data['network']['bytes_sent_mb'],
                                    'bytes_recv_mb' => $all_data['network']['bytes_recv_mb']
                                );
                            }
                            
                            if (isset($all_data['top_processes'])) {
                                $data['web_server']['top_processes'] = array_map(function($proc) {
                                    return array(
                                        'name' => $proc['name'],
                                        'cpu_percent' => $proc['cpu_percent'],
                                        'memory_mb' => $proc['memory_mb']
                                    );
                                }, $all_data['top_processes']);
                            }
                            
                            $data['web_server']['status'] = 'online';
                            error_log('Web Server detailed info retrieved successfully');
                        }
                    } else {
                        error_log('Web Server /all API call failed: ' . $all_response->get_error_message());
                        
                        // Fallback: /statistics/servers 엔드포인트 시도
                        $this->fallback_to_statistics_servers($data, $web_server_url);
                    }
                    
                    // 2. 온도 정보 가져오기 (선택사항)
                    $temp_url = rtrim($web_server_url, '/') . '/temperature';
                    $temp_response = wp_remote_get($temp_url, array(
                        'timeout' => 5,
                        'headers' => array('Accept' => 'application/json'),
                        'sslverify' => false
                    ));
                    
                    if (!is_wp_error($temp_response)) {
                        $temp_body = wp_remote_retrieve_body($temp_response);
                        $temp_data = json_decode($temp_body, true);
                        if ($temp_data !== null && !isset($temp_data['message'])) {
                            $data['web_server']['temperature'] = $temp_data;
                        }
                    }
                } else {
                    // 설정이 없는 경우
                    $data['web_server']['error'] = 'Web Server URL이 설정되지 않았습니다.';
                    error_log('Web Server API URL not configured');
                }
                
                // Ensure uptime is converted for web server
                if (isset($data['web_server']['uptime'])) {
                    $data['web_server']['uptime'] = $this->convert_uptime_to_korean($data['web_server']['uptime']);
                }
            }
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Fallback to /statistics/servers endpoint
     */
    private function fallback_to_statistics_servers(&$data, $web_server_url) {
        $stats_url = rtrim($web_server_url, '/') . '/statistics/servers/';
        $stats_params = array(
            'sosok' => '관리자',
            'site' => '관리자'
        );
        
        $stats_url = add_query_arg($stats_params, $stats_url);
        error_log('Fallback: Calling Web Server /statistics/servers API: ' . $stats_url);
        
        $stats_response = wp_remote_get($stats_url, array(
            'timeout' => get_option('sd_api_timeout', 30),
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'sslverify' => false
        ));
        
        if (!is_wp_error($stats_response)) {
            $status_code = wp_remote_retrieve_response_code($stats_response);
            $body = wp_remote_retrieve_body($stats_response);
            
            if ($status_code >= 200 && $status_code < 300) {
                $stats_data = json_decode($body, true);
                
                if ($stats_data && isset($stats_data['web_server'])) {
                    // Web Server 데이터로 교체
                    $data['web_server'] = $stats_data['web_server'];
                    
                    // Convert uptime to Korean
                    if (isset($data['web_server']['uptime'])) {
                        $data['web_server']['uptime'] = $this->convert_uptime_to_korean($data['web_server']['uptime']);
                    }
                    
                    error_log('Web Server stats retrieved successfully via fallback');
                } else {
                    error_log('Invalid response from Web Server stats API');
                    $data['web_server']['error'] = 'Invalid response format';
                }
            } else {
                $data['web_server']['status'] = 'error';
                $data['web_server']['error'] = 'HTTP ' . $status_code;
                error_log('Web Server stats API returned error: HTTP ' . $status_code);
            }
        } else {
            $data['web_server'] = array(
                'name' => 'WEB Server',
                'status' => 'error',
                'error' => $stats_response->get_error_message()
            );
            error_log('Web Server stats API call failed: ' . $stats_response->get_error_message());
        }
    }
    
    /**
     * KAC Login System 조직 데이터 가져오기
     */
    public function get_organization_data() {
        // Verify nonce
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // KAC Login System의 조직 데이터 가져오기
        $org_data = get_option('kac_organization_data', array());
        
        // 데이터가 없으면 기본값 설정
        if (empty($org_data)) {
            $org_data = array(
                "관리자" => array(
                    "관리자" => array("관리자")
                )
            );
        }
        
        wp_send_json_success($org_data);
    }
    
    /**
     * 설정 저장
     */
    public function save_settings() {
        // Verify nonce
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        // Save settings
        update_option('sd_api_base_url', sanitize_text_field($_POST['api_base_url']));
        update_option('sd_llm_gpu_api_url', sanitize_text_field($_POST['llm_gpu_api_url'] ?? ''));
        update_option('sd_web_server_api_url', sanitize_text_field($_POST['web_server_api_url'] ?? ''));
        update_option('sd_api_timeout', intval($_POST['api_timeout']));
        update_option('sd_enable_cache', intval($_POST['enable_cache']));
        update_option('sd_cache_duration', intval($_POST['cache_duration']));
        
        wp_send_json_success('설정이 저장되었습니다.');
    }
    
    /**
     * API 연결 테스트
     */
    public function test_connection() {
        // Verify nonce
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $api_url = sanitize_text_field($_POST['api_url']);
        $api_type = sanitize_text_field($_POST['api_type'] ?? 'main');
        
        if (!$api_url) {
            wp_send_json_error('API URL을 입력하세요.');
        }
        
        // 연결 테스트할 엔드포인트 결정
        $test_endpoint = '';
        switch ($api_type) {
            case 'gpu':
                $test_endpoint = $api_url; // GPU API는 직접 호출
                break;
            case 'web':
                $test_endpoint = $api_url; // Web Server도 직접 호출
                break;
            default:
                $test_endpoint = $api_url . '/health'; // Main API는 /health 엔드포인트
                break;
        }
        
        // Test connection
        $response = wp_remote_get($test_endpoint, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            ),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            wp_send_json_success('연결 성공');
        } else {
            wp_send_json_error('HTTP ' . $code);
        }
    }
    
    /**
     * 캐시 비우기
     */
    public function clear_cache() {
        // Verify nonce
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'statistics_cache';
        
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        
        if ($result !== false) {
            wp_send_json_success('캐시가 비워졌습니다.');
        } else {
            wp_send_json_error('캐시 비우기 실패');
        }
    }
    
    /**
     * 캐시 가져오기
     */
    private function get_cache($key) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'statistics_cache';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT cache_value FROM $table_name WHERE cache_key = %s AND expiration > NOW()",
            $key
        ));
        
        if ($result) {
            return json_decode($result->cache_value, true);
        }
        
        return false;
    }
    
    /**
     * 캐시 설정
     */
    private function set_cache($key, $value, $duration) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'statistics_cache';
        
        $expiration = date('Y-m-d H:i:s', time() + $duration);
        
        $wpdb->replace(
            $table_name,
            array(
                'cache_key' => $key,
                'cache_value' => json_encode($value),
                'expiration' => $expiration
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * 시스템 정보 가져오기
     */
    public function get_system_info() {
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $server_url = sanitize_text_field($_POST['server_url'] ?? '');
        if (!$server_url) {
            $server_url = get_option('sd_web_server_api_url');
        }
        
        if (!$server_url) {
            wp_send_json_error('서버 URL이 설정되지 않았습니다.');
        }
        
        $api_url = rtrim($server_url, '/') . '/system';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json'),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        wp_send_json_success($data);
    }
    
    /**
     * CPU 정보 가져오기
     */
    public function get_cpu_info() {
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $server_url = sanitize_text_field($_POST['server_url'] ?? '');
        if (!$server_url) {
            $server_url = get_option('sd_web_server_api_url');
        }
        
        if (!$server_url) {
            wp_send_json_error('서버 URL이 설정되지 않았습니다.');
        }
        
        $api_url = rtrim($server_url, '/') . '/cpu';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json'),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        wp_send_json_success($data);
    }
    
    /**
     * 메모리 정보 가져오기
     */
    public function get_memory_info() {
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $server_url = sanitize_text_field($_POST['server_url'] ?? '');
        if (!$server_url) {
            $server_url = get_option('sd_web_server_api_url');
        }
        
        if (!$server_url) {
            wp_send_json_error('서버 URL이 설정되지 않았습니다.');
        }
        
        $api_url = rtrim($server_url, '/') . '/memory';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json'),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        wp_send_json_success($data);
    }
    
    /**
     * 디스크 정보 가져오기
     */
    public function get_disk_info() {
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $server_url = sanitize_text_field($_POST['server_url'] ?? '');
        if (!$server_url) {
            $server_url = get_option('sd_web_server_api_url');
        }
        
        if (!$server_url) {
            wp_send_json_error('서버 URL이 설정되지 않았습니다.');
        }
        
        $api_url = rtrim($server_url, '/') . '/disk';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json'),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        wp_send_json_success($data);
    }
    
    /**
     * 네트워크 정보 가져오기
     */
    public function get_network_info() {
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $server_url = sanitize_text_field($_POST['server_url'] ?? '');
        if (!$server_url) {
            $server_url = get_option('sd_web_server_api_url');
        }
        
        if (!$server_url) {
            wp_send_json_error('서버 URL이 설정되지 않았습니다.');
        }
        
        $api_url = rtrim($server_url, '/') . '/network';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json'),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        wp_send_json_success($data);
    }
    
    /**
     * 프로세스 목록 가져오기
     */
    public function get_processes() {
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $server_url = sanitize_text_field($_POST['server_url'] ?? '');
        if (!$server_url) {
            $server_url = get_option('sd_web_server_api_url');
        }
        
        if (!$server_url) {
            wp_send_json_error('서버 URL이 설정되지 않았습니다.');
        }
        
        $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'cpu_percent');
        $limit = intval($_POST['limit'] ?? 10);
        
        $api_url = rtrim($server_url, '/') . '/processes';
        $api_url = add_query_arg(array(
            'sort_by' => $sort_by,
            'limit' => $limit
        ), $api_url);
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json'),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        wp_send_json_success($data);
    }
    
    /**
     * 온도 정보 가져오기
     */
    public function get_temperature() {
        if (!check_ajax_referer('sd_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        $server_url = sanitize_text_field($_POST['server_url'] ?? '');
        if (!$server_url) {
            $server_url = get_option('sd_web_server_api_url');
        }
        
        if (!$server_url) {
            wp_send_json_error('서버 URL이 설정되지 않았습니다.');
        }
        
        $api_url = rtrim($server_url, '/') . '/temperature';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json'),
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        wp_send_json_success($data);
    }
}