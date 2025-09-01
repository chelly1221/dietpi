<?php
/**
 * Dashboard Rendering Class
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class SD_Dashboard {
    
    /**
     * 대시보드 숏코드 렌더링
     */
    public function render_dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'theme' => 'dark'
        ), $atts);
        
        ob_start();
        ?>
        <div class="sd-dashboard" data-theme="<?php echo esc_attr($atts['theme']); ?>">
            <div class="sd-container">
                <!-- Header -->
                <?php $this->render_header(); ?>
                
                <!-- Main Content -->
                <main class="sd-main">
                    <!-- Loading State -->
                    <?php $this->render_loading_state(); ?>
                    
                    <!-- Error State -->
                    <?php $this->render_error_state(); ?>
                    
                    <!-- Overview Section -->
                    <?php $this->render_overview_section(); ?>
                    
                    <!-- Storage Section -->
                    <?php $this->render_storage_section(); ?>
                    
                    <!-- Servers Section -->
                    <?php $this->render_servers_section(); ?>
                    
                    <!-- Settings Section -->
                    <?php $this->render_settings_section(); ?>
                </main>
            </div>
            
            <!-- Hidden data attributes -->
            <div id="sd-config" 
                data-sosok="관리자"
                data-buseo="관리자"
                data-site="관리자">
            </div>
            
            <!-- Filter Modal -->
            <?php $this->render_filter_modal(); ?>
        </div>
        
        <!-- Inline initialization script -->
        <?php $this->render_inline_script(); ?>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 헤더 렌더링
     */
    private function render_header() {
        ?>
        <header class="sd-header-wrapper">
            <div class="sd-logo-section">
                <div class="sd-logo">
                    <div class="sd-logo-icon">📊</div>
                    <span>통계 대시보드</span>
                </div>
            </div>
            
            <!-- Tab Navigation -->
            <nav class="sd-tabs-nav">
                <a href="#" class="sd-tab-item active" data-section="overview">
                    <span class="sd-tab-icon">📈</span>
                    <span class="sd-tab-text">개요</span>
                </a>
                <a href="#" class="sd-tab-item" data-section="storage">
                    <span class="sd-tab-icon">💾</span>
                    <span class="sd-tab-text">저장소</span>
                </a>
                <a href="#" class="sd-tab-item" data-section="servers">
                    <span class="sd-tab-icon">🖥️</span>
                    <span class="sd-tab-text">서버</span>
                </a>
                <a href="#" class="sd-tab-item" data-section="settings">
                    <span class="sd-tab-icon">⚙️</span>
                    <span class="sd-tab-text">설정</span>
                </a>
            </nav>
        </header>
        <?php
    }
    
    /**
     * 로딩 상태 렌더링
     */
    private function render_loading_state() {
        ?>
        <div class="sd-loading-state">
            <div class="sd-spinner"></div>
            <p>데이터를 불러오는 중...</p>
        </div>
        <?php
    }
    
    /**
     * 에러 상태 렌더링
     */
    private function render_error_state() {
        ?>
        <div class="sd-error-state" style="display: none;">
            <div class="sd-error-icon">⚠️</div>
            <h3>데이터를 불러올 수 없습니다</h3>
            <p class="sd-error-message"></p>
            <button class="sd-btn-primary sd-retry-btn">
                <svg class="sd-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2v6h-6"></path>
                    <path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path>
                    <path d="M3 22v-6h6"></path>
                    <path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path>
                </svg>
                <span>다시 시도</span>
            </button>
        </div>
        <?php
    }
    
    /**
     * 개요 섹션 렌더링
     */
    private function render_overview_section() {
        ?>
        <div class="sd-section active" data-section="overview">
            <div class="sd-section-header">
                <h2 class="sd-section-title">전체 개요</h2>
                <div class="sd-header-controls">
                    <div class="sd-header-buttons">
                        <button class="sd-filter-btn" id="overview-filter-btn">
                            <svg class="sd-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                            <span>필터</span>
                        </button>
                        <button class="sd-btn-secondary sd-refresh-btn">
                            <svg class="sd-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 2v6h-6"></path>
                                <path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path>
                                <path d="M3 22v-6h6"></path>
                                <path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path>
                            </svg>
                            <span>새로고침</span>
                        </button>
                    </div>
                    <div class="sd-header-status">
                        <div class="sd-filter-status" id="overview-filter-status">
                            <span class="sd-status-label">필터:</span>
                            <span id="overview-filter-text">관리자 > 관리자 > 관리자</span>
                        </div>
                        <div class="sd-last-updated"></div>
                    </div>
                </div>
            </div>
            
            <div class="sd-content">
                <!-- Stats Cards -->
                <?php $this->render_stats_cards(); ?>
                
                <!-- Upload Timeline Section -->
                <?php $this->render_upload_timeline(); ?>
                
                <!-- Documents Distribution Section -->
                <?php $this->render_documents_distribution(); ?>
                
                <!-- Charts Row -->
                <?php $this->render_charts_row(); ?>
                
                <!-- Recent Uploads -->
                <?php $this->render_recent_uploads(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 통계 카드 렌더링
     */
    private function render_stats_cards() {
        ?>
        <div class="sd-stats-grid">
            <div class="sd-stat-card">
                <div class="sd-stat-icon">📄</div>
                <div class="sd-stat-content">
                    <h3>전체 문서</h3>
                    <p class="sd-stat-value" id="total-documents">-</p>
                    <span class="sd-stat-label">개</span>
                </div>
            </div>
            
            <div class="sd-stat-card">
                <div class="sd-stat-icon">📑</div>
                <div class="sd-stat-content">
                    <h3>전체 섹션</h3>
                    <p class="sd-stat-value" id="total-sections">-</p>
                    <span class="sd-stat-label">개</span>
                </div>
            </div>
            
            <div class="sd-stat-card">
                <div class="sd-stat-icon">📊</div>
                <div class="sd-stat-content">
                    <h3>평균 섹션</h3>
                    <p class="sd-stat-value" id="avg-sections">-</p>
                    <span class="sd-stat-label">개/문서</span>
                </div>
            </div>
            
            <div class="sd-stat-card">
                <div class="sd-stat-icon">🏢</div>
                <div class="sd-stat-content">
                    <h3>소속</h3>
                    <p class="sd-stat-value" id="total-sosok">-</p>
                    <span class="sd-stat-label">개</span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 업로드 타임라인 렌더링
     */
    private function render_upload_timeline() {
        ?>
        <div class="sd-upload-timeline-section">
            <div class="sd-timeline-header">
                <h3>업로드 현황</h3>
                <div class="sd-date-filter">
                    <label>기간:</label>
                    <select id="upload-days-filter">
                        <option value="7">최근 7일</option>
                        <option value="30" selected>최근 30일</option>
                        <option value="60">최근 60일</option>
                        <option value="90">최근 90일</option>
                    </select>
                </div>
            </div>
            
            <div class="sd-uploads-chart-card">
                <canvas id="chart-uploads-timeline"></canvas>
            </div>
            
            <div class="sd-upload-stats">
                <div class="sd-stat-mini">
                    <span class="sd-stat-mini-label">총 업로드:</span>
                    <span class="sd-stat-mini-value" id="total-uploads">-</span>
                </div>
                <div class="sd-stat-mini">
                    <span class="sd-stat-mini-label">일 평균:</span>
                    <span class="sd-stat-mini-value" id="avg-uploads">-</span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 문서 분포 렌더링
     */
    private function render_documents_distribution() {
        ?>
        <div class="sd-documents-section">
            <h3>문서 분포</h3>
            <div class="sd-documents-grid">
                <!-- By Sosok Chart -->
                <div class="sd-chart-card">
                    <h4>소속별 문서 분포 (상위 10개)</h4>
                    <div class="sd-chart-container">
                        <canvas id="chart-by-sosok"></canvas>
                    </div>
                </div>
                
                <!-- By Buseo Chart -->
                <div class="sd-chart-card">
                    <h4>부서별 문서 분포 (상위 10개)</h4>
                    <div class="sd-chart-container">
                        <canvas id="chart-by-buseo"></canvas>
                    </div>
                </div>
                
                <!-- By Site Chart -->
                <div class="sd-chart-card">
                    <h4>현장별 문서 분포 (상위 10개)</h4>
                    <div class="sd-chart-container">
                        <canvas id="chart-by-site"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 차트 행 렌더링
     */
    private function render_charts_row() {
        ?>
        <div class="sd-charts-row">
            <!-- Document Types Chart -->
            <div class="sd-chart-card">
                <h3>문서 유형별 분포</h3>
                <div class="sd-chart-container">
                    <canvas id="chart-document-types"></canvas>
                </div>
            </div>
            
            <!-- Popular Tags -->
            <div class="sd-chart-card">
                <h3>인기 태그</h3>
                <div class="sd-tags-list" id="popular-tags">
                    <!-- Tags will be populated here -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 최근 업로드 렌더링
     */
    private function render_recent_uploads() {
        ?>
        <div class="sd-recent-uploads">
            <h3>최근 업로드</h3>
            <div class="sd-uploads-list" id="recent-uploads">
                <!-- Recent uploads will be populated here -->
            </div>
        </div>
        <?php
    }
    
    /**
     * 저장소 섹션 렌더링
     */
    private function render_storage_section() {
        ?>
        <div class="sd-section" data-section="storage">
            <div class="sd-section-header">
                <h2 class="sd-section-title">저장소 통계</h2>
                <div class="sd-header-controls">
                    <div class="sd-header-buttons">
                        <button class="sd-filter-btn" id="storage-filter-btn">
                            <svg class="sd-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                            <span>필터</span>
                        </button>
                    </div>
                    <div class="sd-header-status">
                        <div class="sd-filter-status" id="storage-filter-status">
                            <span class="sd-status-label">필터:</span>
                            <span id="storage-filter-text">관리자 > 관리자 > 관리자</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="sd-content">
                <div class="sd-storage-stats" id="storage-stats">
                    <!-- Storage stats will be populated here -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 서버 섹션 렌더링
     */
    private function render_servers_section() {
        ?>
        <div class="sd-section" data-section="servers">
            <div class="sd-section-header">
                <h2 class="sd-section-title">서버 모니터링</h2>
                <div class="sd-header-controls">
                    <div class="sd-header-buttons">
                        <button class="sd-btn-secondary sd-refresh-servers-btn">
                            <svg class="sd-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 2v6h-6"></path>
                                <path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path>
                                <path d="M3 22v-6h6"></path>
                                <path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path>
                            </svg>
                            <span>새로고침</span>
                        </button>
                    </div>
                    <div class="sd-header-status">
                        <div class="sd-last-updated" id="servers-last-updated"></div>
                    </div>
                </div>
            </div>
            
            <div class="sd-content">
                <div class="sd-servers-grid" id="servers-stats">
                    <!-- Server stats will be populated here -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 설정 섹션 렌더링
     */
    private function render_settings_section() {
        ?>
        <div class="sd-section" data-section="settings">
            <div class="sd-section-header">
                <h2 class="sd-section-title">설정</h2>
                <div class="sd-header-controls">
                    <button type="button" class="sd-btn-primary" id="save-settings">
                        <span class="sd-icon">💾</span>
                        <span>설정 저장</span>
                    </button>
                    <div class="sd-save-status" style="display: none;">
                        <span class="sd-icon">✅</span>
                        <span>저장됨</span>
                    </div>
                </div>
            </div>
            
            <div class="sd-content">
                <div class="sd-settings-grid">
                    <!-- AI 서버 API 설정 -->
                    <div class="sd-setting-card">
                        <h3>
                            <span class="sd-icon">🤖</span>
                            AI 서버 API 설정
                        </h3>
                        <p class="sd-help-text">메인 Python 백엔드 API 서버 정보를 설정합니다.</p>
                        
                        <div class="sd-form-group">
                            <label for="api-base-url">AI 서버 API URL</label>
                            <input type="url" id="api-base-url" class="sd-input" 
                                value="<?php echo esc_attr(get_option('sd_api_base_url')); ?>" 
                                placeholder="http://192.168.1.101:8000">
                            <p class="sd-help-text">Python FastAPI 서버의 기본 URL을 입력하세요.</p>
                        </div>
                        
                        <div class="sd-form-actions">
                            <button type="button" class="sd-btn-primary" id="test-ai-connection">
                                <span class="sd-icon">🔍</span>
                                <span>연결 테스트</span>
                            </button>
                        </div>
                        
                        <div class="sd-test-result" id="test-ai-result" style="display: none;">
                            <!-- 테스트 결과가 여기에 표시됩니다 -->
                        </div>
                    </div>
                    
                    <!-- LLM GPU API 설정 -->
                    <div class="sd-setting-card">
                        <h3>
                            <span class="sd-icon">🎮</span>
                            LLM GPU API 설정
                        </h3>
                        <p class="sd-help-text">LLM GPU 모니터링 API 정보를 설정합니다.</p>
                        
                        <div class="sd-form-group">
                            <label for="llm-gpu-api-url">LLM GPU API URL</label>
                            <input type="url" id="llm-gpu-api-url" class="sd-input" 
                                value="<?php echo esc_attr(get_option('sd_llm_gpu_api_url')); ?>" 
                                placeholder="http://192.168.1.101:8000/gpu">
                            <p class="sd-help-text">LLM GPU 상태를 확인할 수 있는 API URL을 입력하세요.</p>
                        </div>
                        
                        <div class="sd-form-actions">
                            <button type="button" class="sd-btn-primary" id="test-gpu-connection">
                                <span class="sd-icon">🔍</span>
                                <span>연결 테스트</span>
                            </button>
                        </div>
                        
                        <div class="sd-test-result" id="test-gpu-result" style="display: none;">
                            <!-- 테스트 결과가 여기에 표시됩니다 -->
                        </div>
                    </div>
                    
                    <!-- WEB 서버 API 설정 -->
                    <div class="sd-setting-card">
                        <h3>
                            <span class="sd-icon">🌐</span>
                            WEB 서버 API 설정
                        </h3>
                        <p class="sd-help-text">웹 서버 상태 체크 URL을 설정합니다.</p>
                        
                        <div class="sd-form-group">
                            <label for="web-server-api-url">WEB 서버 URL</label>
                            <input type="url" id="web-server-api-url" class="sd-input" 
                                value="<?php echo esc_attr(get_option('sd_web_server_api_url')); ?>" 
                                placeholder="https://example.com">
                            <p class="sd-help-text">웹 서버의 상태를 확인할 URL을 입력하세요.</p>
                        </div>
                        
                        <div class="sd-form-actions">
                            <button type="button" class="sd-btn-primary" id="test-web-connection">
                                <span class="sd-icon">🔍</span>
                                <span>연결 테스트</span>
                            </button>
                        </div>
                        
                        <div class="sd-test-result" id="test-web-result" style="display: none;">
                            <!-- 테스트 결과가 여기에 표시됩니다 -->
                        </div>
                    </div>
                    
                    <!-- 공통 설정 -->
                    <div class="sd-setting-card">
                        <h3>
                            <span class="sd-icon">⚙️</span>
                            공통 설정
                        </h3>
                        <p class="sd-help-text">모든 API에 적용되는 공통 설정입니다.</p>
                        
                        <div class="sd-form-group">
                            <label for="api-timeout">요청 타임아웃 (초)</label>
                            <input type="number" id="api-timeout" class="sd-input" 
                                value="<?php echo esc_attr(get_option('sd_api_timeout')); ?>" 
                                min="5" max="120" step="5">
                        </div>
                        
                        <div class="sd-form-group">
                            <label class="sd-checkbox-label">
                                <input type="checkbox" id="enable-cache" 
                                    <?php echo get_option('sd_enable_cache') ? 'checked' : ''; ?>>
                                <span>캐싱 활성화</span>
                            </label>
                        </div>
                        
                        <div class="sd-form-group">
                            <label for="cache-duration">캐시 유지 시간 (초)</label>
                            <input type="number" id="cache-duration" class="sd-input" 
                                value="<?php echo esc_attr(get_option('sd_cache_duration')); ?>" 
                                min="60" max="3600" step="60">
                            <p class="sd-help-text">캐시된 데이터가 유지되는 시간입니다.</p>
                        </div>
                        
                        <div class="sd-form-actions">
                            <button type="button" class="sd-btn-secondary" id="clear-cache">
                                <span class="sd-icon">🗑️</span>
                                <span>캐시 비우기</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="sd-settings-message" id="settings-message"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 필터 모달 렌더링
     */
    private function render_filter_modal() {
        ?>
        <div class="sd-modal-overlay" id="filter-modal">
            <div class="sd-modal">
                <div class="sd-modal-header">
                    <h3 class="sd-modal-title">필터 설정</h3>
                    <button class="sd-modal-close" id="modal-close">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="sd-modal-body">
                    <form class="sd-filter-form" id="filter-form">
                        <div class="sd-filter-group">
                            <label class="sd-filter-label" for="modal-filter-sosok">소속</label>
                            <select id="modal-filter-sosok" class="sd-filter-select">
                                <option value="">소속 선택</option>
                            </select>
                        </div>
                        <div class="sd-filter-group">
                            <label class="sd-filter-label" for="modal-filter-buseo">부서</label>
                            <select id="modal-filter-buseo" class="sd-filter-select" disabled>
                                <option value="">부서 선택</option>
                            </select>
                        </div>
                        <div class="sd-filter-group">
                            <label class="sd-filter-label" for="modal-filter-site">현장</label>
                            <select id="modal-filter-site" class="sd-filter-select" disabled>
                                <option value="">현장 선택</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="sd-modal-footer">
                    <button type="button" class="sd-btn-secondary" id="modal-reset">초기화</button>
                    <div class="sd-modal-actions">
                        <button type="button" class="sd-btn-secondary" id="modal-cancel">취소</button>
                        <button type="button" class="sd-btn-primary" id="modal-apply">적용</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 인라인 초기화 스크립트
     */
    private function render_inline_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Inline script ready');
            
            // Load organization data immediately
            let organizationData = {};
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'sd_get_organization_data',
                    nonce: '<?php echo wp_create_nonce('sd_ajax_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        organizationData = response.data;
                        console.log('Organization data loaded (inline):', organizationData);
                        
                        // Populate sosok options
                        const $sosokSelect = $('#modal-filter-sosok');
                        $sosokSelect.empty();
                        $sosokSelect.append('<option value="">소속 선택</option>');
                        
                        Object.keys(organizationData).forEach(function(sosok) {
                            $sosokSelect.append('<option value="' + sosok + '">' + sosok + '</option>');
                        });
                    }
                }
            });
            
            // Filter button event binding
            $(document).on('click', '#overview-filter-btn, #storage-filter-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Filter button clicked (inline)');
                
                // Show modal directly
                const $modal = $('#filter-modal');
                $modal.addClass('active');
                setTimeout(function() {
                    $modal.addClass('show');
                }, 10);
            });
            
            // Modal close
            $(document).on('click', '#modal-close, #modal-cancel', function(e) {
                e.preventDefault();
                const $modal = $('#filter-modal');
                $modal.removeClass('show');
                setTimeout(function() {
                    $modal.removeClass('active');
                }, 300);
            });
            
            // Modal overlay click to close
            $(document).on('click', '#filter-modal', function(e) {
                if (e.target === this) {
                    $(this).removeClass('show');
                    setTimeout(() => {
                        $(this).removeClass('active');
                    }, 300);
                }
            });
            
            // Sosok change handler
            $(document).on('change', '#modal-filter-sosok', function() {
                const sosok = $(this).val();
                console.log('Sosok changed to:', sosok);
                
                const $buseoSelect = $('#modal-filter-buseo');
                const $siteSelect = $('#modal-filter-site');
                
                // Reset buseo and site
                $buseoSelect.empty().append('<option value="">부서 선택</option>').prop('disabled', true);
                $siteSelect.empty().append('<option value="">현장 선택</option>').prop('disabled', true);
                
                if (sosok && organizationData[sosok]) {
                    console.log('Buseo data for', sosok, ':', organizationData[sosok]);
                    $buseoSelect.prop('disabled', false);
                    
                    Object.keys(organizationData[sosok]).forEach(function(buseo) {
                        $buseoSelect.append('<option value="' + buseo + '">' + buseo + '</option>');
                    });
                }
            });
            
            // Buseo change handler
            $(document).on('change', '#modal-filter-buseo', function() {
                const sosok = $('#modal-filter-sosok').val();
                const buseo = $(this).val();
                console.log('Buseo changed to:', buseo);
                
                const $siteSelect = $('#modal-filter-site');
                
                // Reset site
                $siteSelect.empty().append('<option value="">현장 선택</option>').prop('disabled', true);
                
                if (sosok && buseo && organizationData[sosok] && organizationData[sosok][buseo]) {
                    console.log('Site data for', buseo, ':', organizationData[sosok][buseo]);
                    $siteSelect.prop('disabled', false);
                    
                    // Add "전체 현장" option for non-admin
                    if (!(sosok === '관리자' && buseo === '관리자')) {
                        $siteSelect.append('<option value="' + buseo + '_전체" style="font-weight: bold; color: #0073aa;">' + buseo + ' 전체 현장</option>');
                    }
                    
                    organizationData[sosok][buseo].forEach(function(site) {
                        let optionText = site;
                        if (sosok === '관리자' && buseo === '관리자' && site === '관리자') {
                            optionText = '관리자 (전체 접근)';
                        }
                        $siteSelect.append('<option value="' + site + '">' + optionText + '</option>');
                    });
                }
            });
            
            // Apply button
            $(document).on('click', '#modal-apply', function(e) {
                e.preventDefault();
                console.log('Apply button clicked');
                
                const sosok = $('#modal-filter-sosok').val();
                const buseo = $('#modal-filter-buseo').val();
                const site = $('#modal-filter-site').val();
                
                console.log('Selected filters:', sosok, buseo, site);
                
                if (!sosok || !buseo || !site) {
                    alert('모든 필터를 선택해주세요.');
                    return;
                }
                
                // Trigger the main JS to handle the filter application
                $(document).trigger('sd:filters-applied', {
                    sosok: sosok,
                    buseo: buseo,
                    site: site
                });
                
                // Update filter display
                $('#overview-filter-status').show();
                $('#overview-filter-text').text(sosok + ' > ' + buseo + ' > ' + site);
                $('#overview-filter-btn').addClass('active');
                
                $('#storage-filter-status').show();
                $('#storage-filter-text').text(sosok + ' > ' + buseo + ' > ' + site);
                $('#storage-filter-btn').addClass('active');
                
                // Update global state if available
                if (window.sdState) {
                    window.sdState.filters = {
                        sosok: sosok,
                        buseo: buseo,
                        site: site
                    };
                }
                
                // Close modal
                const $modal = $('#filter-modal');
                console.log('Closing modal...');
                
                // Remove classes and hide directly
                $modal.removeClass('show active');
                $modal.css('display', 'none');
                
                // Re-enable display after animation
                setTimeout(function() {
                    $modal.css('display', '');
                    console.log('Modal closed');
                }, 500);
            });
            
            // Reset button
            $(document).on('click', '#modal-reset', function(e) {
                e.preventDefault();
                $('#modal-filter-sosok').val('');
                $('#modal-filter-buseo').empty().append('<option value="">부서 선택</option>').prop('disabled', true);
                $('#modal-filter-site').empty().append('<option value="">현장 선택</option>').prop('disabled', true);
            });
            
            // Check if elements exist
            console.log('Filter buttons found:', $('#overview-filter-btn').length, $('#storage-filter-btn').length);
            console.log('Modal found:', $('#filter-modal').length);
        });
        </script>
        <?php
    }
}