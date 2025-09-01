// Statistics Dashboard UI Handlers JavaScript
(function($) {
    'use strict';
    
    // UI Module
    window.sdUI = {
        // Show loading state
        showLoadingState: function() {
            $('.sd-loading-state').show();
            $('.sd-error-state').hide();
            $('.sd-section').css('opacity', '0.5');
        },
        
        // Hide loading state
        hideLoadingState: function() {
            $('.sd-loading-state').hide();
            $('.sd-section').css('opacity', '1');
        },
        
        // Show error state
        showErrorState: function(message) {
            $('.sd-loading-state').hide();
            $('.sd-error-state').show();
            $('.sd-error-message').text(message);
            $('.sd-section').css('opacity', '1');
        },
        
        // Show server loading state
        showServerLoadingState: function() {
            const $container = $('#servers-stats');
            if ($container.length > 0 && $container.children().length === 0) {
                $container.html(`
                    <div class="sd-server-loading">
                        <div class="sd-spinner"></div>
                        <p>서버 정보를 불러오는 중...</p>
                    </div>
                `);
            }
        },
        
        // Hide server loading state
        hideServerLoadingState: function() {
            const $container = $('#servers-stats');
            $container.find('.sd-server-loading').remove();
        },
        
        // Update last updated time
        updateLastUpdated: function() {
            window.sdState.lastUpdated = new Date();
            const timeStr = window.sdState.lastUpdated.toLocaleTimeString('ko-KR', {
                hour: '2-digit',
                minute: '2-digit'
            });
            $('.sd-last-updated').text(`마지막 업데이트: ${timeStr}`);
        },
        
        // Switch section
        switchSection: function(section) {
            const state = window.sdState;
            state.currentSection = section;
            
            // Update tabs
            $('.sd-tab-item').removeClass('active');
            $(`.sd-tab-item[data-section="${section}"]`).addClass('active');
            
            // Update sections
            $('.sd-section').removeClass('active');
            $(`.sd-section[data-section="${section}"]`).addClass('active');
            
            // Load storage data when switching to storage tab
            if (section === 'storage') {
                window.sdDataLoader.loadStorageData(window.sdDataLoader.getConfig());
            }
            
            // Load server data when switching to servers tab
            if (section === 'servers') {
                window.sdDataLoader.loadServerData(window.sdDataLoader.getConfig());
                window.sdAutoRefresh.startServerRefresh();
            } else {
                window.sdAutoRefresh.stopServerRefresh();
            }
            
            // Resize charts if needed
            if (state.charts[section]) {
                setTimeout(() => {
                    Object.values(state.charts[section]).forEach(chart => {
                        if (chart && typeof chart.resize === 'function') {
                            chart.resize();
                        }
                    });
                }, 100);
            }
        }
    };
    
    // Event Manager
    window.sdEventManager = {
        bindEvents: function() {
            // Tab navigation
            $('.sd-tab-item').on('click', function(e) {
                e.preventDefault();
                const $tab = $(this);
                const section = $tab.data('section');
                
                if (section && section !== window.sdState.currentSection) {
                    window.sdUI.switchSection(section);
                }
            });
            
            // Filter button clicks
            $(document).on('click', '#overview-filter-btn, #storage-filter-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const target = $(this).attr('id').includes('overview') ? 'overview' : 'storage';
                console.log('Filter button clicked:', target);
                window.sdFilterManager.openFilterModal(target);
            });
            
            // Modal events
            $(document).on('click', '#modal-close, #modal-cancel', function(e) {
                e.preventDefault();
                window.sdFilterManager.closeFilterModal();
            });
            
            $(document).on('click', '#modal-apply', function(e) {
                e.preventDefault();
                window.sdFilterManager.applyFilters();
            });
            
            $(document).on('click', '#modal-reset', function(e) {
                e.preventDefault();
                window.sdFilterManager.resetFilters();
            });
            
            // Modal overlay click
            $(document).on('click', '#filter-modal', function(e) {
                if (e.target === this) {
                    window.sdFilterManager.closeFilterModal();
                }
            });
            
            // Modal filter dropdowns
            $(document).on('change', '#modal-filter-sosok', function() {
                const sosok = $(this).val();
                console.log('Modal sosok changed to:', sosok);
                
                window.sdState.tempFilters.sosok = sosok;
                window.sdState.tempFilters.buseo = '';
                window.sdState.tempFilters.site = '';
                
                window.sdFilterManager.updateModalBuseoOptions(sosok);
                
                // Auto-select 관리자 부서 if 관리자 sosok is selected
                if (sosok === '관리자') {
                    setTimeout(() => {
                        $('#modal-filter-buseo').val('관리자').trigger('change');
                    }, 100);
                }
            });
            
            $(document).on('change', '#modal-filter-buseo', function() {
                const buseo = $(this).val();
                console.log('Modal buseo changed to:', buseo);
                
                window.sdState.tempFilters.buseo = buseo;
                window.sdState.tempFilters.site = '';
                
                window.sdFilterManager.updateModalSiteOptions(window.sdState.tempFilters.sosok, buseo);
                
                // Auto-select 관리자 site if 관리자 buseo is selected
                if (window.sdState.tempFilters.sosok === '관리자' && buseo === '관리자') {
                    setTimeout(() => {
                        $('#modal-filter-site').val('관리자').trigger('change');
                    }, 100);
                }
            });
            
            $(document).on('change', '#modal-filter-site', function() {
                const site = $(this).val();
                console.log('Modal site changed to:', site);
                window.sdState.tempFilters.site = site;
            });
            
            // Refresh button
            $('.sd-refresh-btn').on('click', function() {
                window.sdDataLoader.loadAllData();
            });
            
            // Refresh servers button
            $('.sd-refresh-servers-btn').on('click', function() {
                window.sdDataLoader.loadServerData(window.sdDataLoader.getConfig());
            });
            
            // Retry button
            $('.sd-retry-btn').on('click', function() {
                window.sdDataLoader.loadAllData();
            });
            
            // Upload days filter
            $('#upload-days-filter').on('change', function() {
                window.sdDataLoader.loadUploadsData(
                    $(this).val(), 
                    window.sdDataLoader.getConfig()
                );
            });
            
            // Settings events
            $('#save-settings').on('click', window.sdSettings.saveSettings);
            $('#test-ai-connection').on('click', function() { window.sdSettings.testConnection('main'); });
            $('#test-gpu-connection').on('click', function() { window.sdSettings.testConnection('gpu'); });
            $('#test-web-connection').on('click', function() { window.sdSettings.testConnection('web'); });
            $('#clear-cache').on('click', window.sdSettings.clearCache);
            
            // Auto-save settings on change
            $('.sd-input, .sd-checkbox-label input').on('change', function() {
                window.sdSettings.markSettingsUnsaved();
            });
        }
    };
    
    // Filter Manager
    window.sdFilterManager = {
        // Update filter status display
        updateFilterStatus: function() {
            const state = window.sdState;
            const hasFilters = state.filters.sosok && state.filters.buseo && state.filters.site;
            
            if (hasFilters) {
                // Update overview filter status
                $('#overview-filter-status').show();
                $('#overview-filter-text').text(`${state.filters.sosok} > ${state.filters.buseo} > ${state.filters.site}`);
                $('#overview-filter-btn').addClass('active');
                
                // Update storage filter status
                $('#storage-filter-status').show();
                $('#storage-filter-text').text(`${state.filters.sosok} > ${state.filters.buseo} > ${state.filters.site}`);
                $('#storage-filter-btn').addClass('active');
            } else {
                // Hide filter status
                $('#overview-filter-status').hide();
                $('#overview-filter-btn').removeClass('active');
                $('#storage-filter-status').hide();
                $('#storage-filter-btn').removeClass('active');
            }
        },
        
        // Open filter modal
        openFilterModal: function(target) {
            console.log('Opening filter modal for:', target);
            
            const state = window.sdState;
            state.currentModalTarget = target;
            
            // Set temp filters to current filters
            state.tempFilters = { ...state.filters };
            
            // Populate modal fields
            $('#modal-filter-sosok').val(state.filters.sosok);
            if (state.filters.sosok) {
                this.updateModalBuseoOptions(state.filters.sosok);
                $('#modal-filter-buseo').val(state.filters.buseo);
                
                if (state.filters.buseo) {
                    this.updateModalSiteOptions(state.filters.sosok, state.filters.buseo);
                    $('#modal-filter-site').val(state.filters.site);
                }
            }
            
            // Show modal
            const $modal = $('#filter-modal');
            if ($modal.length === 0) {
                console.error('Modal element not found!');
                return;
            }
            
            $modal.addClass('active');
            setTimeout(() => $modal.addClass('show'), 10);
        },
        
        // Close filter modal
        closeFilterModal: function() {
            const $modal = $('#filter-modal');
            $modal.removeClass('show');
            setTimeout(() => $modal.removeClass('active'), 300);
        },
        
        // Apply filters
        applyFilters: function() {
            const state = window.sdState;
            
            // Validate filters
            if (!state.tempFilters.sosok || !state.tempFilters.buseo || !state.tempFilters.site) {
                alert('모든 필터를 선택해주세요.');
                return;
            }
            
            // Apply filters
            state.filters = { ...state.tempFilters };
            
            // Update filter status display
            this.updateFilterStatus();
            
            // Close modal
            this.closeFilterModal();
            
            // Load data with new filters
            window.sdDataLoader.loadAllData();
        },
        
        // Reset filters
        resetFilters: function() {
            window.sdState.tempFilters = {
                sosok: '',
                buseo: '',
                site: ''
            };
            
            $('#modal-filter-sosok').val('');
            $('#modal-filter-buseo').empty().append('<option value="">부서 선택</option>').prop('disabled', true);
            $('#modal-filter-site').empty().append('<option value="">현장 선택</option>').prop('disabled', true);
        },
        
        // Populate modal filter options
        populateModalFilterOptions: function() {
            const orgData = window.sdState.data.organizationData;
            
            // Populate sosok filter in modal
            const $modalSosok = $('#modal-filter-sosok');
            $modalSosok.empty();
            $modalSosok.append('<option value="">소속 선택</option>');
            
            Object.keys(orgData).forEach(sosok => {
                const option = $(`<option value="${window.sdUtils.escapeHtml(sosok)}">${window.sdUtils.escapeHtml(sosok)}</option>`);
                if (sosok === '관리자') {
                    option.css({
                        'font-weight': 'bold',
                        'color': '#d63638'
                    });
                }
                $modalSosok.append(option);
            });
        },
        
        // Update buseo options in modal
        updateModalBuseoOptions: function(sosok) {
            console.log('Updating buseo options for sosok:', sosok);
            
            const $buseoSelect = $('#modal-filter-buseo');
            const $siteSelect = $('#modal-filter-site');
            
            $buseoSelect.empty();
            $buseoSelect.append('<option value="">부서 선택</option>');
            
            if (sosok && window.sdState.data.organizationData[sosok]) {
                $buseoSelect.prop('disabled', false);
                
                console.log('Buseo data:', window.sdState.data.organizationData[sosok]);
                
                Object.keys(window.sdState.data.organizationData[sosok]).forEach(buseo => {
                    const option = $(`<option value="${window.sdUtils.escapeHtml(buseo)}">${window.sdUtils.escapeHtml(buseo)}</option>`);
                    if (sosok === '관리자' && buseo === '관리자') {
                        option.css({
                            'font-weight': 'bold',
                            'color': '#d63638'
                        });
                    }
                    $buseoSelect.append(option);
                });
            } else {
                $buseoSelect.prop('disabled', true);
                console.log('No data found for sosok:', sosok);
            }
            
            // Reset site select
            $siteSelect.empty();
            $siteSelect.append('<option value="">현장 선택</option>');
            $siteSelect.prop('disabled', true);
        },
        
        // Update site options in modal
        updateModalSiteOptions: function(sosok, buseo) {
            const $siteSelect = $('#modal-filter-site');
            const orgData = window.sdState.data.organizationData;
            
            $siteSelect.empty();
            $siteSelect.append('<option value="">현장 선택</option>');
            
            if (sosok && buseo && orgData[sosok] && orgData[sosok][buseo]) {
                $siteSelect.prop('disabled', false);
                
                // Add "전체 현장" option for non-admin
                if (!(sosok === '관리자' && buseo === '관리자')) {
                    const allOption = $(`<option value="${buseo}_전체">${buseo} 전체 현장</option>`);
                    allOption.css({
                        'font-weight': 'bold',
                        'color': '#0073aa'
                    });
                    $siteSelect.append(allOption);
                }
                
                // Add individual sites
                orgData[sosok][buseo].forEach(site => {
                    const option = $(`<option value="${window.sdUtils.escapeHtml(site)}">${window.sdUtils.escapeHtml(site)}</option>`);
                    if (sosok === '관리자' && buseo === '관리자' && site === '관리자') {
                        option.text('관리자 (전체 접근)');
                        option.css({
                            'font-weight': 'bold',
                            'color': '#d63638'
                        });
                    }
                    $siteSelect.append(option);
                });
            } else {
                $siteSelect.prop('disabled', true);
            }
        }
    };
    
    // Settings module
    window.sdSettings = {
        settingsChanged: false,
        
        markSettingsUnsaved: function() {
            this.settingsChanged = true;
            $('.sd-save-status').hide();
        },
        
        saveSettings: function() {
            const $button = $('#save-settings');
            const originalText = $button.html();
            
            $button.prop('disabled', true).html('<span class="sd-icon">⏳</span><span>저장 중...</span>');
            
            const settings = {
                api_base_url: $('#api-base-url').val(),
                llm_gpu_api_url: $('#llm-gpu-api-url').val(),
                web_server_api_url: $('#web-server-api-url').val(),
                api_timeout: $('#api-timeout').val(),
                enable_cache: $('#enable-cache').is(':checked') ? 1 : 0,
                cache_duration: $('#cache-duration').val()
            };
            
            window.sdAPI.saveSettings(settings)
                .success(function(response) {
                    if (response.success) {
                        window.sdSettings.settingsChanged = false;
                        $('.sd-save-status').fadeIn().delay(3000).fadeOut();
                        window.sdSettings.showSettingsMessage('설정이 저장되었습니다.', 'success');
                        
                        // Update global config
                        sd_ajax.api_base_url = settings.api_base_url;
                        
                        // Reload data with new settings
                        setTimeout(() => window.sdDataLoader.loadAllData(), 500);
                    } else {
                        window.sdSettings.showSettingsMessage(response.data || '저장 실패', 'error');
                    }
                })
                .error(function() {
                    window.sdSettings.showSettingsMessage('저장 중 오류가 발생했습니다.', 'error');
                })
                .complete(function() {
                    $button.prop('disabled', false).html(originalText);
                });
        },
        
        testConnection: function(apiType) {
            let $button, $result, apiUrl;
            
            switch(apiType) {
                case 'gpu':
                    $button = $('#test-gpu-connection');
                    $result = $('#test-gpu-result');
                    apiUrl = $('#llm-gpu-api-url').val();
                    break;
                case 'web':
                    $button = $('#test-web-connection');
                    $result = $('#test-web-result');
                    apiUrl = $('#web-server-api-url').val();
                    break;
                default:
                    $button = $('#test-ai-connection');
                    $result = $('#test-ai-result');
                    apiUrl = $('#api-base-url').val();
                    break;
            }
            
            const originalText = $button.html();
            
            if (!apiUrl) {
                window.sdSettings.showTestResult($result, 'API 주소를 입력하세요.', 'error');
                return;
            }
            
            $button.prop('disabled', true).html('<span class="sd-icon">⏳</span><span>테스트 중...</span>');
            $result.hide();
            
            window.sdAPI.testConnection(apiUrl, apiType)
                .success(function(response) {
                    if (response.success) {
                        window.sdSettings.showTestResult($result, '✅ 연결 성공! API 서버가 정상 작동 중입니다.', 'success');
                    } else {
                        window.sdSettings.showTestResult($result, '❌ 연결 실패: ' + (response.data || '알 수 없는 오류'), 'error');
                    }
                })
                .error(function() {
                    window.sdSettings.showTestResult($result, '❌ 연결 실패: 네트워크 오류가 발생했습니다.', 'error');
                })
                .complete(function() {
                    $button.prop('disabled', false).html(originalText);
                });
        },
        
        clearCache: function() {
            if (!confirm('캐시를 비우시겠습니까?')) {
                return;
            }
            
            const $button = $('#clear-cache');
            const originalText = $button.html();
            
            $button.prop('disabled', true).html('<span class="sd-icon">⏳</span><span>처리 중...</span>');
            
            window.sdAPI.clearCache()
                .success(function(response) {
                    if (response.success) {
                        window.sdSettings.showSettingsMessage('캐시가 비워졌습니다.', 'success');
                        // Reload data
                        window.sdDataLoader.loadAllData();
                    } else {
                        window.sdSettings.showSettingsMessage(response.data || '캐시 비우기 실패', 'error');
                    }
                })
                .error(function() {
                    window.sdSettings.showSettingsMessage('오류가 발생했습니다.', 'error');
                })
                .complete(function() {
                    $button.prop('disabled', false).html(originalText);
                });
        },
        
        showTestResult: function($resultDiv, message, type) {
            $resultDiv.removeClass('success error').addClass(type);
            $resultDiv.html(`<div class="sd-test-result-${type}">${message}</div>`);
            $resultDiv.fadeIn();
        },
        
        showSettingsMessage: function(message, type) {
            const $message = $('#settings-message');
            
            // 메시지를 설정 그리드 아래에 위치시킴
            if ($message.parent().hasClass('sd-settings-footer')) {
                $('.sd-content[data-section="settings"]').append($message);
            }
            
            $message.removeClass('success error').addClass(type);
            $message.text(message).fadeIn();
            
            setTimeout(() => {
                $message.fadeOut();
            }, 5000);
        }
    };
    
})(jQuery);