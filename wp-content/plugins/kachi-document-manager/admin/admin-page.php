<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize variables for statistics
$api_stats = array(
    'total_documents' => 0,
    'total_sections' => 0,
    'total_size' => 0,
    'documents_by_type' => array(),
    'documents_by_sosok' => array(),
    'documents_by_site' => array(),
    'popular_tags' => array(),
    'recent_uploads' => array(),
    'average_sections_per_document' => 0
);

$storage_stats = array(
    'total_size_gb' => 0,
    'file_count' => 0,
    'size_by_type_mb' => array()
);

// Note: API stats will be loaded via AJAX after page load
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="threechan-admin-dashboard">
        <!-- Loading Overlay -->
        <div id="stats-loading" class="stats-loading">
            <div class="loading-spinner"></div>
            <p>통계를 불러오는 중...</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid" id="statsGrid" style="display: none;">
            <div class="stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-content">
                    <h3 id="totalDocuments">0</h3>
                    <p><?php _e('전체 문서', '3chan-pdf-manager'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📑</div>
                <div class="stat-content">
                    <h3 id="totalSections">0</h3>
                    <p><?php _e('전체 섹션', '3chan-pdf-manager'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💾</div>
                <div class="stat-content">
                    <h3 id="totalSize">0 GB</h3>
                    <p><?php _e('전체 용량', '3chan-pdf-manager'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔄</div>
                <div class="stat-content">
                    <h3 id="api-status">확인중...</h3>
                    <p><?php _e('API 상태', '3chan-pdf-manager'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Additional Statistics Row -->
        <div class="stats-grid secondary-stats" id="secondaryStats" style="display: none;">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3><?php _e('파일 타입별 통계', '3chan-pdf-manager'); ?></h3>
                    <div id="fileTypeStats" class="file-type-stats">
                        <div class="loading-text">로딩 중...</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏷️</div>
                <div class="stat-content">
                    <h3><?php _e('인기 태그', '3chan-pdf-manager'); ?></h3>
                    <div id="popularTags" class="popular-tags">
                        <div class="loading-text">로딩 중...</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-content">
                    <h3><?php _e('소속별 통계', '3chan-pdf-manager'); ?></h3>
                    <div id="sosokStats" class="sosok-stats">
                        <div class="loading-text">로딩 중...</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📍</div>
                <div class="stat-content">
                    <h3><?php _e('현장별 통계', '3chan-pdf-manager'); ?></h3>
                    <div id="siteStats" class="site-stats">
                        <div class="loading-text">로딩 중...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Chart Section -->
        <div class="admin-section" id="chartSection" style="display: none;">
            <h2><?php _e('업로드 추이 (최근 30일)', '3chan-pdf-manager'); ?></h2>
            <canvas id="uploadChart" width="400" height="200"></canvas>
        </div>
        
        <!-- Recent Uploads -->
        <div class="admin-section" id="recentUploadsSection" style="display: none;">
            <h2><?php _e('최근 업로드', '3chan-pdf-manager'); ?></h2>
            
            <div id="recentUploadsTable">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('파일명', '3chan-pdf-manager'); ?></th>
                            <th><?php _e('태그', '3chan-pdf-manager'); ?></th>
                            <th><?php _e('소속', '3chan-pdf-manager'); ?></th>
                            <th><?php _e('현장', '3chan-pdf-manager'); ?></th>
                            <th><?php _e('업로드 일시', '3chan-pdf-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="recentUploadsBody">
                        <tr>
                            <td colspan="5" class="text-center">데이터를 불러오는 중...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="admin-section">
            <h2><?php _e('빠른 작업', '3chan-pdf-manager'); ?></h2>
            
            <div class="quick-actions">
                <a href="<?php echo admin_url('admin.php?page=3chan-pdf-manager-settings'); ?>" class="button button-primary">
                    <?php _e('설정 관리', '3chan-pdf-manager'); ?>
                </a>
                
                <button class="button" id="refresh-stats">
                    <?php _e('통계 새로고침', '3chan-pdf-manager'); ?>
                </button>
                
                <button class="button" id="export-stats">
                    <?php _e('통계 내보내기', '3chan-pdf-manager'); ?>
                </button>
                
                <button class="button" id="test-api">
                    <?php _e('API 테스트', '3chan-pdf-manager'); ?>
                </button>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="admin-section">
            <h2><?php _e('시스템 정보', '3chan-pdf-manager'); ?></h2>
            
            <div class="system-info">
                <p><strong><?php _e('플러그인 버전:', '3chan-pdf-manager'); ?></strong> <?php echo THREECHAN_PDF_VERSION; ?></p>
                <p><strong><?php _e('WordPress 버전:', '3chan-pdf-manager'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong><?php _e('PHP 버전:', '3chan-pdf-manager'); ?></strong> <?php echo PHP_VERSION; ?></p>
                <p><strong><?php _e('최대 업로드 크기:', '3chan-pdf-manager'); ?></strong> <?php echo size_format(wp_max_upload_size()); ?></p>
                <p><strong><?php _e('API 서버:', '3chan-pdf-manager'); ?></strong> <?php echo esc_html(get_option('3chan_pdf_manager_settings')['api_url'] ?? 'Not configured'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
jQuery(document).ready(function($) {
    let uploadChart = null;
    
    // Load statistics on page load
    loadStatistics();
    
    // Refresh statistics button
    $('#refresh-stats').on('click', function() {
        loadStatistics();
    });
    
    // Load all statistics
    function loadStatistics() {
        // Show loading
        $('#stats-loading').show();
        $('#statsGrid, #secondaryStats, #chartSection, #recentUploadsSection').hide();
        
        // Load main statistics
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: '3chan_proxy_api',
                endpoint: 'statistics/',
                nonce: '<?php echo wp_create_nonce('3chan-pdf-nonce'); ?>'
            },
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    // Update main stats
                    $('#totalDocuments').text(data.total_documents || 0);
                    $('#totalSections').text(data.total_sections || 0);
                    
                    // Update file type stats
                    if (data.documents_by_type) {
                        let fileTypeHtml = '';
                        for (const [type, count] of Object.entries(data.documents_by_type)) {
                            const icon = getFileTypeIcon(type);
                            fileTypeHtml += `<div class="stat-item">${icon} ${type.toUpperCase()}: <strong>${count}</strong></div>`;
                        }
                        $('#fileTypeStats').html(fileTypeHtml || '<div class="no-data">데이터 없음</div>');
                    }
                    
                    // Update popular tags
                    if (data.popular_tags && data.popular_tags.length > 0) {
                        let tagsHtml = '';
                        data.popular_tags.forEach(tag => {
                            tagsHtml += `<span class="tag-pill">${tag.name} (${tag.count})</span> `;
                        });
                        $('#popularTags').html(tagsHtml);
                    } else {
                        $('#popularTags').html('<div class="no-data">태그 없음</div>');
                    }
                    
                    // Update sosok stats
                    if (data.documents_by_sosok) {
                        let sosokHtml = '';
                        for (const [sosok, count] of Object.entries(data.documents_by_sosok)) {
                            sosokHtml += `<div class="stat-item">${sosok}: <strong>${count}</strong></div>`;
                        }
                        $('#sosokStats').html(sosokHtml || '<div class="no-data">데이터 없음</div>');
                    }
                    
                    // Update site stats
                    if (data.documents_by_site) {
                        let siteHtml = '';
                        for (const [site, count] of Object.entries(data.documents_by_site)) {
                            siteHtml += `<div class="stat-item">${site}: <strong>${count}</strong></div>`;
                        }
                        $('#siteStats').html(siteHtml || '<div class="no-data">데이터 없음</div>');
                    }
                    
                    // Update recent uploads
                    if (data.recent_uploads && data.recent_uploads.length > 0) {
                        let tableHtml = '';
                        data.recent_uploads.forEach(upload => {
                            tableHtml += `
                                <tr>
                                    <td><strong>${upload.filename}</strong></td>
                                    <td>${upload.tags || '-'}</td>
                                    <td>${upload.sosok || '-'}</td>
                                    <td>${upload.site || '-'}</td>
                                    <td>${formatDate(upload.upload_date)}</td>
                                </tr>
                            `;
                        });
                        $('#recentUploadsBody').html(tableHtml);
                    } else {
                        $('#recentUploadsBody').html('<tr><td colspan="5" class="text-center">업로드된 파일이 없습니다.</td></tr>');
                    }
                    
                    // Show content
                    $('#statsGrid, #secondaryStats, #recentUploadsSection').show();
                    
                } catch (e) {
                    console.error('Failed to parse statistics:', e);
                }
            },
            error: function() {
                $('#fileTypeStats, #popularTags, #sosokStats, #siteStats').html('<div class="error">데이터 로드 실패</div>');
            }
        });
        
        // Load storage statistics
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: '3chan_proxy_api',
                endpoint: 'statistics/storage/',
                nonce: '<?php echo wp_create_nonce('3chan-pdf-nonce'); ?>'
            },
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    $('#totalSize').text(`${data.total_size_gb || 0} GB`);
                } catch (e) {
                    $('#totalSize').text('0 GB');
                }
            }
        });
        
        // Load chart data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: '3chan_proxy_api',
                endpoint: 'statistics/uploads-by-date/',
                nonce: '<?php echo wp_create_nonce('3chan-pdf-nonce'); ?>'
            },
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.dates && data.counts) {
                        drawUploadChart(data.dates, data.counts);
                        $('#chartSection').show();
                    }
                } catch (e) {
                    console.error('Failed to parse chart data:', e);
                }
            },
            complete: function() {
                $('#stats-loading').hide();
            }
        });
        
        // Check API status
        checkApiStatus();
    }
    
    // Check API status
    function checkApiStatus() {
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
                $('#api-status').text('정상').css('color', '#46b450');
            },
            error: function() {
                $('#api-status').text('오류').css('color', '#dc3232');
            }
        });
    }
    
    // Draw upload chart
    function drawUploadChart(labels, data) {
        const ctx = document.getElementById('uploadChart').getContext('2d');
        
        if (uploadChart) {
            uploadChart.destroy();
        }
        
        uploadChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.map(date => formatChartDate(date)),
                datasets: [{
                    label: '일별 업로드',
                    data: data,
                    borderColor: '#a70638',
                    backgroundColor: 'rgba(167, 6, 56, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    // Export statistics
    $('#export-stats').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: '3chan_proxy_api',
                endpoint: 'statistics/',
                nonce: '<?php echo wp_create_nonce('3chan-pdf-nonce'); ?>'
            },
            success: function(response) {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                const jsonStr = JSON.stringify(data, null, 2);
                const blob = new Blob([jsonStr], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `3chan-pdf-statistics-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                alert('통계가 JSON 파일로 내보내졌습니다.');
            }
        });
    });
    
    // Test API
    $('#test-api').on('click', function() {
        $(this).prop('disabled', true).text('테스트 중...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: '3chan_proxy_api',
                endpoint: 'health',
                nonce: '<?php echo wp_create_nonce('3chan-pdf-nonce'); ?>'
            },
            success: function(response) {
                alert('API 테스트 성공!');
            },
            error: function() {
                alert('API 테스트 실패!');
            },
            complete: function() {
                $('#test-api').prop('disabled', false).text('API 테스트');
            }
        });
    });
    
    // Helper functions
    function getFileTypeIcon(type) {
        const icons = {
            'pdf': '📕',
            'docx': '📘',
            'pptx': '📙',
            'hwpx': '📗'
        };
        return icons[type] || '📄';
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        
        // Handle different date formats
        dateStr = dateStr.toString();
        
        // If already formatted (YYYY-MM-DD)
        if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
            return dateStr;
        }
        
        // If YYYYMMDD format
        if (dateStr.match(/^\d{8}$/)) {
            const year = dateStr.substr(0, 4);
            const month = dateStr.substr(4, 2);
            const day = dateStr.substr(6, 2);
            
            // Validate date components
            if (year >= 2000 && year <= 2100 && month >= 1 && month <= 12 && day >= 1 && day <= 31) {
                return `${year}-${month}-${day}`;
            }
        }
        
        // If timestamp or other format, try to parse
        try {
            const date = new Date(dateStr);
            if (!isNaN(date.getTime())) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
        } catch (e) {
            console.error('Date parsing error:', e);
        }
        
        // Return original if can't parse
        return dateStr || '-';
    }
    
    function formatChartDate(dateStr) {
        try {
            const parts = dateStr.split('-');
            return `${parts[1]}/${parts[2]}`;
        } catch (e) {
            return dateStr;
        }
    }
});
</script>

<style>
/* Additional styles for statistics display */
.stats-loading {
    text-align: center;
    padding: 50px;
    background: rgba(255, 255, 255, 0.9);
    position: relative;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #a70638;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.secondary-stats {
    margin-top: 20px;
}

/* Fix overlapping text in stat cards */
.stat-card {
    min-height: 120px;
    position: relative;
    overflow: hidden;
}

.stat-card .stat-content {
    width: 100%;
}

.stat-card .stat-content h3 {
    margin: 0 0 10px 0;
    line-height: 1.2;
    word-break: break-word;
}

.stat-card .stat-content p {
    margin: 0;
    line-height: 1.4;
}

/* Statistics content inside cards */
.file-type-stats,
.sosok-stats,
.site-stats,
.popular-tags {
    font-size: 13px;
    line-height: 1.6;
    margin-top: 10px;
    max-height: 200px;
    overflow-y: auto;
    padding-right: 5px;
}

.stat-item {
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-item strong {
    font-weight: 600;
    color: #23282d;
    white-space: nowrap;
}

/* Tag pills styling */
.popular-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 5px 0;
}

.tag-pill {
    display: inline-block;
    background: #e3f2fd;
    color: #1976d2;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    line-height: 1.4;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}

.no-data,
.error {
    color: #999;
    font-style: italic;
    padding: 15px 0;
    text-align: center;
}

.error {
    color: #dc3232;
}

/* Chart container */
#uploadChart {
    max-height: 300px !important;
}

.text-center {
    text-align: center;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        min-height: auto;
    }
}

/* Scrollbar styling for stat content */
.file-type-stats::-webkit-scrollbar,
.sosok-stats::-webkit-scrollbar,
.site-stats::-webkit-scrollbar {
    width: 4px;
}

.file-type-stats::-webkit-scrollbar-track,
.sosok-stats::-webkit-scrollbar-track,
.site-stats::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.file-type-stats::-webkit-scrollbar-thumb,
.sosok-stats::-webkit-scrollbar-thumb,
.site-stats::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 2px;
}

.file-type-stats::-webkit-scrollbar-thumb:hover,
.sosok-stats::-webkit-scrollbar-thumb:hover,
.site-stats::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>