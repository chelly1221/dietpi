// Statistics Dashboard Core JavaScript
(function($) {
    'use strict';
    
    console.log('Statistics Dashboard Core loaded');
    
    // Configuration
    window.sdConfig = {
        refreshInterval: 10000, // 10 seconds
        serverRefreshInterval: 5000, // 5 seconds for server monitoring
        chartColors: {
            primary: '#a70638',
            secondary: '#d91850',
            tertiary: '#8a0230',
            background: 'rgba(167, 6, 56, 0.1)',
            grid: 'rgba(255, 255, 255, 0.1)',
            text: '#fafafa'
        }
    };
    
    // State management
    window.sdState = {
        currentSection: 'overview',
        charts: {},
        data: {
            statistics: null,
            uploadsData: null,
            storageData: null,
            serverData: null,
            organizationData: null
        },
        filters: {
            sosok: '관리자',
            buseo: '관리자',
            site: '관리자'
        },
        tempFilters: {
            sosok: '관리자',
            buseo: '관리자',
            site: '관리자'
        },
        isLoading: false,
        isLoadingServers: false,
        lastUpdated: null,
        refreshTimer: null,
        serverRefreshTimer: null,
        currentModalTarget: null
    };
    
    // API Module
    window.sdAPI = {
        // Get statistics
        getStatistics: function(params) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_statistics',
                    nonce: sd_ajax.nonce,
                    ...params
                }
            });
        },
        
        // Get uploads by date
        getUploadsByDate: function(days, params) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_uploads_by_date',
                    nonce: sd_ajax.nonce,
                    days: days,
                    ...params
                }
            });
        },
        
        // Get storage statistics
        getStorageStatistics: function(params) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_storage_statistics',
                    nonce: sd_ajax.nonce,
                    ...params
                }
            });
        },
        
        // Get server statistics
        getServerStatistics: function(params) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_server_statistics',
                    nonce: sd_ajax.nonce,
                    ...params
                }
            });
        },
        
        // Get organization data
        getOrganizationData: function() {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_organization_data',
                    nonce: sd_ajax.nonce
                }
            });
        },
        
        // Save settings
        saveSettings: function(settings) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_save_settings',
                    nonce: sd_ajax.nonce,
                    ...settings
                }
            });
        },
        
        // Test connection
        testConnection: function(apiUrl, apiType) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_test_connection',
                    nonce: sd_ajax.nonce,
                    api_url: apiUrl,
                    api_type: apiType
                }
            });
        },
        
        // Clear cache
        clearCache: function() {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_clear_cache',
                    nonce: sd_ajax.nonce
                }
            });
        },
        
        // Get system info
        getSystemInfo: function(serverUrl) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_system_info',
                    nonce: sd_ajax.nonce,
                    server_url: serverUrl
                }
            });
        },
        
        // Get CPU info
        getCPUInfo: function(serverUrl) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_cpu_info',
                    nonce: sd_ajax.nonce,
                    server_url: serverUrl
                }
            });
        },
        
        // Get memory info
        getMemoryInfo: function(serverUrl) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_memory_info',
                    nonce: sd_ajax.nonce,
                    server_url: serverUrl
                }
            });
        },
        
        // Get disk info
        getDiskInfo: function(serverUrl) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_disk_info',
                    nonce: sd_ajax.nonce,
                    server_url: serverUrl
                }
            });
        },
        
        // Get network info
        getNetworkInfo: function(serverUrl) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_network_info',
                    nonce: sd_ajax.nonce,
                    server_url: serverUrl
                }
            });
        },
        
        // Get processes
        getProcesses: function(serverUrl, sortBy = 'cpu_percent', limit = 10) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_processes',
                    nonce: sd_ajax.nonce,
                    server_url: serverUrl,
                    sort_by: sortBy,
                    limit: limit
                }
            });
        },
        
        // Get temperature
        getTemperature: function(serverUrl) {
            return $.ajax({
                url: sd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sd_get_temperature',
                    nonce: sd_ajax.nonce,
                    server_url: serverUrl
                }
            });
        }
    };
    
    // Utility functions
    window.sdUtils = {
        formatNumber: function(num) {
            if (num === null || num === undefined || isNaN(num)) return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        
        formatFileSize: function(bytes) {
            if (!bytes || bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        formatDate: function(dateStr, short = false) {
            if (!dateStr) return '-';
            
            // Handle YYYYMMDD format
            if (dateStr.length === 8 && /^\d{8}$/.test(dateStr)) {
                const year = dateStr.substr(0, 4);
                const month = dateStr.substr(4, 2);
                const day = dateStr.substr(6, 2);
                dateStr = `${year}-${month}-${day}`;
            }
            
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            
            if (short) {
                return date.toLocaleDateString('ko-KR', {
                    month: 'short',
                    day: 'numeric'
                });
            }
            
            return date.toLocaleDateString('ko-KR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        },
        
        formatUptime: function(uptimeStr) {
            if (!uptimeStr) return '-';
            
            // API가 이미 "X일 X시간 X분 X초" 형식으로 보내므로 그대로 반환
            return uptimeStr;
        },
        
        escapeHtml: function(text) {
            if (text === null || text === undefined) return '';
            
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return String(text).replace(/[&<>"']/g, m => map[m]);
        },
        
        generateColors: function(count) {
            const baseColors = [
                '#a70638', '#d91850', '#8a0230', '#ff4757', '#ff6348',
                '#e84393', '#6c5ce7', '#74b9ff', '#00b894', '#fdcb6e'
            ];
            
            const colors = [];
            for (let i = 0; i < count; i++) {
                colors.push(baseColors[i % baseColors.length]);
            }
            
            return colors;
        }
    };
    
    // Data loader module
    window.sdDataLoader = {
        loadAllData: function(silent = false) {
            const state = window.sdState;
            
            if (state.isLoading) return;
            
            // Don't load if filters are not complete
            if (!state.filters.sosok || !state.filters.buseo || !state.filters.site) {
                return;
            }
            
            state.isLoading = true;
            
            // Only show loading state on initial load or manual refresh
            if (!silent) {
                window.sdUI.showLoadingState();
            }
            
            const config = window.sdDataLoader.getConfig();
            
            Promise.all([
                window.sdDataLoader.loadStatistics(config),
                window.sdDataLoader.loadUploadsData($('#upload-days-filter').val() || 30, config),
                window.sdDataLoader.loadStorageData(config)
            ]).then(() => {
                state.isLoading = false;
                if (!silent) {
                    window.sdUI.hideLoadingState();
                }
                window.sdUI.updateLastUpdated();
            }).catch(error => {
                state.isLoading = false;
                console.error('Error loading data:', error);
                if (!silent) {
                    window.sdUI.showErrorState(error.message || '데이터를 불러오는 중 오류가 발생했습니다');
                }
            });
        },
        
        getConfig: function() {
            const state = window.sdState;
            return {
                sosok: state.filters.sosok,
                buseo: state.filters.buseo,
                site: state.filters.site
            };
        },
        
        loadStatistics: function(params) {
            return new Promise((resolve, reject) => {
                window.sdAPI.getStatistics(params)
                    .success(function(response) {
                        if (response.success) {
                            window.sdState.data.statistics = response.data;
                            window.sdRender.renderStatistics(response.data);
                            resolve();
                        } else {
                            reject(new Error(response.data || '데이터를 불러오는 중 오류가 발생했습니다'));
                        }
                    })
                    .error(function(xhr, status, error) {
                        reject(new Error(error || '데이터를 불러오는 중 오류가 발생했습니다'));
                    });
            });
        },
        
        loadUploadsData: function(days, params) {
            return new Promise((resolve, reject) => {
                window.sdAPI.getUploadsByDate(days, params)
                    .success(function(response) {
                        if (response.success) {
                            window.sdState.data.uploadsData = response.data;
                            window.sdCharts.renderUploadsChart(response.data);
                            resolve();
                        } else {
                            reject(new Error(response.data || '데이터를 불러오는 중 오류가 발생했습니다'));
                        }
                    })
                    .error(function(xhr, status, error) {
                        reject(new Error(error || '데이터를 불러오는 중 오류가 발생했습니다'));
                    });
            });
        },
        
        loadStorageData: function(params) {
            return new Promise((resolve, reject) => {
                window.sdAPI.getStorageStatistics(params)
                    .success(function(response) {
                        console.log('Storage API response:', response);
                        
                        if (response.success && response.data) {
                            window.sdState.data.storageData = response.data;
                            window.sdRender.renderStorageStats(response.data);
                            resolve();
                        } else {
                            console.log('Storage API error response:', response);
                            window.sdRender.renderStorageStats({
                                access_level: 'error', 
                                message: '저장소 통계를 불러오는 중 오류가 발생했습니다.'
                            });
                            resolve();
                        }
                    })
                    .error(function(xhr, status, error) {
                        console.error('Storage stats AJAX error:', error);
                        window.sdRender.renderStorageStats({
                            access_level: 'error', 
                            message: '저장소 통계를 불러올 수 없습니다.'
                        });
                        resolve();
                    });
            });
        },
        
        loadServerData: function(params) {
            return new Promise((resolve, reject) => {
                const state = window.sdState;
                
                // Set loading state for servers
                state.isLoadingServers = true;
                
                // Show loading state for servers
                window.sdUI.showServerLoadingState();
                
                window.sdAPI.getServerStatistics(params)
                    .success(function(response) {
                        console.log('Server API response:', response);
                        
                        if (response.success && response.data) {
                            window.sdState.data.serverData = response.data;
                            window.sdRender.renderServerStats(response.data);
                            
                            // Update last updated time for servers
                            const timeStr = new Date().toLocaleTimeString('ko-KR', {
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            $('#servers-last-updated').text(`마지막 업데이트: ${timeStr}`);
                            
                            resolve();
                        } else {
                            console.log('Server API error response:', response);
                            window.sdRender.renderServerStats({
                                access_level: 'error', 
                                message: '서버 통계를 불러오는 중 오류가 발생했습니다.'
                            });
                            resolve();
                        }
                    })
                    .error(function(xhr, status, error) {
                        console.error('Server stats AJAX error:', error);
                        window.sdRender.renderServerStats({
                            access_level: 'error', 
                            message: '서버 통계를 불러올 수 없습니다.'
                        });
                        resolve();
                    })
                    .always(function() {
                        // Hide loading state for servers
                        state.isLoadingServers = false;
                        window.sdUI.hideServerLoadingState();
                    });
            });
        },
        
        loadOrganizationData: function() {
            window.sdAPI.getOrganizationData()
                .success(function(response) {
                    if (response.success) {
                        window.sdState.data.organizationData = response.data;
                        console.log('Organization data loaded:', window.sdState.data.organizationData);
                        window.sdFilterManager.populateModalFilterOptions();
                    }
                })
                .error(function() {
                    console.error('Failed to load organization data');
                    // Use default data if failed
                    window.sdState.data.organizationData = {
                        "관리자": {
                            "관리자": ["관리자"]
                        }
                    };
                    window.sdFilterManager.populateModalFilterOptions();
                });
        }
    };
    
    // Auto refresh
    window.sdAutoRefresh = {
        start: function() {
            const state = window.sdState;
            
            if (state.refreshTimer) {
                clearInterval(state.refreshTimer);
            }
            
            state.refreshTimer = setInterval(() => {
                if (!state.isLoading && state.currentSection !== 'settings' && state.currentSection !== 'servers') {
                    // Silent refresh - no loading indicators
                    window.sdDataLoader.loadAllData(true);
                }
            }, window.sdConfig.refreshInterval);
        },
        
        stop: function() {
            const state = window.sdState;
            
            if (state.refreshTimer) {
                clearInterval(state.refreshTimer);
                state.refreshTimer = null;
            }
        },
        
        startServerRefresh: function() {
            const state = window.sdState;
            
            if (state.serverRefreshTimer) {
                clearInterval(state.serverRefreshTimer);
            }
            
            state.serverRefreshTimer = setInterval(() => {
                if (!state.isLoadingServers && state.currentSection === 'servers') {
                    window.sdDataLoader.loadServerData(window.sdDataLoader.getConfig());
                }
            }, window.sdConfig.serverRefreshInterval);
        },
        
        stopServerRefresh: function() {
            const state = window.sdState;
            
            if (state.serverRefreshTimer) {
                clearInterval(state.serverRefreshTimer);
                state.serverRefreshTimer = null;
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        // Wait for dependencies
        if (typeof Chart === 'undefined') {
            console.error('Statistics Dashboard: Chart.js not loaded');
            return;
        }
        
        // Initialize modules
        window.sdEventManager.bindEvents();
        window.sdDataLoader.loadOrganizationData();
        
        // Load initial data with default admin filters
        window.sdFilterManager.updateFilterStatus();
        window.sdDataLoader.loadAllData();
        
        // Start auto refresh
        window.sdAutoRefresh.start();
        
        // Listen for filter applied event
        $(document).on('sd:filters-applied', function(e, filters) {
            console.log('Filters applied:', filters);
            window.sdState.filters = filters;
            window.sdFilterManager.updateFilterStatus();
            window.sdDataLoader.loadAllData();
        });
    });
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        window.sdAutoRefresh.stop();
        window.sdAutoRefresh.stopServerRefresh();
        
        // Destroy all charts
        Object.values(window.sdState.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
    });
    
})(jQuery);