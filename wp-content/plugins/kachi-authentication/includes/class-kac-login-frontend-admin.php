<?php
/**
 * KAC Login Frontend Admin Class
 * 프론트엔드 관리자 페이지를 위한 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class KAC_Login_Frontend_Admin {
    
    private $option_name = 'kac_login_settings';
    
    public function __construct() {
        // 쇼트코드 등록
        add_shortcode('kac_login_admin', array($this, 'render_admin_shortcode'));
        
        // AJAX 핸들러
        add_action('wp_ajax_kac_frontend_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_kac_frontend_save_organization', array($this, 'ajax_save_organization'));
        add_action('wp_ajax_kac_frontend_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_kac_frontend_update_user_sites', array($this, 'ajax_update_user_sites'));
        add_action('wp_ajax_kac_frontend_update_organization_item', array($this, 'ajax_update_organization_item'));
        
        // 스크립트 및 스타일 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * 프론트엔드 관리자 쇼트코드 렌더링
     */
    public function render_admin_shortcode($atts) {
        // 권한 확인
        if (!current_user_can('manage_options')) {
            return $this->render_access_denied();
        }
        
        // 기존 설정 가져오기
        $settings = get_option($this->option_name, array());
        $org_data = get_option('kac_organization_data', $this->get_default_organization_data());
        
        ob_start();
        ?>
        
        <div class="kac-frontend-admin">
            <div class="kac-fa-container">
                <!-- 헤더 영역 (로고 + 탭) -->
                <header class="kac-fa-header-wrapper">
                    <!-- 로고 섹션 -->
                    <div class="kac-fa-logo-section">
                        <a href="#" class="kac-fa-logo">
                            <div class="kac-fa-logo-icon">🔐</div>
                            <span>로그인 시스템</span>
                        </a>
                    </div>
                    
                    <!-- 상단 탭 네비게이션 -->
                    <nav class="kac-fa-tabs-nav">
                        <a href="#" class="kac-fa-tab-item active" data-section="organization">
                            <span class="kac-fa-tab-icon">🏢</span>
                            <span class="kac-fa-tab-text">조직 관리</span>
                        </a>
                        <a href="#" class="kac-fa-tab-item" data-section="settings">
                            <span class="kac-fa-tab-icon">⚙️</span>
                            <span class="kac-fa-tab-text">기본 설정</span>
                        </a>
                        <a href="#" class="kac-fa-tab-item" data-section="shortcodes">
                            <span class="kac-fa-tab-icon">📝</span>
                            <span class="kac-fa-tab-text">사용 방법</span>
                        </a>
                    </nav>
                </header>
                
                <!-- 메인 영역 -->
                <main class="kac-fa-main">
                    <!-- 알림 영역 -->
                    <div class="kac-fa-notice" style="display:none;"></div>
                    
                    <!-- 조직 관리 섹션 -->
                    <?php $this->render_organization_section($org_data); ?>
                    
                    <!-- 기본 설정 섹션 -->
                    <?php $this->render_settings_section($settings); ?>
                    
                    <!-- 사용 방법 섹션 -->
                    <?php $this->render_shortcodes_section(); ?>
                </main>
            </div>
        </div>
        
        <!-- SVG 아이콘 정의 -->
        <svg style="display: none;">
            <defs>
                <!-- 수정 아이콘 -->
                <symbol id="kac-icon-edit" viewBox="0 0 24 24">
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                </symbol>
                <!-- 삭제 아이콘 -->
                <symbol id="kac-icon-delete" viewBox="0 0 24 24">
                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                </symbol>
            </defs>
        </svg>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 기본 설정 섹션 렌더링
     */
    private function render_settings_section($settings) {
        ?>
        <div class="kac-fa-section" data-section="settings">
            <header class="kac-fa-section-header">
                <div class="kac-fa-header-left">
                    <h1 class="kac-fa-title">기본 설정</h1>
                </div>
                <div class="kac-fa-header-actions">
                    <span class="kac-fa-save-status" style="display: none;">
                        <span class="kac-fa-icon">✅</span>
                        <span>자동 저장됨</span>
                    </span>
                </div>
            </header>
            
            <div class="kac-fa-content">
                <div class="kac-fa-settings-grid">
                    <!-- 로고 설정 -->
                    <div class="kac-fa-setting-card">
                        <h3>
                            <span class="kac-fa-icon">🖼️</span>
                            로고 이미지
                        </h3>
                        <p class="kac-fa-help-text">로그인 및 회원가입 폼에 표시될 로고 이미지를 설정합니다.</p>
                        
                        <div class="kac-fa-form-group">
                            <label>로고 이미지 URL</label>
                            <div class="kac-fa-image-input-group">
                                <input type="text" 
                                       id="logo_url" 
                                       class="kac-fa-input kac-image-url auto-save" 
                                       value="<?php echo esc_attr($settings['logo_url'] ?? ''); ?>" 
                                       placeholder="이미지 URL을 입력하거나 선택하세요">
                                <button type="button" class="kac-fa-btn-secondary kac-upload-button" data-target="#logo_url">
                                    <span class="kac-fa-icon">📁</span>
                                    이미지 선택
                                </button>
                            </div>
                            <?php if (!empty($settings['logo_url'])): ?>
                                <div class="kac-fa-image-preview">
                                    <img src="<?php echo esc_url($settings['logo_url']); ?>" alt="로고 미리보기">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 배경 이미지 설정 -->
                    <div class="kac-fa-setting-card">
                        <h3>
                            <span class="kac-fa-icon">🎨</span>
                            배경 이미지
                        </h3>
                        <p class="kac-fa-help-text">전체 페이지 모드에서 사용될 배경 이미지를 설정합니다.</p>
                        
                        <div class="kac-fa-form-group">
                            <label>배경 이미지 URL</label>
                            <div class="kac-fa-image-input-group">
                                <input type="text" 
                                       id="background_url" 
                                       class="kac-fa-input kac-image-url auto-save" 
                                       value="<?php echo esc_attr($settings['background_url'] ?? ''); ?>" 
                                       placeholder="이미지 URL을 입력하거나 선택하세요">
                                <button type="button" class="kac-fa-btn-secondary kac-upload-button" data-target="#background_url">
                                    <span class="kac-fa-icon">📁</span>
                                    이미지 선택
                                </button>
                            </div>
                            <?php if (!empty($settings['background_url'])): ?>
                                <div class="kac-fa-image-preview">
                                    <img src="<?php echo esc_url($settings['background_url']); ?>" alt="배경 미리보기">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 회원가입 완료 페이지 설정 -->
                    <div class="kac-fa-setting-card">
                        <h3>
                            <span class="kac-fa-icon">✅</span>
                            회원가입 완료 페이지
                        </h3>
                        <p class="kac-fa-help-text">회원가입 완료 페이지에 표시될 이미지를 설정합니다.</p>
                        
                        <div class="kac-fa-form-group">
                            <label>배경 이미지 URL</label>
                            <div class="kac-fa-image-input-group">
                                <input type="text" 
                                       id="complete_background_url" 
                                       class="kac-fa-input kac-image-url auto-save" 
                                       value="<?php echo esc_attr($settings['complete_background_url'] ?? ''); ?>" 
                                       placeholder="배경 이미지 URL">
                                <button type="button" class="kac-fa-btn-secondary kac-upload-button" data-target="#complete_background_url">
                                    <span class="kac-fa-icon">📁</span>
                                    선택
                                </button>
                            </div>
                        </div>
                        
                        <div class="kac-fa-form-group">
                            <label>표시 이미지 URL</label>
                            <div class="kac-fa-image-input-group">
                                <input type="text" 
                                       id="complete_image_url" 
                                       class="kac-fa-input kac-image-url auto-save" 
                                       value="<?php echo esc_attr($settings['complete_image_url'] ?? ''); ?>" 
                                       placeholder="표시 이미지 URL">
                                <button type="button" class="kac-fa-btn-secondary kac-upload-button" data-target="#complete_image_url">
                                    <span class="kac-fa-icon">📁</span>
                                    선택
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 리다이렉트 및 링크 설정 -->
                    <div class="kac-fa-setting-card">
                        <h3>
                            <span class="kac-fa-icon">🔗</span>
                            페이지 설정
                        </h3>
                        <p class="kac-fa-help-text">로그인 후 이동 페이지 및 약관 링크를 설정합니다.</p>
                        
                        <div class="kac-fa-form-group">
                            <label>로그인 후 이동 페이지</label>
                            <input type="text" 
                                   id="default_redirect" 
                                   class="kac-fa-input auto-save" 
                                   value="<?php echo esc_attr($settings['default_redirect'] ?? home_url('/')); ?>" 
                                   placeholder="<?php echo home_url('/'); ?>">
                        </div>
                        
                        <div class="kac-fa-form-group">
                            <label>이용약관 URL</label>
                            <input type="text" 
                                   id="terms_url" 
                                   class="kac-fa-input auto-save" 
                                   value="<?php echo esc_attr($settings['terms_url'] ?? '#'); ?>" 
                                   placeholder="이용약관 페이지 URL">
                        </div>
                        
                        <div class="kac-fa-form-group">
                            <label>개인정보처리방침 URL</label>
                            <input type="text" 
                                   id="privacy_url" 
                                   class="kac-fa-input auto-save" 
                                   value="<?php echo esc_attr($settings['privacy_url'] ?? '#'); ?>" 
                                   placeholder="개인정보처리방침 페이지 URL">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 조직 관리 섹션 렌더링
     */
    private function render_organization_section($org_data) {
        ?>
        <div class="kac-fa-section active" data-section="organization">
            <header class="kac-fa-section-header">
                <div class="kac-fa-header-left">
                    <h1 class="kac-fa-title">조직 관리</h1>
                </div>
                <div class="kac-fa-header-actions">
                    <span class="kac-fa-save-status" style="display: none;">
                        <span class="kac-fa-icon">✅</span>
                        <span>자동 저장됨</span>
                    </span>
                </div>
            </header>
            
            <div class="kac-fa-content">
                <div class="kac-fa-org-container">
                    <!-- 소속 관리 -->
                    <div class="kac-fa-org-section">
                        <h3>소속 목록</h3>
                        <div id="sosok-list" class="kac-fa-org-list">
                            <!-- 소속 목록이 여기에 표시됩니다 -->
                        </div>
                        <div class="kac-fa-add-item">
                            <input type="text" id="new-sosok" class="kac-fa-input" placeholder="새 소속 추가">
                            <button type="button" class="kac-fa-btn-secondary" onclick="addSosok()">추가</button>
                        </div>
                    </div>
                    
                    <!-- 부서 관리 -->
                    <div class="kac-fa-org-section">
                        <h3>부서 목록</h3>
                        <div id="selected-sosok" class="kac-fa-selected-label">소속을 선택하세요</div>
                        <div id="buseo-list" class="kac-fa-org-list">
                            <!-- 부서 목록이 여기에 표시됩니다 -->
                        </div>
                        <div class="kac-fa-add-item">
                            <input type="text" id="new-buseo" class="kac-fa-input" placeholder="새 부서 추가" disabled>
                            <button type="button" class="kac-fa-btn-secondary" onclick="addBuseo()" disabled id="add-buseo-btn">추가</button>
                        </div>
                    </div>
                    
                    <!-- 현장 관리 -->
                    <div class="kac-fa-org-section">
                        <h3>현장 목록</h3>
                        <div id="selected-buseo" class="kac-fa-selected-label">부서를 선택하세요</div>
                        <div id="site-list" class="kac-fa-org-list">
                            <!-- 현장 목록이 여기에 표시됩니다 -->
                        </div>
                        <div class="kac-fa-add-item">
                            <input type="text" id="new-site" class="kac-fa-input" placeholder="새 현장 추가" disabled>
                            <button type="button" class="kac-fa-btn-secondary" onclick="addSite()" disabled id="add-site-btn">추가</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        let organizationData = <?php echo json_encode($org_data); ?>;
        let selectedSosok = null;
        let selectedBuseo = null;
        </script>
        <?php
    }
    
    /**
     * 사용 방법 섹션 렌더링
     */
    private function render_shortcodes_section() {
        ?>
        <div class="kac-fa-section" data-section="shortcodes">
            <header class="kac-fa-section-header">
                <div class="kac-fa-header-left">
                    <h1 class="kac-fa-title">사용 방법</h1>
                </div>
            </header>
            
            <div class="kac-fa-content">
                <div class="kac-fa-shortcode-grid">
                    <!-- 로그인 페이지 -->
                    <div class="kac-fa-shortcode-card">
                        <h3>
                            <span class="kac-fa-icon">🔐</span>
                            로그인 페이지
                        </h3>
                        <div class="kac-fa-code-block">
                            <code>[kac_login]</code>
                        </div>
                        <p>옵션 사용:</p>
                        <div class="kac-fa-code-block">
                            <code>[kac_login logo="이미지URL" redirect="리다이렉트URL"]</code>
                        </div>
                        <ul class="kac-fa-option-list">
                            <li><strong>logo:</strong> 로고 이미지 URL</li>
                            <li><strong>redirect:</strong> 로그인 후 이동할 페이지 URL</li>
                        </ul>
                    </div>
                    
                    <!-- 회원가입 페이지 -->
                    <div class="kac-fa-shortcode-card">
                        <h3>
                            <span class="kac-fa-icon">✍️</span>
                            회원가입 페이지
                        </h3>
                        <div class="kac-fa-code-block">
                            <code>[kac_register]</code>
                        </div>
                        <p>옵션 사용:</p>
                        <div class="kac-fa-code-block">
                            <code>[kac_register logo="이미지URL" redirect="완료페이지URL"]</code>
                        </div>
                        <ul class="kac-fa-option-list">
                            <li><strong>logo:</strong> 로고 이미지 URL</li>
                            <li><strong>redirect:</strong> 가입 완료 후 이동할 페이지 URL</li>
                        </ul>
                    </div>
                    
                    <!-- 회원가입 완료 페이지 -->
                    <div class="kac-fa-shortcode-card">
                        <h3>
                            <span class="kac-fa-icon">✅</span>
                            회원가입 완료 페이지
                        </h3>
                        <div class="kac-fa-code-block">
                            <code>[kac_register_complete]</code>
                        </div>
                        <p>옵션 사용:</p>
                        <div class="kac-fa-code-block">
                            <code>[kac_register_complete background="배경URL" image="이미지URL"]</code>
                        </div>
                        <ul class="kac-fa-option-list">
                            <li><strong>background:</strong> 페이지 배경 이미지 URL</li>
                            <li><strong>image:</strong> 표시할 이미지 URL</li>
                        </ul>
                    </div>
                    
                    <!-- 관리자 페이지 -->
                    <div class="kac-fa-shortcode-card">
                        <h3>
                            <span class="kac-fa-icon">⚙️</span>
                            관리자 페이지
                        </h3>
                        <div class="kac-fa-code-block">
                            <code>[kac_login_admin]</code>
                        </div>
                        <p>로그인 시스템의 모든 설정을 관리할 수 있는 프론트엔드 관리자 페이지입니다.</p>
                        <ul class="kac-fa-option-list">
                            <li>관리자 권한이 필요합니다</li>
                            <li>기본 설정, 조직 관리, 사용 방법을 확인할 수 있습니다</li>
                        </ul>
                    </div>
                </div>
                
                <!-- 전체 페이지 모드 안내 -->
                <div class="kac-fa-info-box">
                    <h3>
                        <span class="kac-fa-icon">💡</span>
                        전체 페이지 모드
                    </h3>
                    <p>테마 레이아웃 없이 로그인/회원가입 페이지만 표시하려면 URL 끝에 <code>?full_page=1</code> 파라미터를 추가하세요.</p>
                    <div class="kac-fa-code-block">
                        <code>https://your-site.com/login-page/?full_page=1</code>
                    </div>
                </div>
                
                <!-- 페이지 생성 가이드 -->
                <div class="kac-fa-info-box">
                    <h3>
                        <span class="kac-fa-icon">📋</span>
                        페이지 생성 가이드
                    </h3>
                    <ol class="kac-fa-step-list">
                        <li>워드프레스 관리자에서 "페이지 > 새로 추가" 클릭</li>
                        <li>페이지 제목 입력 (예: "로그인", "회원가입", "가입완료")</li>
                        <li>본문에 해당 쇼트코드 입력</li>
                        <li>페이지 발행</li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 접근 거부 메시지
     */
    private function render_access_denied() {
        return '
        <div class="kac-fa-access-denied">
            <span class="kac-fa-icon">🚫</span>
            <h3>접근 권한이 없습니다</h3>
            <p>이 페이지는 관리자만 접근할 수 있습니다.</p>
        </div>';
    }
    
    /**
     * 기본 조직 데이터
     */
    private function get_default_organization_data() {
        return array(
            "관리자" => array(
                "관리자" => array("관리자")
            ),
            "전략기획본부" => array(
                "스마트공항추진실" => array("스마트공항추진실장", "스마트기획부", "데이터융합부", "스마트공항부")
            ),
            "김포공항" => array(
                "레이더관제부" => array("레이더관제송신소", "관제수신소", "관제통신소", "ASDE", "레이더관제부 사무실"),
                "기술지원부" => array("test1", "test2")
            ),
            "김해공항" => array(
                "관제부" => array("김해 제1현장", "김해 제2현장")
            ),
            "제주공항" => array(
                "운영부" => array("제주 A현장", "제주 B현장"),
                "기술부" => array("제주 C현장")
            ),
            "항공기술훈련원" => array(
                "훈련지원부" => array("훈련센터 1호관", "훈련센터 2호관")
            )
        );
    }
    
    /**
     * 프론트엔드 스크립트 및 스타일 등록
     */
    public function enqueue_frontend_scripts() {
        global $post;
        
        // 쇼트코드가 있는 페이지인지 확인
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'kac_login_admin')) {
            // 미디어 업로더
            wp_enqueue_media();
            
            // 스타일
            wp_enqueue_style('kac-frontend-admin', KAC_LOGIN_PLUGIN_URL . 'assets/css/kac-frontend-admin.css', array(), KAC_LOGIN_VERSION);
            
            // 스크립트
            wp_enqueue_script('kac-frontend-admin', KAC_LOGIN_PLUGIN_URL . 'assets/js/kac-frontend-admin.js', array('jquery'), KAC_LOGIN_VERSION, true);
            
            // Localization
            wp_localize_script('kac-frontend-admin', 'kac_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kac_frontend_nonce'),
                'messages' => array(
                    'save_success' => '저장되었습니다.',
                    'save_error' => '저장 중 오류가 발생했습니다.',
                    'saving' => '저장 중...',
                    'saved' => '자동 저장됨',
                    'reset_confirm' => '모든 설정을 초기화하시겠습니까?',
                    'delete_confirm' => '정말 삭제하시겠습니까?',
                    'edit_prompt' => '새로운 이름을 입력하세요:',
                    'update_users_confirm' => '이 현장으로 등록된 모든 사용자의 현장 정보도 함께 변경됩니다. 계속하시겠습니까?'
                )
            ));
        }
    }
    
    /**
     * AJAX: 설정 저장
     */
    public function ajax_save_settings() {
        // Nonce 검증
        if (!check_ajax_referer('kac_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $settings = array(
            'logo_url' => esc_url_raw($_POST['logo_url'] ?? ''),
            'background_url' => esc_url_raw($_POST['background_url'] ?? ''),
            'complete_background_url' => esc_url_raw($_POST['complete_background_url'] ?? ''),
            'complete_image_url' => esc_url_raw($_POST['complete_image_url'] ?? ''),
            'default_redirect' => esc_url_raw($_POST['default_redirect'] ?? home_url('/')),
            'terms_url' => esc_url_raw($_POST['terms_url'] ?? '#'),
            'privacy_url' => esc_url_raw($_POST['privacy_url'] ?? '#')
        );
        
        update_option($this->option_name, $settings);
        
        wp_send_json_success('설정이 저장되었습니다.');
    }
    
    /**
     * AJAX: 조직 데이터 저장
     */
    public function ajax_save_organization() {
        // Nonce 검증
        if (!check_ajax_referer('kac_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);
        
        if (!is_array($data)) {
            wp_send_json_error('잘못된 데이터 형식입니다.');
        }
        
        // 조직 데이터 저장
        update_option('kac_organization_data', $data);
        
        wp_send_json_success('조직 데이터가 저장되었습니다.');
    }
    
    /**
     * AJAX: 조직 항목 업데이트 (실시간 저장)
     */
    public function ajax_update_organization_item() {
        // Nonce 검증
        if (!check_ajax_referer('kac_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        $item_type = sanitize_text_field($_POST['item_type'] ?? '');
        $old_value = sanitize_text_field($_POST['old_value'] ?? '');
        $new_value = sanitize_text_field($_POST['new_value'] ?? '');
        $parent_sosok = sanitize_text_field($_POST['parent_sosok'] ?? '');
        $parent_buseo = sanitize_text_field($_POST['parent_buseo'] ?? '');
        
        $org_data = get_option('kac_organization_data', array());
        
        switch ($action_type) {
            case 'add':
                if ($item_type === 'sosok') {
                    $org_data[$new_value] = array();
                } elseif ($item_type === 'buseo' && $parent_sosok) {
                    $org_data[$parent_sosok][$new_value] = array();
                } elseif ($item_type === 'site' && $parent_sosok && $parent_buseo) {
                    $org_data[$parent_sosok][$parent_buseo][] = $new_value;
                }
                break;
                
            case 'edit':
                if ($item_type === 'site' && $old_value && $new_value) {
                    // 현장 이름 변경 시 사용자 데이터 업데이트
                    $this->update_users_site($old_value, $new_value);
                }
                break;
                
            case 'delete':
                if ($item_type === 'sosok') {
                    unset($org_data[$old_value]);
                } elseif ($item_type === 'buseo' && $parent_sosok) {
                    unset($org_data[$parent_sosok][$old_value]);
                } elseif ($item_type === 'site' && $parent_sosok && $parent_buseo) {
                    $index = array_search($old_value, $org_data[$parent_sosok][$parent_buseo]);
                    if ($index !== false) {
                        array_splice($org_data[$parent_sosok][$parent_buseo], $index, 1);
                    }
                }
                break;
        }
        
        update_option('kac_organization_data', $org_data);
        
        wp_send_json_success('저장되었습니다.');
    }
    
    /**
     * 사용자 현장 정보 업데이트
     */
    private function update_users_site($old_site, $new_site) {
        global $wpdb;
        
        // 해당 현장을 가진 모든 사용자 업데이트
        $wpdb->update(
            $wpdb->usermeta,
            array('meta_value' => $new_site),
            array(
                'meta_key' => 'kachi_site',
                'meta_value' => $old_site
            )
        );
    }
    
    /**
     * AJAX: 사용자 현장 정보 업데이트
     */
    public function ajax_update_user_sites() {
        // Nonce 검증
        if (!check_ajax_referer('kac_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $old_site = sanitize_text_field($_POST['old_site'] ?? '');
        $new_site = sanitize_text_field($_POST['new_site'] ?? '');
        
        if (empty($old_site) || empty($new_site)) {
            wp_send_json_error('잘못된 데이터입니다.');
        }
        
        global $wpdb;
        
        // 해당 현장을 가진 사용자 수 확인
        $user_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'kachi_site' AND meta_value = %s",
            $old_site
        ));
        
        // 사용자 현장 정보 업데이트
        $wpdb->update(
            $wpdb->usermeta,
            array('meta_value' => $new_site),
            array(
                'meta_key' => 'kachi_site',
                'meta_value' => $old_site
            )
        );
        
        wp_send_json_success(array(
            'message' => sprintf('%d명의 사용자 현장 정보가 업데이트되었습니다.', $user_count),
            'count' => $user_count
        ));
    }
    
    /**
     * AJAX: 설정 초기화
     */
    public function ajax_reset_settings() {
        // Nonce 검증
        if (!check_ajax_referer('kac_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        // 기본값으로 초기화
        $default_settings = array(
            'logo_url' => '',
            'background_url' => '',
            'complete_background_url' => '',
            'complete_image_url' => '',
            'default_redirect' => home_url('/'),
            'terms_url' => '#',
            'privacy_url' => '#'
        );
        
        update_option($this->option_name, $default_settings);
        update_option('kac_organization_data', $this->get_default_organization_data());
        
        wp_send_json_success('설정이 초기화되었습니다.');
    }
    
    /**
     * 설정 값 가져오기 (정적 메서드)
     */
    public static function get_option($key, $default = '') {
        $options = get_option('kac_login_settings');
        return isset($options[$key]) ? $options[$key] : $default;
    }
}