<?php
/**
 * AJAX 처리 클래스 - 수정된 버전
 */

if (!defined('ABSPATH')) {
    exit;
}

class UAS_Ajax {
    
    /**
     * 생성자
     */
    public function __construct() {
        // 승인 처리
        add_action('wp_ajax_approve_user_role', array($this, 'approve_user'));
        add_action('wp_ajax_nopriv_approve_user_role', array($this, 'approve_user_nopriv'));
        
        // 반려 처리
        add_action('wp_ajax_reject_user_role', array($this, 'reject_user'));
        add_action('wp_ajax_nopriv_reject_user_role', array($this, 'reject_user_nopriv'));
        
        // 사용자 삭제
        add_action('wp_ajax_delete_user_account', array($this, 'delete_user'));
        add_action('wp_ajax_nopriv_delete_user_account', array($this, 'delete_user_nopriv'));
        
        // 역할 변경
        add_action('wp_ajax_change_user_role', array($this, 'change_user_role'));
        add_action('wp_ajax_nopriv_change_user_role', array($this, 'change_user_role_nopriv'));
        
        // 사용자 정보 업데이트
        add_action('wp_ajax_update_user_info', array($this, 'update_user_info'));
        add_action('wp_ajax_nopriv_update_user_info', array($this, 'update_user_info_nopriv'));
    }
    
    /**
     * 사용자 승인 처리
     */
    public function approve_user() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'approve_user_nonce')) {
            wp_send_json_error(__('보안 검증 실패', 'user-approval-system'));
        }
        
        // 권한 확인 - 수정: current_user_can 사용
        if (!current_user_can('manage_options') && !in_array('approver', wp_get_current_user()->roles)) {
            wp_send_json_error(__('권한이 없습니다.', 'user-approval-system'));
        }
        
        $user_id = absint($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error(__('잘못된 사용자 ID입니다.', 'user-approval-system'));
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error(__('사용자를 찾을 수 없습니다.', 'user-approval-system'));
        }
        
        // 현재 역할 확인
        if (!in_array('pending', $user->roles)) {
            wp_send_json_error(__('이미 승인된 사용자이거나 승인 대기 상태가 아닙니다.', 'user-approval-system'));
        }
        
        // 역할 변경: pending → subscriber
        $user->set_role('subscriber');
        
        // 승인 날짜 기록
        update_user_meta($user_id, 'uas_approved_date', current_time('mysql'));
        update_user_meta($user_id, 'uas_approved_by', get_current_user_id());
        
        // 승인 후 액션 훅
        do_action('uas_user_approved', $user_id);
        
        wp_send_json_success(array(
            'message' => __('사용자가 승인되었습니다.', 'user-approval-system'),
            'user_id' => $user_id
        ));
    }
    
    /**
     * 사용자 반려 처리
     */
    public function reject_user() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'reject_user_nonce')) {
            wp_send_json_error(__('보안 검증 실패', 'user-approval-system'));
        }
        
        // 권한 확인 - 수정: current_user_can 사용
        if (!current_user_can('manage_options') && !in_array('approver', wp_get_current_user()->roles)) {
            wp_send_json_error(__('권한이 없습니다.', 'user-approval-system'));
        }
        
        $user_id = absint($_POST['user_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        if (!$user_id) {
            wp_send_json_error(__('잘못된 사용자 ID입니다.', 'user-approval-system'));
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error(__('사용자를 찾을 수 없습니다.', 'user-approval-system'));
        }
        
        // 현재 역할 확인
        if (!in_array('pending', $user->roles)) {
            wp_send_json_error(__('승인 대기 상태의 사용자가 아닙니다.', 'user-approval-system'));
        }
        
        // 반려 정보 기록
        update_user_meta($user_id, 'uas_rejected_date', current_time('mysql'));
        update_user_meta($user_id, 'uas_rejected_by', get_current_user_id());
        update_user_meta($user_id, 'uas_rejection_reason', $reason);
        
        // 사용자 삭제
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
        
        // 반려 후 액션 훅
        do_action('uas_user_rejected', $user_id, $reason);
        
        wp_send_json_success(array(
            'message' => __('사용자가 반려되었습니다.', 'user-approval-system'),
            'user_id' => $user_id
        ));
    }
    
    /**
     * 사용자 삭제 처리
     */
    public function delete_user() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'delete_user_nonce')) {
            wp_send_json_error(__('보안 검증 실패', 'user-approval-system'));
        }
        
        // 권한 확인 - 관리자(approver)도 삭제 가능
        if (!current_user_can('manage_options') && !in_array('approver', wp_get_current_user()->roles)) {
            wp_send_json_error(__('권한이 필요합니다.', 'user-approval-system'));
        }
        
        $user_id = absint($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(__('잘못된 사용자 ID입니다.', 'user-approval-system'));
        }
        
        // 자기 자신은 삭제 불가
        if ($user_id == get_current_user_id()) {
            wp_send_json_error(__('자기 자신은 삭제할 수 없습니다.', 'user-approval-system'));
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error(__('사용자를 찾을 수 없습니다.', 'user-approval-system'));
        }
        
        // 개발자는 삭제 불가
        if (user_can($user_id, 'manage_options')) {
            wp_send_json_error(__('개발자 계정은 삭제할 수 없습니다.', 'user-approval-system'));
        }
        
        // 사용자 삭제
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($user_id);
        
        if ($result) {
            // 삭제 로그 (옵션)
            do_action('uas_user_deleted', $user_id, get_current_user_id());
            
            wp_send_json_success(array(
                'message' => __('사용자가 삭제되었습니다.', 'user-approval-system'),
                'user_id' => $user_id
            ));
        } else {
            wp_send_json_error(__('사용자 삭제 중 오류가 발생했습니다.', 'user-approval-system'));
        }
    }
    
    /**
     * 역할 우선순위 가져오기
     */
    private function get_role_priority($role) {
        $priorities = array(
            'administrator' => 100,
            'approver' => 50,
            'subscriber' => 10,
            'pending' => 1
        );
        
        return isset($priorities[$role]) ? $priorities[$role] : 0;
    }
    
    /**
     * 사용자의 최고 역할 가져오기
     */
    private function get_highest_role($user) {
        $highest_role = '';
        $highest_priority = -1;
        
        foreach ($user->roles as $role) {
            $priority = $this->get_role_priority($role);
            if ($priority > $highest_priority) {
                $highest_priority = $priority;
                $highest_role = $role;
            }
        }
        
        return $highest_role;
    }
    
    /**
     * 역할 변경 처리
     */
    public function change_user_role() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'change_role_nonce')) {
            wp_send_json_error(__('보안 검증 실패', 'user-approval-system'));
        }
        
        // 권한 확인 - 수정: 표준 권한 체크 사용
        if (!current_user_can('manage_options') && !in_array('approver', wp_get_current_user()->roles)) {
            wp_send_json_error(__('권한이 없습니다.', 'user-approval-system'));
        }
        
        $user_id = absint($_POST['user_id'] ?? 0);
        $new_role = sanitize_text_field($_POST['new_role'] ?? '');
        
        if (!$user_id || !$new_role) {
            wp_send_json_error(__('잘못된 요청입니다.', 'user-approval-system'));
        }
        
        // 허용된 역할인지 확인
        $allowed_roles = array('subscriber', 'approver', 'administrator');
        if (!in_array($new_role, $allowed_roles)) {
            wp_send_json_error(__('허용되지 않은 역할입니다.', 'user-approval-system'));
        }
        
        $current_user = wp_get_current_user();
        $target_user = get_user_by('ID', $user_id);
        
        if (!$target_user) {
            wp_send_json_error(__('사용자를 찾을 수 없습니다.', 'user-approval-system'));
        }
        
        // 권한 계층 확인
        $current_user_role = $this->get_highest_role($current_user);
        $target_user_role = $this->get_highest_role($target_user);
        
        // 관리자(approver)의 경우
        if ($current_user_role === 'approver') {
            // 개발자 역할로는 변경 불가
            if ($new_role === 'administrator') {
                wp_send_json_error(__('개발자 권한으로는 설정할 수 없습니다.', 'user-approval-system'));
            }
            
            // 개발자의 역할은 변경 불가
            if ($target_user_role === 'administrator') {
                wp_send_json_error(__('개발자의 역할은 변경할 수 없습니다.', 'user-approval-system'));
            }
        }
        // 개발자(administrator)가 아닌 경우
        else if ($current_user_role !== 'administrator') {
            // 자신보다 높은 권한으로는 설정 불가
            $current_priority = $this->get_role_priority($current_user_role);
            $new_role_priority = $this->get_role_priority($new_role);
            
            if ($new_role_priority >= $current_priority) {
                wp_send_json_error(__('자신의 권한 이상으로는 설정할 수 없습니다.', 'user-approval-system'));
            }
        }
        
        // 역할 변경
        $target_user->set_role($new_role);
        
        // 변경 로그 기록
        update_user_meta($user_id, 'uas_role_changed_date', current_time('mysql'));
        update_user_meta($user_id, 'uas_role_changed_by', $current_user->ID);
        update_user_meta($user_id, 'uas_role_changed_from', $target_user_role);
        update_user_meta($user_id, 'uas_role_changed_to', $new_role);
        
        // 역할 변경 액션 훅
        do_action('uas_user_role_changed', $user_id, $target_user_role, $new_role, $current_user->ID);
        
        wp_send_json_success(array(
            'message' => __('역할이 변경되었습니다.', 'user-approval-system'),
            'user_id' => $user_id,
            'new_role' => $new_role
        ));
    }
    
    /**
     * 사용자 정보 업데이트
     */
    public function update_user_info() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_user_info_nonce')) {
            wp_send_json_error(__('보안 검증 실패', 'user-approval-system'));
        }
        
        // 권한 확인 - 수정: 표준 권한 체크 사용
        if (!current_user_can('manage_options') && !in_array('approver', wp_get_current_user()->roles)) {
            wp_send_json_error(__('권한이 없습니다.', 'user-approval-system'));
        }
        
        $user_id = absint($_POST['user_id'] ?? 0);
        $sosok = sanitize_text_field($_POST['sosok'] ?? '');
        $site = sanitize_text_field($_POST['site'] ?? '');
        
        if (!$user_id) {
            wp_send_json_error(__('잘못된 사용자 ID입니다.', 'user-approval-system'));
        }
        
        $current_user = wp_get_current_user();
        $target_user = get_user_by('ID', $user_id);
        
        if (!$target_user) {
            wp_send_json_error(__('사용자를 찾을 수 없습니다.', 'user-approval-system'));
        }
        
        // 현장 정보 수정은 개발자만 가능
        $current_site = get_user_meta($user_id, 'kachi_site', true);
        if ($site !== $current_site && !current_user_can('manage_options')) {
            wp_send_json_error(__('현장 정보는 개발자만 수정할 수 있습니다.', 'user-approval-system'));
        }
        
        // 권한 계층 확인
        $current_user_role = $this->get_highest_role($current_user);
        $target_user_role = $this->get_highest_role($target_user);
        
        // 관리자(approver)의 경우
        if ($current_user_role === 'approver') {
            // 개발자 정보는 수정 불가
            if ($target_user_role === 'administrator') {
                wp_send_json_error(__('개발자의 정보는 수정할 수 없습니다.', 'user-approval-system'));
            }
        }
        // 개발자(administrator)가 아닌 경우
        else if ($current_user_role !== 'administrator') {
            $current_priority = $this->get_role_priority($current_user_role);
            $target_priority = $this->get_role_priority($target_user_role);
            
            // 현재 사용자보다 높은 권한의 사용자는 수정 불가
            if ($target_priority >= $current_priority) {
                wp_send_json_error(__('상위 권한 사용자의 정보는 수정할 수 없습니다.', 'user-approval-system'));
            }
        }
        
        // 소속과 현장 정보 업데이트
        update_user_meta($user_id, 'kachi_sosok', $sosok);
        if (current_user_can('manage_options')) {
            update_user_meta($user_id, 'kachi_site', $site);
        }
        
        // 변경 로그 기록
        update_user_meta($user_id, 'uas_info_updated_date', current_time('mysql'));
        update_user_meta($user_id, 'uas_info_updated_by', $current_user->ID);
        
        // 정보 업데이트 액션 훅
        do_action('uas_user_info_updated', $user_id, array('sosok' => $sosok, 'site' => $site), $current_user->ID);
        
        wp_send_json_success(array(
            'message' => __('사용자 정보가 업데이트되었습니다.', 'user-approval-system'),
            'user_id' => $user_id,
            'sosok' => $sosok,
            'site' => $site
        ));
    }
    
    /**
     * 비로그인 사용자 처리 - 승인
     */
    public function approve_user_nopriv() {
        wp_send_json_error(__('로그인이 필요합니다.', 'user-approval-system'));
    }
    
    /**
     * 비로그인 사용자 처리 - 반려
     */
    public function reject_user_nopriv() {
        wp_send_json_error(__('로그인이 필요합니다.', 'user-approval-system'));
    }
    
    /**
     * 비로그인 사용자 처리 - 삭제
     */
    public function delete_user_nopriv() {
        wp_send_json_error(__('로그인이 필요합니다.', 'user-approval-system'));
    }
    
    /**
     * 비로그인 사용자 처리 - 역할 변경
     */
    public function change_user_role_nopriv() {
        wp_send_json_error(__('로그인이 필요합니다.', 'user-approval-system'));
    }
    
    /**
     * 비로그인 사용자 처리 - 정보 업데이트
     */
    public function update_user_info_nopriv() {
        wp_send_json_error(__('로그인이 필요합니다.', 'user-approval-system'));
    }
}