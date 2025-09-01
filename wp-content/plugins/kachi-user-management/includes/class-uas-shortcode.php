<?php
/**
 * 숏코드 클래스 - 모던 스타일 적용 버전
 */

if (!defined('ABSPATH')) {
    exit;
}

class UAS_Shortcode {
    
    /**
     * 생성자
     */
    public function __construct() {
        add_shortcode('user_approval_system', array($this, 'render_approval_system'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * 프론트엔드 스타일 및 스크립트 등록
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        // 숏코드가 있는 페이지에서만 스타일 로드
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'user_approval_system')) {
            // 모던 CSS 파일 등록 및 로드
            wp_enqueue_style(
                'uas-frontend-style',
                UAS_PLUGIN_URL . 'assets/css/uas-frontend.css',
                array(),
                UAS_PLUGIN_VERSION
            );
            
            // 배경 이미지 인라인 스타일 추가
            $this->add_background_styles();
        }
    }
    
    /**
     * 배경 이미지 스타일 추가
     */
    private function add_background_styles() {
        $background_image = get_option('uas_background_image', '');
        $background_position = get_option('uas_background_position', 'bottom center');
        $background_size = get_option('uas_background_size', 'cover');
        
        if ($background_image) {
            $custom_css = sprintf(
                'body {
                    background: url("%s") no-repeat %s fixed;
                    background-size: %s;
                }',
                esc_url($background_image),
                esc_attr($background_position),
                esc_attr($background_size)
            );
            wp_add_inline_style('uas-frontend-style', $custom_css);
        }
    }
    
    /**
     * 역할 라벨 한글화
     */
    private function get_role_label($role) {
        $labels = array(
            'administrator' => '개발자',
            'approver' => '관리자',
            'subscriber' => '사용자',
            'pending' => '승인대기'
        );
        
        return isset($labels[$role]) ? $labels[$role] : $role;
    }
    
    /**
     * 역할 우선순위 (높을수록 상위)
     */
    private function get_role_priority($role) {
        $priorities = array(
            'administrator' => 100,
            'approver' => 50,
            'subscriber' => 10,
            'pending' => 1
        );
        
        return isset($priorities[$role]) ? $priorities[$role] : 0;
    }
    
    /**
     * 사용자가 다른 사용자의 역할을 변경할 수 있는지 확인
     */
    private function can_change_user_role($target_user_id) {
        $current_user = wp_get_current_user();
        $target_user = get_user_by('ID', $target_user_id);
        
        if (!$target_user) return false;
        
        // 관리자(approver)는 본인 역할도 변경 가능
        if (in_array('approver', $current_user->roles)) {
            // 관리자는 개발자보다 낮은 모든 역할 변경 가능
            $target_user_role = $this->get_highest_role($target_user);
            if ($target_user_role === 'administrator') {
                return false; // 개발자는 변경 불가
            }
            return true;
        }
        
        // 개발자는 모든 사용자 역할 변경 가능
        if (current_user_can('manage_options')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 사용자의 정보를 편집할 수 있는지 확인
     */
    private function can_edit_user_info($target_user_id) {
        $current_user = wp_get_current_user();
        $target_user = get_user_by('ID', $target_user_id);
        
        if (!$target_user) return false;
        
        // 관리자(approver)는 모든 사용자 정보 편집 가능 (개발자 제외)
        if (in_array('approver', $current_user->roles)) {
            $target_user_role = $this->get_highest_role($target_user);
            if ($target_user_role === 'administrator') {
                return false; // 개발자 정보는 편집 불가
            }
            return true;
        }
        
        // 개발자는 모든 사용자 정보 편집 가능
        if (current_user_can('manage_options')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 사용자의 최고 역할 가져오기
     */
    private function get_highest_role($user) {
        $highest_role = '';
        $highest_priority = -1;
        
        foreach ($user->roles as $role) {
            $priority = $this->get_role_priority($role);
            if ($priority > $highest_priority) {
                $highest_priority = $priority;
                $highest_role = $role;
            }
        }
        
        return $highest_role;
    }
    
    /**
     * 조직 데이터 가져오기
     */
    private function get_organization_data() {
        $org_data = get_option('kac_organization_data', array());
        
        // 기본 데이터가 없으면 기존 데이터 사용
        if (empty($org_data)) {
            $org_data = array(
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
        
        return $org_data;
    }
    
    /**
     * 승인 시스템 렌더링
     */
    public function render_approval_system($atts) {
        $pending_users = get_users(array('role' => 'pending'));
        $all_users = get_users(array('role__not_in' => array('pending')));
        $org_data = $this->get_organization_data();
        
        ob_start();
        ?>
        <div class="uas-approval-wrapper">
            <h2>사용자 관리</h2>
            
            <?php if (!is_user_logged_in()): ?>
                <div class="uas-notice-box">
                    <p>승인 기능을 사용하려면 로그인이 필요합니다.</p>
                    <a href="<?php echo wp_login_url(get_permalink()); ?>">로그인하기</a>
                </div>
            <?php elseif (!current_user_can('manage_options') && !in_array('approver', wp_get_current_user()->roles)): ?>
                <div class="uas-notice-box uas-warning-box">
                    <p>⚠️ 승인 권한이 없습니다.</p>
                    <p>관리자 또는 승인자 권한이 필요합니다.</p>
                </div>
            <?php else: ?>
                <!-- 탭 메뉴 -->
                <div class="uas-tabs">
                    <button class="uas-tab active" data-tab="pending">
                        승인 대기 <span><?php echo count($pending_users); ?></span>
                    </button>
                    <button class="uas-tab" data-tab="users">
                        사용자 관리
                    </button>
                </div>
                
                <!-- 승인 대기 탭 -->
                <div class="uas-tab-content active" id="pending-tab">
                    <div class="filter-header">
                        <h4>📋 승인 대기 사용자</h4>
                        <input type="text" class="userSearch" placeholder="사번/소속/현장 필터" />
                    </div>
                    
                    <?php if (empty($pending_users)): ?>
                        <div class="uas-empty-state">
                            <p>현재 승인 대기 중인 사용자가 없습니다.</p>
                        </div>
                    <?php else: ?>
                        <ul class="uas-user-list">
                            <?php foreach ($pending_users as $user): 
                                $user_sosok = get_user_meta($user->ID, 'kachi_sosok', true);
                                $user_site = get_user_meta($user->ID, 'kachi_site', true);
                            ?>
                            <li class="user-item"
                                data-user-id="<?php echo esc_attr($user->ID); ?>"
                                data-user-login="<?php echo esc_attr($user->user_login); ?>"
                                data-user-sosok="<?php echo esc_attr($user_sosok ?: ''); ?>"
                                data-user-site="<?php echo esc_attr($user_site ?: ''); ?>">
                                <div>
                                    <div class="user-info-container">
                                        <div class="user-name">
                                            <?php echo esc_html($user->user_login); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="detail-item">
                                                <span class="detail-label">소속:</span>
                                                <strong><?php echo esc_html($user_sosok ?: '—'); ?></strong>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">현장:</span>
                                                <strong><?php echo esc_html($user_site ?: '—'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="action-buttons">
                                        <button class="approve-button" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                            ✓ 승인
                                        </button>
                                        <button class="reject-button" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                            ✗ 반려
                                        </button>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <!-- 사용자 관리 탭 -->
                <div class="uas-tab-content" id="users-tab">
                    <div class="filter-header">
                        <h4>👥 전체 사용자</h4>
                        <input type="text" class="userSearch" placeholder="사번/소속/현장 필터" />
                    </div>
                    
                    <?php if (empty($all_users)): ?>
                        <div class="uas-empty-state">
                            <p>등록된 사용자가 없습니다.</p>
                        </div>
                    <?php else: ?>
                        <ul class="uas-user-list">
                            <?php foreach ($all_users as $user): 
                                $user_sosok = get_user_meta($user->ID, 'kachi_sosok', true);
                                $user_site = get_user_meta($user->ID, 'kachi_site', true);
                                $user_role = $this->get_highest_role($user);
                                $role_label = $this->get_role_label($user_role);
                                $can_change = $this->can_change_user_role($user->ID);
                                $can_edit_info = $this->can_edit_user_info($user->ID);
                            ?>
                            <li class="user-item"
                                data-user-id="<?php echo esc_attr($user->ID); ?>"
                                data-user-login="<?php echo esc_attr($user->user_login); ?>"
                                data-user-sosok="<?php echo esc_attr($user_sosok ?: ''); ?>"
                                data-user-site="<?php echo esc_attr($user_site ?: ''); ?>">
                                <div>
                                    <div class="user-info-container">
                                        <div class="user-name">
                                            <?php echo esc_html($user->user_login); ?>
                                            <span class="role-badge <?php echo esc_attr($user_role); ?>">
                                                <?php echo esc_html($role_label); ?>
                                            </span>
                                        </div>
                                        <div class="user-info-display user-details">
                                            <div class="detail-item">
                                                <span class="detail-label">소속:</span>
                                                <strong><?php echo esc_html($user_sosok ?: '—'); ?></strong>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">현장:</span>
                                                <strong><?php echo esc_html($user_site ?: '—'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($can_change || $can_edit_info): ?>
                                    <div class="action-buttons">
                                        <?php if ($can_change): ?>
                                        <select class="role-selector" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                            <?php 
                                            $current_user_role = $this->get_highest_role(wp_get_current_user());
                                            $available_roles = array('subscriber', 'approver', 'administrator');
                                            
                                            foreach ($available_roles as $role):
                                                // 관리자는 administrator 제외, 개발자는 모든 역할 가능
                                                if ($current_user_role === 'approver' && $role === 'administrator') {
                                                    continue;
                                                }
                                            ?>
                                            <option value="<?php echo esc_attr($role); ?>" <?php selected($user_role, $role); ?>>
                                                <?php echo esc_html($this->get_role_label($role)); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                        <?php if ($can_edit_info): ?>
                                        <button class="edit-button" 
                                                data-user-id="<?php echo esc_attr($user->ID); ?>"
                                                data-user-login="<?php echo esc_attr($user->user_login); ?>"
                                                data-user-role="<?php echo esc_attr($user_role); ?>"
                                                data-user-sosok="<?php echo esc_attr($user_sosok ?: ''); ?>"
                                                data-user-site="<?php echo esc_attr($user_site ?: ''); ?>">
                                            ✏️ 편집
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($can_change): ?>
                                        <button class="delete-button" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                            🗑 삭제
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <div style="color: #666; font-size: 14px;">
                                        권한 없음
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <!-- 편집 모달 -->
                <div class="uas-modal-overlay" id="edit-modal">
                    <div class="uas-modal">
                        <div class="uas-modal-header">
                            <h3>✏️ 사용자 정보 편집</h3>
                            <button class="uas-modal-close" type="button">✕</button>
                        </div>
                        <div class="uas-modal-body">
                            <form id="edit-user-form">
                                <input type="hidden" id="modal-user-id" value="">
                                
                                <div class="uas-modal-form-group">
                                    <label>사번</label>
                                    <div class="form-info">
                                        <span id="modal-user-login"></span>
                                        <span id="modal-user-role" class="role-badge"></span>
                                    </div>
                                </div>
                                
                                <div class="uas-modal-form-group">
                                    <label>소속</label>
                                    <div class="modal-select-wrapper">
                                        <input type="text" class="custom-select-search modal-sosok-search" placeholder="소속 검색...">
                                        <input type="hidden" id="modal-sosok" value="">
                                        <div class="custom-select-dropdown">
                                            <?php foreach (array_keys($org_data) as $sosok): ?>
                                                <div class="custom-select-option <?php echo ($sosok === '관리자') ? 'admin-option' : ''; ?>" data-value="<?php echo esc_attr($sosok); ?>">
                                                    <?php echo esc_html($sosok); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="uas-modal-form-group">
                                    <label>부서</label>
                                    <div class="modal-select-wrapper">
                                        <input type="text" class="custom-select-search modal-buseo-search" placeholder="소속 선택 필요" disabled>
                                        <input type="hidden" id="modal-buseo" value="">
                                        <div class="custom-select-dropdown"></div>
                                    </div>
                                </div>
                                
                                <div class="uas-modal-form-group">
                                    <label>현장</label>
                                    <?php if (current_user_can('manage_options')): ?>
                                    <div class="modal-select-wrapper">
                                        <input type="text" class="custom-select-search modal-site-search" placeholder="부서 선택 필요" disabled>
                                        <input type="hidden" id="modal-site" value="">
                                        <div class="custom-select-dropdown"></div>
                                    </div>
                                    <?php else: ?>
                                    <div class="form-info">
                                        <span id="modal-site-display">—</span>
                                        <input type="hidden" id="modal-site" value="">
                                        <small style="color: #999; margin-left: 1rem;">※ 현장 변경은 개발자만 가능합니다</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="uas-modal-actions">
                                    <button type="button" class="save-button" id="modal-save-button">
                                        ✓ 저장
                                    </button>
                                    <button type="button" class="cancel-button" id="modal-cancel-button">
                                        ✗ 취소
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <script>
                // 조직 데이터
                const organizationData = <?php echo json_encode($org_data); ?>;
                
                document.addEventListener("DOMContentLoaded", function () {
                    // URL 파라미터에서 탭 정보 가져오기
                    const urlParams = new URLSearchParams(window.location.search);
                    const activeTab = urlParams.get('tab');
                    
                    if (activeTab === 'users') {
                        // 사용자 관리 탭 활성화
                        document.querySelectorAll(".uas-tab").forEach(t => t.classList.remove("active"));
                        document.querySelectorAll(".uas-tab-content").forEach(c => c.classList.remove("active"));
                        
                        document.querySelector('[data-tab="users"]').classList.add("active");
                        document.getElementById("users-tab").classList.add("active");
                    }
                    
                    // 탭 전환
                    document.querySelectorAll(".uas-tab").forEach(tab => {
                        tab.addEventListener("click", function() {
                            // 모든 탭 비활성화
                            document.querySelectorAll(".uas-tab").forEach(t => t.classList.remove("active"));
                            document.querySelectorAll(".uas-tab-content").forEach(c => c.classList.remove("active"));
                            
                            // 선택한 탭 활성화
                            this.classList.add("active");
                            const tabId = this.dataset.tab + "-tab";
                            document.getElementById(tabId).classList.add("active");
                        });
                    });
                    
                    // 승인 버튼
                    document.querySelectorAll(".approve-button").forEach(button => {
                        button.addEventListener("click", function () {
                            const userId = this.dataset.userId;
                            if (!confirm("이 사용자를 승인하시겠습니까?")) return;
                            
                            fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                                method: "POST",
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: "approve_user_role",
                                    user_id: userId,
                                    nonce: "<?php echo wp_create_nonce('approve_user_nonce'); ?>"
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    alert("✅ 승인되었습니다!");
                                    location.reload();
                                } else {
                                    alert("❌ 오류: " + data.data);
                                }
                            });
                        });
                    });
                    
                    // 반려 버튼
                    document.querySelectorAll(".reject-button").forEach(button => {
                        button.addEventListener("click", function () {
                            const userId = this.dataset.userId;
                            const reason = prompt("반려 사유를 입력하세요:");
                            if (!reason) return;
                            
                            if (!confirm("정말 이 사용자를 반려하시겠습니까?")) return;
                            
                            fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                                method: "POST",
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: "reject_user_role",
                                    user_id: userId,
                                    reason: reason,
                                    nonce: "<?php echo wp_create_nonce('reject_user_nonce'); ?>"
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    alert("✅ 반려되었습니다!");
                                    location.reload();
                                } else {
                                    alert("❌ 오류: " + data.data);
                                }
                            });
                        });
                    });
                    
                    // 역할 변경
                    document.querySelectorAll(".role-selector").forEach(select => {
                        select.addEventListener("change", function () {
                            const userId = this.dataset.userId;
                            const newRole = this.value;
                            const currentRole = this.options[this.selectedIndex].defaultSelected ? this.value : null;
                            
                            if (!newRole || newRole === currentRole) return;
                            
                            if (!confirm("정말 이 사용자의 역할을 변경하시겠습니까?")) {
                                // 원래 값으로 되돌리기
                                for (let option of this.options) {
                                    if (option.defaultSelected) {
                                        this.value = option.value;
                                        break;
                                    }
                                }
                                return;
                            }
                            
                            // 현재 페이지 URL에 tab 파라미터 추가
                            const currentUrl = new URL(window.location.href);
                            currentUrl.searchParams.set('tab', 'users');
                            
                            fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                                method: "POST",
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: "change_user_role",
                                    user_id: userId,
                                    new_role: newRole,
                                    nonce: "<?php echo wp_create_nonce('change_role_nonce'); ?>"
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    alert("✅ 역할이 변경되었습니다!");
                                    window.location.href = currentUrl.href;
                                } else {
                                    alert("❌ 오류: " + data.data);
                                    // 원래 값으로 되돌리기
                                    for (let option of this.options) {
                                        if (option.defaultSelected) {
                                            this.value = option.value;
                                            break;
                                        }
                                    }
                                }
                            });
                        });
                    });
                    
                    // 삭제 버튼
                    document.querySelectorAll(".delete-button").forEach(button => {
                        button.addEventListener("click", function () {
                            const userId = this.dataset.userId;
                            if (!confirm("⚠️ 정말 이 사용자를 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.")) return;
                            
                            // 현재 페이지 URL에 tab 파라미터 추가
                            const currentUrl = new URL(window.location.href);
                            currentUrl.searchParams.set('tab', 'users');
                            
                            fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                                method: "POST",
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: "delete_user_account",
                                    user_id: userId,
                                    nonce: "<?php echo wp_create_nonce('delete_user_nonce'); ?>"
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    alert("✅ 삭제되었습니다!");
                                    window.location.href = currentUrl.href;
                                } else {
                                    alert("❌ 오류: " + data.data);
                                }
                            });
                        });
                    });
                    
                    // 모달 요소들
                    const modal = document.getElementById('edit-modal');
                    const modalCloseBtn = modal.querySelector('.uas-modal-close');
                    const modalCancelBtn = document.getElementById('modal-cancel-button');
                    const modalSaveBtn = document.getElementById('modal-save-button');
                    
                    // 모달 닫기 함수
                    function closeModal() {
                        modal.classList.remove('active');
                        // 모든 드롭다운 닫기
                        document.querySelectorAll('.modal-select-wrapper .custom-select-dropdown').forEach(dropdown => {
                            dropdown.classList.remove('show');
                        });
                        // dropdown-open 클래스 제거
                        const modalBody = modal.querySelector('.uas-modal-body');
                        modalBody.classList.remove('dropdown-open');
                    }
                    
                    // 모달 닫기 이벤트
                    modalCloseBtn.addEventListener('click', closeModal);
                    modalCancelBtn.addEventListener('click', closeModal);
                    
                    // 모달 외부 클릭시 닫기
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeModal();
                        }
                    });
                    
                    // 편집 버튼 - 모달 열기
                    document.querySelectorAll(".edit-button").forEach(button => {
                        button.addEventListener("click", function () {
                            const userId = this.dataset.userId;
                            const userLogin = this.dataset.userLogin;
                            const userRole = this.dataset.userRole;
                            const userSosok = this.dataset.userSosok;
                            const userSite = this.dataset.userSite;
                            
                            // 모달에 데이터 설정
                            document.getElementById('modal-user-id').value = userId;
                            document.getElementById('modal-user-login').textContent = userLogin;
                            document.getElementById('modal-user-role').textContent = 
                                userRole === 'administrator' ? '개발자' :
                                userRole === 'approver' ? '관리자' :
                                userRole === 'subscriber' ? '사용자' : '승인대기';
                            document.getElementById('modal-user-role').className = 'role-badge ' + userRole;
                            
                            // 소속 설정
                            document.getElementById('modal-sosok').value = userSosok;
                            document.querySelector('.modal-sosok-search').value = userSosok;
                            
                            // 현장 설정
                            document.getElementById('modal-site').value = userSite;
                            <?php if (current_user_can('manage_options')): ?>
                            document.querySelector('.modal-site-search').value = userSite;
                            <?php else: ?>
                            document.getElementById('modal-site-display').textContent = userSite || '—';
                            <?php endif; ?>
                            
                            // 모달 열기
                            modal.classList.add('active');
                            
                            // 커스텀 드롭다운 초기화
                            initializeModalSelects(userSosok, userSite);
                        });
                    });
                    
                    // 모달용 커스텀 셀렉트 초기화
                    function initializeModalSelects(currentSosok, currentSite) {
                        const sosokSearch = document.querySelector('.modal-sosok-search');
                        const buseoSearch = document.querySelector('.modal-buseo-search');
                        const siteSearch = document.querySelector('.modal-site-search');
                        
                        // 현재 소속에 따라 부서 로드
                        if (currentSosok) {
                            loadModalBuseoOptions(currentSosok, currentSite);
                        }
                        
                        // 드롭다운 토글
                        document.querySelectorAll('.uas-modal .custom-select-search').forEach(input => {
                            // 기존 이벤트 리스너 제거
                            const newInput = input.cloneNode(true);
                            input.parentNode.replaceChild(newInput, input);
                            
                            newInput.addEventListener('focus', function() {
                                if (!this.disabled) {
                                    const dropdown = this.nextElementSibling.nextElementSibling;
                                    const modalBody = this.closest('.uas-modal-body');
                                    
                                    // 모달 바디의 오버플로우를 일시적으로 visible로 변경
                                    modalBody.classList.add('dropdown-open');
                                    dropdown.classList.add('show');
                                    
                                    // 드롭다운이 모달 하단을 벗어나는지 확인
                                    setTimeout(() => {
                                        const dropdownRect = dropdown.getBoundingClientRect();
                                        const modalRect = modalBody.getBoundingClientRect();
                                        
                                        if (dropdownRect.bottom > modalRect.bottom) {
                                            // 드롭다운을 위로 표시
                                            dropdown.style.top = 'auto';
                                            dropdown.style.bottom = '100%';
                                            dropdown.style.marginBottom = '2px';
                                        }
                                    }, 10);
                                }
                            });
                            
                            newInput.addEventListener('blur', function(e) {
                                const modalBody = this.closest('.uas-modal-body');
                                setTimeout(() => {
                                    this.nextElementSibling.nextElementSibling.classList.remove('show');
                                    modalBody.classList.remove('dropdown-open');
                                    // 위치 초기화
                                    const dropdown = this.nextElementSibling.nextElementSibling;
                                    dropdown.style.top = 'calc(100% + 2px)';
                                    dropdown.style.bottom = 'auto';
                                    dropdown.style.marginBottom = '0';
                                }, 200);
                            });
                            
                            // 검색 기능
                            newInput.addEventListener('input', function() {
                                const searchTerm = this.value.toLowerCase();
                                const dropdown = this.nextElementSibling.nextElementSibling;
                                const options = dropdown.querySelectorAll('.custom-select-option');
                                let hasResults = false;
                                
                                options.forEach(option => {
                                    const optionText = option.textContent.trim();
                                    if (optionText.toLowerCase().includes(searchTerm)) {
                                        option.style.display = 'block';
                                        hasResults = true;
                                    } else {
                                        option.style.display = 'none';
                                    }
                                });
                                
                                // 검색 결과가 없을 때
                                const noResultsMsg = dropdown.querySelector('.no-results');
                                if (!hasResults) {
                                    if (!noResultsMsg) {
                                        const div = document.createElement('div');
                                        div.className = 'custom-select-option no-results';
                                        div.textContent = '검색 결과가 없습니다';
                                        dropdown.appendChild(div);
                                    }
                                } else if (noResultsMsg) {
                                    noResultsMsg.remove();
                                }
                            });
                        });
                        
                        // 소속 옵션 클릭 이벤트
                        document.querySelectorAll('.modal-sosok-search').forEach(input => {
                            const dropdown = input.nextElementSibling.nextElementSibling;
                            
                            // 현재 선택된 소속에 selected 클래스 추가
                            dropdown.querySelectorAll('.custom-select-option').forEach(option => {
                                if (option.dataset.value === currentSosok) {
                                    option.classList.add('selected');
                                }
                            });
                            
                            dropdown.querySelectorAll('.custom-select-option').forEach(option => {
                                option.addEventListener('click', function() {
                                    const value = this.dataset.value;
                                    input.value = this.textContent.trim();
                                    input.nextElementSibling.value = value;
                                    
                                    // 선택 표시
                                    dropdown.querySelectorAll('.custom-select-option').forEach(opt => {
                                        opt.classList.remove('selected');
                                    });
                                    this.classList.add('selected');
                                    
                                    // 드롭다운 닫기
                                    dropdown.classList.remove('show');
                                    
                                    // 부서 옵션 로드
                                    loadModalBuseoOptions(value);
                                });
                            });
                        });
                    }
                    
                    // 모달용 부서 옵션 로드
                    function loadModalBuseoOptions(sosok, currentSite) {
                        const buseoWrapper = document.querySelector('.modal-buseo-search').closest('.modal-select-wrapper');
                        const buseoSearch = buseoWrapper.querySelector('.modal-buseo-search');
                        const buseoHidden = buseoWrapper.querySelector('#modal-buseo');
                        const buseoDropdown = buseoWrapper.querySelector('.custom-select-dropdown');
                        const siteWrapper = document.querySelector('.modal-site-search')?.closest('.modal-select-wrapper');
                        
                        // 부서 초기화
                        buseoDropdown.innerHTML = '';
                        buseoSearch.value = '';
                        buseoHidden.value = '';
                        buseoSearch.placeholder = '부서 검색...';
                        
                        if (sosok && organizationData[sosok]) {
                            buseoSearch.disabled = false;
                            const buseoList = Object.keys(organizationData[sosok]);
                            
                            buseoList.forEach(buseo => {
                                const option = document.createElement('div');
                                option.className = 'custom-select-option';
                                if (sosok === '관리자' && buseo === '관리자') {
                                    option.className += ' admin-option';
                                }
                                option.dataset.value = buseo;
                                option.textContent = buseo;
                                buseoDropdown.appendChild(option);
                            });
                            
                            // 현재 현장으로 부서 추측
                            if (currentSite) {
                                // "부서명_전체" 형식인 경우 부서 추출
                                if (currentSite.endsWith('_전체')) {
                                    const buseoFromSite = currentSite.replace('_전체', '');
                                    if (buseoList.includes(buseoFromSite)) {
                                        buseoSearch.value = buseoFromSite;
                                        buseoHidden.value = buseoFromSite;
                                        
                                        // 부서가 설정되면 바로 현장 옵션도 로드
                                        setTimeout(() => {
                                            loadModalSiteOptions(sosok, buseoFromSite, currentSite);
                                        }, 100);
                                    }
                                } else {
                                    // 일반 현장에서 부서 찾기
                                    for (const buseo in organizationData[sosok]) {
                                        if (organizationData[sosok][buseo].includes(currentSite)) {
                                            buseoSearch.value = buseo;
                                            buseoHidden.value = buseo;
                                            
                                            // 부서가 설정되면 바로 현장 옵션도 로드
                                            setTimeout(() => {
                                                loadModalSiteOptions(sosok, buseo, currentSite);
                                            }, 100);
                                            break;
                                        }
                                    }
                                }
                            } else if (buseoHidden.value) {
                                // currentSite가 없지만 부서가 이미 설정되어 있는 경우
                                setTimeout(() => {
                                    loadModalSiteOptions(sosok, buseoHidden.value);
                                }, 100);
                            }
                            
                            // 이벤트 리스너 등록
                            buseoDropdown.querySelectorAll('.custom-select-option').forEach(option => {
                                // 현재 선택된 부서에 selected 클래스 추가
                                if (option.dataset.value === buseoHidden.value) {
                                    option.classList.add('selected');
                                }
                                
                                option.addEventListener('click', function() {
                                    buseoSearch.value = this.textContent.trim();
                                    buseoHidden.value = this.dataset.value;
                                    buseoDropdown.classList.remove('show');
                                    
                                    // 부서 선택시 현장 업데이트
                                    loadModalSiteOptions(sosok, this.dataset.value);
                                });
                            });
                        } else {
                            buseoSearch.disabled = true;
                            buseoSearch.placeholder = '먼저 소속을 선택하세요';
                            
                            // 현장도 초기화
                            if (siteWrapper) {
                                const siteSearch = siteWrapper.querySelector('.modal-site-search');
                                const siteHidden = siteWrapper.querySelector('#modal-site');
                                const siteDropdown = siteWrapper.querySelector('.custom-select-dropdown');
                                siteDropdown.innerHTML = '';
                                siteSearch.value = '';
                                siteHidden.value = '';
                                siteSearch.disabled = true;
                                siteSearch.placeholder = '먼저 부서를 선택하세요';
                            }
                        }
                    }
                    
                    // 모달용 현장 옵션 로드
                    function loadModalSiteOptions(sosok, buseo, currentSite) {
                        const siteWrapper = document.querySelector('.modal-site-search')?.closest('.modal-select-wrapper');
                        if (!siteWrapper) return; // 개발자가 아닌 경우
                        
                        const siteSearch = siteWrapper.querySelector('.modal-site-search');
                        const siteHidden = siteWrapper.querySelector('#modal-site');
                        const siteDropdown = siteWrapper.querySelector('.custom-select-dropdown');
                        
                        // 현장 초기화
                        siteDropdown.innerHTML = '';
                        siteSearch.value = '';
                        siteHidden.value = '';
                        siteSearch.placeholder = '현장 검색...';
                        
                        if (sosok && buseo && organizationData[sosok] && organizationData[sosok][buseo]) {
                            siteSearch.disabled = false;
                            const siteList = organizationData[sosok][buseo];
                            
                            // 관리자가 아닌 경우에만 "전체 현장" 옵션 추가
                            if (!(sosok === '관리자' && buseo === '관리자')) {
                                const allOption = document.createElement('div');
                                allOption.className = 'custom-select-option all-sites';
                                allOption.dataset.value = buseo + '_전체';
                                allOption.textContent = buseo + ' 전체 현장';
                                siteDropdown.appendChild(allOption);
                                
                                // 이벤트 리스너 추가
                                allOption.addEventListener('click', function() {
                                    siteSearch.value = this.textContent.trim();
                                    siteHidden.value = this.dataset.value;
                                    siteDropdown.classList.remove('show');
                                });
                            }
                            
                            // 개별 현장 옵션 추가
                            siteList.forEach(site => {
                                const option = document.createElement('div');
                                option.className = 'custom-select-option';
                                if (sosok === '관리자' && buseo === '관리자' && site === '관리자') {
                                    option.className += ' admin-option';
                                    option.textContent = '관리자 (전체 접근)';
                                } else {
                                    option.textContent = site;
                                }
                                option.dataset.value = site;
                                siteDropdown.appendChild(option);
                                
                                // 이벤트 리스너 추가
                                option.addEventListener('click', function() {
                                    siteSearch.value = (sosok === '관리자' && buseo === '관리자' && site === '관리자') ? '관리자 (전체 접근)' : this.dataset.value;
                                    siteHidden.value = this.dataset.value;
                                    siteDropdown.classList.remove('show');
                                });
                            });
                            
                            // 현재 값 설정
                            if (currentSite) {
                                if (currentSite === buseo + '_전체') {
                                    siteSearch.value = buseo + ' 전체 현장';
                                    siteHidden.value = currentSite;
                                } else if (currentSite === '관리자' && sosok === '관리자' && buseo === '관리자') {
                                    siteSearch.value = '관리자 (전체 접근)';
                                    siteHidden.value = currentSite;
                                } else if (siteList.includes(currentSite)) {
                                    siteSearch.value = currentSite;
                                    siteHidden.value = currentSite;
                                }
                            }
                        } else {
                            siteSearch.disabled = true;
                            siteSearch.placeholder = '먼저 부서를 선택하세요';
                        }
                    }
                    
                    // 저장 버튼
                    modalSaveBtn.addEventListener('click', function() {
                        const userId = document.getElementById('modal-user-id').value;
                        const newSosok = document.getElementById('modal-sosok').value;
                        const newSite = document.getElementById('modal-site').value;
                        
                        // 현재 페이지 URL에 tab 파라미터 추가
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('tab', 'users');
                        
                        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                            method: "POST",
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: "update_user_info",
                                user_id: userId,
                                sosok: newSosok,
                                site: newSite,
                                nonce: "<?php echo wp_create_nonce('update_user_info_nonce'); ?>"
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                alert("✅ 정보가 수정되었습니다!");
                                window.location.href = currentUrl.href;
                            } else {
                                alert("❌ 오류: " + data.data);
                            }
                        });
                    });
                    
                    // 필터 기능 (각 탭별로)
                    document.querySelectorAll(".userSearch").forEach(input => {
                        input.addEventListener("input", function () {
                            const query = this.value.trim().toLowerCase();
                            const tabContent = this.closest(".uas-tab-content");
                            
                            tabContent.querySelectorAll("li[data-user-id]").forEach(li => {
                                const login = li.dataset.userLogin?.toLowerCase() || "";
                                const sosok = li.dataset.userSosok?.toLowerCase() || "";
                                const site = li.dataset.userSite?.toLowerCase() || "";
                                
                                const isMatch = (
                                    login.includes(query) ||
                                    sosok.includes(query) ||
                                    site.includes(query)
                                );
                                
                                li.style.display = (!query || isMatch) ? "block" : "none";
                            });
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}