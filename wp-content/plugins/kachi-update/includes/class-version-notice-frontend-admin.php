<?php
/**
 * Version Notice Frontend Admin Class
 * 프론트엔드 관리자 페이지를 위한 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class Version_Notice_Frontend_Admin {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'version_notices';
        
        // AJAX 핸들러
        add_action('wp_ajax_vn_frontend_save_notice', array($this, 'ajax_save_notice'));
        add_action('wp_ajax_vn_frontend_delete_notice', array($this, 'ajax_delete_notice'));
        add_action('wp_ajax_vn_frontend_get_notice', array($this, 'ajax_get_notice'));
        add_action('wp_ajax_vn_frontend_save_settings', array($this, 'ajax_save_settings'));
        
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
        $background_image = get_option('version_notice_background_image', '');
        $background_pages = get_option('version_notice_background_pages', array());
        
        // 공지사항 데이터 가져오기
        global $wpdb;
        $table_name = $wpdb->prefix . 'version_notices';
        $notices = $wpdb->get_results("SELECT * FROM `{$table_name}` ORDER BY `year_month` DESC, `sort_order` ASC, `id` DESC");
        
        ob_start();
        ?>
        
        <div class="vn-frontend-admin">
            <div class="vn-fa-container">
                <!-- 헤더 영역 (로고 + 탭) -->
                <header class="vn-fa-header-wrapper">
                    <!-- 로고 섹션 -->
                    <div class="vn-fa-logo-section">
                        <a href="#" class="vn-fa-logo">
                            <div class="vn-fa-logo-icon">📢</div>
                            <span>버전 공지사항</span>
                        </a>
                    </div>
                    
                    <!-- 상단 탭 네비게이션 -->
                    <nav class="vn-fa-tabs-nav">
                        <a href="#" class="vn-fa-tab-item active" data-section="notices">
                            <span class="vn-fa-tab-icon">📝</span>
                            <span class="vn-fa-tab-text">공지사항</span>
                        </a>
                        <a href="#" class="vn-fa-tab-item" data-section="settings">
                            <span class="vn-fa-tab-icon">⚙️</span>
                            <span class="vn-fa-tab-text">설정</span>
                        </a>
                        <a href="#" class="vn-fa-tab-item" data-section="guide">
                            <span class="vn-fa-tab-icon">❓</span>
                            <span class="vn-fa-tab-text">사용 방법</span>
                        </a>
                    </nav>
                </header>
                
                <!-- 메인 영역 -->
                <main class="vn-fa-main">
                    <!-- 알림 영역 -->
                    <div class="vn-fa-notice" style="display:none;"></div>
                    
                    <!-- 공지사항 관리 섹션 -->
                    <?php self::render_notices_section($notices); ?>
                    
                    <!-- 설정 섹션 -->
                    <?php self::render_settings_section($background_image, $background_pages); ?>
                    
                    <!-- 사용 방법 섹션 -->
                    <?php self::render_guide_section(); ?>
                </main>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 공지사항 관리 섹션 렌더링
     */
    private static function render_notices_section($notices) {
        ?>
        <div class="vn-fa-section active" data-section="notices">
            <header class="vn-fa-section-header">
                <div class="vn-fa-header-left">
                    <h1 class="vn-fa-title" style="font-size:28px;">공지사항</h1>
                </div>
                <div class="vn-fa-header-actions">
                    <button type="button" class="vn-fa-btn-primary" id="add-new-notice">
                        <span class="vn-fa-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>새 공지사항 추가</span>
                    </button>
                </div>
            </header>
            
            <div class="vn-fa-content">
                <!-- 공지사항 폼 (기본적으로 숨김) -->
                <div class="vn-fa-notice-form" style="display: none;">
                    <div class="vn-fa-form-card">
                        <h3>
                            <span class="vn-fa-icon">✏️</span>
                            <span id="form-title">새 공지사항 추가</span>
                        </h3>
                        
                        <form id="vn-notice-form">
                            <input type="hidden" id="notice-id" value="">
                            
                            <div class="vn-fa-form-grid">
                                <div class="vn-fa-form-group">
                                    <label for="year-month">연도-월</label>
                                    <input type="month" id="year-month" name="year_month" class="vn-fa-input" required>
                                </div>
                                
                                <div class="vn-fa-form-group">
                                    <label for="version-number">버전 번호</label>
                                    <input type="text" id="version-number" name="version_number" class="vn-fa-input" placeholder="v3.3.11" required>
                                </div>
                                
                                <div class="vn-fa-form-group">
                                    <label for="notice-date">날짜</label>
                                    <input type="text" id="notice-date" name="notice_date" class="vn-fa-input" placeholder="27일" required>
                                </div>
                                
                                <div class="vn-fa-form-group">
                                    <label for="sort-order">정렬 순서</label>
                                    <input type="number" id="sort-order" name="sort_order" class="vn-fa-input" value="0">
                                </div>
                            </div>
                            
                            <div class="vn-fa-form-group">
                                <label for="title">제목</label>
                                <input type="text" id="title" name="title" class="vn-fa-input" required>
                            </div>
                            
                            <div class="vn-fa-form-group">
                                <label for="content">내용</label>
                                <textarea id="content" name="content" class="vn-fa-input" rows="5" required></textarea>
                                <p class="vn-fa-help-text">&lt;li&gt; 태그를 사용하여 목록 항목을 작성하세요.</p>
                            </div>
                            
                            <div class="vn-fa-form-actions">
                                <button type="submit" class="vn-fa-btn-primary">
                                    <span class="vn-fa-icon">💾</span>
                                    <span>저장</span>
                                </button>
                                <button type="button" class="vn-fa-btn-secondary" id="cancel-edit">
                                    <span class="vn-fa-icon">❌</span>
                                    <span>취소</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- 공지사항 목록 -->
                <div class="vn-fa-notices-list">
                    <?php self::render_notices_list($notices); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 공지사항 목록 렌더링
     */
    private static function render_notices_list($notices) {
        if (empty($notices)) {
            echo '<div class="vn-fa-empty-state">';
            echo '<span class="vn-fa-icon">📭</span>';
            echo '<h3>공지사항이 없습니다</h3>';
            echo '<p>상단의 "새 공지사항 추가" 버튼을 클릭하여 첫 번째 공지사항을 작성해보세요.</p>';
            echo '</div>';
            return;
        }
        
        // 연월별로 그룹화
        $grouped = array();
        foreach ($notices as $notice) {
            if (!isset($grouped[$notice->year_month])) {
                $grouped[$notice->year_month] = array();
            }
            $grouped[$notice->year_month][] = $notice;
        }
        
        foreach ($grouped as $year_month => $month_notices) {
            $parts = explode('-', $year_month);
            $year = $parts[0];
            $month = intval($parts[1]);
            
            echo '<div class="vn-fa-notice-group">';
            echo '<div class="vn-fa-group-header">';
            echo '<h3>' . $year . '년 ' . $month . '월</h3>';
            echo '<span class="vn-fa-badge">' . count($month_notices) . '개</span>';
            echo '</div>';
            
            echo '<div class="vn-fa-notice-items">';
            foreach ($month_notices as $notice) {
                echo '<div class="vn-fa-notice-item" data-id="' . $notice->id . '">';
                echo '<div class="vn-fa-notice-header">';
                echo '<div class="vn-fa-notice-meta">';
                echo '<span class="vn-fa-version-tag">' . esc_html($notice->version_number) . '</span>';
                echo '<span class="vn-fa-date">' . esc_html($notice->notice_date) . '</span>';
                echo '<span class="vn-fa-title">' . esc_html($notice->title) . '</span>';
                echo '</div>';
                echo '<div class="vn-fa-notice-actions">';
                echo '<button class="vn-fa-btn-icon edit-notice" data-id="' . $notice->id . '" title="수정">';
                echo '<span class="vn-fa-icon">✏️</span>';
                echo '</button>';
                echo '<button class="vn-fa-btn-icon delete-notice" data-id="' . $notice->id . '" title="삭제">';
                echo '<span class="vn-fa-icon">🗑️</span>';
                echo '</button>';
                echo '</div>';
                echo '</div>';
                echo '<div class="vn-fa-notice-content">';
                echo wp_kses_post($notice->content);
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * 설정 섹션 렌더링
     */
    private static function render_settings_section($background_image, $background_pages) {
        // 모든 페이지 목록 가져오기
        $pages = get_pages();
        ?>
        <div class="vn-fa-section" data-section="settings">
            <header class="vn-fa-section-header">
                <div class="vn-fa-header-left">
                    <h1 class="vn-fa-title" style="font-size:28px;">설정</h1>
                </div>
                <div class="vn-fa-header-actions">
                    <span class="vn-fa-save-status" style="display: none;">
                        <span class="vn-fa-icon">✅</span>
                        <span>자동 저장됨</span>
                    </span>
                </div>
            </header>
            
            <div class="vn-fa-content">
                <div class="vn-fa-settings-grid">
                    <!-- 배경 이미지 설정 -->
                    <div class="vn-fa-setting-card">
                        <h3>
                            <span class="vn-fa-icon">🎨</span>
                            배경 이미지
                        </h3>
                        <p class="vn-fa-help-text">버전 공지사항이 표시되는 페이지의 배경 이미지를 설정합니다.</p>
                        
                        <div class="vn-fa-form-group">
                            <label>배경 이미지</label>
                            <div class="vn-fa-image-input-group">
                                <input type="hidden" id="background-image-url" value="<?php echo esc_attr($background_image); ?>">
                                <button type="button" class="vn-fa-btn-secondary" id="upload-background-image">
                                    <span class="vn-fa-icon">📁</span>
                                    이미지 선택
                                </button>
                                <?php if ($background_image): ?>
                                    <button type="button" class="vn-fa-btn-secondary" id="remove-background-image">
                                        <span class="vn-fa-icon">❌</span>
                                        이미지 제거
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($background_image): ?>
                                <div class="vn-fa-image-preview" id="background-image-preview">
                                    <img src="<?php echo esc_url($background_image); ?>" alt="배경 이미지 미리보기">
                                </div>
                            <?php else: ?>
                                <div class="vn-fa-image-preview" id="background-image-preview" style="display: none;">
                                    <img src="" alt="배경 이미지 미리보기">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 페이지 선택 설정 -->
                    <div class="vn-fa-setting-card">
                        <h3>
                            <span class="vn-fa-icon">📄</span>
                            배경 이미지 적용 페이지
                        </h3>
                        <p class="vn-fa-help-text">배경 이미지를 적용할 페이지를 선택하세요.</p>
                        
                        <div class="vn-fa-form-group">
                            <label class="vn-fa-checkbox-label">
                                <input type="checkbox" id="apply-all-pages" <?php echo empty($background_pages) ? 'checked' : ''; ?>>
                                <span>모든 버전 공지사항 페이지에 적용</span>
                            </label>
                        </div>
                        
                        <div id="page-selection" class="vn-fa-page-selection" <?php echo empty($background_pages) ? 'style="display:none;"' : ''; ?>>
                            <p class="vn-fa-help-text">특정 페이지만 선택:</p>
                            <div class="vn-fa-pages-list">
                                <?php foreach ($pages as $page): ?>
                                    <label class="vn-fa-checkbox-label">
                                        <input type="checkbox" name="background_pages[]" value="<?php echo $page->ID; ?>" 
                                            <?php echo in_array($page->ID, $background_pages) ? 'checked' : ''; ?>>
                                        <span><?php echo esc_html($page->post_title); ?></span>
                                        <span class="vn-fa-page-id">(ID: <?php echo $page->ID; ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
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
        <div class="vn-fa-section" data-section="guide">
            <header class="vn-fa-section-header">
                <div class="vn-fa-header-left">
                    <h1 class="vn-fa-title"  style="font-size:28px;">사용 방법</h1>
                </div>
            </header>
            
            <div class="vn-fa-content">
                <div class="vn-fa-guide-grid">
                    <!-- 공지사항 표시 -->
                    <div class="vn-fa-guide-card">
                        <h3>
                            <span class="vn-fa-icon">📢</span>
                            공지사항 표시
                        </h3>
                        <div class="vn-fa-code-block">
                            <code>[version_notice]</code>
                        </div>
                        <p>페이지나 포스트에 위 숏코드를 삽입하면 버전 공지사항이 표시됩니다.</p>
                        <ul class="vn-fa-feature-list">
                            <li>연월별 자동 그룹화</li>
                            <li>아코디언 스타일 UI</li>
                            <li>반응형 디자인</li>
                            <li>모던한 애니메이션 효과</li>
                        </ul>
                    </div>
                    
                    <!-- 관리자 페이지 -->
                    <div class="vn-fa-guide-card">
                        <h3>
                            <span class="vn-fa-icon">⚙️</span>
                            관리자 페이지
                        </h3>
                        <div class="vn-fa-code-block">
                            <code>[version_notice_admin]</code>
                        </div>
                        <p>프론트엔드에서 공지사항을 관리할 수 있는 관리자 페이지입니다.</p>
                        <ul class="vn-fa-feature-list">
                            <li>관리자 권한 필요</li>
                            <li>공지사항 추가/수정/삭제</li>
                            <li>배경 이미지 설정</li>
                            <li>페이지별 설정 가능</li>
                        </ul>
                    </div>
                    
                    <!-- 공지사항 작성 가이드 -->
                    <div class="vn-fa-guide-card">
                        <h3>
                            <span class="vn-fa-icon">✍️</span>
                            공지사항 작성
                        </h3>
                        <p>공지사항 작성 시 다음 항목을 입력하세요:</p>
                        <ul class="vn-fa-input-guide">
                            <li><strong>연도-월:</strong> 공지사항이 속할 연월 선택</li>
                            <li><strong>버전 번호:</strong> 예) v3.3.11</li>
                            <li><strong>날짜:</strong> 예) 27일</li>
                            <li><strong>제목:</strong> 업데이트 제목</li>
                            <li><strong>내용:</strong> HTML 지원, &lt;li&gt; 태그 사용 권장</li>
                            <li><strong>정렬 순서:</strong> 낮은 숫자가 먼저 표시</li>
                        </ul>
                    </div>
                    
                    <!-- 팁과 주의사항 -->
                    <div class="vn-fa-guide-card">
                        <h3>
                            <span class="vn-fa-icon">💡</span>
                            팁과 주의사항
                        </h3>
                        <div class="vn-fa-info-box">
                            <h4>데이터 백업</h4>
                            <p>중요한 공지사항은 정기적으로 백업하는 것을 권장합니다.</p>
                        </div>
                        <div class="vn-fa-info-box">
                            <h4>이미지 최적화</h4>
                            <p>배경 이미지는 2MB 이하의 최적화된 이미지를 사용하세요.</p>
                        </div>
                        <div class="vn-fa-info-box">
                            <h4>플러그인 제거</h4>
                            <p>플러그인 제거 시 데이터베이스 테이블은 유지됩니다. 완전 제거를 원하면 수동으로 삭제해야 합니다.</p>
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
        <div class="vn-fa-access-denied">
            <span class="vn-fa-icon">🚫</span>
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
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'version_notice_admin')) {
            // 미디어 업로더
            wp_enqueue_media();
            
            // 스타일
            wp_enqueue_style('vn-frontend-admin', VERSION_NOTICE_PLUGIN_URL . 'assets/css/vn-frontend-admin.css', array(), VERSION_NOTICE_VERSION);
            
            // 스크립트
            wp_enqueue_script('vn-frontend-admin', VERSION_NOTICE_PLUGIN_URL . 'assets/js/vn-frontend-admin.js', array('jquery'), VERSION_NOTICE_VERSION, true);
            
            // Localization
            wp_localize_script('vn-frontend-admin', 'vn_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vn_frontend_nonce'),
                'messages' => array(
                    'save_success' => '저장되었습니다.',
                    'save_error' => '저장 중 오류가 발생했습니다.',
                    'delete_confirm' => '정말 삭제하시겠습니까?',
                    'delete_success' => '삭제되었습니다.',
                    'loading' => '로딩 중...',
                    'saving' => '저장 중...',
                    'saved' => '자동 저장됨'
                )
            ));
        }
    }
    
    /**
     * AJAX: 공지사항 저장
     */
    public function ajax_save_notice() {
        // Nonce 검증
        if (!check_ajax_referer('vn_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $data = array(
            'version_number' => sanitize_text_field($_POST['version_number']),
            'notice_date' => sanitize_text_field($_POST['notice_date']),
            'title' => sanitize_text_field($_POST['title']),
            'content' => wp_kses_post($_POST['content']),
            'year_month' => sanitize_text_field($_POST['year_month']),
            'sort_order' => intval($_POST['sort_order'])
        );
        
        if ($id > 0) {
            $result = $wpdb->update($this->table_name, $data, array('id' => $id));
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($this->table_name, $data);
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => '공지사항이 저장되었습니다.',
                'id' => $id > 0 ? $id : $wpdb->insert_id
            ));
        } else {
            wp_send_json_error('저장 실패: ' . $wpdb->last_error);
        }
    }
    
    /**
     * AJAX: 공지사항 삭제
     */
    public function ajax_delete_notice() {
        // Nonce 검증
        if (!check_ajax_referer('vn_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        global $wpdb;
        
        $id = intval($_POST['id']);
        $result = $wpdb->delete($this->table_name, array('id' => $id));
        
        if ($result !== false) {
            wp_send_json_success('공지사항이 삭제되었습니다.');
        } else {
            wp_send_json_error('삭제 실패');
        }
    }
    
    /**
     * AJAX: 공지사항 가져오기
     */
    public function ajax_get_notice() {
        // Nonce 검증
        if (!check_ajax_referer('vn_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        global $wpdb;
        
        $id = intval($_POST['id']);
        $notice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE id = %d",
            $id
        ));
        
        if ($notice) {
            wp_send_json_success($notice);
        } else {
            wp_send_json_error('공지사항을 찾을 수 없습니다.');
        }
    }
    
    /**
     * AJAX: 설정 저장
     */
    public function ajax_save_settings() {
        // Nonce 검증
        if (!check_ajax_referer('vn_frontend_nonce', 'nonce', false)) {
            wp_send_json_error('보안 검증 실패');
        }
        
        // 권한 확인
        if (!current_user_can('manage_options')) {
            wp_send_json_error('권한이 없습니다.');
        }
        
        $background_image = sanitize_text_field($_POST['background_image']);
        $background_pages = isset($_POST['background_pages']) ? array_map('intval', $_POST['background_pages']) : array();
        
        update_option('version_notice_background_image', $background_image);
        update_option('version_notice_background_pages', $background_pages);
        
        wp_send_json_success('설정이 저장되었습니다.');
    }
}