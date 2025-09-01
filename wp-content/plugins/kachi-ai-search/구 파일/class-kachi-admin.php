<?php
/**
 * 관리자 메뉴 클래스
 *
 * @package Kachi_Query_System
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 관리자 기능을 처리하는 클래스
 */
class Kachi_Admin {
    
    /**
     * 관리자 메뉴 추가
     */
    public function add_admin_menu() {
        add_menu_page(
            '까치 쿼리 시스템',           // 페이지 타이틀
            '까치 쿼리',                  // 메뉴 타이틀
            'manage_options',             // 권한
            'kachi-query-system',         // 메뉴 슬러그
            array($this, 'admin_page'),   // 콜백 함수
            'dashicons-search',           // 아이콘
            999                           // 위치 (제일 아래)
        );
        
        // 서브메뉴 추가
        add_submenu_page(
            'kachi-query-system',
            '설정',
            '설정',
            'manage_options',
            'kachi-query-settings',
            array($this, 'settings_page')
        );
        
        // 로그 페이지
        add_submenu_page(
            'kachi-query-system',
            '사용 로그',
            '사용 로그',
            'manage_options',
            'kachi-query-logs',
            array($this, 'logs_page')
        );
    }
    
    /**
     * 관리자 스크립트 등록
     */
    public function enqueue_admin_scripts($hook) {
        // 까치 쿼리 관리 페이지에서만 로드
        if (strpos($hook, 'kachi-query') === false) {
            return;
        }
        
        // 미디어 업로더 스크립트 추가
        wp_enqueue_media();
        
        wp_enqueue_style(
            'kachi-admin-style',
            KACHI_PLUGIN_URL . 'assets/css/kachi-admin.css',
            array(),
            KACHI_VERSION
        );
        
        wp_enqueue_script(
            'kachi-admin-script',
            KACHI_PLUGIN_URL . 'assets/js/kachi-admin.js',
            array('jquery'),
            KACHI_VERSION,
            true
        );
        
        wp_localize_script('kachi-admin-script', 'kachi_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kachi_admin_nonce')
        ));
    }
    
    /**
     * 메인 관리자 페이지
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2>사용 방법</h2>
                <p>다음 쇼트코드를 페이지나 포스트에 삽입하여 까치 쿼리 시스템을 사용할 수 있습니다:</p>
                <code>[kachi_query]</code>
                
                <h3>쇼트코드 옵션</h3>
                <ul>
                    <li><code>[kachi_query class="custom-class"]</code> - 커스텀 CSS 클래스 추가</li>
                </ul>
                
                <h3>주요 기능</h3>
                <ul>
                    <li>✅ AI 기반 문서 검색 및 질의응답</li>
                    <li>✅ 태그 및 문서 필터링</li>
                    <li>✅ 실시간 스트리밍 응답 (기본)</li>
                    <li>✅ 채팅 형식 인터페이스</li>
                    <li>✅ 사용자 권한별 접근 제어</li>
                    <li>✅ 시설물 정의 자동 확장</li>
                    <li>✅ 실시간 필터 업데이트</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>시스템 상태</h2>
                <?php $this->display_system_status(); ?>
            </div>
            
            <div class="card">
                <h2>빠른 테스트</h2>
                <p>API 연결 상태를 테스트합니다.</p>
                <button id="test-api-connection" class="button button-primary">API 연결 테스트</button>
                <div id="test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 시스템 상태 표시
     */
    private function display_system_status() {
        $options = get_option('kachi_settings');
        $api_url = isset($options['api_url']) ? $options['api_url'] : 'http://chelly.kr:8001';
        
        ?>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong>API 서버</strong></td>
                    <td><?php echo esc_html($api_url); ?></td>
                </tr>
                <tr>
                    <td><strong>로그인 필수</strong></td>
                    <td><?php echo isset($options['require_login']) && $options['require_login'] ? '✅ 활성' : '❌ 비활성'; ?></td>
                </tr>
                <tr>
                    <td><strong>응답 방식</strong></td>
                    <td>✅ 스트리밍 (실시간)</td>
                </tr>
                <tr>
                    <td><strong>ACF 플러그인</strong></td>
                    <td><?php echo function_exists('get_field') ? '✅ 활성' : '❌ 비활성'; ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * 설정 페이지
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>까치 쿼리 시스템 설정</h1>
            
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>설정이 저장되었습니다.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('kachi_settings_group');
                do_settings_sections('kachi_settings_page');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * 로그 페이지
     */
    public function logs_page() {
        ?>
        <div class="wrap">
            <h1>까치 쿼리 사용 로그</h1>
            <p>향후 사용 로그 기능이 추가될 예정입니다.</p>
        </div>
        <?php
    }
    
    /**
     * 설정 등록
     */
    public function register_settings() {
        register_setting('kachi_settings_group', 'kachi_settings', array($this, 'sanitize_settings'));
        
        // API 섹션
        add_settings_section(
            'kachi_api_section',
            'API 설정',
            array($this, 'api_section_callback'),
            'kachi_settings_page'
        );
        
        add_settings_field(
            'kachi_api_url',
            'API 서버 URL',
            array($this, 'api_url_field_callback'),
            'kachi_settings_page',
            'kachi_api_section'
        );
        
        // 일반 설정 섹션
        add_settings_section(
            'kachi_general_section',
            '일반 설정',
            array($this, 'general_section_callback'),
            'kachi_settings_page'
        );
        
        add_settings_field(
            'kachi_require_login',
            '로그인 필수',
            array($this, 'require_login_field_callback'),
            'kachi_settings_page',
            'kachi_general_section'
        );
        
        add_settings_field(
            'kachi_max_query_length',
            '최대 질문 길이',
            array($this, 'max_query_length_field_callback'),
            'kachi_settings_page',
            'kachi_general_section'
        );
        
        // 디자인 설정 섹션
        add_settings_section(
            'kachi_design_section',
            '디자인 설정',
            array($this, 'design_section_callback'),
            'kachi_settings_page'
        );
        
        add_settings_field(
            'kachi_background_image',
            '배경 이미지',
            array($this, 'background_image_field_callback'),
            'kachi_settings_page',
            'kachi_design_section'
        );
        
        add_settings_field(
            'kachi_background_overlay',
            '배경 오버레이 투명도',
            array($this, 'background_overlay_field_callback'),
            'kachi_settings_page',
            'kachi_design_section'
        );
    }
    
    /**
     * 설정 검증
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // API URL
        if (isset($input['api_url'])) {
            $sanitized['api_url'] = esc_url_raw($input['api_url']);
        }
        
        // 체크박스
        $sanitized['require_login'] = isset($input['require_login']) ? 1 : 0;
        
        // 숫자 필드
        if (isset($input['max_query_length'])) {
            $sanitized['max_query_length'] = absint($input['max_query_length']);
            if ($sanitized['max_query_length'] < 10) {
                $sanitized['max_query_length'] = 500; // 기본값
            }
        }
        
        // 배경 이미지
        if (isset($input['background_image'])) {
            $sanitized['background_image'] = absint($input['background_image']);
        }
        
        // 배경 오버레이
        if (isset($input['background_overlay'])) {
            $sanitized['background_overlay'] = floatval($input['background_overlay']);
            if ($sanitized['background_overlay'] < 0 || $sanitized['background_overlay'] > 1) {
                $sanitized['background_overlay'] = 0.9;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * API 섹션 설명
     */
    public function api_section_callback() {
        echo '<p>까치 쿼리 시스템의 API 서버 설정을 관리합니다.</p>';
    }
    
    /**
     * 일반 설정 섹션 설명
     */
    public function general_section_callback() {
        echo '<p>까치 쿼리 시스템의 일반적인 동작을 설정합니다.</p>';
    }
    
    /**
     * API URL 필드
     */
    public function api_url_field_callback() {
        $options = get_option('kachi_settings');
        $api_url = isset($options['api_url']) ? $options['api_url'] : 'http://chelly.kr:8001';
        ?>
        <input type="text" 
               name="kachi_settings[api_url]" 
               value="<?php echo esc_attr($api_url); ?>" 
               class="regular-text" />
        <p class="description">까치 API 서버의 URL을 입력하세요. (예: http://chelly.kr:8001)</p>
        <?php
    }
    
    /**
     * 로그인 필수 필드
     */
    public function require_login_field_callback() {
        $options = get_option('kachi_settings');
        $require_login = isset($options['require_login']) ? $options['require_login'] : 1;
        ?>
        <input type="checkbox" 
               name="kachi_settings[require_login]" 
               value="1" 
               <?php checked(1, $require_login, true); ?> />
        <label>쿼리 시스템 사용 시 로그인 필수</label>
        <p class="description">체크하면 로그인한 사용자만 쿼리 시스템을 사용할 수 있습니다.</p>
        <?php
    }
    
    /**
     * 최대 질문 길이 필드
     */
    public function max_query_length_field_callback() {
        $options = get_option('kachi_settings');
        $max_length = isset($options['max_query_length']) ? $options['max_query_length'] : 500;
        ?>
        <input type="number" 
               name="kachi_settings[max_query_length]" 
               value="<?php echo esc_attr($max_length); ?>" 
               min="10"
               max="2000"
               class="small-text" />
        <label>글자</label>
        <p class="description">사용자가 입력할 수 있는 최대 질문 길이입니다. (10-2000)</p>
        <?php
    }
    
    /**
     * 디자인 섹션 설명
     */
    public function design_section_callback() {
        echo '<p>까치 쿼리 시스템의 디자인 요소를 설정합니다.</p>';
    }
    
    /**
     * 배경 이미지 필드
     */
    public function background_image_field_callback() {
        $options = get_option('kachi_settings');
        $image_id = isset($options['background_image']) ? $options['background_image'] : '';
        $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
        ?>
        <div class="kachi-image-upload">
            <input type="hidden" id="kachi_background_image" name="kachi_settings[background_image]" value="<?php echo esc_attr($image_id); ?>" />
            <div id="kachi-image-preview" style="margin-bottom: 10px;">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 5px;" />
                <?php endif; ?>
            </div>
            <button type="button" class="button" id="kachi-upload-btn">이미지 선택</button>
            <button type="button" class="button" id="kachi-remove-btn" <?php echo $image_id ? '' : 'style="display:none;"'; ?>>이미지 제거</button>
            <p class="description">쿼리 시스템 페이지의 배경 이미지를 설정합니다. 권장 크기: 1920x1080 이상</p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            $('#kachi-upload-btn').click(function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: '배경 이미지 선택',
                    button: {
                        text: '이 이미지 사용'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#kachi_background_image').val(attachment.id);
                    $('#kachi-image-preview').html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 5px;" />');
                    $('#kachi-remove-btn').show();
                });
                
                mediaUploader.open();
            });
            
            $('#kachi-remove-btn').click(function(e) {
                e.preventDefault();
                $('#kachi_background_image').val('');
                $('#kachi-image-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }
    
    /**
     * 배경 오버레이 필드
     */
    public function background_overlay_field_callback() {
        $options = get_option('kachi_settings');
        $overlay = isset($options['background_overlay']) ? $options['background_overlay'] : 0.9;
        ?>
        <input type="range" 
               id="kachi_background_overlay"
               name="kachi_settings[background_overlay]" 
               value="<?php echo esc_attr($overlay); ?>" 
               min="0"
               max="1"
               step="0.1"
               style="width: 200px;" />
        <span id="overlay-value"><?php echo esc_html($overlay); ?></span>
        <p class="description">배경 이미지 위의 흰색 오버레이 불투명도입니다. (0: 완전 투명, 1: 완전 불투명)</p>
        
        <script>
        jQuery(document).ready(function($) {
            $('#kachi_background_overlay').on('input', function() {
                $('#overlay-value').text($(this).val());
            });
        });
        </script>
        <?php
    }
}