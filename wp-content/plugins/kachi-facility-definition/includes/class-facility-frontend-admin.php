<?php
/**
 * Facility Frontend Admin Class
 * 프론트엔드 관리자 페이지를 위한 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facility_Frontend_Admin {
    
    public function __construct() {
        // AJAX 핸들러
        add_action('wp_ajax_fd_frontend_clear_data', array($this, 'ajax_clear_data'));
        add_action('wp_ajax_fd_frontend_save_background', array($this, 'ajax_save_background'));
        
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
        $background_image = get_option('facility_background_image', '');
        $background_position = get_option('facility_background_position', 'bottom center');
        $background_size = get_option('facility_background_size', 'cover');
        
        ob_start();
        ?>
        
        <div class="fd-frontend-admin">
            <div class="fd-fa-container">
                <!-- 헤더 영역 (로고 + 탭) -->
                <header class="fd-fa-header-wrapper">
                    <!-- 로고 섹션 -->
                    <div class="fd-fa-logo-section">
                        <a href="#" class="fd-fa-logo">
                            <div class="fd-fa-logo-icon">🏢</div>
                            <span>시설 정의 관리자</span>
                        </a>
                    </div>
                    
                    <!-- 상단 탭 네비게이션 -->
                    <nav class="fd-fa-tabs-nav">
                        <a href="#" class="fd-fa-tab-item active" data-section="info">
                            <span class="fd-fa-tab-icon">📋</span>
                            <span class="fd-fa-tab-text">플러그인 정보</span>
                        </a>
                        <a href="#" class="fd-fa-tab-item" data-section="background">
                            <span class="fd-fa-tab-icon">🎨</span>
                            <span class="fd-fa-tab-text">배경 설정</span>
                        </a>
                        <a href="#" class="fd-fa-tab-item" data-section="definitions">
                            <span class="fd-fa-tab-icon">📊</span>
                            <span class="fd-fa-tab-text">저장된 정의</span>
                        </a>
                        <a href="#" class="fd-fa-tab-item" data-section="guide">
                            <span class="fd-fa-tab-icon">❓</span>
                            <span class="fd-fa-tab-text">사용 방법</span>
                        </a>
                    </nav>
                </header>
                
                <!-- 메인 영역 -->
                <main class="fd-fa-main">
                    <!-- 알림 영역 -->
                    <div class="fd-fa-notice" style="display:none;"></div>
                    
                    <!-- 플러그인 정보 섹션 -->
                    <?php self::render_info_section(); ?>
                    
                    <!-- 배경 설정 섹션 -->
                    <?php self::render_background_section($background_image, $background_position, $background_size); ?>
                    
                    <!-- 저장된 정의 섹션 -->
                    <?php self::render_definitions_section(); ?>
                    
                    <!-- 사용 방법 섹션 -->
                    <?php self::render_guide_section(); ?>
                </main>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 플러그인 정보 섹션 렌더링
     */
    private static function render_info_section() {
        ?>
        <div class="fd-fa-section active" data-section="info">
            <header class="fd-fa-section-header">
                <div class="fd-fa-header-left">
                    <h1 class="fd-fa-title" style="font-size:28px;">플러그인 정보</h1>
                </div>
            </header>
            
            <div class="fd-fa-content">
                <div class="fd-fa-info-grid">
                    <!-- 기본 정보 카드 -->
                    <div class="fd-fa-info-card">
                        <h3>
                            <span class="fd-fa-icon">📌</span>
                            기본 정보
                        </h3>
                        <div class="fd-fa-info-items">
                            <div class="fd-fa-info-item">
                                <span class="fd-fa-label">버전:</span>
                                <span class="fd-fa-value"><?php echo FACILITY_DEFINITION_VERSION; ?></span>
                            </div>
                            <div class="fd-fa-info-item">
                                <span class="fd-fa-label">제작자:</span>
                                <span class="fd-fa-value"><a href="https://3chan.kr" target="_blank">3chan</a></span>
                            </div>
                            <div class="fd-fa-info-item">
                                <span class="fd-fa-label">ACF 의존성:</span>
                                <span class="fd-fa-value fd-fa-badge-success">✓ 제거됨</span>
                            </div>
                            <div class="fd-fa-info-item">
                                <span class="fd-fa-label">접근 제어:</span>
                                <span class="fd-fa-value fd-fa-badge-success">✓ 모든 사용자 접근 가능</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 데이터 관리 카드 -->
                    <div class="fd-fa-info-card">
                        <h3>
                            <span class="fd-fa-icon">🗑️</span>
                            데이터 관리
                        </h3>
                        <p class="fd-fa-help-text">모든 시설 정의 데이터를 삭제합니다. 이 작업은 되돌릴 수 없습니다.</p>
                        <div class="fd-fa-button-group">
                            <button type="button" class="fd-fa-btn-danger" id="clear-all-data">
                                <span class="fd-fa-icon">🗑️</span>
                                <span>모든 데이터 삭제</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 배경 설정 섹션 렌더링
     */
    private static function render_background_section($background_image, $background_position, $background_size) {
        ?>
        <div class="fd-fa-section" data-section="background">
            <header class="fd-fa-section-header">
                <div class="fd-fa-header-left">
                    <h1 class="fd-fa-title" style="font-size:28px;">배경 설정</h1>
                </div>
            </header>
            
            <div class="fd-fa-content">
                <div class="fd-fa-setting-card">
                    <h3>
                        <span class="fd-fa-icon">🖼️</span>
                        배경 이미지 설정
                    </h3>
                    <p class="fd-fa-help-text">시설 정의 페이지의 배경 이미지를 설정합니다.</p>
                    
                    <div class="fd-fa-form-group">
                        <label>배경 이미지</label>
                        <div class="fd-fa-image-input-group">
                            <input type="hidden" id="background-image-url" value="<?php echo esc_attr($background_image); ?>">
                            <button type="button" class="fd-fa-btn-secondary" id="upload-background-image">
                                <span class="fd-fa-icon">📁</span>
                                이미지 선택
                            </button>
                            <?php if ($background_image): ?>
                                <button type="button" class="fd-fa-btn-secondary" id="remove-background-image">
                                    <span class="fd-fa-icon">❌</span>
                                    이미지 제거
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($background_image): ?>
                            <div class="fd-fa-image-preview" id="background-image-preview">
                                <img src="<?php echo esc_url($background_image); ?>" alt="배경 이미지 미리보기">
                            </div>
                        <?php else: ?>
                            <div class="fd-fa-image-preview" id="background-image-preview" style="display: none;">
                                <img src="" alt="배경 이미지 미리보기">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="fd-fa-form-grid">
                        <div class="fd-fa-form-group">
                            <label for="background-position">배경 위치</label>
                            <select id="background-position" class="fd-fa-input">
                                <option value="center center" <?php selected($background_position, 'center center'); ?>>중앙</option>
                                <option value="top left" <?php selected($background_position, 'top left'); ?>>왼쪽 상단</option>
                                <option value="top center" <?php selected($background_position, 'top center'); ?>>상단 중앙</option>
                                <option value="top right" <?php selected($background_position, 'top right'); ?>>오른쪽 상단</option>
                                <option value="center left" <?php selected($background_position, 'center left'); ?>>왼쪽 중앙</option>
                                <option value="center right" <?php selected($background_position, 'center right'); ?>>오른쪽 중앙</option>
                                <option value="bottom left" <?php selected($background_position, 'bottom left'); ?>>왼쪽 하단</option>
                                <option value="bottom center" <?php selected($background_position, 'bottom center'); ?>>하단 중앙</option>
                                <option value="bottom right" <?php selected($background_position, 'bottom right'); ?>>오른쪽 하단</option>
                            </select>
                        </div>
                        
                        <div class="fd-fa-form-group">
                            <label for="background-size">배경 크기</label>
                            <select id="background-size" class="fd-fa-input">
                                <option value="auto" <?php selected($background_size, 'auto'); ?>>원본 크기</option>
                                <option value="cover" <?php selected($background_size, 'cover'); ?>>화면에 맞춤 (Cover)</option>
                                <option value="contain" <?php selected($background_size, 'contain'); ?>>화면에 맞춤 (Contain)</option>
                                <option value="100% 100%" <?php selected($background_size, '100% 100%'); ?>>화면 전체</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="fd-fa-form-actions">
                        <button type="button" class="fd-fa-btn-primary" id="save-background-settings">
                            <span class="fd-fa-icon">💾</span>
                            <span>배경 설정 저장</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 저장된 정의 섹션 렌더링
     */
    private static function render_definitions_section() {
        $all_defs = get_option('facility_definitions_data', array());
        ?>
        <div class="fd-fa-section" data-section="definitions">
            <header class="fd-fa-section-header">
                <div class="fd-fa-header-left">
                    <h1 class="fd-fa-title" style="font-size:28px;">저장된 시설 정의</h1>
                </div>
            </header>
            
            <div class="fd-fa-content">
                <?php
                if (empty($all_defs)) {
                    echo '<div class="fd-fa-empty-state">';
                    echo '<span class="fd-fa-icon">📭</span>';
                    echo '<h3>저장된 시설 정의가 없습니다</h3>';
                    echo '<p>사용자들이 아직 시설 정의를 추가하지 않았습니다.</p>';
                    echo '</div>';
                } else {
                    // 소속/현장별로 그룹화
                    $grouped = array();
                    foreach ($all_defs as $def) {
                        $key = $def['sosok'] . ' - ' . $def['site'];
                        if (!isset($grouped[$key])) {
                            $grouped[$key] = array();
                        }
                        $grouped[$key][] = $def;
                    }
                    
                    echo '<div class="fd-fa-stats-bar">';
                    echo '<span>총 ' . count($all_defs) . '개의 정의가 저장되어 있습니다.</span>';
                    echo '</div>';
                    
                    foreach ($grouped as $group_key => $definitions) {
                        echo '<div class="fd-fa-definition-group">';
                        echo '<div class="fd-fa-group-header">';
                        echo '<h3>' . esc_html($group_key) . '</h3>';
                        echo '<span class="fd-fa-badge">' . count($definitions) . '개</span>';
                        echo '</div>';
                        
                        echo '<div class="fd-fa-definition-items">';
                        echo '<table class="fd-fa-table">';
                        echo '<thead><tr><th>용어</th><th>정의</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($definitions as $def) {
                            echo '<tr>';
                            echo '<td class="fd-fa-term"><strong>' . esc_html($def['key']) . '</strong></td>';
                            echo '<td class="fd-fa-definition">' . esc_html($def['value']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                        echo '</div>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 사용 방법 섹션 렌더링
     */
    private static function render_guide_section() {
        ?>
        <div class="fd-fa-section" data-section="guide">
            <header class="fd-fa-section-header">
                <div class="fd-fa-header-left">
                    <h1 class="fd-fa-title" style="font-size:28px;">사용 방법</h1>
                </div>
            </header>
            
            <div class="fd-fa-content">
                <div class="fd-fa-guide-grid">
                    <!-- 시설 정의 표시 -->
                    <div class="fd-fa-guide-card">
                        <h3>
                            <span class="fd-fa-icon">🏢</span>
                            시설 정의 관리
                        </h3>
                        <div class="fd-fa-code-block">
                            <code>[facility_definition]</code>
                        </div>
                        <p>페이지나 포스트에 위 숏코드를 삽입하면 시설 정의 관리 인터페이스가 표시됩니다.</p>
                        <ul class="fd-fa-feature-list">
                            <li>사용자별 데이터 분리</li>
                            <li>실시간 저장</li>
                            <li>편집/삭제 기능</li>
                            <li>모던한 UI/UX</li>
                        </ul>
                    </div>
                    
                    <!-- 관리자 페이지 -->
                    <div class="fd-fa-guide-card">
                        <h3>
                            <span class="fd-fa-icon">⚙️</span>
                            관리자 페이지
                        </h3>
                        <div class="fd-fa-code-block">
                            <code>[facility_definition_admin]</code>
                        </div>
                        <p>프론트엔드에서 플러그인 설정을 관리할 수 있는 관리자 페이지입니다.</p>
                        <ul class="fd-fa-feature-list">
                            <li>관리자 권한 필요</li>
                            <li>배경 이미지 설정</li>
                            <li>저장된 데이터 확인</li>
                            <li>데이터 전체 삭제</li>
                        </ul>
                    </div>
                    
                    <!-- 데이터 구조 -->
                    <div class="fd-fa-guide-card">
                        <h3>
                            <span class="fd-fa-icon">📊</span>
                            데이터 구조
                        </h3>
                        <p>시설 정의는 다음과 같은 구조로 저장됩니다:</p>
                        <ul class="fd-fa-input-guide">
                            <li><strong>소속:</strong> 사용자 프로필의 소속 정보</li>
                            <li><strong>현장:</strong> 사용자 프로필의 현장 정보</li>
                            <li><strong>용어:</strong> 시설 용어 (예: 1레이더)</li>
                            <li><strong>정의:</strong> 시설 정의 (예: NPG-1460E)</li>
                        </ul>
                        <p class="fd-fa-help-text">로그인하지 않은 사용자는 'default' 그룹으로 관리됩니다.</p>
                    </div>
                    
                    <!-- 팁과 주의사항 -->
                    <div class="fd-fa-guide-card">
                        <h3>
                            <span class="fd-fa-icon">💡</span>
                            팁과 주의사항
                        </h3>
                        <div class="fd-fa-info-box">
                            <h4>데이터 백업</h4>
                            <p>중요한 시설 정의는 정기적으로 백업하는 것을 권장합니다.</p>
                        </div>
                        <div class="fd-fa-info-box">
                            <h4>사용자 권한</h4>
                            <p>모든 사용자가 자신의 소속/현장 데이터를 관리할 수 있습니다.</p>
                        </div>
                        <div class="fd-fa-info-box">
                            <h4>플러그인 제거</h4>
                            <p>플러그인 제거 시 모든 데이터가 삭제됩니다.</p>
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
        <div class="fd-fa-access-denied">
            <span class="fd-fa-icon">🚫</span>
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
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'facility_definition_admin')) {
            // 미디어 업로더
            wp_enqueue_media();
            
            // 스타일
            wp_enqueue_style('fd-frontend-admin', KACHI_FACILITY_DEFINITION_PLUGIN_URL . 'assets/css/fd-frontend-admin.css', array(), KACHI_FACILITY_DEFINITION_VERSION);
            
            // 스크립트
            wp_enqueue_script('fd-frontend-admin', KACHI_FACILITY_DEFINITION_PLUGIN_URL . 'assets/js/fd-frontend-admin.js', array('jquery'), KACHI_FACILITY_DEFINITION_VERSION, true);
            
            // Localization
            wp_localize_script('fd-frontend-admin', 'fd_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fd_frontend_nonce'),
                'messages' => array(
                    'clear_confirm' => '정말로 모든 데이터를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.',
                    'clear_success' => '모든 데이터가 삭제되었습니다.',
                    'save_success' => '설정이 저장되었습니다.',
                    'save_error' => '저장 중 오류가 발생했습니다.',
                    'loading' => '처리 중...'
                )
            ));
        }
    }
    
    /**
     * AJAX: 모든 데이터 삭제
     */
    public function ajax_clear_data() {
        // Nonce 검증
        if (!check_ajax_referer('fd_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        // 데이터 삭제
        update_option('facility_definitions_data', array());
        wp_send_json_success('모든 데이터가 삭제되었습니다.');
    }
    
    /**
     * AJAX: 배경 설정 저장
     */
    public function ajax_save_background() {
        // Nonce 검증
        if (!check_ajax_referer('fd_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        // 배경 이미지 URL 저장
        if (isset($_POST['background_image'])) {
            update_option('facility_background_image', esc_url_raw($_POST['background_image']));
        }
        
        // 배경 위치 저장
        if (isset($_POST['background_position'])) {
            update_option('facility_background_position', sanitize_text_field($_POST['background_position']));
        }
        
        // 배경 크기 저장
        if (isset($_POST['background_size'])) {
            update_option('facility_background_size', sanitize_text_field($_POST['background_size']));
        }
        
        wp_send_json_success('배경 설정이 저장되었습니다.');
    }
}