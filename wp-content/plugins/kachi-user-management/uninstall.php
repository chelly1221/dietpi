<?php
/**
 * 플러그인 제거 시 실행되는 파일
 */

// 워드프레스에서 호출되지 않았다면 종료
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 배경 설정 옵션 삭제
delete_option('uas_background_image');
delete_option('uas_background_position');
delete_option('uas_background_size');

// 역할 제거 (선택사항 - 주석 해제하여 사용)
// remove_role('pending');
// remove_role('approver');

// 데이터베이스 정리 (필요한 경우)
// global $wpdb;
// $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'uas_%'");