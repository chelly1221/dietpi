<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = get_option('3chan_pdf_manager_settings', array());
$default_settings = array(
    'api_url' => THREECHAN_API_INTERNAL_URL, // 내부망 URL 사용
    'max_file_size' => 50,
    'allowed_file_types' => array('pdf', 'docx', 'pptx', 'hwpx'),
    'enable_notifications' => true,
    'default_page_size' => 10,
    'primary_color' => '#a70638',
    'enable_auto_save' => true,
    'cache_duration' => 3600,
    'use_proxy' => true, // 항상 true
    'enable_duplicate_check' => true
);
$settings = wp_parse_args($settings, $default_settings);

// Force internal network settings
$settings['use_proxy'] = true;
$settings['api_url'] = THREECHAN_API_INTERNAL_URL;

// Handle form submission
if (isset($_POST['submit']) && isset($_POST['3chan_pdf_manager_nonce'])) {
    if (wp_verify_nonce($_POST['3chan_pdf_manager_nonce'], '3chan_pdf_manager_settings')) {
        $new_settings = array(
            'api_url' => THREECHAN_API_INTERNAL_URL, // 항상 내부망 URL
            'max_file_size' => intval($_POST['max_file_size'] ?? $default_settings['max_file_size']),
            'allowed_file_types' => array_map('sanitize_text_field', $_POST['allowed_file_types'] ?? $default_settings['allowed_file_types']),
            'enable_notifications' => isset($_POST['enable_notifications']) ? true : false,
            'default_page_size' => intval($_POST['default_page_size'] ?? $default_settings['default_page_size']),
            'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? $default_settings['primary_color']),
            'enable_auto_save' => isset($_POST['enable_auto_save']) ? true : false,
            'cache_duration' => intval($_POST['cache_duration'] ?? $default_settings['cache_duration']),
            'use_proxy' => true, // 항상 true
            'enable_duplicate_check' => isset($_POST['enable_duplicate_check']) ? true : false
        );
        
        update_option('3chan_pdf_manager_settings', $new_settings);
        $settings = $new_settings;
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('설정이 저장되었습니다.', '3chan-pdf-manager') . '</p></div>';
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Internal Network Mode Indicator -->
    <div class="notice notice-info" style="background: #e3f2fd; border-left-color: #2196f3;">
        <p>
            <strong>🔒 내부망 전용 모드</strong> - 
            이 플러그인은 내부망에서만 작동하도록 설정되어 있습니다.
            API 서버: <code><?php echo esc_html(THREECHAN_API_INTERNAL_URL); ?></code>
        </p>
    </div>
    
    <form method="post" action="">
        <?php wp_nonce_field('3chan_pdf_manager_settings', '3chan_pdf_manager_nonce'); ?>
        
        <div class="settings-container">
            <!-- API Settings - Read Only for Internal Network -->
            <div class="settings-section">
                <h2><?php _e('API 설정 (내부망)', '3chan-pdf-manager'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('API URL', '3chan-pdf-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   value="<?php echo esc_url(THREECHAN_API_INTERNAL_URL); ?>" 
                                   class="regular-text" 
                                   readonly
                                   style="background-color: #f0f0f0;">
                            <p class="description">
                                <?php _e('내부망 API 서버 주소 (wp-config.php에서 설정)', '3chan-pdf-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('프록시 모드', '3chan-pdf-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       checked 
                                       disabled>
                                <?php _e('WordPress 프록시 사용 (내부망 전용 모드에서 항상 활성화)', '3chan-pdf-manager'); ?>
                            </label>
                            <p class="description"><?php _e('내부망 모드에서는 항상 프록시를 통해 API와 통신합니다.', '3chan-pdf-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cache_duration"><?php _e('캐시 유효 시간', '3chan-pdf-manager'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="cache_duration" 
                                   name="cache_duration" 
                                   value="<?php echo esc_attr($settings['cache_duration']); ?>" 
                                   min="0" 
                                   class="small-text"> 
                            <?php _e('초', '3chan-pdf-manager'); ?>
                            <p class="description"><?php _e('API 응답 캐시 시간 (0 = 캐시 사용 안 함)', '3chan-pdf-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('네트워크 상태', '3chan-pdf-manager'); ?></th>
                        <td>
                            <div id="network-status">
                                <span class="status-checking">확인 중...</span>
                            </div>
                            <button type="button" class="button" id="test-internal-connection">
                                <?php _e('내부망 연결 테스트', '3chan-pdf-manager'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- File Upload Settings -->
            <div class="settings-section">
                <h2><?php _e('파일 업로드 설정', '3chan-pdf-manager'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="max_file_size"><?php _e('최대 파일 크기', '3chan-pdf-manager'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="max_file_size" 
                                   name="max_file_size" 
                                   value="<?php echo esc_attr($settings['max_file_size']); ?>" 
                                   min="1" 
                                   max="500" 
                                   class="small-text"> MB
                            <p class="description">
                                <?php 
                                $max_upload = wp_max_upload_size();
                                printf(__('서버 최대 업로드 크기: %s', '3chan-pdf-manager'), size_format($max_upload));
                                ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('허용 파일 형식', '3chan-pdf-manager'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="allowed_file_types[]" 
                                           value="pdf" 
                                           <?php checked(in_array('pdf', $settings['allowed_file_types'])); ?>>
                                    PDF
                                </label><br>
                                <label>
                                    <input type="checkbox" 
                                           name="allowed_file_types[]" 
                                           value="docx" 
                                           <?php checked(in_array('docx', $settings['allowed_file_types'])); ?>>
                                    DOCX
                                </label><br>
                                <label>
                                    <input type="checkbox" 
                                           name="allowed_file_types[]" 
                                           value="pptx" 
                                           <?php checked(in_array('pptx', $settings['allowed_file_types'])); ?>>
                                    PPTX
                                </label><br>
                                <label>
                                    <input type="checkbox" 
                                           name="allowed_file_types[]" 
                                           value="hwpx" 
                                           <?php checked(in_array('hwpx', $settings['allowed_file_types'])); ?>>
                                    HWPX
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- UI Settings -->
            <div class="settings-section">
                <h2><?php _e('사용자 인터페이스', '3chan-pdf-manager'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="primary_color"><?php _e('주 색상', '3chan-pdf-manager'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="primary_color" 
                                   name="primary_color" 
                                   value="<?php echo esc_attr($settings['primary_color']); ?>">
                            <span class="color-preview" style="background-color: <?php echo esc_attr($settings['primary_color']); ?>"></span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_page_size"><?php _e('페이지당 항목 수', '3chan-pdf-manager'); ?></label>
                        </th>
                        <td>
                            <select id="default_page_size" name="default_page_size">
                                <option value="10" <?php selected($settings['default_page_size'], 10); ?>>10</option>
                                <option value="20" <?php selected($settings['default_page_size'], 20); ?>>20</option>
                                <option value="30" <?php selected($settings['default_page_size'], 30); ?>>30</option>
                                <option value="50" <?php selected($settings['default_page_size'], 50); ?>>50</option>
                                <option value="100" <?php selected($settings['default_page_size'], 100); ?>>100</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Feature Settings -->
            <div class="settings-section">
                <h2><?php _e('기능 설정', '3chan-pdf-manager'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('알림', '3chan-pdf-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_notifications" 
                                       value="1" 
                                       <?php checked($settings['enable_notifications']); ?>>
                                <?php _e('알림 메시지 표시', '3chan-pdf-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('자동 저장', '3chan-pdf-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_auto_save" 
                                       value="1" 
                                       <?php checked($settings['enable_auto_save']); ?>>
                                <?php _e('입력 내용 자동 저장', '3chan-pdf-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('중복 파일 확인', '3chan-pdf-manager'); ?></th>
                        <td>
                            <div class="duplicate-check-option">
                                <label>
                                    <input type="checkbox" 
                                           name="enable_duplicate_check" 
                                           value="1" 
                                           <?php checked($settings['enable_duplicate_check'] ?? true); ?>>
                                    <?php _e('파일 업로드 시 동일한 이름의 파일 확인', '3chan-pdf-manager'); ?>
                                </label>
                                <p class="description"><?php _e('같은 현장(site)에서 동일한 이름의 파일이 있을 경우 덮어쓰기 또는 중복 허용을 선택할 수 있습니다.', '3chan-pdf-manager'); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Internal Network Info -->
            <div class="settings-section">
                <h2><?php _e('내부망 정보', '3chan-pdf-manager'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('접속 IP', '3chan-pdf-manager'); ?></label>
                        </th>
                        <td>
                            <code><?php echo $_SERVER['REMOTE_ADDR']; ?></code>
                            <p class="description"><?php _e('현재 접속중인 IP 주소', '3chan-pdf-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('서버 정보', '3chan-pdf-manager'); ?></label>
                        </th>
                        <td>
                            <p>WordPress 서버: <code><?php echo $_SERVER['SERVER_ADDR'] ?? 'Unknown'; ?></code></p>
                            <p>API 서버: <code><?php echo THREECHAN_API_INTERNAL_URL; ?></code></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(__('설정 저장', '3chan-pdf-manager')); ?>
    </form>
</div>

<style>
/* Internal Network Indicator Styles */
#network-status {
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
    display: inline-block;
}

#network-status.status-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

#network-status.status-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status-checking {
    color: #666;
}

.duplicate-check-option {
    background-color: #e3f2fd;
    padding: 15px;
    border-radius: 4px;
    margin-top: 10px;
}

.duplicate-check-option label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.duplicate-check-option input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Check network status on load
    checkNetworkStatus();
    
    // Color picker preview
    $('#primary_color').on('change', function() {
        $('.color-preview').css('background-color', $(this).val());
    });
    
    // Test internal network connection
    $('#test-internal-connection').on('click', function() {
        var $button = $(this);
        var $status = $('#network-status');
        
        $button.prop('disabled', true).text('테스트 중...');
        $status.html('<span class="status-checking">연결 확인 중...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: '3chan_proxy_api',
                endpoint: 'health',
                nonce: '<?php echo wp_create_nonce('3chan-pdf-nonce'); ?>'
            },
            timeout: 10000,
            success: function(response) {
                $status.removeClass('status-error').addClass('status-success')
                    .html('✅ 내부망 API 서버 연결 성공');
            },
            error: function(xhr, status, error) {
                var errorMsg = '❌ 내부망 API 서버 연결 실패';
                
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg += ': ' + xhr.responseJSON.data.message;
                    
                    // Check if it's external network error
                    if (xhr.responseJSON.data.message.includes('External network')) {
                        errorMsg = '🚫 외부 네트워크에서 접속 시도 - 내부망에서만 사용 가능';
                    }
                }
                
                $status.removeClass('status-success').addClass('status-error')
                    .html(errorMsg);
            },
            complete: function() {
                $button.prop('disabled', false).text('내부망 연결 테스트');
            }
        });
    });
    
    // Check network status
    function checkNetworkStatus() {
        var $status = $('#network-status');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: '3chan_proxy_api',
                endpoint: 'health',
                nonce: '<?php echo wp_create_nonce('3chan-pdf-nonce'); ?>'
            },
            timeout: 5000,
            success: function(response) {
                $status.removeClass('status-error').addClass('status-success')
                    .html('✅ 내부망 연결됨');
            },
            error: function(xhr) {
                var errorMsg = '❌ 내부망 연결 끊김';
                
                if (xhr.responseJSON && xhr.responseJSON.data && 
                    xhr.responseJSON.data.message.includes('External network')) {
                    errorMsg = '🚫 외부 네트워크 접속 차단됨';
                }
                
                $status.removeClass('status-success').addClass('status-error')
                    .html(errorMsg);
            }
        });
    }
    
    // Check network status every 30 seconds
    setInterval(checkNetworkStatus, 30000);
});
</script>