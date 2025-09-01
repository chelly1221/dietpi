<?php
/**
 * Login handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KAC_Login_Handler {
    
    public static function process_login() {
        if (isset($_POST['kac_login_submit']) && $_POST['kac_login_submit'] === '1') {
            // Nonce 검증
            if (!isset($_POST['kac_login_nonce']) || !wp_verify_nonce($_POST['kac_login_nonce'], 'kac_login_action')) {
                wp_die('보안 검증 실패');
            }
            
            $creds = array(
                'user_login'    => sanitize_text_field($_POST['log'] ?? ''),
                'user_password' => $_POST['pwd'] ?? '',
                'remember'      => !empty($_POST['rememberme']),
            );

            $user = wp_signon($creds, is_ssl());
            
            if (is_wp_error($user)) {
                // 로그인 실패 - 현재 페이지로 리다이렉트
                $redirect_url = add_query_arg('login', 'failed', wp_get_referer());
                wp_redirect($redirect_url);
                exit;
            } else {
                // 로그인 성공
                $redirect_to = isset($_POST['kac_redirect_to']) ? esc_url_raw($_POST['kac_redirect_to']) : home_url('/');
                wp_redirect($redirect_to);
                exit;
            }
        }
    }
    
    public static function handle_login_failed($username) {
        // wp_login_failed 액션에서 호출됨
        // 쇼트코드가 있는 페이지에서만 처리
        $referrer = wp_get_referer();
        
        if (!empty($referrer) && !strstr($referrer, 'wp-login') && !strstr($referrer, 'wp-admin')) {
            // KAC 로그인 페이지인지 확인 (쿼리 파라미터나 특정 조건으로)
            if (strpos($referrer, 'kac_login') !== false || isset($_POST['kac_login_submit'])) {
                $redirect_url = add_query_arg('login', 'failed', $referrer);
                wp_redirect($redirect_url);
                exit;
            }
        }
    }
}