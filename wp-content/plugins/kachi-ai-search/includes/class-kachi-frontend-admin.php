<?php
/**
 * Kachi Frontend Admin Class
 * 프론트엔드 관리자 페이지를 위한 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kachi_Frontend_Admin {
    
    public function __construct() {
        // AJAX 핸들러
        add_action('wp_ajax_kachi_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_kachi_save_settings', array($this, 'ajax_save_settings'));
        
        // 스크립트 및 스타일 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * 프론트엔드 관리자 쇼트코드 렌더링
     */
    public static function render_admin_shortcode($atts) {
        // 권한 확인
        if (!current_user_can('manage_options')) {
            return self::render_access_denied();
        }
        
        // 기존 설정 가져오기
        $options = get_option('kachi_settings', array());
        $api_url = isset($options['api_url']) ? $options['api_url'] : '';
        $require_login = isset($options['require_login']) ? $options['require_login'] : 1;
        $max_query_length = isset($options['max_query_length']) ? $options['max_query_length'] : 500;
        $background_id = isset($options['background_image']) ? $options['background_image'] : '';
        $background_url = $background_id ? wp_get_attachment_url($background_id) : '';
        $background_overlay = isset($options['background_overlay']) ? $options['background_overlay'] : 0.9;
        
        ob_start();
        ?>
        
        <div class="kachi-frontend-admin">
            <div class="kachi-fa-container">
                <!-- 헤더 영역 (로고 + 탭) -->
                <header class="kachi-fa-header-wrapper">
                    <!-- 로고 섹션 -->
                    <div class="kachi-fa-logo-section">
                        <a href="#" class="kachi-fa-logo">
                            <div class="kachi-fa-logo-icon">🦅</div>
                            <span>까치 쿼리 시스템</span>
                        </a>
                    </div>
                    
                    <!-- 상단 탭 네비게이션 -->
                    <nav class="kachi-fa-tabs-nav">
                        <a href="#" class="kachi-fa-tab-item active" data-section="info">
                            <span class="kachi-fa-tab-icon">📋</span>
                            <span class="kachi-fa-tab-text">시스템 정보</span>
                        </a>
                        <a href="#" class="kachi-fa-tab-item" data-section="settings">
                            <span class="kachi-fa-tab-icon">⚙️</span>
                            <span class="kachi-fa-tab-text">설정</span>
                        </a>
                        <a href="#" class="kachi-fa-tab-item" data-section="design">
                            <span class="kachi-fa-tab-icon">🎨</span>
                            <span class="kachi-fa-tab-text">디자인 설정</span>
                        </a>
                        <a href="#" class="kachi-fa-tab-item" data-section="guide">
                            <span class="kachi-fa-tab-icon">❓</span>
                            <span class="kachi-fa-tab-text">사용 방법</span>
                        </a>
                    </nav>
                </header>
                
                <!-- 메인 영역 -->
                <main class="kachi-fa-main">
                    <!-- 알림 영역 -->
                    <div class="kachi-fa-notice" style="display:none;"></div>
                    
                    <!-- 시스템 정보 섹션 -->
                    <?php self::render_info_section($options); ?>
                    
                    <!-- 설정 섹션 -->
                    <?php self::render_settings_section($api_url, $require_login, $max_query_length); ?>
                    
                    <!-- 디자인 설정 섹션 -->
                    <?php self::render_design_section($background_id, $background_url, $background_overlay); ?>
                    
                    <!-- 사용 방법 섹션 -->
                    <?php self::render_guide_section(); ?>
                </main>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 시스템 정보 섹션 렌더링
     */
    private static function render_info_section($options) {
        $api_url = isset($options['api_url']) ? $options['api_url'] : '';
        ?>
        <div class="kachi-fa-section active" data-section="info">
            <header class="kachi-fa-section-header">
                <div class="kachi-fa-header-left">
                    <h1 class="kachi-fa-title" style="font-size:28px;">시스템 정보</h1>
                </div>
            </header>
            
            <div class="kachi-fa-content">
                <div class="kachi-fa-info-grid">
                    <!-- 시스템 상태 카드 -->
                    <div class="kachi-fa-info-card">
                        <h3>
                            <span class="kachi-fa-icon">🖥️</span>
                            시스템 상태
                        </h3>
                        <div class="kachi-fa-info-items">
                            <div class="kachi-fa-info-item">
                                <span class="kachi-fa-label">플러그인 버전:</span>
                                <span class="kachi-fa-value"><?php echo KACHI_VERSION; ?></span>
                            </div>
                            <div class="kachi-fa-info-item">
                                <span class="kachi-fa-label">API 서버:</span>
                                <span class="kachi-fa-value"><?php echo esc_html($api_url); ?></span>
                            </div>
                            <div class="kachi-fa-info-item">
                                <span class="kachi-fa-label">로그인 필수:</span>
                                <span class="kachi-fa-value">
                                    <?php echo isset($options['require_login']) && $options['require_login'] ? 
                                        '<span class="kachi-fa-badge-success">✓ 활성</span>' : 
                                        '<span class="kachi-fa-badge-danger">✗ 비활성</span>'; ?>
                                </span>
                            </div>
                            <div class="kachi-fa-info-item">
                                <span class="kachi-fa-label">응답 방식:</span>
                                <span class="kachi-fa-value kachi-fa-badge-success">✓ 스트리밍 (실시간)</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- API 테스트 카드 -->
                    <div class="kachi-fa-info-card">
                        <h3>
                            <span class="kachi-fa-icon">🔍</span>
                            API 연결 테스트
                        </h3>
                        <p class="kachi-fa-help-text">현재 설정된 API 서버와의 연결 상태를 확인합니다.</p>
                        <div class="kachi-fa-button-group">
                            <button type="button" class="kachi-fa-btn-primary" id="test-api-connection">
                                <span class="kachi-fa-icon">🚀</span>
                                <span>API 연결 테스트</span>
                            </button>
                        </div>
                        <div id="test-result" class="kachi-fa-test-result" style="margin-top: 15px;"></div>
                    </div>
                    
                    <!-- 주요 기능 카드 -->
                    <div class="kachi-fa-info-card" style="grid-column: 1 / -1;">
                        <h3>
                            <span class="kachi-fa-icon">✨</span>
                            주요 기능
                        </h3>
                        <div class="kachi-fa-feature-grid">
                            <div class="kachi-fa-feature-item">
                                <span class="kachi-fa-feature-icon">🤖</span>
                                <span class="kachi-fa-feature-text">AI 기반 문서 검색 및 질의응답</span>
                            </div>
                            <div class="kachi-fa-feature-item">
                                <span class="kachi-fa-feature-icon">🏷️</span>
                                <span class="kachi-fa-feature-text">태그 및 문서 필터링</span>
                            </div>
                            <div class="kachi-fa-feature-item">
                                <span class="kachi-fa-feature-icon">⚡</span>
                                <span class="kachi-fa-feature-text">실시간 스트리밍 응답</span>
                            </div>
                            <div class="kachi-fa-feature-item">
                                <span class="kachi-fa-feature-icon">💬</span>
                                <span class="kachi-fa-feature-text">채팅 형식 인터페이스</span>
                            </div>
                            <div class="kachi-fa-feature-item">
                                <span class="kachi-fa-feature-icon">🔐</span>
                                <span class="kachi-fa-feature-text">사용자 권한별 접근 제어</span>
                            </div>
                            <div class="kachi-fa-feature-item">
                                <span class="kachi-fa-feature-icon">🏢</span>
                                <span class="kachi-fa-feature-text">시설물 정의 자동 확장</span>
                            </div>
                            <div class="kachi-fa-feature-item">
                                <span class="kachi-fa-feature-icon">🔄</span>
                                <span class="kachi-fa-feature-text">실시간 필터 업데이트</span>
                            </div>
                            <div class="kachi-fa-feature-item">
                                <span class="kachi-fa-feature-icon">📝</span>
                                <span class="kachi-fa-feature-text">대화 기록 저장 및 관리</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 설정 섹션 렌더링
     */
    private static function render_settings_section($api_url, $require_login, $max_query_length) {
        ?>
        <div class="kachi-fa-section" data-section="settings">
            <header class="kachi-fa-section-header">
                <div class="kachi-fa-header-left">
                    <h1 class="kachi-fa-title" style="font-size:28px;">설정</h1>
                </div>
            </header>
            
            <div class="kachi-fa-content">
                <div class="kachi-fa-setting-card">
                    <h3>
                        <span class="kachi-fa-icon">🔧</span>
                        API 설정
                    </h3>
                    
                    <div class="kachi-fa-form-group">
                        <label for="api-url">API 서버 URL</label>
                        <input type="text" id="api-url" class="kachi-fa-input" 
                               value="<?php echo esc_attr($api_url); ?>" 
                               placeholder="http://192.168.10.101:8001">
                        <p class="kachi-fa-help-text">까치 API 서버의 URL을 입력하세요.</p>
                    </div>
                </div>
                
                <div class="kachi-fa-setting-card">
                    <h3>
                        <span class="kachi-fa-icon">⚡</span>
                        일반 설정
                    </h3>
                    
                    <div class="kachi-fa-form-group">
                        <label class="kachi-fa-checkbox-label">
                            <input type="checkbox" id="require-login" <?php checked($require_login, 1); ?>>
                            <span>로그인 필수</span>
                        </label>
                        <p class="kachi-fa-help-text">체크하면 로그인한 사용자만 쿼리 시스템을 사용할 수 있습니다.</p>
                    </div>
                    
                    <div class="kachi-fa-form-group">
                        <label for="max-query-length">최대 질문 길이</label>
                        <div class="kachi-fa-input-group">
                            <input type="number" id="max-query-length" class="kachi-fa-input" 
                                   value="<?php echo esc_attr($max_query_length); ?>" 
                                   min="10" max="2000" style="max-width: 150px;">
                            <span class="kachi-fa-input-suffix">글자</span>
                        </div>
                        <p class="kachi-fa-help-text">사용자가 입력할 수 있는 최대 질문 길이입니다. (10-2000)</p>
                    </div>
                    
                    <div class="kachi-fa-form-actions">
                        <button type="button" class="kachi-fa-btn-primary" id="save-general-settings">
                            <span class="kachi-fa-icon">💾</span>
                            <span>설정 저장</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 디자인 설정 섹션 렌더링
     */
    private static function render_design_section($background_id, $background_url, $background_overlay) {
        ?>
        <div class="kachi-fa-section" data-section="design">
            <header class="kachi-fa-section-header">
                <div class="kachi-fa-header-left">
                    <h1 class="kachi-fa-title" style="font-size:28px;">디자인 설정</h1>
                </div>
            </header>
            
            <div class="kachi-fa-content">
                <div class="kachi-fa-setting-card">
                    <h3>
                        <span class="kachi-fa-icon">🖼️</span>
                        배경 이미지 설정
                    </h3>
                    <p class="kachi-fa-help-text">쿼리 시스템 페이지의 배경 이미지를 설정합니다. 권장 크기: 1920x1080 이상</p>
                    
                    <div class="kachi-fa-form-group">
                        <label>배경 이미지</label>
                        <div class="kachi-fa-image-input-group">
                            <input type="hidden" id="background-image-id" value="<?php echo esc_attr($background_id); ?>">
                            <button type="button" class="kachi-fa-btn-secondary" id="upload-background-image">
                                <span class="kachi-fa-icon">📁</span>
                                이미지 선택
                            </button>
                            <?php if ($background_url): ?>
                                <button type="button" class="kachi-fa-btn-secondary" id="remove-background-image">
                                    <span class="kachi-fa-icon">❌</span>
                                    이미지 제거
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($background_url): ?>
                            <div class="kachi-fa-image-preview" id="background-image-preview">
                                <img src="<?php echo esc_url($background_url); ?>" alt="배경 이미지 미리보기">
                            </div>
                        <?php else: ?>
                            <div class="kachi-fa-image-preview" id="background-image-preview" style="display: none;">
                                <img src="" alt="배경 이미지 미리보기">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="kachi-fa-form-group">
                        <label for="background-overlay">배경 오버레이 투명도</label>
                        <div class="kachi-fa-range-group">
                            <input type="range" id="background-overlay" class="kachi-fa-range" 
                                   value="<?php echo esc_attr($background_overlay); ?>" 
                                   min="0" max="1" step="0.1">
                            <span class="kachi-fa-range-value"><?php echo esc_html($background_overlay); ?></span>
                        </div>
                        <p class="kachi-fa-help-text">배경 이미지 위의 흰색 오버레이 불투명도입니다. (0: 완전 투명, 1: 완전 불투명)</p>
                    </div>
                    
                    <div class="kachi-fa-form-actions">
                        <button type="button" class="kachi-fa-btn-primary" id="save-design-settings">
                            <span class="kachi-fa-icon">💾</span>
                            <span>디자인 설정 저장</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 사용 방법 섹션 렌더링
     */
    private static function render_guide_section() {
        ?>
        <div class="kachi-fa-section" data-section="guide">
            <header class="kachi-fa-section-header">
                <div class="kachi-fa-header-left">
                    <h1 class="kachi-fa-title" style="font-size:28px;">사용 방법</h1>
                </div>
            </header>
            
            <div class="kachi-fa-content">
                <div class="kachi-fa-guide-grid">
                    <!-- 까치 쿼리 시스템 -->
                    <div class="kachi-fa-guide-card">
                        <h3>
                            <span class="kachi-fa-icon">🦅</span>
                            까치 쿼리 시스템
                        </h3>
                        <div class="kachi-fa-code-block">
                            <code>[kachi_query]</code>
                        </div>
                        <p>페이지나 포스트에 위 숏코드를 삽입하여 까치 쿼리 시스템을 사용할 수 있습니다.</p>
                        <ul class="kachi-fa-feature-list">
                            <li>AI 기반 자연어 검색</li>
                            <li>실시간 스트리밍 응답</li>
                            <li>대화 기록 저장</li>
                            <li>태그/문서 필터링</li>
                        </ul>
                    </div>
                    
                    <!-- 관리자 페이지 -->
                    <div class="kachi-fa-guide-card">
                        <h3>
                            <span class="kachi-fa-icon">⚙️</span>
                            관리자 페이지
                        </h3>
                        <div class="kachi-fa-code-block">
                            <code>[kachi_admin]</code>
                        </div>
                        <p>프론트엔드에서 플러그인 설정을 관리할 수 있는 관리자 페이지입니다.</p>
                        <ul class="kachi-fa-feature-list">
                            <li>관리자 권한 필요</li>
                            <li>API 서버 설정</li>
                            <li>배경 이미지 설정</li>
                            <li>접근 권한 관리</li>
                        </ul>
                    </div>
                    
                    <!-- 쇼트코드 옵션 -->
                    <div class="kachi-fa-guide-card">
                        <h3>
                            <span class="kachi-fa-icon">🔧</span>
                            쇼트코드 옵션
                        </h3>
                        <div class="kachi-fa-code-block">
                            <code>[kachi_query class="custom-class"]</code>
                        </div>
                        <p>커스텀 CSS 클래스를 추가하여 스타일을 커스터마이징할 수 있습니다.</p>
                        <div class="kachi-fa-code-block">
                            <code>[kachi_query fullpage="yes"]</code>
                        </div>
                        <p>전체 화면 모드로 표시합니다. (기본값: yes)</p>
                    </div>
                    
                    <!-- 사용자 프로필 설정 -->
                    <div class="kachi-fa-guide-card">
                        <h3>
                            <span class="kachi-fa-icon">👤</span>
                            사용자 프로필 설정
                        </h3>
                        <p>각 사용자는 프로필에서 소속과 현장 정보를 설정할 수 있습니다.</p>
                        <ul class="kachi-fa-input-guide">
                            <li><strong>소속:</strong> 사용자가 속한 조직이나 부서</li>
                            <li><strong>현장:</strong> 사용자가 담당하는 현장이나 프로젝트</li>
                        </ul>
                        <p class="kachi-fa-help-text">이 정보는 문서 필터링과 시설물 정의 확장에 사용됩니다.</p>
                    </div>
                    
                    <!-- API 연동 -->
                    <div class="kachi-fa-guide-card">
                        <h3>
                            <span class="kachi-fa-icon">🔌</span>
                            API 연동
                        </h3>
                        <div class="kachi-fa-info-box">
                            <h4>엔드포인트</h4>
                            <p><code>/query-stream</code> - 스트리밍 쿼리</p>
                            <p><code>/list-tags</code> - 태그 목록</p>
                            <p><code>/list-documents</code> - 문서 목록</p>
                        </div>
                        <div class="kachi-fa-info-box">
                            <h4>파라미터</h4>
                            <p><code>user_query</code> - 사용자 질문</p>
                            <p><code>tags[]</code> - 선택된 태그</p>
                            <p><code>doc_names[]</code> - 선택된 문서</p>
                            <p><code>sosok</code> - 사용자 소속</p>
                            <p><code>site</code> - 사용자 현장</p>
                        </div>
                    </div>
                    
                    <!-- 문제 해결 -->
                    <div class="kachi-fa-guide-card">
                        <h3>
                            <span class="kachi-fa-icon">🛠️</span>
                            문제 해결
                        </h3>
                        <div class="kachi-fa-info-box">
                            <h4>API 연결 실패</h4>
                            <p>API 서버 URL이 올바른지 확인하고, 방화벽 설정을 확인하세요.</p>
                        </div>
                        <div class="kachi-fa-info-box">
                            <h4>로그인 문제</h4>
                            <p>로그인 필수 옵션을 해제하거나, 사용자 권한을 확인하세요.</p>
                        </div>
                        <div class="kachi-fa-info-box">
                            <h4>스트리밍 중단</h4>
                            <p>네트워크 연결 상태를 확인하고, API 서버 상태를 점검하세요.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 접근 거부 메시지
     */
    private static function render_access_denied() {
        return '
        <div class="kachi-fa-access-denied">
            <span class="kachi-fa-icon">🚫</span>
            <h3>접근 권한이 없습니다</h3>
            <p>이 페이지는 관리자만 접근할 수 있습니다.</p>
        </div>';
    }
    
    /**
     * 프론트엔드 스크립트 및 스타일 등록
     */
    public function enqueue_frontend_scripts() {
        global $post;
        
        // 쇼트코드가 있는 페이지인지 확인
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'kachi_admin')) {
            // 미디어 업로더
            wp_enqueue_media();
            
            // 스타일
            wp_enqueue_style('kachi-frontend-admin', KACHI_PLUGIN_URL . 'assets/css/kachi-frontend-admin.css', array(), KACHI_VERSION);
            
            // 스크립트
            wp_enqueue_script('kachi-frontend-admin', KACHI_PLUGIN_URL . 'assets/js/kachi-frontend-admin.js', array('jquery'), KACHI_VERSION, true);
            
            // Localization
            wp_localize_script('kachi-frontend-admin', 'kachi_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kachi_frontend_nonce'),
                'api_url' => isset($options['api_url']) ? $options['api_url'] : 'http://chelly.kr:8001',
                'messages' => array(
                    'save_success' => '설정이 저장되었습니다.',
                    'save_error' => '저장 중 오류가 발생했습니다.',
                    'test_in_progress' => 'API 연결을 테스트하는 중...',
                    'test_success' => 'API 연결 성공!',
                    'test_error' => 'API 연결 실패:',
                    'loading' => '처리 중...'
                )
            ));
        }
    }
    
    /**
     * AJAX: API 테스트
     */
    public function ajax_test_api() {
        // Nonce 검증
        if (!check_ajax_referer('kachi_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $options = get_option('kachi_settings');
        $api_url = isset($options['api_url']) ? $options['api_url'] : '';
        
        // API 테스트
        $response = wp_remote_get($api_url . '/list-tags', array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            wp_send_json_success('API 서버와 정상적으로 연결되었습니다.');
        } else {
            wp_send_json_error('API 서버 응답 오류: HTTP ' . $status_code);
        }
    }
    
    /**
     * AJAX: 설정 저장
     */
    public function ajax_save_settings() {
        // Nonce 검증
        if (!check_ajax_referer('kachi_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        // 기존 설정 가져오기
        $options = get_option('kachi_settings', array());
        
        // 설정 업데이트
        if (isset($_POST['api_url'])) {
            $options['api_url'] = esc_url_raw($_POST['api_url']);
        }
        
        if (isset($_POST['require_login'])) {
            $options['require_login'] = $_POST['require_login'] === 'true' ? 1 : 0;
        }
        
        if (isset($_POST['max_query_length'])) {
            $options['max_query_length'] = absint($_POST['max_query_length']);
            if ($options['max_query_length'] < 10 || $options['max_query_length'] > 2000) {
                $options['max_query_length'] = 500;
            }
        }
        
        if (isset($_POST['background_image'])) {
            $options['background_image'] = absint($_POST['background_image']);
        }
        
        if (isset($_POST['background_overlay'])) {
            $options['background_overlay'] = floatval($_POST['background_overlay']);
            if ($options['background_overlay'] < 0 || $options['background_overlay'] > 1) {
                $options['background_overlay'] = 0.9;
            }
        }
        
        // 설정 저장
        update_option('kachi_settings', $options);
        wp_send_json_success('설정이 저장되었습니다.');
    }
}