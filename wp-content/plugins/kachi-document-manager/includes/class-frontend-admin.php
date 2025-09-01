<?php
/**
 * 3chan PDF Manager Frontend Admin Class
 * 프론트엔드 관리자 페이지를 위한 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class ThreeChan_PDF_Frontend_Admin {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . '3chan_pdf_uploads';
        
        // AJAX 핸들러
        add_action('wp_ajax_3chan_fa_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_3chan_fa_get_documents', array($this, 'ajax_get_documents'));
        add_action('wp_ajax_3chan_fa_delete_documents', array($this, 'ajax_delete_documents'));
        add_action('wp_ajax_3chan_fa_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_3chan_fa_clear_data', array($this, 'ajax_clear_data'));
        
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
        $settings = get_option('3chan_pdf_manager_settings', array());
        $default_settings = array(
            'api_url' => 'http://1.3chan.kr',
            'max_file_size' => 50,
            'allowed_file_types' => array('pdf', 'docx', 'pptx', 'hwpx'),
            'enable_notifications' => true,
            'default_page_size' => 10,
            'primary_color' => '#a70638',
            'enable_auto_save' => true,
            'cache_duration' => 3600,
            'use_proxy' => true,
            'enable_duplicate_check' => true
        );
        $settings = wp_parse_args($settings, $default_settings);
        
        ob_start();
        ?>
        
        <div class="pdf-frontend-admin">
            <div class="pdf-fa-container">
                <!-- 헤더 영역 (로고 + 탭) -->
                <header class="pdf-fa-header-wrapper">
                    <!-- 로고 섹션 -->
                    <div class="pdf-fa-logo-section">
                        <a href="#" class="pdf-fa-logo">
                            <div class="pdf-fa-logo-icon">📄</div>
                            <span>PDF Manager</span>
                        </a>
                    </div>
                    
                    <!-- 상단 탭 네비게이션 -->
                    <nav class="pdf-fa-tabs-nav">
                        <a href="#" class="pdf-fa-tab-item active" data-section="documents">
                            <span class="pdf-fa-tab-icon">📁</span>
                            <span class="pdf-fa-tab-text">문서 관리</span>
                        </a>
                        <a href="#" class="pdf-fa-tab-item" data-section="settings">
                            <span class="pdf-fa-tab-icon">⚙️</span>
                            <span class="pdf-fa-tab-text">설정</span>
                        </a>
                        <a href="#" class="pdf-fa-tab-item" data-section="guide">
                            <span class="pdf-fa-tab-icon">❓</span>
                            <span class="pdf-fa-tab-text">사용 방법</span>
                        </a>
                    </nav>
                </header>
                
                <!-- 메인 영역 -->
                <main class="pdf-fa-main">
                    <!-- 알림 영역 -->
                    <div class="pdf-fa-notice" style="display:none;"></div>
                    
                    <!-- 문서 관리 섹션 -->
                    <?php self::render_documents_section(); ?>
                    
                    <!-- 설정 섹션 -->
                    <?php self::render_settings_section($settings); ?>
                    
                    <!-- 사용 방법 섹션 -->
                    <?php self::render_guide_section(); ?>
                </main>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 문서 관리 섹션 렌더링
     */
    private static function render_documents_section() {
        ?>
        <div class="pdf-fa-section active" data-section="documents">
            <header class="pdf-fa-section-header">
                <div class="pdf-fa-header-left">
                    <h1 class="pdf-fa-title" style="font-size:28px;">문서 관리</h1>
                </div>
                <div class="pdf-fa-header-actions">
                    <button type="button" class="pdf-fa-btn-secondary" id="refresh-documents">
                        <span class="pdf-fa-icon">🔄</span>
                        <span>새로고침</span>
                    </button>
                </div>
            </header>
            
            <div class="pdf-fa-content">
                <!-- 문서 통계 -->
                <div class="pdf-fa-stats-grid">
                    <div class="pdf-fa-stat-card">
                        <div class="stat-icon">📄</div>
                        <div class="stat-content">
                            <h3 id="totalDocuments">0</h3>
                            <p>전체 문서</p>
                        </div>
                    </div>
                    
                    <div class="pdf-fa-stat-card">
                        <div class="stat-icon">💾</div>
                        <div class="stat-content">
                            <h3 id="totalSize">0 MB</h3>
                            <p>전체 용량</p>
                        </div>
                    </div>
                    
                    <div class="pdf-fa-stat-card">
                        <div class="stat-icon">🏷️</div>
                        <div class="stat-content">
                            <h3 id="totalTags">0</h3>
                            <p>태그 수</p>
                        </div>
                    </div>
                    
                    <div class="pdf-fa-stat-card">
                        <div class="stat-icon">🔄</div>
                        <div class="stat-content">
                            <h3 id="apiStatus">확인중...</h3>
                            <p>API 상태</p>
                        </div>
                    </div>
                </div>
                
                <!-- 문서 목록 -->
                <div class="pdf-fa-documents-section">
                    <div class="pdf-fa-section-title">
                        <h3>
                            <span class="pdf-fa-icon">📋</span>
                            최근 업로드된 문서
                        </h3>
                        <button type="button" class="pdf-fa-btn-secondary" id="delete-selected">
                            <span class="pdf-fa-icon">🗑️</span>
                            <span>선택 삭제</span>
                        </button>
                    </div>
                    
                    <div class="pdf-fa-documents-list" id="documentsListContainer">
                        <div class="pdf-fa-loading">
                            <div class="loading-spinner"></div>
                            <p>문서 목록을 불러오는 중...</p>
                        </div>
                    </div>
                </div>
                
                <!-- 빠른 작업 -->
                <div class="pdf-fa-quick-actions">
                    <h3>
                        <span class="pdf-fa-icon">⚡</span>
                        빠른 작업
                    </h3>
                    <div class="quick-actions-grid">
                        <button type="button" class="pdf-fa-btn-secondary" id="clear-all-data">
                            <span class="pdf-fa-icon">🔥</span>
                            <span>모든 데이터 삭제</span>
                        </button>
                        <button type="button" class="pdf-fa-btn-secondary" id="test-api-connection">
                            <span class="pdf-fa-icon">🔌</span>
                            <span>API 연결 테스트</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 설정 섹션 렌더링
     */
    private static function render_settings_section($settings) {
        ?>
        <div class="pdf-fa-section" data-section="settings">
            <header class="pdf-fa-section-header">
                <div class="pdf-fa-header-left">
                    <h1 class="pdf-fa-title" style="font-size:28px;">설정</h1>
                </div>
                <div class="pdf-fa-header-actions">
                    <span class="pdf-fa-save-status" style="display: none;">
                        <span class="pdf-fa-icon">✅</span>
                        <span>자동 저장됨</span>
                    </span>
                </div>
            </header>
            
            <div class="pdf-fa-content">
                <form id="pdf-settings-form">
                    <div class="pdf-fa-settings-grid">
                        <!-- API 설정 -->
                        <div class="pdf-fa-setting-card">
                            <h3>
                                <span class="pdf-fa-icon">🔌</span>
                                API 설정
                            </h3>
                            
                            <div class="pdf-fa-form-group">
                                <label for="api_url">API URL</label>
                                <input type="url" 
                                       id="api_url" 
                                       name="api_url" 
                                       class="pdf-fa-input" 
                                       value="<?php echo esc_url($settings['api_url']); ?>" 
                                       required>
                                <p class="pdf-fa-help-text">외부 API 서버의 URL을 입력하세요.</p>
                            </div>
                            
                            <div class="pdf-fa-form-group">
                                <label class="pdf-fa-checkbox-label">
                                    <input type="checkbox" 
                                           name="use_proxy" 
                                           value="1" 
                                           <?php checked($settings['use_proxy']); ?>>
                                    <span>WordPress 프록시를 통해 API 요청 (CORS 문제 해결)</span>
                                </label>
                            </div>
                            
                            <div class="pdf-fa-form-group">
                                <label for="cache_duration">캐시 유효 시간 (초)</label>
                                <input type="number" 
                                       id="cache_duration" 
                                       name="cache_duration" 
                                       class="pdf-fa-input" 
                                       value="<?php echo esc_attr($settings['cache_duration']); ?>" 
                                       min="0">
                                <p class="pdf-fa-help-text">API 응답 캐시 시간 (0 = 캐시 사용 안 함)</p>
                            </div>
                        </div>
                        
                        <!-- 파일 업로드 설정 -->
                        <div class="pdf-fa-setting-card">
                            <h3>
                                <span class="pdf-fa-icon">📁</span>
                                파일 업로드 설정
                            </h3>
                            
                            <div class="pdf-fa-form-group">
                                <label for="max_file_size">최대 파일 크기 (MB)</label>
                                <input type="number" 
                                       id="max_file_size" 
                                       name="max_file_size" 
                                       class="pdf-fa-input" 
                                       value="<?php echo esc_attr($settings['max_file_size']); ?>" 
                                       min="1" 
                                       max="500">
                                <p class="pdf-fa-help-text">
                                    서버 최대 업로드 크기: <?php echo size_format(wp_max_upload_size()); ?>
                                </p>
                            </div>
                            
                            <div class="pdf-fa-form-group">
                                <label>허용 파일 형식</label>
                                <div class="pdf-fa-checkbox-group">
                                    <label class="pdf-fa-checkbox-label">
                                        <input type="checkbox" 
                                               name="allowed_file_types[]" 
                                               value="pdf" 
                                               <?php checked(in_array('pdf', $settings['allowed_file_types'])); ?>>
                                        <span>PDF</span>
                                    </label>
                                    <label class="pdf-fa-checkbox-label">
                                        <input type="checkbox" 
                                               name="allowed_file_types[]" 
                                               value="docx" 
                                               <?php checked(in_array('docx', $settings['allowed_file_types'])); ?>>
                                        <span>DOCX</span>
                                    </label>
                                    <label class="pdf-fa-checkbox-label">
                                        <input type="checkbox" 
                                               name="allowed_file_types[]" 
                                               value="pptx" 
                                               <?php checked(in_array('pptx', $settings['allowed_file_types'])); ?>>
                                        <span>PPTX</span>
                                    </label>
                                    <label class="pdf-fa-checkbox-label">
                                        <input type="checkbox" 
                                               name="allowed_file_types[]" 
                                               value="hwpx" 
                                               <?php checked(in_array('hwpx', $settings['allowed_file_types'])); ?>>
                                        <span>HWPX</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="pdf-fa-form-group">
                                <label class="pdf-fa-checkbox-label">
                                    <input type="checkbox" 
                                           name="enable_duplicate_check" 
                                           value="1" 
                                           <?php checked($settings['enable_duplicate_check']); ?>>
                                    <span>파일 업로드 시 중복 확인</span>
                                </label>
                                <p class="pdf-fa-help-text">같은 현장(site)에서 동일한 이름의 파일이 있을 경우 확인합니다.</p>
                            </div>
                        </div>
                        
                        <!-- UI 설정 -->
                        <div class="pdf-fa-setting-card">
                            <h3>
                                <span class="pdf-fa-icon">🎨</span>
                                사용자 인터페이스
                            </h3>
                            
                            <div class="pdf-fa-form-group">
                                <label for="primary_color">주 색상</label>
                                <div class="color-input-group">
                                    <input type="color" 
                                           id="primary_color" 
                                           name="primary_color" 
                                           value="<?php echo esc_attr($settings['primary_color']); ?>">
                                    <span class="color-preview" style="background-color: <?php echo esc_attr($settings['primary_color']); ?>"></span>
                                </div>
                            </div>
                            
                            <div class="pdf-fa-form-group">
                                <label for="default_page_size">페이지당 항목 수</label>
                                <select id="default_page_size" name="default_page_size" class="pdf-fa-input">
                                    <option value="10" <?php selected($settings['default_page_size'], 10); ?>>10</option>
                                    <option value="20" <?php selected($settings['default_page_size'], 20); ?>>20</option>
                                    <option value="30" <?php selected($settings['default_page_size'], 30); ?>>30</option>
                                    <option value="50" <?php selected($settings['default_page_size'], 50); ?>>50</option>
                                    <option value="100" <?php selected($settings['default_page_size'], 100); ?>>100</option>
                                </select>
                            </div>
                            
                            <div class="pdf-fa-form-group">
                                <label class="pdf-fa-checkbox-label">
                                    <input type="checkbox" 
                                           name="enable_notifications" 
                                           value="1" 
                                           <?php checked($settings['enable_notifications']); ?>>
                                    <span>알림 메시지 표시</span>
                                </label>
                            </div>
                            
                            <div class="pdf-fa-form-group">
                                <label class="pdf-fa-checkbox-label">
                                    <input type="checkbox" 
                                           name="enable_auto_save" 
                                           value="1" 
                                           <?php checked($settings['enable_auto_save']); ?>>
                                    <span>입력 내용 자동 저장</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * 사용 방법 섹션 렌더링
     */
    private static function render_guide_section() {
        ?>
        <div class="pdf-fa-section" data-section="guide">
            <header class="pdf-fa-section-header">
                <div class="pdf-fa-header-left">
                    <h1 class="pdf-fa-title" style="font-size:28px;">사용 방법</h1>
                </div>
            </header>
            
            <div class="pdf-fa-content">
                <div class="pdf-fa-guide-grid">
                    <!-- PDF 문서 표시 -->
                    <div class="pdf-fa-guide-card">
                        <h3>
                            <span class="pdf-fa-icon">📄</span>
                            PDF 문서 표시
                        </h3>
                        <div class="pdf-fa-code-block">
                            <code>[3chan_pdf_manager]</code>
                        </div>
                        <p>페이지나 포스트에 위 숏코드를 삽입하면 PDF 업로드 및 관리 인터페이스가 표시됩니다.</p>
                        <ul class="pdf-fa-feature-list">
                            <li>PDF, DOCX, PPTX, HWPX 파일 지원</li>
                            <li>PDF 여백 설정 기능</li>
                            <li>태그 기반 문서 관리</li>
                            <li>페이지별 내용 검색</li>
                        </ul>
                    </div>
                    
                    <!-- 관리자 페이지 -->
                    <div class="pdf-fa-guide-card">
                        <h3>
                            <span class="pdf-fa-icon">⚙️</span>
                            관리자 페이지
                        </h3>
                        <div class="pdf-fa-code-block">
                            <code>[3chan_pdf_admin]</code>
                        </div>
                        <p>프론트엔드에서 PDF Manager를 관리할 수 있는 관리자 페이지입니다.</p>
                        <ul class="pdf-fa-feature-list">
                            <li>관리자 권한 필요</li>
                            <li>문서 목록 관리</li>
                            <li>API 설정</li>
                            <li>UI 커스터마이징</li>
                        </ul>
                    </div>
                    
                    <!-- 파일 업로드 가이드 -->
                    <div class="pdf-fa-guide-card">
                        <h3>
                            <span class="pdf-fa-icon">📤</span>
                            파일 업로드
                        </h3>
                        <p>파일 업로드 시 다음 사항을 확인하세요:</p>
                        <ul class="pdf-fa-input-guide">
                            <li><strong>파일 형식:</strong> PDF, DOCX, PPTX, HWPX</li>
                            <li><strong>최대 크기:</strong> 설정에서 지정한 크기까지</li>
                            <li><strong>여러 파일:</strong> 동시에 여러 파일 업로드 가능</li>
                            <li><strong>태그 추가:</strong> 업로드 시 태그 입력 가능</li>
                            <li><strong>중복 확인:</strong> 같은 이름의 파일 자동 확인</li>
                        </ul>
                    </div>
                    
                    <!-- API 설정 가이드 -->
                    <div class="pdf-fa-guide-card">
                        <h3>
                            <span class="pdf-fa-icon">🔌</span>
                            API 설정
                        </h3>
                        <div class="pdf-fa-info-box">
                            <h4>API 서버 주소</h4>
                            <p>기본값: http://1.3chan.kr</p>
                        </div>
                        <div class="pdf-fa-info-box">
                            <h4>프록시 사용</h4>
                            <p>CORS 문제가 발생할 경우 WordPress 프록시를 통해 API를 호출합니다.</p>
                        </div>
                        <div class="pdf-fa-info-box">
                            <h4>캐시 설정</h4>
                            <p>API 응답을 캐시하여 성능을 향상시킬 수 있습니다.</p>
                        </div>
                    </div>
                    
                    <!-- 개발자 가이드 -->
                    <div class="pdf-fa-guide-card">
                        <h3>
                            <span class="pdf-fa-icon">💻</span>
                            개발자 가이드
                        </h3>
                        <p>사용 가능한 PHP 함수들:</p>
                        <div class="pdf-fa-code-block">
                            <code>threechan_pdf_get_user_files($user_id)</code>
                        </div>
                        <p>사용자의 업로드된 파일 목록을 가져옵니다.</p>
                        
                        <div class="pdf-fa-code-block">
                            <code>threechan_pdf_update_user_info($user_id, $sosok, $site)</code>
                        </div>
                        <p>사용자의 소속과 현장 정보를 업데이트합니다.</p>
                        
                        <div class="pdf-fa-code-block">
                            <code>threechan_pdf_delete_file($file_id)</code>
                        </div>
                        <p>특정 파일을 삭제합니다.</p>
                    </div>
                    
                    <!-- 주의사항 -->
                    <div class="pdf-fa-guide-card">
                        <h3>
                            <span class="pdf-fa-icon">⚠️</span>
                            주의사항
                        </h3>
                        <div class="pdf-fa-info-box">
                            <h4>데이터 백업</h4>
                            <p>중요한 문서는 정기적으로 백업하는 것을 권장합니다.</p>
                        </div>
                        <div class="pdf-fa-info-box">
                            <h4>파일 크기</h4>
                            <p>대용량 파일 업로드 시 서버 설정을 확인하세요.</p>
                        </div>
                        <div class="pdf-fa-info-box">
                            <h4>권한 관리</h4>
                            <p>관리자 페이지는 manage_options 권한이 필요합니다.</p>
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
        <div class="pdf-fa-access-denied">
            <span class="pdf-fa-icon">🚫</span>
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
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, '3chan_pdf_admin')) {
            // 스타일
            wp_enqueue_style('pdf-frontend-admin', THREECHAN_PDF_PLUGIN_URL . 'assets/css/pdf-frontend-admin.css', array(), THREECHAN_PDF_VERSION);
            
            // 스크립트
            wp_enqueue_script('pdf-frontend-admin', THREECHAN_PDF_PLUGIN_URL . 'assets/js/pdf-frontend-admin.js', array('jquery'), THREECHAN_PDF_VERSION, true);
            
            // Localization
            wp_localize_script('pdf-frontend-admin', 'pdf_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pdf_frontend_nonce'),
                'messages' => array(
                    'save_success' => '저장되었습니다.',
                    'save_error' => '저장 중 오류가 발생했습니다.',
                    'delete_confirm' => '정말 삭제하시겠습니까?',
                    'delete_success' => '삭제되었습니다.',
                    'loading' => '로딩 중...',
                    'saving' => '저장 중...',
                    'saved' => '자동 저장됨',
                    'test_success' => 'API 연결 성공!',
                    'test_error' => 'API 연결 실패!',
                    'clear_confirm' => '정말로 모든 데이터를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.',
                    'clear_success' => '모든 데이터가 삭제되었습니다.'
                )
            ));
        }
    }
    
    /**
     * AJAX: 설정 저장
     */
    public function ajax_save_settings() {
        // Nonce 검증
        if (!check_ajax_referer('pdf_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        // Parse form data
        parse_str($_POST['form'], $form_data);
        
        $settings = array(
            'api_url' => sanitize_url($form_data['api_url'] ?? ''),
            'max_file_size' => intval($form_data['max_file_size'] ?? 50),
            'allowed_file_types' => array_map('sanitize_text_field', $form_data['allowed_file_types'] ?? array()),
            'enable_notifications' => isset($form_data['enable_notifications']),
            'default_page_size' => intval($form_data['default_page_size'] ?? 10),
            'primary_color' => sanitize_hex_color($form_data['primary_color'] ?? '#a70638'),
            'enable_auto_save' => isset($form_data['enable_auto_save']),
            'cache_duration' => intval($form_data['cache_duration'] ?? 3600),
            'use_proxy' => isset($form_data['use_proxy']),
            'enable_duplicate_check' => isset($form_data['enable_duplicate_check'])
        );
        
        update_option('3chan_pdf_manager_settings', $settings);
        
        wp_send_json_success(array('message' => '설정이 저장되었습니다.'));
    }
    
    /**
     * AJAX: 문서 목록 가져오기
     */
    public function ajax_get_documents() {
        // Nonce 검증
        if (!check_ajax_referer('pdf_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . '3chan_pdf_uploads';
        
        // 테이블이 존재하는지 확인
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            wp_send_json_success(array(
                'documents' => array(),
                'total' => 0,
                'total_size' => 0,
                'tags' => array()
            ));
            return;
        }
        
        // 최근 문서 목록 가져오기
        $documents = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active' ORDER BY upload_date DESC LIMIT 100"
        );
        
        // 통계 계산
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
        $total_size = $wpdb->get_var("SELECT SUM(file_size) FROM $table_name WHERE status = 'active'");
        
        // 태그 수집
        $all_tags = array();
        foreach ($documents as $doc) {
            if (!empty($doc->tags)) {
                $tags = explode(',', $doc->tags);
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if (!empty($tag)) {
                        $all_tags[] = $tag;
                    }
                }
            }
        }
        $unique_tags = array_unique($all_tags);
        
        wp_send_json_success(array(
            'documents' => $documents,
            'total' => $total_count,
            'total_size' => $total_size ?: 0,
            'tags' => count($unique_tags)
        ));
    }
    
    /**
     * AJAX: 문서 삭제
     */
    public function ajax_delete_documents() {
        // Nonce 검증
        if (!check_ajax_referer('pdf_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $file_ids = isset($_POST['file_ids']) ? array_map('sanitize_text_field', $_POST['file_ids']) : array();
        
        if (empty($file_ids)) {
            wp_send_json_error('삭제할 파일이 없습니다.');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . '3chan_pdf_uploads';
        
        // API를 통해 각 파일 삭제
        $settings = get_option('3chan_pdf_manager_settings');
        $api_url = $settings['api_url'];
        $success_count = 0;
        
        foreach ($file_ids as $file_id) {
            // API 호출
            $response = wp_remote_request($api_url . '/delete-document/?file_id=' . $file_id, array(
                'method' => 'DELETE',
                'timeout' => 30,
                'sslverify' => false
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                // 로컬 DB에서도 삭제
                $wpdb->update(
                    $table_name,
                    array('status' => 'deleted'),
                    array('file_id' => $file_id),
                    array('%s'),
                    array('%s')
                );
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf('%d개의 파일이 삭제되었습니다.', $success_count)
            ));
        } else {
            wp_send_json_error('파일 삭제에 실패했습니다.');
        }
    }
    
    /**
     * AJAX: API 테스트
     */
    public function ajax_test_api() {
        // Nonce 검증
        if (!check_ajax_referer('pdf_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $settings = get_option('3chan_pdf_manager_settings');
        $api_url = $settings['api_url'];
        
        // Health check
        $response = wp_remote_get($api_url . '/health', array(
            'timeout' => 10,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('API 연결 실패: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            wp_send_json_success('API 연결 성공!');
        } else {
            wp_send_json_error('API 연결 실패: HTTP ' . $status_code);
        }
    }
    
    /**
     * AJAX: 모든 데이터 삭제
     */
    public function ajax_clear_data() {
        // Nonce 검증
        if (!check_ajax_referer('pdf_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . '3chan_pdf_uploads';
        
        // 모든 데이터를 'deleted' 상태로 변경
        $wpdb->update(
            $table_name,
            array('status' => 'deleted'),
            array('status' => 'active'),
            array('%s'),
            array('%s')
        );
        
        wp_send_json_success('모든 데이터가 삭제되었습니다.');
    }
}