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
        $api_url = isset($options['api_url']) ? $options['api_url'] : 'http://chelly.kr:8001';
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
        $api_url = isset($options['api_url']) ? $options['api_url'] : 'http://chelly.kr:8001';
        
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
        $api_url = isset($options['api_url']) ? $options['api_url'] : 'http://chelly.kr:8001';
        
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
        $api_url = isset($options['api_url']) ? $options['api_url'] : 'http://chelly.kr:8001';
        
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
        // 이미지 경로 가져오기
        $image_path = isset($_GET['path']) ? $_GET['path'] : '';
        
        if (empty($image_path)) {
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
        $api_url = isset($options['api_url']) ? $options['api_url'] : 'http://chelly.kr:8001';
        
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
        
        // 데이터 가져오기
        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '새 대화';
        $messages = isset($_POST['messages']) ? wp_unslash($_POST['messages']) : '[]';
        
        if (empty($conversation_id)) {
            wp_send_json_error(array('message' => '대화 ID가 없습니다.'));
            wp_die();
        }
        
        // JSON 유효성 검증
        $messages_array = json_decode($messages, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => '메시지 데이터가 올바르지 않습니다.'));
            wp_die();
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'kachi_conversations';
        
        // 기존 대화가 있는지 확인
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE user_id = %d AND conversation_id = %s",
                $user_id,
                $conversation_id
            )
        );
        
        if ($existing) {
            // 업데이트
            $result = $wpdb->update(
                $table_name,
                array(
                    'title' => $title,
                    'messages' => wp_json_encode($messages_array, JSON_UNESCAPED_UNICODE),
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'user_id' => $user_id,
                    'conversation_id' => $conversation_id
                ),
                array('%s', '%s', '%s'),
                array('%d', '%s')
            );
        } else {
            // 새로 삽입
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'conversation_id' => $conversation_id,
                    'title' => $title,
                    'messages' => wp_json_encode($messages_array, JSON_UNESCAPED_UNICODE),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        if ($result === false) {
            wp_send_json_error(array('message' => '대화 저장에 실패했습니다.'));
        } else {
            wp_send_json_success(array('message' => '대화가 저장되었습니다.'));
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
}