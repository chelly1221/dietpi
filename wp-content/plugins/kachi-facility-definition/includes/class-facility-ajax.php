<?php
/**
 * AJAX 요청 처리 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facility_Ajax {
    
    public function init() {
        // AJAX 액션 등록
        add_action('wp_ajax_save_facility_definitions', array($this, 'save_facility_definitions'));
        add_action('wp_ajax_nopriv_save_facility_definitions', array($this, 'save_facility_definitions'));
        add_action('wp_ajax_load_facility_definitions', array($this, 'load_facility_definitions'));
        add_action('wp_ajax_nopriv_load_facility_definitions', array($this, 'load_facility_definitions'));
    }
    
    /**
     * 시설 정의 저장
     */
    public function save_facility_definitions() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'facility_ajax_nonce')) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 사용자 정보 가져오기
        $user_id = get_current_user_id();
        $sosok = '';
        $site = '';
        
        if ($user_id) {
            $sosok = get_user_meta($user_id, 'kachi_sosok', true);
            $site = get_user_meta($user_id, 'kachi_site', true);
        }
        
        // 기본값 설정
        if (!$sosok) $sosok = 'default';
        if (!$site) $site = 'default';
        
        if (!isset($_POST['facility_definitions_json'])) {
            wp_send_json_error('❌ 데이터가 없습니다.');
        }
        
        $incoming = json_decode(wp_unslash($_POST['facility_definitions_json']), true);
        if (!is_array($incoming)) {
            wp_send_json_error('❌ 잘못된 JSON');
        }
        
        // 기존 데이터 가져오기
        $all = get_option('facility_definitions_data', array());
        
        // 현재 사용자의 소속/현장 데이터 제거
        $all = array_filter($all, function($def) use ($sosok, $site) {
            return !($def['sosok'] === $sosok && $def['site'] === $site);
        });
        
        // 새 데이터 추가
        foreach ($incoming as $item) {
            $all[] = array_merge($item, ['sosok' => $sosok, 'site' => $site]);
        }
        
        // 저장
        update_option('facility_definitions_data', array_values($all));
        wp_send_json_success('✅ 저장 성공');
    }
    
    /**
     * 시설 정의 불러오기
     */
    public function load_facility_definitions() {
        $all_defs = get_option('facility_definitions_data', array());
        
        $user_id = get_current_user_id();
        $sosok = '';
        $site = '';
        
        if ($user_id) {
            $sosok = get_user_meta($user_id, 'kachi_sosok', true);
            $site = get_user_meta($user_id, 'kachi_site', true);
        }
        
        // 기본값 설정
        if (!$sosok) $sosok = 'default';
        if (!$site) $site = 'default';
        
        // 해당 소속/현장 데이터만 필터링
        $all_defs = array_filter($all_defs, function($def) use ($sosok, $site) {
            return $def['sosok'] === $sosok && $def['site'] === $site;
        });
        
        wp_send_json(['definitions' => array_values($all_defs)]);
    }
}