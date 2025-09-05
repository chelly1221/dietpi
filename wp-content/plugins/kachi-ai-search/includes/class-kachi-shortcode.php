<?php
/**
 * 쇼트코드 처리 클래스 - 사이드바 수정 버전
 *
 * @package Kachi_Query_System
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 쇼트코드 기능을 처리하는 클래스
 */
class Kachi_Shortcode {
    
    /**
     * 쇼트코드 등록
     */
    public function register_shortcode() {
        add_shortcode('kachi_query', array($this, 'render_shortcode'));
        
        // wpautop 제거
        add_filter('the_content', array($this, 'remove_wpautop_for_shortcode'), 9);
        
        // 바디 클래스 추가
        add_filter('body_class', array($this, 'add_body_class'));
    }
    
    /**
     * wpautop 제거
     */
    public function remove_wpautop_for_shortcode($content) {
        if (has_shortcode($content, 'kachi_query')) {
            remove_filter('the_content', 'wpautop');
        }
        return $content;
    }
    
    /**
     * 바디 클래스 추가
     */
    public function add_body_class($classes) {
        global $post;
        
        if (is_singular() && is_object($post) && has_shortcode($post->post_content, 'kachi_query')) {
            $classes[] = 'kachi-query-page';
        }
        
        return $classes;
    }
    
    /**
     * 쇼트코드 렌더링
     */
    public function render_shortcode($atts) {
        // REST API 요청인 경우 렌더링하지 않음
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return '';
        }
        
        // 블록 에디터에서 미리보기 중인 경우
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return '<div style="padding: 20px; background: #f0f0f0; text-align: center;">까치 쿼리 시스템 (미리보기에서는 표시되지 않습니다)</div>';
        }
        
        $atts = shortcode_atts(array(
            'class' => '',
            'fullpage' => 'yes',
        ), $atts, 'kachi_query');
        
        // 사용자 정보 가져오기
        $user_data = $this->get_user_data();
        
        // 디자인 설정 가져오기
        $options = get_option('kachi_settings');
        $background_id = isset($options['background_image']) ? $options['background_image'] : '';
        $background_url = $background_id ? wp_get_attachment_url($background_id) : '';
        $overlay_opacity = isset($options['background_overlay']) ? $options['background_overlay'] : 0.9;
        
        // 컨테이너 클래스 및 스타일 설정
        $container_class = 'kachi-query-container ' . esc_attr($atts['class']);
        $container_style = '';
        
        if ($background_url) {
            $container_class .= ' has-background';
            $container_style = 'background-image: url(' . esc_url($background_url) . ');';
        }
        
        // Wrapper 클래스 설정
        $wrapper_class = 'kachi-query-wrapper';
        if ($atts['fullpage'] === 'yes') {
            $wrapper_class .= ' fullpage-mode';
        }
        
        // 버퍼링 시작
        ob_start();
        
        // JavaScript 변수를 data 속성으로 전달
        $js_data = array(
            'is_logged_in' => $user_data['is_logged_in'],
            'api_url' => isset($options['api_url']) ? $options['api_url'] : '',
            'user_sosok' => $user_data['sosok'],
            'user_site' => $user_data['site'],
            'facility_definitions' => $user_data['facility_definitions']
        );
        ?>
        
        <?php if ($background_url) : ?>
        <style>
        .kachi-query-container.has-background::before {
            background: rgba(255, 255, 255, <?php echo esc_attr($overlay_opacity); ?>);
        }
        </style>
        <?php endif; ?>
        
        <!-- 전체 컨테이너 -->
        <div class="kachi-main-container">
            <!-- 모바일 오버레이 -->
            <div class="sidebar-overlay"></div>
            
            <!-- 대화 목록 사이드바 -->
            <div class="conversations-sidebar" style="display: flex;">
                <div class="sidebar-header">
                    <h3>대화 기록</h3>
                    <button class="sidebar-header-close" title="닫기">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <!-- 사이드바 닫기 버튼 -->
                <button class="sidebar-close-btn" title="닫기">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="conversation-list"></div>
                <!-- 닫힌 상태에서 보이는 새 대화 버튼 -->
                <button class="sidebar-bottom-new-chat" title="새 대화">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>새 대화 시작</span>
                </button>
            </div>
            
            <!-- 메인 콘텐츠 영역 -->
            <div class="<?php echo esc_attr($wrapper_class); ?>" data-kachi-config='<?php echo esc_attr(json_encode($js_data)); ?>'>
                <!-- 사이드바 토글 버튼 -->
                <button class="sidebar-toggle" title="대화 목록">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                
                <div id="kachi-query-container-wrapper">
                    <div class="<?php echo esc_attr($container_class); ?>" style="<?php echo esc_attr($container_style); ?>">
                        
                        <!-- 쿼리 박스 컨테이너 -->
                        <div class="query-box-container">
                            
                            <!-- 로고 -->
                            <img src="<?php echo esc_url(home_url('/wp-content/uploads/2025/06/kachi_vivid_pink_gradient.png')); ?>" 
                                 alt="까치 로고" class="kachi-logo" />
                            
                            <!-- 쿼리 헤더 (검색 시 표시) -->
                            <div class="query-header">
                                <div class="query-header-title">까치 AI 어시스턴트</div>
                                <button class="new-chat-btn">
                                    새 대화
                                </button>
                            </div>
                            
                            <!-- 채팅 메시지 영역 -->
                            <div class="chat-messages"></div>
                            
                            <!-- 선택된 필터 표시 -->
                            <div class="selected-filters-container">
                                <span class="inline-pill-container" id="selectedTagPreview"></span>
                                <span class="inline-pill-container" id="selectedDocPreview"></span>
                            </div>
                            
                            <!-- 쿼리 입력 섹션 -->
                            <div class="query-input-section">
                                <div class="query-input-wrapper">
                                    <textarea id="queryInput" 
                                              class="query-input" 
                                              rows="1" 
                                              placeholder="질문을 입력하세요..."></textarea>
                                    
                                    <!-- 통합된 필터 버튼 -->
                                    <div class="query-input-buttons">
                                        <!-- 중지 버튼 (처음에는 숨김) -->
                                        <button id="stopButton" class="stop-button-inline" style="display:none;">
                                            중지
                                        </button>
                                        
                                        <!-- 통합 필터 버튼 -->
                                        <div class="filter-button-container">
                                            <button class="filter-main-btn">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                                </svg>
                                            </button>
                                            <div class="filter-options">
                                                <button class="filter-option-btn" data-type="tag">
                                                    태그
                                                </button>
                                                <button class="filter-option-btn" data-type="doc">
                                                    문서
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 태그 드롭다운 -->
                                <div id="tagDropdownContent" class="dropdown-content" style="display:none;">
                                    <input id="tagSearchInput" 
                                           class="dropdown-search-input" 
                                           placeholder=" 태그 검색…" 
                                           oninput="filterOptions('tag')">
                                    <div id="tagOptionsContainer" class="dropdown-options"></div>
                                </div>
                                
                                <!-- 문서 드롭다운 -->
                                <div id="docDropdownContent" class="dropdown-content" style="display:none;">
                                    <input id="docSearchInput" 
                                           class="dropdown-search-input" 
                                           placeholder=" 문서 검색…" 
                                           oninput="filterOptions('doc')">
                                    <div id="docOptionsContainer" class="dropdown-options"></div>
                                </div>
                            </div>
                            
                            <!-- 로딩 텍스트 -->
                            <div id="loadingMessage" class="loading-text" style="display:none;">
                                까치가 AI에게 물어보러 가는 중...
                            </div>
                            
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        $output = ob_get_clean();
        
        // wp_footer에서 스크립트 추가
        add_action('wp_footer', array($this, 'add_footer_scripts'), 999);
        
        return $output;
    }
    
    /**
     * 푸터 스크립트 추가
     */
    public function add_footer_scripts() {
        ?>
        <script>
        (function() {
            // 페이지에 까치 쿼리가 있는지 확인
            const kachiWrapper = document.querySelector('.kachi-query-wrapper');
            if (!kachiWrapper) return;
            
            // 설정 데이터 가져오기
            const configData = kachiWrapper.dataset.kachiConfig;
            if (configData) {
                try {
                    const config = JSON.parse(configData);
                    window.isUserLoggedIn = config.is_logged_in;
                    window.kachiApiUrl = config.api_url;
                    window.userSosok = config.user_sosok;
                    window.userSite = config.user_site;
                    if (config.is_logged_in && config.facility_definitions) {
                        window.facilityDefinitionsFromPHP = config.facility_definitions;
                    }
                } catch (e) {
                    console.error('Kachi config parse error:', e);
                }
            }
            
            // 전체화면 모드 처리
            if (kachiWrapper.classList.contains('fullpage-mode')) {
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            }
            
            // 스크롤 최상단으로
            window.scrollTo(0, 0);
        })();
        </script>
        <?php
    }
    
    /**
     * 사용자 데이터 가져오기
     */
    private function get_user_data() {
        $data = array(
            'is_logged_in' => is_user_logged_in(),
            'sosok' => '',
            'site' => '',
            'facility_definitions' => array()
        );
        
        if ($data['is_logged_in']) {
            $user_id = get_current_user_id();
            
            // user_meta 사용
            $data['sosok'] = get_user_meta($user_id, 'kachi_sosok', true) ?: '';
            $data['site'] = get_user_meta($user_id, 'kachi_site', true) ?: '';
            
            // Facility definitions 가져오기 - WordPress 옵션 사용
            $all_defs = get_option('facility_definitions_data', array());
            
            if (!empty($all_defs)) {
                $filtered_defs = array_values(array_filter($all_defs, function ($def) use ($data) {
                    return (!$data['sosok'] || $def['sosok'] === $data['sosok']) && 
                           (!$data['site'] || $def['site'] === $data['site']);
                }));
                
                $grouped = [];
                foreach ($filtered_defs as $def) {
                    $key = $def['key'];
                    $values = array_map('trim', explode(',', $def['value']));
                    
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [];
                    }
                    
                    foreach ($values as $v) {
                        if (!in_array($v, $grouped[$key])) {
                            $grouped[$key][] = $v;
                        }
                    }
                }
                
                $data['facility_definitions'] = $grouped;
            }
        }
        
        return $data;
    }
}