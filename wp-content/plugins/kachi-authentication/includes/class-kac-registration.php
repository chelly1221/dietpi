<?php
/**
 * Registration handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KAC_Registration {
    
    /**
     * 회원가입 폼 렌더링
     */
    public static function render_form($atts) {
        // 이미 로그인한 경우
        if (is_user_logged_in()) {
            return '<div class="kac-already-logged-in">이미 로그인되어 있습니다. <a href="' . home_url() . '">홈으로 이동</a></div>';
        }
        
        // 프론트엔드 관리자 설정에서 기본값 가져오기
        $default_logo = KAC_Login_Frontend_Admin::get_option('logo_url', KAC_LOGIN_PLUGIN_URL . 'assets/images/kac.webp');
        
        // 속성 파싱
        $atts = shortcode_atts(array(
            'logo' => $default_logo,
            'redirect' => home_url('/?page_id=264'), // 승인 대기 페이지로 리다이렉트
        ), $atts, 'kac_register');
        
        // 관리자가 설정한 조직 데이터 가져오기
        $org_data = get_option('kac_organization_data', array());
        
        // 기본 데이터가 없으면 기존 데이터 사용 (관리자 포함)
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
        } else {
            // 기존 데이터가 있어도 관리자가 없으면 추가
            if (!isset($org_data['관리자'])) {
                $org_data = array_merge(array("관리자" => array("관리자" => array("관리자"))), $org_data);
            }
        }
        
        ob_start();
        ?>
        
        <div class="kac-register-container">
            <form id="kac-register-form" method="post" action="">
                <div class="form-logo" style="text-align:center; margin-bottom: 0px;">
                    <img src="<?php echo esc_url($atts['logo']); ?>" alt="KAC Logo" style="width:180px;">
                </div>
                <input type="hidden" name="kac_register_submit" value="1">
                <?php wp_nonce_field('kac_register_action', 'kac_register_nonce'); ?>
            
            <p>
                <label for="kac_user_login">사번</label><br>
                <input type="number" name="kac_user_login" id="kac_user_login" required pattern="\d+">
            </p>
            <p>
                <label for="kac_user_pass">비밀번호</label><br>
                <span class="password-wrap">
                    <input type="password" name="kac_user_pass" id="kac_user_pass" required placeholder="9자 이상, 영문자, 숫자, 특수문자 포함">
                    <span class="dashicons dashicons-visibility toggle-password" data-target="kac_user_pass" title="비밀번호 보기"></span>
                </span>
            </p>
            <p>
                <label for="kac_user_pass_confirm">비밀번호 확인</label><br>
                <span class="password-wrap">
                    <input type="password" name="kac_user_pass_confirm" id="kac_user_pass_confirm" required>
                    <span class="dashicons dashicons-visibility toggle-password" data-target="kac_user_pass_confirm" title="비밀번호 보기"></span>
                </span>
            </p>
            <div class="form-group">
                <label for="kac_user_sosok">소속</label>
                <select name="kac_user_sosok" id="kac_user_sosok" required>
                    <option value="" disabled selected hidden>소속을 선택하세요</option>
                    <?php foreach (array_keys($org_data) as $sosok): ?>
                        <option value="<?php echo esc_attr($sosok); ?>"><?php echo esc_html($sosok); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="kac_user_buseo">부서</label>
                <select id="kac_user_buseo">
                    <option value="" disabled selected hidden>부서를 선택하세요</option>
                </select>
            </div>
            <div class="form-group">
                <label for="kac_user_site">현장</label>
                <select name="kac_user_site" id="kac_user_site" required>
                    <option value="" disabled selected hidden>현장을 선택하세요</option>
                </select>
            </div>
            <p><button type="submit">회원가입</button></p>
            </form>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            const form = document.getElementById("kac-register-form");
            if (!form) return;
            const submitBtn = form.querySelector("button[type='submit']");
            let isSubmitting = false;

            form.addEventListener("submit", function (e) {
                if (isSubmitting) return;
                const id = document.getElementById("kac_user_login").value.trim();
                const pw = document.getElementById("kac_user_pass").value.trim();
                const sosok = document.getElementById("kac_user_sosok").value.trim();
                const site = document.getElementById("kac_user_site").value.trim();
                if (!id || !pw || !sosok || !site) {
                    alert("필수 항목을 모두 입력해주세요.");
                    e.preventDefault(); return;
                }
                if (pw.length < 9 || !/[A-Za-z]/.test(pw) || !/[0-9]/.test(pw) || !/[!@#$%^&*(),.?":{}|<>_\-+=\\[\]\/]/.test(pw)) {
                    alert("비밀번호는 영문자, 숫자, 특수문자를 포함해 9자 이상이어야 합니다.");
                    e.preventDefault(); return;
                }
                const pwConfirm = document.getElementById("kac_user_pass_confirm").value.trim();
                if (pw !== pwConfirm) {
                    alert("비밀번호가 일치하지 않습니다.");
                    e.preventDefault(); return;
                }
                isSubmitting = true;
                submitBtn.disabled = true;
                submitBtn.textContent = "처리 중...";
            });

            document.querySelectorAll(".toggle-password").forEach(btn => {
                btn.addEventListener("click", function () {
                    const input = document.getElementById(this.dataset.target);
                    if (!input) return;
                    const isHidden = input.type === "password";
                    input.type = isHidden ? "text" : "password";
                    this.classList.remove("dashicons-visibility", "dashicons-hidden");
                    this.classList.add(isHidden ? "dashicons-hidden" : "dashicons-visibility");
                    this.title = isHidden ? "비밀번호 숨기기" : "비밀번호 보기";
                });
            });

            // 관리자가 설정한 조직 데이터
            const buseoOptions = <?php echo json_encode($org_data); ?>;
            
            const siteSelect = document.getElementById("kac_user_site");
            const sosokSelect = document.getElementById("kac_user_sosok");
            const buseoSelect = document.getElementById("kac_user_buseo");

            buseoSelect.addEventListener("change", function () {
                const selectedSosok = sosokSelect.value;
                const selectedBuseo = this.value;
                const sites = (buseoOptions[selectedSosok] || {})[selectedBuseo] || [];
                siteSelect.innerHTML = "";
                
                if (sites.length > 0) {
                    siteSelect.appendChild(new Option("현장을 선택하세요", "", true, true));
                    siteSelect.firstChild.disabled = true;
                    
                    // 관리자가 아닌 경우에만 "전체 현장" 옵션 추가
                    if (!(selectedSosok === '관리자' && selectedBuseo === '관리자')) {
                        const allSitesOption = new Option(`${selectedBuseo} 전체 현장`, `${selectedBuseo}_전체`);
                        allSitesOption.style.fontWeight = 'bold';
                        allSitesOption.style.color = '#0073aa';
                        siteSelect.appendChild(allSitesOption);
                    }
                    
                    sites.forEach(site => {
                        const option = new Option(site, site);
                        if (selectedSosok === '관리자' && selectedBuseo === '관리자' && site === '관리자') {
                            option.text = '관리자 (전체 접근)';
                            option.style.fontWeight = 'bold';
                            option.style.color = '#d63638';
                        }
                        siteSelect.appendChild(option);
                    });
                } else {
                    siteSelect.appendChild(new Option("부서를 먼저 선택해주세요", "", true, true));
                    siteSelect.firstChild.disabled = true;
                }
            });

            sosokSelect.addEventListener("change", function () {
                const selectedSosok = this.value;
                const buseoList = Object.keys(buseoOptions[selectedSosok] || {});
                buseoSelect.innerHTML = "";
                if (buseoList.length > 0) {
                    buseoSelect.appendChild(new Option("부서를 선택하세요", "", true, true));
                    buseoSelect.firstChild.disabled = true;
                    buseoList.forEach(buseo => {
                        const option = new Option(buseo, buseo);
                        if (selectedSosok === '관리자' && buseo === '관리자') {
                            option.style.fontWeight = 'bold';
                            option.style.color = '#d63638';
                        }
                        buseoSelect.appendChild(option);
                    });
                } else {
                    buseoSelect.appendChild(new Option("소속을 먼저 선택해주세요", "", true, true));
                    buseoSelect.firstChild.disabled = true;
                }
                siteSelect.innerHTML = "";
                siteSelect.appendChild(new Option("부서를 먼저 선택하세요", "", true, true));
                siteSelect.firstChild.disabled = true;
            });
        });
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 회원가입 완료 페이지 렌더링 (승인 대기)
     */
    public static function render_complete($atts) {
        // 프론트엔드 관리자 설정에서 기본값 가져오기
        $default_background = KAC_Login_Frontend_Admin::get_option('complete_background_url', '/wp-content/uploads/2025/05/공항.png');
        $default_image = KAC_Login_Frontend_Admin::get_option('complete_image_url', 'http://kac.chelly.kr/wp-content/uploads/2025/04/그림1232-scaled-e1745976308341-1024x681.png');
        
        // 속성 파싱
        $atts = shortcode_atts(array(
            'background' => $default_background,
            'image' => $default_image,
        ), $atts, 'kac_register_complete');
        
        ob_start();
        ?>
        
        <div class="kac-approval-container">
            <!-- 승인 대기 메시지 박스 -->
            <div class="approval-message-box">
                <h2>승인 대기중입니다</h2>
                <div class="contact-box">
                    담당자: 13615 서상현
                </div>
                <a href="/" class="back-button">← 돌아가기</a>
            </div>
            <figure class="wp-block-image size-large">
                <img src="<?php echo esc_url($atts['image']); ?>" alt="승인 대기 이미지" class="wp-image-549"/>
            </figure>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 회원가입 처리
     */
    public static function process_registration() {
        if (isset($_POST['kac_register_submit'])) {
            // Nonce 검증
            if (!isset($_POST['kac_register_nonce']) || !wp_verify_nonce($_POST['kac_register_nonce'], 'kac_register_action')) {
                wp_die('보안 검증 실패');
            }
            
            $id = sanitize_user($_POST['kac_user_login']);
            $pw = $_POST['kac_user_pass'];
            $sosok = sanitize_text_field($_POST['kac_user_sosok']);
            $site = sanitize_text_field($_POST['kac_user_site']);
            
            if (username_exists($id)) {
                wp_die("이미 등록된 사번입니다.");
            }
            
            $dummy_email = $id . '@noemail.local';
            $user_id = wp_create_user($id, $pw, $dummy_email);
            
            if (!is_wp_error($user_id)) {
                wp_update_user(['ID' => $user_id, 'role' => 'pending']);
                
                // 통일된 메타 키 사용 (kachi_sosok, kachi_site)
                update_user_meta($user_id, 'kachi_sosok', $sosok);
                update_user_meta($user_id, 'kachi_site', $site);
            }
            
            // 회원가입 완료 페이지로 리다이렉트
            $redirect_url = isset($_POST['kac_redirect_to']) ? esc_url_raw($_POST['kac_redirect_to']) : home_url('/?page_id=264');
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * 승인 대기 역할 추가
     */
    public static function add_pending_role() {
        add_role('pending', '승인 대기', ['read' => false]);
    }
    
    /**
     * 승인 대기 사용자 로그인 제한
     */
    public static function restrict_pending_login($user, $username, $password) {
        if (is_a($user, 'WP_User') && in_array('pending', $user->roles)) {
            return new WP_Error('approval_required', '관리자 승인 후 로그인 가능합니다.');
        }
        return $user;
    }
    
    /**
     * 구독자 및 승인 대기 사용자 관리자 접근 제한
     */
    public static function restrict_admin_access() {
        if ((current_user_can('subscriber') || current_user_can('pending')) && !defined('DOING_AJAX')) {
            wp_redirect(home_url());
            exit;
        }
    }
}