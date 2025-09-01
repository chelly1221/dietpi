<?php
/**
 * Shortcode handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KAC_Login_Shortcode {
    
    public static function render($atts) {
        // 이미 로그인한 경우
        if (is_user_logged_in()) {
            return '<div class="kac-already-logged-in">이미 로그인되어 있습니다. <a href="' . home_url() . '">홈으로 이동</a></div>';
        }
        
        // 프론트엔드 관리자 설정에서 기본값 가져오기
        $default_logo = KAC_Login_Frontend_Admin::get_option('logo_url', KAC_LOGIN_PLUGIN_URL . 'assets/images/kac.webp');
        $default_redirect = KAC_Login_Frontend_Admin::get_option('default_redirect', home_url('/'));
        
        // 속성 파싱
        $atts = shortcode_atts(array(
            'logo' => $default_logo,
            'redirect' => $default_redirect,
        ), $atts, 'kac_login');
        
        ob_start();
        ?>
        
        <?php if (isset($_GET['login']) && $_GET['login'] === 'failed'): ?>
            <div class="login-error-message">아이디 또는 비밀번호가 틀렸습니다.</div>
        <?php endif; ?>
        
        <div id="login" class="custom-login-wrapper">
            <form method="post" action="">
                <input type="hidden" name="kac_login_submit" value="1">
                <input type="hidden" name="kac_redirect_to" value="<?php echo esc_attr($atts['redirect']); ?>">
                <?php wp_nonce_field('kac_login_action', 'kac_login_nonce'); ?>
                
                <div class="logo-box">
                    <img src="<?php echo esc_url($atts['logo']); ?>" alt="KAC Logo" />
                </div>

                <div class="form-group">
                    <label for="user_login">사번</label>
                    <input type="text" name="log" id="user_login" class="full-input" required>
                </div>

                <div class="form-group password-wrapper">
                    <label for="user_pass">비밀번호</label>
                    <div class="wp-pwd">
                        <input type="password" name="pwd" id="user_pass" class="full-input" required>
                        <button type="button" class="button wp-hide-pw" data-toggle="0" aria-label="비밀번호 표시">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                </div>

                <div class="form-group rememberme">
                    <input name="rememberme" type="checkbox" id="rememberme" value="forever">
                    <label for="rememberme">로그인 유지</label>
                </div>

                <div class="button-group">
                    <input type="submit" name="wp-submit" id="wp-submit" value="로그인">
                    <a class="wp-login-register" href="<?php echo wp_registration_url(); ?>">회원가입</a>
                </div>
            </form>
        </div>
        
        <?php
        return ob_get_clean();
    }
}