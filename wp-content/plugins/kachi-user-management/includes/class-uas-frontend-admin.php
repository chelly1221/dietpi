<?php
/**
 * UAS Frontend Admin Class
 * 프론트엔드 관리자 페이지를 위한 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class UAS_Frontend_Admin {
    
    public function __construct() {
        // AJAX 핸들러
        add_action('wp_ajax_uas_frontend_save_background', array($this, 'ajax_save_background'));
        
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
        $background_image = get_option('uas_background_image', '');
        $background_position = get_option('uas_background_position', 'bottom center');
        $background_size = get_option('uas_background_size', 'cover');
        
        // 대기 중인 사용자 수
        $pending_users = get_users(array('role' => 'pending'));
        $pending_count = count($pending_users);
        
        ob_start();
        ?>
        
        <div class="uas-frontend-admin">
            <div class="uas-fa-container">
                <!-- 헤더 영역 (로고 + 탭) -->
                <header class="uas-fa-header-wrapper">
                    <!-- 로고 섹션 -->
                    <div class="uas-fa-logo-section">
                        <a href="#" class="uas-fa-logo">
                            <div class="uas-fa-logo-icon">👥</div>
                            <span>사용자 승인 시스템</span>
                        </a>
                    </div>
                    
                    <!-- 상단 탭 네비게이션 -->
                    <nav class="uas-fa-tabs-nav">
                        <a href="#" class="uas-fa-tab-item active" data-section="info">
                            <span class="uas-fa-tab-icon">📋</span>
                            <span class="uas-fa-tab-text">시스템 정보</span>
                        </a>
                        <a href="#" class="uas-fa-tab-item" data-section="pending">
                            <span class="uas-fa-tab-icon">⏳</span>
                            <span class="uas-fa-tab-text">승인 대기</span>
                            <?php if ($pending_count > 0): ?>
                            <span class="uas-fa-tab-badge"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="#" class="uas-fa-tab-item" data-section="background">
                            <span class="uas-fa-tab-icon">🎨</span>
                            <span class="uas-fa-tab-text">배경 설정</span>
                        </a>
                        <a href="#" class="uas-fa-tab-item" data-section="guide">
                            <span class="uas-fa-tab-icon">❓</span>
                            <span class="uas-fa-tab-text">사용 방법</span>
                        </a>
                    </nav>
                </header>
                
                <!-- 메인 영역 -->
                <main class="uas-fa-main">
                    <!-- 알림 영역 -->
                    <div class="uas-fa-notice" style="display:none;"></div>
                    
                    <!-- 시스템 정보 섹션 -->
                    <?php self::render_info_section(); ?>
                    
                    <!-- 승인 대기 섹션 -->
                    <?php self::render_pending_section($pending_users); ?>
                    
                    <!-- 배경 설정 섹션 -->
                    <?php self::render_background_section($background_image, $background_position, $background_size); ?>
                    
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
    private static function render_info_section() {
        ?>
        <div class="uas-fa-section active" data-section="info">
            <header class="uas-fa-section-header">
                <div class="uas-fa-header-left">
                    <h1 class="uas-fa-title" style="font-size:28px;">시스템 정보</h1>
                </div>
            </header>
            
            <div class="uas-fa-content">
                <div class="uas-fa-info-grid">
                    <!-- 플러그인 정보 카드 -->
                    <div class="uas-fa-info-card">
                        <h3>
                            <span class="uas-fa-icon">📌</span>
                            기본 정보
                        </h3>
                        <div class="uas-fa-info-items">
                            <div class="uas-fa-info-item">
                                <span class="uas-fa-label">버전:</span>
                                <span class="uas-fa-value"><?php echo UAS_PLUGIN_VERSION; ?></span>
                            </div>
                            <div class="uas-fa-info-item">
                                <span class="uas-fa-label">제작자:</span>
                                <span class="uas-fa-value"><a href="https://3chan.kr" target="_blank">3chan</a></span>
                            </div>
                            <div class="uas-fa-info-item">
                                <span class="uas-fa-label">라이선스:</span>
                                <span class="uas-fa-value">GPL v2 or later</span>
                            </div>
                            <div class="uas-fa-info-item">
                                <span class="uas-fa-label">텍스트 도메인:</span>
                                <span class="uas-fa-value">user-approval-system</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 역할 관리 카드 -->
                    <div class="uas-fa-info-card">
                        <h3>
                            <span class="uas-fa-icon">🔑</span>
                            역할 관리
                        </h3>
                        <p class="uas-fa-help-text">이 플러그인은 다음 역할을 사용합니다:</p>
                        <div class="uas-fa-role-list">
                            <div class="uas-fa-role-item">
                                <span class="uas-fa-role-name">pending</span>
                                <span class="uas-fa-role-desc">승인 대기 중인 사용자</span>
                            </div>
                            <div class="uas-fa-role-item">
                                <span class="uas-fa-role-name">approver</span>
                                <span class="uas-fa-role-desc">관리자</span>
                            </div>
                            <div class="uas-fa-role-item">
                                <span class="uas-fa-role-name">administrator</span>
                                <span class="uas-fa-role-desc">개발자</span>
                            </div>
                        </div>
                        <p class="uas-fa-help-text" style="margin-top: 16px;">※ 관리자는 별도 설정 없이 승인 권한을 가집니다.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 승인 대기 섹션 렌더링
     */
    private static function render_pending_section($pending_users) {
        ?>
        <div class="uas-fa-section" data-section="pending">
            <header class="uas-fa-section-header">
                <div class="uas-fa-header-left">
                    <h1 class="uas-fa-title" style="font-size:28px;">승인 대기 사용자</h1>
                </div>
            </header>
            
            <div class="uas-fa-content">
                <?php if (empty($pending_users)): ?>
                    <div class="uas-fa-empty-state">
                        <span class="uas-fa-icon">✅</span>
                        <h3>현재 승인 대기 중인 사용자가 없습니다</h3>
                        <p>새로운 가입 요청이 있으면 여기에 표시됩니다.</p>
                    </div>
                <?php else: ?>
                    <div class="uas-fa-stats-bar">
                        <span>총 <?php echo count($pending_users); ?>명의 사용자가 승인을 대기하고 있습니다.</span>
                    </div>
                    
                    <div class="uas-fa-pending-list">
                        <table class="uas-fa-table">
                            <thead>
                                <tr>
                                    <th>사번</th>
                                    <th>소속</th>
                                    <th>현장</th>
                                    <th>가입일</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_users as $user): 
                                    $user_sosok = get_user_meta($user->ID, 'kachi_sosok', true);
                                    $user_site = get_user_meta($user->ID, 'kachi_site', true);
                                ?>
                                <tr>
                                    <td class="uas-fa-user-login"><?php echo esc_html($user->user_login); ?></td>
                                    <td><?php echo esc_html($user_sosok ?: '—'); ?></td>
                                    <td><?php echo esc_html($user_site ?: '—'); ?></td>
                                    <td><?php echo esc_html(date('Y-m-d', strtotime($user->user_registered))); ?></td>
                                    <td>
                                        <a href="<?php echo add_query_arg('tab', 'pending', get_permalink()); ?>" class="uas-fa-btn-action">
                                            승인 페이지로 이동
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 배경 설정 섹션 렌더링
     */
    private static function render_background_section($background_image, $background_position, $background_size) {
        ?>
        <div class="uas-fa-section" data-section="background">
            <header class="uas-fa-section-header">
                <div class="uas-fa-header-left">
                    <h1 class="uas-fa-title" style="font-size:28px;">배경 설정</h1>
                </div>
            </header>
            
            <div class="uas-fa-content">
                <div class="uas-fa-setting-card">
                    <h3>
                        <span class="uas-fa-icon">🖼️</span>
                        배경 이미지 설정
                    </h3>
                    <p class="uas-fa-help-text">사용자 승인 페이지의 배경 이미지를 설정합니다.</p>
                    
                    <div class="uas-fa-form-group">
                        <label>배경 이미지</label>
                        <div class="uas-fa-image-input-group">
                            <input type="hidden" id="background-image-url" value="<?php echo esc_attr($background_image); ?>">
                            <button type="button" class="uas-fa-btn-secondary" id="upload-background-image">
                                <span class="uas-fa-icon">📁</span>
                                이미지 선택
                            </button>
                            <?php if ($background_image): ?>
                                <button type="button" class="uas-fa-btn-secondary" id="remove-background-image">
                                    <span class="uas-fa-icon">❌</span>
                                    이미지 제거
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($background_image): ?>
                            <div class="uas-fa-image-preview" id="background-image-preview">
                                <img src="<?php echo esc_url($background_image); ?>" alt="배경 이미지 미리보기">
                            </div>
                        <?php else: ?>
                            <div class="uas-fa-image-preview" id="background-image-preview" style="display: none;">
                                <img src="" alt="배경 이미지 미리보기">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="uas-fa-form-grid">
                        <div class="uas-fa-form-group">
                            <label for="background-position">배경 위치</label>
                            <select id="background-position" class="uas-fa-input">
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
                        
                        <div class="uas-fa-form-group">
                            <label for="background-size">배경 크기</label>
                            <select id="background-size" class="uas-fa-input">
                                <option value="auto" <?php selected($background_size, 'auto'); ?>>원본 크기</option>
                                <option value="cover" <?php selected($background_size, 'cover'); ?>>화면에 맞춤 (Cover)</option>
                                <option value="contain" <?php selected($background_size, 'contain'); ?>>화면에 맞춤 (Contain)</option>
                                <option value="100% 100%" <?php selected($background_size, '100% 100%'); ?>>화면 전체</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="uas-fa-form-actions">
                        <button type="button" class="uas-fa-btn-primary" id="save-background-settings">
                            <span class="uas-fa-icon">💾</span>
                            <span>배경 설정 저장</span>
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
        <div class="uas-fa-section" data-section="guide">
            <header class="uas-fa-section-header">
                <div class="uas-fa-header-left">
                    <h1 class="uas-fa-title" style="font-size:28px;">사용 방법</h1>
                </div>
            </header>
            
            <div class="uas-fa-content">
                <div class="uas-fa-guide-grid">
                    <!-- 승인 시스템 -->
                    <div class="uas-fa-guide-card">
                        <h3>
                            <span class="uas-fa-icon">👥</span>
                            사용자 승인 시스템
                        </h3>
                        <div class="uas-fa-code-block">
                            <code>[user_approval_system]</code>
                        </div>
                        <p>페이지나 포스트에 위 숏코드를 삽입하면 사용자 승인 시스템이 표시됩니다.</p>
                        <ul class="uas-fa-feature-list">
                            <li>승인 대기 사용자 관리</li>
                            <li>사용자 역할 변경</li>
                            <li>소속/현장별 필터링</li>
                            <li>실시간 편집 기능</li>
                        </ul>
                    </div>
                    
                    <!-- 관리자 페이지 -->
                    <div class="uas-fa-guide-card">
                        <h3>
                            <span class="uas-fa-icon">⚙️</span>
                            관리자 페이지
                        </h3>
                        <div class="uas-fa-code-block">
                            <code>[user_approval_admin]</code>
                        </div>
                        <p>프론트엔드에서 시스템 설정을 관리할 수 있는 관리자 페이지입니다.</p>
                        <ul class="uas-fa-feature-list">
                            <li>관리자 권한 필요</li>
                            <li>배경 이미지 설정</li>
                            <li>승인 대기 현황 확인</li>
                            <li>시스템 정보 확인</li>
                        </ul>
                    </div>
                    
                    <!-- 역할 구조 -->
                    <div class="uas-fa-guide-card">
                        <h3>
                            <span class="uas-fa-icon">🔐</span>
                            역할 구조
                        </h3>
                        <p>사용자 역할별 권한 구조:</p>
                        <ul class="uas-fa-input-guide">
                            <li><strong>개발자(administrator):</strong> 모든 권한</li>
                            <li><strong>관리자(approver):</strong> 승인/반려/역할 변경 권한</li>
                            <li><strong>사용자(subscriber):</strong> 일반 사용자</li>
                            <li><strong>승인대기(pending):</strong> 승인 대기 중</li>
                        </ul>
                        <p class="uas-fa-help-text">관리자는 개발자를 제외한 모든 사용자를 관리할 수 있습니다.</p>
                    </div>
                    
                    <!-- 팁과 주의사항 -->
                    <div class="uas-fa-guide-card">
                        <h3>
                            <span class="uas-fa-icon">💡</span>
                            팁과 주의사항
                        </h3>
                        <div class="uas-fa-info-box">
                            <h4>권한 관리</h4>
                            <p>관리자(approver) 권한이 있는 사용자만 승인 기능을 사용할 수 있습니다.</p>
                        </div>
                        <div class="uas-fa-info-box">
                            <h4>접근 제어</h4>
                            <p>로그인하지 않은 사용자도 페이지에 접근할 수 있지만, 승인 기능은 사용할 수 없습니다.</p>
                        </div>
                        <div class="uas-fa-info-box">
                            <h4>데이터 백업</h4>
                            <p>중요한 사용자 정보는 정기적으로 백업하는 것을 권장합니다.</p>
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
        <div class="uas-fa-access-denied">
            <span class="uas-fa-icon">🚫</span>
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
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'user_approval_admin')) {
            // 미디어 업로더
            wp_enqueue_media();
            
            // 스타일
            wp_enqueue_style('uas-frontend-admin', UAS_PLUGIN_URL . 'assets/css/uas-frontend-admin.css', array(), UAS_PLUGIN_VERSION);
            
            // 스크립트
            wp_enqueue_script('uas-frontend-admin', UAS_PLUGIN_URL . 'assets/js/uas-frontend-admin.js', array('jquery'), UAS_PLUGIN_VERSION, true);
            
            // Localization
            wp_localize_script('uas-frontend-admin', 'uas_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('uas_frontend_nonce'),
                'messages' => array(
                    'save_success' => '설정이 저장되었습니다.',
                    'save_error' => '저장 중 오류가 발생했습니다.',
                    'loading' => '처리 중...'
                )
            ));
        }
    }
    
    /**
     * AJAX: 배경 설정 저장
     */
    public function ajax_save_background() {
        // Nonce 검증
        if (!check_ajax_referer('uas_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        // 배경 이미지 URL 저장
        if (isset($_POST['background_image'])) {
            update_option('uas_background_image', esc_url_raw($_POST['background_image']));
        }
        
        // 배경 위치 저장
        if (isset($_POST['background_position'])) {
            update_option('uas_background_position', sanitize_text_field($_POST['background_position']));
        }
        
        // 배경 크기 저장
        if (isset($_POST['background_size'])) {
            update_option('uas_background_size', sanitize_text_field($_POST['background_size']));
        }
        
        wp_send_json_success('배경 설정이 저장되었습니다.');
    }
}