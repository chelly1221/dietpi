<?php
/**
 * AJAX 처리 클래스 - 프록시 API 버전
 *
 * @package Kachi_Query_System
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX 요청을 처리하는 클래스
 */
class Kachi_Ajax {
    
    /**
     * 쿼리 처리 - 프록시 방식
     */
    public function handle_query() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            wp_send_json_error(array('message' => '보안 검증 실패'));
            wp_die();
        }
        
        // 로그인 확인 (설정에 따라)
        $options = get_option('kachi_settings');
        $require_login = isset($options['require_login']) ? $options['require_login'] : 1;
        
        if ($require_login && !is_user_logged_in()) {
            wp_send_json_error(array('message' => '로그인이 필요합니다.'));
            wp_die();
        }
        
        // 쿼리 데이터 가져오기
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $tags = isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array();
        $docs = isset($_POST['docs']) ? array_map('sanitize_text_field', $_POST['docs']) : array();
        
        if (empty($query)) {
            wp_send_json_error(array('message' => '질문을 입력해주세요.'));
            wp_die();
        }
        
        // 사용자 정보 추가
        $user_data = array();
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $sosok = get_user_meta($user_id, 'kachi_sosok', true);
            $site = get_user_meta($user_id, 'kachi_site', true);
            
            if ($sosok) $user_data['sosok'] = $sosok;
            if ($site) $user_data['site'] = $site;
        }
        
        // 스트리밍 응답 시작
        $this->stream_query_response($query, $tags, $docs, $user_data);
        wp_die();
    }
    
    /**
     * 스트리밍 쿼리 응답
     */
    private function stream_query_response($query, $tags, $docs, $user_data) {
        // 스트리밍 준비
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        
        while (@ob_end_flush());
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        
        // API 설정
        $options = get_option('kachi_settings');
        $api_url = isset($options['api_url']) ? $options['api_url'] : '';
        $endpoint = '/query-stream/';
        
        // 쿼리 파라미터를 수동으로 구성 (FastAPI 형식에 맞게)
        $query_parts = array();
        $query_parts[] = 'user_query=' . urlencode($query);
        
        // 태그 처리 - 각 태그를 개별 파라미터로
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $query_parts[] = 'tags=' . urlencode($tag);
            }
        }
        
        // 문서 처리 - 각 문서를 개별 파라미터로
        if (!empty($docs)) {
            foreach ($docs as $doc) {
                $query_parts[] = 'doc_names=' . urlencode($doc);
            }
        }
        
        // 사용자 정보
        if (!empty($user_data['sosok'])) {
            $query_parts[] = 'sosok=' . urlencode($user_data['sosok']);
        }
        
        if (!empty($user_data['site'])) {
            $query_parts[] = 'site=' . urlencode($user_data['site']);
        }
        
        // URL 구성
        $url = $api_url . $endpoint . '?' . implode('&', $query_parts);
        
        // 디버깅용 로그
        error_log("Kachi Query URL: " . $url);
        
        // cURL을 사용한 스트리밍 요청
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: text/event-stream',
            'Cache-Control: no-cache'
        ));
        
        // 버퍼링 비활성화
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 1);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        
        // SSL 검증 비활성화 (개발 환경용)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // 스트리밍 데이터 처리
        $buffer = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer) {
            $buffer .= $data;
            
            // SSE 형식으로 파싱
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // 마지막 불완전한 라인은 버퍼에 보관
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // SSE 데이터 라인 처리
                if (strpos($line, 'data: ') === 0) {
                    echo $line . "\n\n";
                    flush();
                }
            }
            
            return strlen($data);
        });
        
        // 요청 실행
        $result = curl_exec($ch);
        
        // 남은 버퍼 처리
        if (!empty($buffer)) {
            $line = trim($buffer);
            if (strpos($line, 'data: ') === 0) {
                echo $line . "\n\n";
                flush();
            }
        }
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            error_log("cURL error: " . $error_msg);
            echo "data: " . json_encode(array('error' => $error_msg)) . "\n\n";
            flush();
        }
        
        curl_close($ch);
        
        // 스트림 종료
        echo "data: [DONE]\n\n";
        flush();
    }
    
    /**
     * 문서 정보 가져오기 - 프록시 방식
     */
    public function get_query_documents() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            wp_send_json_error(array('message' => '보안 검증 실패'));
            wp_die();
        }
        
        // 파라미터 가져오기
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $tags = isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array();
        $docs = isset($_POST['docs']) ? array_map('sanitize_text_field', $_POST['docs']) : array();
        
        if (empty($query)) {
            wp_send_json_error(array('message' => '질문이 필요합니다.'));
            wp_die();
        }
        
        $options = get_option('kachi_settings');
        $api_url = isset($options['api_url']) ? $options['api_url'] : '';
        
        // 쿼리 파라미터를 수동으로 구성 (FastAPI 형식에 맞게)
        $query_parts = array();
        $query_parts[] = 'user_query=' . urlencode($query);
        
        // 태그 처리 - 각 태그를 개별 파라미터로
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $query_parts[] = 'tags=' . urlencode($tag);
            }
        }
        
        // 문서 처리 - 각 문서를 개별 파라미터로
        if (!empty($docs)) {
            foreach ($docs as $doc) {
                $query_parts[] = 'doc_names=' . urlencode($doc);
            }
        }
        
        // 사용자 정보 추가
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $sosok = get_user_meta($user_id, 'kachi_sosok', true);
            $site = get_user_meta($user_id, 'kachi_site', true);
            
            if ($sosok) $query_parts[] = 'sosok=' . urlencode($sosok);
            if ($site) $query_parts[] = 'site=' . urlencode($site);
        }
        
        $url = $api_url . '/query-documents/?' . implode('&', $query_parts);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            wp_die();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => '문서 데이터 파싱 오류'));
            wp_die();
        }
        
        wp_send_json_success($data);
        wp_die();
    }
    
    /**
     * 태그 목록 가져오기
     */
    public function get_tags() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            wp_send_json_error(array('message' => '보안 검증 실패'));
            wp_die();
        }
        
        $options = get_option('kachi_settings');
        $api_url = isset($options['api_url']) ? $options['api_url'] : '';
        
        $url = $api_url . '/list-tags/';
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        );
        
        // 사용자 필터 추가
        $params = array();
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $sosok = get_user_meta($user_id, 'kachi_sosok', true);
            $site = get_user_meta($user_id, 'kachi_site', true);
            
            if ($sosok) $params['sosok'] = sanitize_text_field($sosok);
            if ($site) $params['site'] = sanitize_text_field($site);
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Kachi Tags API Error: ' . $response->get_error_message());
            wp_send_json_error(array('message' => $response->get_error_message()));
            wp_die();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => '태그 데이터 파싱 오류'));
            wp_die();
        }
        
        wp_send_json_success($data);
        wp_die();
    }
    
    /**
     * 문서 목록 가져오기
     */
    public function get_documents() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            wp_send_json_error(array('message' => '보안 검증 실패'));
            wp_die();
        }
        
        $options = get_option('kachi_settings');
        $api_url = isset($options['api_url']) ? $options['api_url'] : '';
        
        $url = $api_url . '/list-documents/';
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        );
        
        // 사용자 필터 추가
        $params = array();
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $sosok = get_user_meta($user_id, 'kachi_sosok', true);
            $site = get_user_meta($user_id, 'kachi_site', true);
            
            if ($sosok) $params['sosok'] = sanitize_text_field($sosok);
            if ($site) $params['site'] = sanitize_text_field($site);
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Kachi Docs API Error: ' . $response->get_error_message());
            wp_send_json_error(array('message' => $response->get_error_message()));
            wp_die();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => '문서 데이터 파싱 오류'));
            wp_die();
        }
        
        // 문서 데이터 정리
        if (isset($data['documents']) && is_array($data['documents'])) {
            $documents = array();
            foreach ($data['documents'] as $doc) {
                if (is_array($doc)) {
                    $documents[] = array(
                        'filename' => isset($doc['filename']) ? $doc['filename'] : (isset($doc['file_id']) ? $doc['file_id'] : 'Unknown'),
                        'file_id' => isset($doc['file_id']) ? $doc['file_id'] : null,
                        'size' => isset($doc['size']) ? $doc['size'] : null,
                        'date' => isset($doc['date']) ? $doc['date'] : null
                    );
                } else {
                    $documents[] = array(
                        'filename' => $doc,
                        'file_id' => null,
                        'size' => null,
                        'date' => null
                    );
                }
            }
            $data['documents'] = $documents;
        }
        
        wp_send_json_success($data);
        wp_die();
    }
    
    /**
     * 이미지 프록시 처리
     */
    public function proxy_image() {
        error_log('KACHI: proxy_image() called with $_GET: ' . print_r($_GET, true));
        
        // 이미지 경로 가져오기
        $image_path = isset($_GET['path']) ? $_GET['path'] : '';
        
        error_log('KACHI: Proxy image requested for path: ' . $image_path);
        
        if (empty($image_path)) {
            error_log('KACHI: Empty image path provided');
            header('HTTP/1.1 404 Not Found');
            exit('Image not found');
        }
        
        // 보안을 위해 경로 검증 (../나 절대 경로 방지)
        if (strpos($image_path, '..') !== false || strpos($image_path, '/') === 0) {
            header('HTTP/1.1 403 Forbidden');
            exit('Invalid image path');
        }
        
        // API 서버에서 이미지 가져오기
        $options = get_option('kachi_settings');
        $api_url = isset($options['api_url']) ? $options['api_url'] : '';
        
        // 이미지 URL 구성 (FastAPI의 /images 경로)
        $image_url = $api_url . '/images/' . $image_path;
        
        // 이미지 가져오기
        $response = wp_remote_get($image_url, array(
            'timeout' => 30,
            'redirection' => 5,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            header('HTTP/1.1 500 Internal Server Error');
            exit('Failed to fetch image');
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            header('HTTP/1.1 ' . $response_code . ' ' . wp_remote_retrieve_response_message($response));
            exit('Image not found');
        }
        
        // 이미지 데이터와 헤더 가져오기
        $image_data = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        // Content-Type이 없으면 확장자로 추측
        if (empty($content_type)) {
            $ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
            $mime_types = array(
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml'
            );
            $content_type = isset($mime_types[$ext]) ? $mime_types[$ext] : 'image/jpeg';
        }
        
        // 캐시 헤더 설정 (1일)
        $expires = 60 * 60 * 24;
        header('Cache-Control: public, max-age=' . $expires);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
        
        // 이미지 출력
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($image_data));
        echo $image_data;
        exit;
    }
    
    /**
     * 대화 목록 로드
     */
    public function load_conversations() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            wp_send_json_error(array('message' => '보안 검증 실패'));
            wp_die();
        }
        
        // 로그인 확인
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => '로그인이 필요합니다.'));
            wp_die();
        }
        
        // 페이지네이션 파라미터
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? min(50, max(10, intval($_POST['per_page']))) : 20;
        $offset = ($page - 1) * $per_page;
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'kachi_conversations';
        
        // 전체 대화 수 가져오기
        $total_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
                $user_id
            )
        );
        
        // 사용자의 대화 목록 가져오기 (페이지네이션 적용)
        $conversations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT conversation_id, title, messages, created_at, updated_at 
                 FROM $table_name 
                 WHERE user_id = %d 
                 ORDER BY updated_at DESC 
                 LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        // 대화 목록 포맷팅
        $formatted_conversations = array();
        foreach ($conversations as $conv) {
            $messages = json_decode($conv['messages'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $formatted_conversations[] = array(
                    'id' => $conv['conversation_id'],
                    'title' => $conv['title'],
                    'messages' => $messages,
                    'createdAt' => $conv['created_at'],
                    'updatedAt' => $conv['updated_at']
                );
            }
        }
        
        // 응답 데이터
        $response_data = array(
            'conversations' => $formatted_conversations,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_count' => intval($total_count),
                'total_pages' => ceil($total_count / $per_page),
                'has_more' => ($page * $per_page) < $total_count
            )
        );
        
        wp_send_json_success($response_data);
        wp_die();
    }
    
    /**
     * 대화 저장
     */
    public function save_conversation() {
        // 로깅 및 성능 모니터링
        $start_time = microtime(true);
        $user_id = get_current_user_id();
        
        error_log("KACHI: Starting save_conversation for user {$user_id}");
        
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            error_log('KACHI: Nonce verification failed');
            wp_send_json_error(array(
                'message' => '보안 검증 실패',
                'code' => 'INVALID_NONCE'
            ));
            wp_die();
        }
        
        // 로그인 확인
        if (!is_user_logged_in()) {
            error_log('KACHI: User not logged in');
            wp_send_json_error(array(
                'message' => '로그인이 필요합니다.',
                'code' => 'NOT_LOGGED_IN'
            ));
            wp_die();
        }
        
        // 데이터 검증 및 살마이징
        $validation_result = $this->validate_save_data($_POST);
        if ($validation_result['error']) {
            error_log("KACHI: Validation failed: {$validation_result['message']}");
            wp_send_json_error(array(
                'message' => $validation_result['message'],
                'code' => $validation_result['code']
            ));
            wp_die();
        }
        
        $conversation_id = $validation_result['conversation_id'];
        $title = $validation_result['title'];
        $messages_array = $validation_result['messages'];
        
        // 데이터베이스 작업 수행
        $save_result = $this->perform_conversation_save($user_id, $conversation_id, $title, $messages_array);
        
        // 성능 모니터링
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        error_log("KACHI: Save operation completed in {$execution_time}ms, result: " . ($save_result['success'] ? 'SUCCESS' : 'FAILED'));
        
        if ($save_result['success']) {
            wp_send_json_success(array(
                'message' => $save_result['message'],
                'execution_time' => $execution_time,
                'operation' => $save_result['operation']
            ));
        } else {
            error_log("KACHI: Database error: {$save_result['error']}");
            wp_send_json_error(array(
                'message' => $save_result['message'],
                'code' => $save_result['code'],
                'execution_time' => $execution_time
            ));
        }
        
        wp_die();
    }
    
    /**
     * 대화 삭제
     */
    public function delete_conversation() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            wp_send_json_error(array('message' => '보안 검증 실패'));
            wp_die();
        }
        
        // 로그인 확인
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => '로그인이 필요합니다.'));
            wp_die();
        }
        
        // 대화 ID 가져오기
        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
        
        if (empty($conversation_id)) {
            wp_send_json_error(array('message' => '대화 ID가 없습니다.'));
            wp_die();
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'kachi_conversations';
        
        // 대화 삭제
        $result = $wpdb->delete(
            $table_name,
            array(
                'user_id' => $user_id,
                'conversation_id' => $conversation_id
            ),
            array('%d', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => '대화 삭제에 실패했습니다.'));
        } else {
            wp_send_json_success(array('message' => '대화가 삭제되었습니다.'));
        }
        
        wp_die();
    }
    
    /**
     * 저장 데이터 검증
     */
    private function validate_save_data($post_data) {
        // 기본 필드 검증
        $conversation_id = isset($post_data['conversation_id']) ? sanitize_text_field($post_data['conversation_id']) : '';
        $title = isset($post_data['title']) ? sanitize_text_field($post_data['title']) : '새 대화';
        $messages = isset($post_data['messages']) ? wp_unslash($post_data['messages']) : '[]';
        
        if (empty($conversation_id)) {
            return array(
                'error' => true,
                'message' => '대화 ID가 없습니다.',
                'code' => 'MISSING_CONVERSATION_ID'
            );
        }
        
        // 제목 길이 제한
        if (mb_strlen($title) > 255) {
            $title = mb_substr($title, 0, 255);
        }
        
        // JSON 유효성 검증
        $messages_array = json_decode($messages, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'error' => true,
                'message' => '메시지 데이터가 올바르지 않습니다: ' . json_last_error_msg(),
                'code' => 'INVALID_JSON'
            );
        }
        
        // 메시지 배열 검증
        if (!is_array($messages_array)) {
            return array(
                'error' => true,
                'message' => '메시지 데이터는 배열이어야 합니다.',
                'code' => 'INVALID_MESSAGE_FORMAT'
            );
        }
        
        // 메시지 개수 제한 (성능상)
        if (count($messages_array) > 1000) {
            error_log("KACHI: Large message count detected: " . count($messages_array));
            // 최근 메시지만 유지
            $messages_array = array_slice($messages_array, -1000);
        }
        
        // 개별 메시지 검증 및 정리
        $validated_messages = array();
        foreach ($messages_array as $index => $message) {
            $validated_message = $this->validate_message($message, $index);
            if ($validated_message) {
                $validated_messages[] = $validated_message;
            }
        }
        
        return array(
            'error' => false,
            'conversation_id' => $conversation_id,
            'title' => $title,
            'messages' => $validated_messages
        );
    }
    
    /**
     * 개별 메시지 검증
     */
    private function validate_message($message, $index) {
        if (!is_array($message)) {
            error_log("KACHI: Invalid message at index {$index}: not an array");
            return null;
        }
        
        // 필수 필드 확인
        $id = isset($message['id']) ? sanitize_text_field($message['id']) : '';
        $type = isset($message['type']) ? sanitize_text_field($message['type']) : '';
        $content = isset($message['content']) ? $message['content'] : '';
        $time = isset($message['time']) ? sanitize_text_field($message['time']) : '';
        $referenced_docs = isset($message['referencedDocs']) ? $message['referencedDocs'] : null;
        
        if (empty($id) || empty($type)) {
            error_log("KACHI: Message at index {$index} missing required fields");
            return null;
        }
        
        // 콘텐츠 크기 제한 (5MB)
        if (strlen($content) > 5242880) {
            error_log("KACHI: Large content detected in message {$id}: " . strlen($content) . " bytes");
            $content = substr($content, 0, 5242880) . '[...내용이 잘렸습니다...]';
        }
        
        return array(
            'id' => $id,
            'type' => $type,
            'content' => $content,
            'time' => $time,
            'referencedDocs' => $referenced_docs
        );
    }
    
    /**
     * 대화 저장 수행
     */
    private function perform_conversation_save($user_id, $conversation_id, $title, $messages_array) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kachi_conversations';
        
        // 트랜잭션 시작
        $wpdb->query('START TRANSACTION');
        
        try {
            // 기존 대화 확인
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE user_id = %d AND conversation_id = %s",
                    $user_id,
                    $conversation_id
                )
            );
            
            $messages_json = wp_json_encode($messages_array, JSON_UNESCAPED_UNICODE);
            if ($messages_json === false) {
                throw new Exception('JSON encoding failed');
            }
            
            if ($existing) {
                // 업데이트
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'title' => $title,
                        'messages' => $messages_json,
                        'updated_at' => current_time('mysql')
                    ),
                    array(
                        'user_id' => $user_id,
                        'conversation_id' => $conversation_id
                    ),
                    array('%s', '%s', '%s'),
                    array('%d', '%s')
                );
                
                if ($result === false) {
                    throw new Exception('Database update failed: ' . $wpdb->last_error);
                }
                
                $operation = 'UPDATE';
                $message = '대화가 업데이트되었습니다.';
            } else {
                // 새로 삽입
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'conversation_id' => $conversation_id,
                        'title' => $title,
                        'messages' => $messages_json,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s')
                );
                
                if ($result === false) {
                    throw new Exception('Database insert failed: ' . $wpdb->last_error);
                }
                
                $operation = 'INSERT';
                $message = '새 대화가 생성되었습니다.';
            }
            
            // 트랜잭션 커밋
            $wpdb->query('COMMIT');
            
            return array(
                'success' => true,
                'message' => $message,
                'operation' => $operation
            );
            
        } catch (Exception $e) {
            // 트랜잭션 롤백
            $wpdb->query('ROLLBACK');
            
            return array(
                'success' => false,
                'message' => '대화 저장에 실패했습니다.',
                'error' => $e->getMessage(),
                'code' => 'DATABASE_ERROR'
            );
        }
    }
    
    /**
     * 시스템 상태 체크
     */
    public function health_check() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            wp_send_json_error(array('message' => '보안 검증 실패'));
            wp_die();
        }
        
        // 관리자 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '권한이 없습니다.'));
            wp_die();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'kachi_conversations';
        
        $health_data = array(
            'timestamp' => current_time('c'),
            'database' => array(),
            'api' => array(),
            'system' => array()
        );
        
        // 데이터베이스 상태 체크
        try {
            // 테이블 존재 여부
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            $health_data['database']['table_exists'] = $table_exists;
            
            if ($table_exists) {
                // 총 대화 수
                $total_conversations = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $health_data['database']['total_conversations'] = intval($total_conversations);
                
                // 최근 7일간 대화 수
                $recent_conversations = $wpdb->get_var(
                    "SELECT COUNT(*) FROM $table_name WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                );
                $health_data['database']['recent_conversations'] = intval($recent_conversations);
                
                // 평균 메시지 수
                $avg_messages = $wpdb->get_var(
                    "SELECT AVG(JSON_LENGTH(messages)) FROM $table_name WHERE messages IS NOT NULL"
                );
                $health_data['database']['avg_messages_per_conversation'] = round(floatval($avg_messages), 2);
                
                // 데이터베이스 크기 (근사치)
                $table_size = $wpdb->get_var(
                    "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'DB Size in MB' 
                     FROM information_schema.tables 
                     WHERE table_schema = DATABASE() 
                     AND table_name = '$table_name'"
                );
                $health_data['database']['size_mb'] = floatval($table_size);
            }
            
            $health_data['database']['status'] = 'healthy';
        } catch (Exception $e) {
            $health_data['database']['status'] = 'error';
            $health_data['database']['error'] = $e->getMessage();
        }
        
        // API 상태 체크
        $options = get_option('kachi_settings', array());
        $api_url = isset($options['threechan_api_internal_url']) ? $options['threechan_api_internal_url'] : '';
        
        if (!empty($api_url)) {
            $api_health_url = trailingslashit($api_url) . 'health';
            $response = wp_remote_get($api_health_url, array('timeout' => 5));
            
            if (is_wp_error($response)) {
                $health_data['api']['status'] = 'error';
                $health_data['api']['error'] = $response->get_error_message();
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $health_data['api']['status'] = $status_code === 200 ? 'healthy' : 'warning';
                $health_data['api']['response_code'] = $status_code;
                $health_data['api']['response_time'] = 'N/A'; // WordPress doesn't provide timing info
            }
        } else {
            $health_data['api']['status'] = 'not_configured';
        }
        
        // 시스템 상태
        $health_data['system'] = array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => KACHI_AI_SEARCH_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'user_count' => count_users()['total_users']
        );
        
        // 전체 상태 결정
        $overall_status = 'healthy';
        if ($health_data['database']['status'] === 'error' || $health_data['api']['status'] === 'error') {
            $overall_status = 'critical';
        } elseif ($health_data['api']['status'] === 'warning' || $health_data['api']['status'] === 'not_configured') {
            $overall_status = 'warning';
        }
        
        $health_data['overall_status'] = $overall_status;
        
        wp_send_json_success($health_data);
        wp_die();
    }
    
    /**
     * 시스템 정보 가져오기
     */
    public function get_system_info() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kachi_ajax_nonce')) {
            wp_send_json_error(array('message' => '보안 검증 실패'));
            wp_die();
        }
        
        // 관리자 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '권한이 없습니다.'));
            wp_die();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'kachi_conversations';
        
        $system_info = array(
            'timestamp' => current_time('c'),
            'environment' => array(
                'php' => array(
                    'version' => PHP_VERSION,
                    'sapi' => php_sapi_name(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'post_max_size' => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize')
                ),
                'wordpress' => array(
                    'version' => get_bloginfo('version'),
                    'multisite' => is_multisite(),
                    'debug' => defined('WP_DEBUG') && WP_DEBUG,
                    'language' => get_locale()
                ),
                'server' => array(
                    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'php_max_vars' => ini_get('max_input_vars'),
                    'mysql_version' => $wpdb->db_version()
                )
            ),
            'plugin' => array(
                'version' => KACHI_AI_SEARCH_VERSION,
                'database_version' => get_option('kachi_db_version', '1.0'),
                'settings' => get_option('kachi_settings', array())
            ),
            'performance' => array(
                'active_plugins' => count(get_option('active_plugins', array())),
                'current_theme' => wp_get_theme()->get('Name'),
                'total_users' => count_users()['total_users']
            )
        );
        
        // 데이터베이스 통계
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $db_stats = array();
            
            // 기본 통계
            $db_stats['total_conversations'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));
            $db_stats['total_users_with_conversations'] = intval($wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name"));
            
            // 날짜별 통계
            $db_stats['conversations_last_24h'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            ));
            $db_stats['conversations_last_7d'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            ));
            $db_stats['conversations_last_30d'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ));
            
            // 메시지 통계
            $db_stats['avg_messages'] = round(floatval($wpdb->get_var(
                "SELECT AVG(JSON_LENGTH(messages)) FROM $table_name WHERE messages IS NOT NULL"
            )), 2);
            
            $system_info['database'] = $db_stats;
        }
        
        wp_send_json_success($system_info);
        wp_die();
    }
}