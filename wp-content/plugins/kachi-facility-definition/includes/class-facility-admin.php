<?php
/**
 * 관리자 메뉴 처리 클래스
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facility_Admin {
    
    public function init() {
        // 관리자 메뉴 추가
        add_action('admin_menu', array($this, 'add_admin_menu'), 999);
        // 데이터 삭제 액션 추가
        add_action('wp_ajax_clear_all_facility_data', array($this, 'clear_all_facility_data'));
        // 배경 설정 저장 액션 추가
        add_action('wp_ajax_save_facility_background', array($this, 'save_facility_background'));
        // 관리자 스크립트 등록
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * 관리자 메뉴 추가
     */
    public function add_admin_menu() {
        add_menu_page(
            '시설 정의 관리',                    // 페이지 제목
            '시설 정의',                        // 메뉴 제목
            'manage_options',                   // 권한
            'facility-definition',              // 메뉴 슬러그
            array($this, 'render_admin_page'),  // 콜백 함수
            'dashicons-building',               // 아이콘
            999                                 // 위치 (제일 아래)
        );
    }
    
    /**
     * 관리자 스크립트 등록
     */
    public function enqueue_admin_scripts($hook) {
        // 플러그인 관리 페이지에서만 로드
        if ($hook !== 'toplevel_page_facility-definition') {
            return;
        }
        
        // 미디어 업로더 스크립트
        wp_enqueue_media();
        
        // 관리자 CSS
        wp_enqueue_style(
            'facility-admin-style',
            FACILITY_DEFINITION_PLUGIN_URL . 'assets/css/facility-admin.css',
            array(),
            FACILITY_DEFINITION_VERSION
        );
    }
    
    /**
     * 모든 데이터 삭제
     */
    public function clear_all_facility_data() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'clear_facility_data')) {
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
     * 배경 설정 저장
     */
    public function save_facility_background() {
        // nonce 검증
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_facility_background')) {
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
    
    /**
     * 관리자 페이지 렌더링
     */
    public function render_admin_page() {
        // 현재 배경 설정 가져오기
        $background_image = get_option('facility_background_image', '');
        $background_position = get_option('facility_background_position', 'bottom center');
        $background_size = get_option('facility_background_size', 'cover');
        ?>
        <div class="wrap facility-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p><strong>사용 방법:</strong></p>
                <p>1. 페이지나 포스트에서 <code>[facility_definition]</code> 쇼트코드를 입력하세요.</p>
                <p>2. 모든 사용자가 시설 정의를 관리할 수 있습니다.</p>
                <p>3. 로그인한 사용자는 프로필의 소속과 현장 정보를 기준으로 데이터가 분리됩니다.</p>
                <p>4. 로그인하지 않은 사용자는 기본(default) 그룹으로 관리됩니다.</p>
            </div>
            
            <div class="card">
                <h2>플러그인 정보</h2>
                <p><strong>버전:</strong> <?php echo FACILITY_DEFINITION_VERSION; ?></p>
                <p><strong>제작자:</strong> <a href="https://3chan.kr" target="_blank">3chan</a></p>
                <p><strong>플러그인 경로:</strong> <?php echo FACILITY_DEFINITION_PLUGIN_DIR; ?></p>
                <p><strong>ACF 의존성:</strong> <span style="color: green;">✓ 제거됨</span></p>
                <p><strong>접근 제어:</strong> <span style="color: green;">✓ 제거됨 (모든 사용자 접근 가능)</span></p>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>배경 이미지 설정</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">배경 이미지</th>
                        <td>
                            <div class="background-preview" id="background-preview">
                                <?php if ($background_image): ?>
                                    <img src="<?php echo esc_url($background_image); ?>" alt="배경 이미지" />
                                <?php else: ?>
                                    <p>배경 이미지가 설정되지 않았습니다.</p>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="facility_background_image" value="<?php echo esc_attr($background_image); ?>" />
                            <button type="button" class="button button-primary" id="upload_background_button">이미지 선택</button>
                            <button type="button" class="button" id="remove_background_button" <?php echo $background_image ? '' : 'style="display:none;"'; ?>>이미지 제거</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">배경 위치</th>
                        <td>
                            <select id="facility_background_position">
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
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">배경 크기</th>
                        <td>
                            <select id="facility_background_size">
                                <option value="auto" <?php selected($background_size, 'auto'); ?>>원본 크기</option>
                                <option value="cover" <?php selected($background_size, 'cover'); ?>>화면에 맞춤 (Cover)</option>
                                <option value="contain" <?php selected($background_size, 'contain'); ?>>화면에 맞춤 (Contain)</option>
                                <option value="100% 100%" <?php selected($background_size, '100% 100%'); ?>>화면 전체</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" class="button button-primary" id="save_background_settings">배경 설정 저장</button>
                </p>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>현재 저장된 시설 정의</h2>
                <?php $this->display_all_definitions(); ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>데이터 관리</h2>
                <p>모든 시설 정의 데이터를 삭제하시겠습니까?</p>
                <button class="button button-secondary" onclick="if(confirm('정말로 모든 데이터를 삭제하시겠습니까?')) { clearAllFacilityData(); }">모든 데이터 삭제</button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            // 이미지 업로드 버튼 클릭
            $('#upload_background_button').click(function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: '배경 이미지 선택',
                    button: {
                        text: '이 이미지 사용'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#facility_background_image').val(attachment.url);
                    $('#background-preview').html('<img src="' + attachment.url + '" alt="배경 이미지" />');
                    $('#remove_background_button').show();
                });
                
                mediaUploader.open();
            });
            
            // 이미지 제거 버튼 클릭
            $('#remove_background_button').click(function(e) {
                e.preventDefault();
                $('#facility_background_image').val('');
                $('#background-preview').html('<p>배경 이미지가 설정되지 않았습니다.</p>');
                $(this).hide();
            });
            
            // 배경 설정 저장
            $('#save_background_settings').click(function(e) {
                e.preventDefault();
                
                var backgroundImage = $('#facility_background_image').val();
                var backgroundPosition = $('#facility_background_position').val();
                var backgroundSize = $('#facility_background_size').val();
                
                $.post(ajaxurl, {
                    action: 'save_facility_background',
                    nonce: '<?php echo wp_create_nonce('save_facility_background'); ?>',
                    background_image: backgroundImage,
                    background_position: backgroundPosition,
                    background_size: backgroundSize
                }, function(response) {
                    if (response.success) {
                        alert('배경 설정이 저장되었습니다.');
                    } else {
                        alert('저장 실패: ' + response.data);
                    }
                });
            });
        });
        
        function clearAllFacilityData() {
            jQuery.post(ajaxurl, {
                action: 'clear_all_facility_data',
                nonce: '<?php echo wp_create_nonce('clear_facility_data'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('모든 데이터가 삭제되었습니다.');
                    location.reload();
                } else {
                    alert('삭제 실패: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * 모든 시설 정의 표시
     */
    private function display_all_definitions() {
        $all_defs = get_option('facility_definitions_data', array());
        
        if (empty($all_defs)) {
            echo '<p>저장된 시설 정의가 없습니다.</p>';
            return;
        }
        
        // 소속/현장별로 그룹화
        $grouped = array();
        foreach ($all_defs as $def) {
            $key = $def['sosok'] . ' - ' . $def['site'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = array();
            }
            $grouped[$key][] = $def;
        }
        
        echo '<p>총 ' . count($all_defs) . '개의 정의가 저장되어 있습니다.</p>';
        
        foreach ($grouped as $group_key => $definitions) {
            echo '<h3>' . esc_html($group_key) . ' (' . count($definitions) . '개)</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th style="width: 30%;">용어</th><th>정의</th></tr></thead>';
            echo '<tbody>';
            foreach ($definitions as $def) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($def['key']) . '</strong></td>';
                echo '<td>' . esc_html($def['value']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }
}