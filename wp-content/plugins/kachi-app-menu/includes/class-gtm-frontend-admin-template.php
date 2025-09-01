<?php
/**
 * Google Themed Menu - Frontend Admin Template
 * 
 * 프론트엔드 관리자 페이지의 HTML 템플릿을 담당하는 클래스
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class GTM_Frontend_Admin_Template {
    
    private $core;
    
    public function __construct($core) {
        $this->core = $core;
    }
    
    /**
     * 프론트엔드 관리자 페이지 렌더링
     */
    public function render($menu_items, $permissions, $login_settings, $roles) {
        ob_start();
        ?>
        
        <div class="gtm-frontend-admin">
            
            <div class="gtm-fa-container">
                <!-- 헤더 영역 (로고 + 탭) -->
                <header class="gtm-fa-header-wrapper">
                    <!-- 로고 섹션 -->
                    <div class="gtm-fa-logo-section">
                        <a href="#" class="gtm-fa-logo">
                            <div class="gtm-fa-logo-icon">⚙️</div>
                            <span>메뉴</span>
                        </a>
                    </div>
                    
                    <!-- 상단 탭 네비게이션 -->
                    <nav class="gtm-fa-tabs-nav">
                        <a href="#" class="gtm-fa-tab-item active" data-section="menus">
                            <span class="gtm-fa-tab-icon">📋</span>
                            <span class="gtm-fa-tab-text">메뉴 관리</span>
                        </a>
                        <a href="#" class="gtm-fa-tab-item" data-section="appearance">
                            <span class="gtm-fa-tab-icon">🎨</span>
                            <span class="gtm-fa-tab-text">테마 설정</span>
                        </a>
                        <a href="#" class="gtm-fa-tab-item" data-section="login">
                            <span class="gtm-fa-tab-icon">🔐</span>
                            <span class="gtm-fa-tab-text">로그인 설정</span>
                        </a>
                        <a href="#" class="gtm-fa-tab-item" data-section="advanced">
                            <span class="gtm-fa-tab-icon">🔧</span>
                            <span class="gtm-fa-tab-text">고급 설정</span>
                        </a>
                    </nav>
                </header>
                
                <!-- 메인 영역 -->
                <main class="gtm-fa-main">
                    <!-- 알림 영역 -->
                    <div class="gtm-fa-notice" style="display:none;"></div>
                    
                    <!-- 메뉴 관리 섹션 -->
                    <?php $this->render_menu_section($menu_items, $permissions, $roles); ?>
                    
                    <!-- 테마 설정 섹션 -->
                    <?php $this->render_appearance_section(); ?>
                    
                    <!-- 로그인 설정 섹션 -->
                    <?php $this->render_login_section($login_settings); ?>
                    
                    <!-- 고급 설정 섹션 -->
                    <?php $this->render_advanced_section(); ?>
                </main>
            </div>
            
            <!-- 메뉴 편집 모달 -->
            <?php $this->render_edit_modal($roles); ?>
            
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 메뉴 관리 섹션 렌더링
     */
    private function render_menu_section($menu_items, $permissions, $roles) {
        ?>
        <div class="gtm-fa-section active" data-section="menus">
            <!-- 섹션 헤더 -->
            <header class="gtm-fa-section-header">
                <div class="gtm-fa-header-left">
                    <h1 class="gtm-fa-title">메뉴 관리</h1>
                </div>
                <div class="gtm-fa-header-actions">
                    <div class="gtm-fa-search">
                        <input type="text" class="gtm-fa-search-input" placeholder="메뉴 검색...">
                        <span class="gtm-fa-search-icon">🔍</span>
                    </div>
                    <button class="gtm-fa-btn-primary gtm-fa-add-menu" type="button">
                        <span class="gtm-fa-icon">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 1V15M1 8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>새 메뉴</span>
                    </button>
                </div>
            </header>
            
            <!-- 콘텐츠 영역 -->
            <div class="gtm-fa-content">
                <!-- 메뉴 목록 -->
                <div class="gtm-fa-menu-list">
                    <div class="gtm-fa-menu-grid">
                        <?php if (!empty($menu_items)) : ?>
                            <?php foreach ($menu_items as $item) : ?>
                                <?php $this->render_menu_item_list($item, $permissions, $roles); ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="gtm-fa-empty">
                                <span class="gtm-fa-empty-icon">📭</span>
                                <h3>메뉴가 없습니다</h3>
                                <p>새 메뉴를 추가하여 시작하세요.</p>
                                <button class="gtm-fa-btn-primary gtm-fa-add-menu" type="button">
                                    <span class="gtm-fa-icon">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M8 1V15M1 8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    첫 메뉴 추가하기
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 메뉴 아이템 렌더링 - 목록 뷰
     */
    private function render_menu_item_list($item, $permissions, $roles) {
        $item_permissions = isset($permissions[$item['id']]) ? $permissions[$item['id']] : array('all');
        ?>
        
        <div class="gtm-fa-menu-item" 
             data-id="<?php echo esc_attr($item['id']); ?>" 
             data-icon="<?php echo esc_attr($item['icon']); ?>"
             data-text="<?php echo esc_attr($item['text']); ?>"
             data-url="<?php echo esc_attr($item['url']); ?>"
             data-guest-action="<?php echo esc_attr($item['guest_action'] ?? 'redirect_login'); ?>"
             data-no-permission-action="<?php echo esc_attr($item['no_permission_action'] ?? 'show_message'); ?>"
             data-no-permission-message="<?php echo esc_attr($item['no_permission_message'] ?? '이 페이지에 접근할 권한이 없습니다.'); ?>"
             data-no-permission-redirect="<?php echo esc_attr($item['no_permission_redirect'] ?? '/'); ?>"
             data-permissions="<?php echo esc_attr(json_encode($item_permissions)); ?>">
            
            <div class="gtm-fa-item-header">
                <div class="gtm-fa-item-handle">
                    <span>⋮⋮</span>
                </div>
                
                <div class="gtm-fa-item-icon">
                    <?php echo esc_html($item['icon']); ?>
                </div>
                
                <div class="gtm-fa-item-content">
                    <h4 class="gtm-fa-item-title"><?php echo esc_html($item['text']); ?></h4>
                    <div class="gtm-fa-item-url"><?php echo esc_html($item['url']); ?></div>
                    <div class="gtm-fa-item-permissions">
                        <?php $this->render_permission_tags($item_permissions); ?>
                    </div>
                </div>
                
                <div class="gtm-fa-item-actions">
                    <button class="gtm-fa-btn-icon gtm-fa-edit" title="편집" type="button">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14.166 2.5009C14.3849 2.28203 14.6447 2.10842 14.9307 1.98996C15.2167 1.87151 15.5232 1.81055 15.8327 1.81055C16.1422 1.81055 16.4487 1.87151 16.7347 1.98996C17.0206 2.10842 17.2805 2.28203 17.4993 2.5009C17.7182 2.71977 17.8918 2.97961 18.0103 3.26558C18.1287 3.55154 18.1897 3.85804 18.1897 4.16757C18.1897 4.4771 18.1287 4.7836 18.0103 5.06956C17.8918 5.35553 17.7182 5.61537 17.4993 5.83424L6.24935 17.0842L1.66602 18.3342L2.91602 13.7509L14.166 2.5009Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <button class="gtm-fa-btn-icon gtm-fa-delete" title="삭제" type="button">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.5 5H4.16667H17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M6.66602 4.99984V3.33317C6.66602 2.89114 6.84161 2.46722 7.15417 2.15466C7.46673 1.8421 7.89066 1.6665 8.33268 1.6665H11.666C12.108 1.6665 12.532 1.8421 12.8445 2.15466C13.1571 2.46722 13.3327 2.89114 13.3327 3.33317V4.99984M15.8327 4.99984V16.6665C15.8327 17.1085 15.6571 17.5325 15.3445 17.845C15.032 18.1576 14.608 18.3332 14.166 18.3332H5.83268C5.39066 18.3332 4.96673 18.1576 4.65417 17.845C4.34161 17.5325 4.16602 17.1085 4.16602 16.6665V4.99984H15.8327Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M8.33398 9.1665V14.1665" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M11.666 9.1665V14.1665" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * 테마 설정 섹션 렌더링
     */
    private function render_appearance_section() {
        $white_mode_pages = get_option('gtm_white_mode_pages', array());
        if (!is_array($white_mode_pages)) $white_mode_pages = array();
        ?>
        
        <div class="gtm-fa-section" data-section="appearance">
            <header class="gtm-fa-section-header">
                <div class="gtm-fa-header-left">
                    <h1 class="gtm-fa-title">테마 설정</h1>
                </div>
            </header>
            
            <div class="gtm-fa-content">
                <div class="gtm-fa-settings-grid">
                    <!-- 화이트모드 설정 -->
                    <div class="gtm-fa-setting-card">
                        <h3>
                            <span class="gtm-fa-icon">☀️</span>
                            화이트모드 설정
                        </h3>
                        <p class="gtm-fa-help-text">페이지별로 화이트모드를 적용할 수 있습니다. 선택하지 않은 페이지는 다크모드로 표시됩니다.</p>
                        
                        <div class="gtm-fa-page-selector">
                            <!-- 홈페이지 -->
                            <div class="gtm-fa-page-item">
                                <label class="gtm-fa-switch">
                                    <input type="checkbox" class="white-mode-page" value="front_page" 
                                        <?php checked(in_array('front_page', $white_mode_pages)); ?>>
                                    <span class="gtm-fa-switch-slider"></span>
                                </label>
                                <div class="gtm-fa-page-info">
                                    <strong>홈페이지</strong>
                                    <span>사이트 메인 페이지</span>
                                </div>
                            </div>
                            
                            <!-- 다른 페이지들 -->
                            <?php
                            $pages = get_pages(array(
                                'sort_order' => 'asc',
                                'sort_column' => 'post_title',
                            ));
                            
                            foreach ($pages as $page) :
                            ?>
                            <div class="gtm-fa-page-item">
                                <label class="gtm-fa-switch">
                                    <input type="checkbox" class="white-mode-page" value="<?php echo $page->ID; ?>" 
                                        <?php checked(in_array($page->ID, $white_mode_pages)); ?>>
                                    <span class="gtm-fa-switch-slider"></span>
                                </label>
                                <div class="gtm-fa-page-info">
                                    <strong><?php echo esc_html($page->post_title); ?></strong>
                                    <span>ID: <?php echo $page->ID; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- 메뉴 스타일 설정 -->
                    <div class="gtm-fa-setting-card">
                        <h3>
                            <span class="gtm-fa-icon">🎨</span>
                            메뉴 스타일
                        </h3>
                        <p class="gtm-fa-help-text">메뉴의 기본 스타일을 설정합니다.</p>
                        
                        <div class="gtm-fa-form-group">
                            <label>메뉴 위치</label>
                            <select class="gtm-fa-select" id="menu-position">
                                <option value="top-right">상단 우측</option>
                                <option value="top-left">상단 좌측</option>
                                <option value="bottom-right">하단 우측</option>
                                <option value="bottom-left">하단 좌측</option>
                            </select>
                        </div>
                        
                        <div class="gtm-fa-form-group">
                            <label>메뉴 크기</label>
                            <select class="gtm-fa-select" id="menu-size">
                                <option value="small">작게</option>
                                <option value="medium" selected>보통</option>
                                <option value="large">크게</option>
                            </select>
                        </div>
                        
                        <div class="gtm-fa-form-group">
                            <label>애니메이션 효과</label>
                            <label class="gtm-fa-switch">
                                <input type="checkbox" id="menu-animation" checked>
                                <span class="gtm-fa-switch-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * 로그인 설정 섹션 렌더링
     */
    private function render_login_section($login_settings) {
        ?>
        
        <div class="gtm-fa-section" data-section="login">
            <header class="gtm-fa-section-header">
                <div class="gtm-fa-header-left">
                    <h1 class="gtm-fa-title">로그인 설정</h1>
                </div>
            </header>
            
            <div class="gtm-fa-content">
                <div class="gtm-fa-settings-group">
                    <h3>로그인 페이지</h3>
                    <p class="gtm-fa-help-text">사용자가 로그인할 페이지를 설정합니다.</p>
                    
                    <div class="gtm-fa-form-group">
                        <label>로그인 페이지 선택</label>
                        <select class="gtm-fa-select" id="login-page-id">
                            <?php
                            $pages = get_pages();
                            foreach ($pages as $page) {
                                $selected = ($login_settings['login_page_id'] == $page->ID) ? 'selected' : '';
                                echo '<option value="' . $page->ID . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="gtm-fa-settings-group">
                    <h3>리다이렉트 설정</h3>
                    <p class="gtm-fa-help-text">로그인/로그아웃 후 이동할 페이지를 설정합니다.</p>
                    
                    <div class="gtm-fa-form-row">
                        <div class="gtm-fa-form-group">
                            <label>로그인 후 이동</label>
                            <select class="gtm-fa-select" id="login-redirect">
                                <option value="previous_page" <?php selected($login_settings['login_redirect'], 'previous_page'); ?>>이전 페이지로</option>
                                <option value="home" <?php selected($login_settings['login_redirect'], 'home'); ?>>홈페이지로</option>
                                <option value="custom" <?php selected($login_settings['login_redirect'], 'custom'); ?>>사용자 정의</option>
                            </select>
                            
                            <div class="gtm-fa-sub-group" id="custom-login-redirect-group" style="<?php echo $login_settings['login_redirect'] !== 'custom' ? 'display:none;' : ''; ?>">
                                <input type="text" class="gtm-fa-input" id="custom-login-redirect" 
                                    value="<?php echo esc_attr($login_settings['custom_login_redirect']); ?>" 
                                    placeholder="/dashboard">
                                <small>사용자 정의 URL을 입력하세요</small>
                            </div>
                        </div>
                        
                        <div class="gtm-fa-form-group">
                            <label>로그아웃 후 이동</label>
                            <input type="text" class="gtm-fa-input" id="logout-redirect" 
                                value="<?php echo esc_attr($login_settings['logout_redirect']); ?>" 
                                placeholder="/">
                            <small>로그아웃 후 이동할 URL</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * 고급 설정 섹션 렌더링
     */
    private function render_advanced_section() {
        ?>
        
        <div class="gtm-fa-section" data-section="advanced">
            <header class="gtm-fa-section-header">
                <div class="gtm-fa-header-left">
                    <h1 class="gtm-fa-title">고급 설정</h1>
                </div>
            </header>
            
            <div class="gtm-fa-content">
                <div class="gtm-fa-advanced-section">
                    <h3>백업 및 복원</h3>
                    <p class="gtm-fa-help-text">설정을 백업하거나 복원할 수 있습니다.</p>
                    
                    <div class="gtm-fa-button-group">
                        <button class="gtm-fa-btn-secondary" id="export-settings">
                            <span class="gtm-fa-icon">📥</span>
                            설정 내보내기
                        </button>
                        <button class="gtm-fa-btn-secondary" id="import-settings">
                            <span class="gtm-fa-icon">📤</span>
                            설정 가져오기
                        </button>
                    </div>
                </div>
                
                <div class="gtm-fa-advanced-section">
                    <h3>초기화</h3>
                    <p class="gtm-fa-help-text">모든 설정을 기본값으로 초기화합니다.</p>
                    
                    <button class="gtm-fa-btn-danger" id="reset-settings">
                        <span class="gtm-fa-icon">⚠️</span>
                        설정 초기화
                    </button>
                </div>
                
                <div class="gtm-fa-advanced-section">
                    <h3>시스템 정보</h3>
                    <div class="gtm-fa-system-info">
                        <p><strong>플러그인 버전:</strong> <?php echo GTM_VERSION; ?></p>
                        <p><strong>WordPress 버전:</strong> <?php echo get_bloginfo('version'); ?></p>
                        <p><strong>PHP 버전:</strong> <?php echo phpversion(); ?></p>
                        <p><strong>활성 테마:</strong> <?php echo wp_get_theme()->get('Name'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * 권한 태그 렌더링
     */
    private function render_permission_tags($permissions) {
        if (in_array('guest', $permissions)) {
            echo '<span class="gtm-fa-tag gtm-fa-tag-guest">모든 사용자</span>';
        } elseif (in_array('all', $permissions)) {
            echo '<span class="gtm-fa-tag gtm-fa-tag-all">로그인 사용자</span>';
        } else {
            foreach ($permissions as $permission) {
                $label = $this->get_role_label($permission);
                echo '<span class="gtm-fa-tag">' . esc_html($label) . '</span>';
            }
        }
    }
    
    /**
     * 역할 레이블 가져오기
     */
    private function get_role_label($role) {
        $labels = array(
            'administrator' => '개발자',
            'approver' => '관리자',
            'pending' => '승인 대기',
            'subscriber' => '사용자',
            // 기타 역할들 (표시는 안되지만 혹시 데이터에 있을 경우를 위해)
            'editor' => '편집자',
            'author' => '글쓴이',
            'contributor' => '기여자'
        );
        
        return isset($labels[$role]) ? $labels[$role] : $role;
    }
    
    /**
     * 편집 모달 렌더링
     */
    private function render_edit_modal($roles) {
        // 표시할 역할만 필터링
        $allowed_roles = array(
            'administrator' => '개발자',
            'approver' => '관리자',
            'pending' => '승인 대기',
            'subscriber' => '사용자'
        );
        ?>
        
        <div class="gtm-fa-modal" style="display:none;">
            <div class="gtm-fa-modal-content">
                <div class="gtm-fa-modal-header">
                    <h3 class="gtm-fa-modal-title">메뉴 편집</h3>
                    <button class="gtm-fa-modal-close" type="button">&times;</button>
                </div>
                
                <div class="gtm-fa-modal-body">
                    <input type="hidden" class="gtm-fa-menu-id">
                    
                    <div class="gtm-fa-form-row">
                        <div class="gtm-fa-form-group">
                            <label>아이콘</label>
                            <input type="text" class="gtm-fa-input gtm-fa-menu-icon" placeholder="예: 🏠">
                            <small>이모지나 텍스트를 입력하세요</small>
                        </div>
                        
                        <div class="gtm-fa-form-group">
                            <label>메뉴 이름</label>
                            <input type="text" class="gtm-fa-input gtm-fa-menu-text" placeholder="메뉴 이름">
                        </div>
                    </div>
                    
                    <div class="gtm-fa-form-group">
                        <label>URL</label>
                        <input type="text" class="gtm-fa-input gtm-fa-menu-url" placeholder="예: /?page_id=123">
                        <small>절대 경로 또는 상대 경로를 입력하세요</small>
                    </div>
                    
                    <div class="gtm-fa-form-group">
                        <label>접근 권한</label>
                        <div class="gtm-fa-permission-grid">
                            <label class="gtm-fa-checkbox">
                                <input type="checkbox" name="permissions" value="guest" class="gtm-fa-permission-checkbox">
                                <span>모든 사용자 (비로그인 포함)</span>
                            </label>
                            <label class="gtm-fa-checkbox">
                                <input type="checkbox" name="permissions" value="all" class="gtm-fa-permission-checkbox">
                                <span>로그인 사용자</span>
                            </label>
                            <?php foreach ($allowed_roles as $role_key => $role_name) : ?>
                            <label class="gtm-fa-checkbox">
                                <input type="checkbox" name="permissions" value="<?php echo esc_attr($role_key); ?>" class="gtm-fa-permission-checkbox">
                                <span><?php echo esc_html($role_name); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="gtm-fa-form-row">
                        <div class="gtm-fa-form-group">
                            <label>비로그인 사용자 접근 시</label>
                            <select class="gtm-fa-select gtm-fa-guest-action">
                                <option value="allow">접근 허용</option>
                                <option value="redirect_login">로그인 페이지로</option>
                                <option value="show_message">메시지 표시</option>
                            </select>
                        </div>
                        
                        <div class="gtm-fa-form-group">
                            <label>권한 없는 사용자 접근 시</label>
                            <select class="gtm-fa-select gtm-fa-no-permission-action">
                                <option value="show_message">메시지 표시</option>
                                <option value="redirect_home">홈으로 이동</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="gtm-fa-form-group">
                        <label>접근 거부 메시지</label>
                        <textarea class="gtm-fa-textarea gtm-fa-no-permission-message" rows="2">이 페이지에 접근할 권한이 없습니다.</textarea>
                    </div>
                </div>
                
                <div class="gtm-fa-modal-footer">
                    <button class="gtm-fa-btn-secondary gtm-fa-modal-cancel" type="button">취소</button>
                    <button class="gtm-fa-btn-primary gtm-fa-save-menu" type="button">
                        <span class="gtm-fa-icon">💾</span>
                        저장
                    </button>
                </div>
            </div>
        </div>
        
        <?php
    }
}