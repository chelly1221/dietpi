<?php
/**
 * Settings Management Class
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class SD_Settings {
    
    public function __construct() {
        // 설정 메뉴 추가
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * 관리자 메뉴 추가
     */
    public function add_admin_menu() {
        add_options_page(
            '통계 대시보드 설정',
            '통계 대시보드',
            'manage_options',
            'statistics-dashboard',
            array($this, 'settings_page')
        );
    }
    
    /**
     * 설정 페이지 렌더링
     */
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            check_admin_referer('sd_settings_nonce');
            
            update_option('sd_api_base_url', sanitize_text_field($_POST['api_base_url']));
            update_option('sd_llm_gpu_api_url', sanitize_text_field($_POST['llm_gpu_api_url']));
            update_option('sd_web_server_api_url', sanitize_text_field($_POST['web_server_api_url']));
            update_option('sd_api_timeout', intval($_POST['api_timeout']));
            update_option('sd_cache_duration', intval($_POST['cache_duration']));
            update_option('sd_enable_cache', isset($_POST['enable_cache']) ? 1 : 0);
            
            echo '<div class="notice notice-success"><p>설정이 저장되었습니다!</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>통계 대시보드 설정</h1>
            
            <div class="sd-settings-wrapper">
                <form method="post" action="">
                    <?php wp_nonce_field('sd_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">AI 서버 API URL</th>
                            <td>
                                <input type="text" name="api_base_url" value="<?php echo esc_attr(get_option('sd_api_base_url')); ?>" class="regular-text" />
                                <p class="description">메인 Python API 서버 URL (예: http://192.168.1.101:8000)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">LLM GPU API URL</th>
                            <td>
                                <input type="text" name="llm_gpu_api_url" value="<?php echo esc_attr(get_option('sd_llm_gpu_api_url')); ?>" class="regular-text" />
                                <p class="description">LLM GPU 모니터링 API URL (예: http://192.168.1.101:8000/gpu)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">WEB 서버 API URL</th>
                            <td>
                                <input type="text" name="web_server_api_url" value="<?php echo esc_attr(get_option('sd_web_server_api_url')); ?>" class="regular-text" />
                                <p class="description">웹 서버 상태 체크 URL (예: https://example.com)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">API 타임아웃</th>
                            <td>
                                <input type="number" name="api_timeout" value="<?php echo esc_attr(get_option('sd_api_timeout')); ?>" class="small-text" min="5" max="120" /> 초
                                <p class="description">API 요청 최대 대기 시간</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">캐시</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_cache" value="1" <?php checked(get_option('sd_enable_cache'), 1); ?> />
                                    캐싱 활성화
                                </label>
                                <p class="description">데이터 캐싱을 활성화하여 성능을 향상시킵니다.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">캐시 유지 시간</th>
                            <td>
                                <input type="number" name="cache_duration" value="<?php echo esc_attr(get_option('sd_cache_duration')); ?>" class="small-text" min="60" max="3600" step="60" /> 초
                                <p class="description">캐시된 데이터가 유지되는 시간</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('변경 사항 저장'); ?>
                </form>
                
                <div class="sd-settings-info">
                    <h2>사용법</h2>
                    <p>다음 숏코드를 사용하여 통계 대시보드를 표시합니다:</p>
                    <code>[statistics_dashboard]</code>
                    
                    <p>테마를 변경할 수도 있습니다:</p>
                    <code>[statistics_dashboard theme="light"]</code>
                    
                    <h3>필수 API 엔드포인트</h3>
                    <h4>AI 서버 API:</h4>
                    <ul>
                        <li><code>GET /statistics/</code> - 전체 통계 데이터</li>
                        <li><code>GET /statistics/uploads-by-date/</code> - 날짜별 업로드 통계</li>
                        <li><code>GET /statistics/storage/</code> - 저장소 통계</li>
                        <li><code>GET /statistics/servers/</code> - 서버 통계</li>
                        <li><code>GET /health</code> - 상태 확인</li>
                    </ul>
                    
                    <h4>LLM GPU API:</h4>
                    <ul>
                        <li><code>GET /gpu</code> - GPU 상태 및 사용률 정보</li>
                    </ul>
                    
                    <h4>WEB 서버 API:</h4>
                    <ul>
                        <li>설정된 URL로 HTTP GET 요청을 보내 상태를 확인합니다</li>
                    </ul>
                    
                    <h3>문제 해결</h3>
                    <p>API 연결 오류가 발생하는 경우:</p>
                    <ol>
                        <li>각 API 서버가 실행 중인지 확인</li>
                        <li>API URL이 올바른지 확인</li>
                        <li>방화벽이나 네트워크 설정 확인</li>
                        <li>브라우저 콘솔에서 JavaScript 오류 확인</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <style>
            .sd-settings-wrapper {
                max-width: 800px;
                background: #fff;
                padding: 20px;
                margin-top: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            
            .sd-settings-info {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            
            .sd-settings-info code {
                display: inline-block;
                margin: 5px 0;
                padding: 3px 5px;
                background: #f0f0f1;
                font-size: 13px;
            }
            
            .sd-settings-info ul {
                list-style: disc;
                margin-left: 30px;
            }
            
            .sd-settings-info ol {
                list-style: decimal;
                margin-left: 30px;
            }
            
            .sd-settings-info h4 {
                margin-top: 15px;
                margin-bottom: 10px;
                font-size: 14px;
                font-weight: 600;
            }
        </style>
        <?php
    }
}