/**
 * 3chan PDF Manager - Admin JavaScript
 */

(function($) {
    'use strict';

    // Admin Manager Class
    class ThreeChanAdminManager {
        constructor() {
            this.apiUrl = threechan_pdf_admin?.api_url || 'http://1.3chan.kr';
            this.nonce = threechan_pdf_admin?.nonce || '';
            this.init();
        }

        init() {
            // DOM이 완전히 로드된 후 실행
            $(window).on('load', () => {
                this.bindEvents();
                // API 상태 체크를 약간 지연시켜 실행
                setTimeout(() => {
                    this.checkApiStatus();
                }, 1000);
                this.initCharts();
                this.initTooltips();
            });
        }

        bindEvents() {
            // API Test
            $(document).on('click', '#test-api', this.testApi.bind(this));
            
            // Export Data
            $(document).on('click', '#export-data', this.exportData.bind(this));
            
            // Clear Cache
            $(document).on('click', '#clear-cache', this.clearCache.bind(this));
            
            // Database Operations
            $(document).on('click', '#optimize-db', this.optimizeDatabase.bind(this));
            $(document).on('click', '#reset-db', this.resetDatabase.bind(this));
            
            // Log Operations
            $(document).on('click', '#view-logs', this.viewLogs.bind(this));
            $(document).on('click', '#clear-logs', this.clearLogs.bind(this));
            
            // File Operations
            $(document).on('click', '.view-file', this.viewFile.bind(this));
            $(document).on('click', '.delete-file', this.deleteFile.bind(this));
            
            // Settings Form
            $(document).on('submit', '#threechan-settings-form', this.saveSettings.bind(this));
            
            // Color Picker
            $(document).on('change', '#primary_color', this.updateColorPreview);
            
            // Tab Navigation
            $(document).on('click', '.nav-tab', this.handleTabClick);
            
            // Modal Close
            $(document).on('click', '.threechan-modal-close', this.closeModal);
            
            // Bulk Actions
            $(document).on('change', '#bulk-action-selector', this.handleBulkAction.bind(this));
        }

        // Check API Status
        async checkApiStatus() {
            const $status = $('#api-status');
            if (!$status.length) return;

            // 프록시를 통한 API 상태 확인으로 변경
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_proxy_api',
                    endpoint: 'health',
                    nonce: this.nonce
                },
                timeout: 10000, // 10초 타임아웃
                success: (response) => {
                    $status.text('정상').css('color', '#46b450');
                    // 성공 시 알림 제거 (너무 많은 알림 방지)
                },
                error: (xhr, status, error) => {
                    // 재시도 로직 추가
                    setTimeout(() => {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: '3chan_proxy_api',
                                endpoint: 'health',
                                nonce: this.nonce
                            },
                            timeout: 10000,
                            success: (response) => {
                                $status.text('정상').css('color', '#46b450');
                            },
                            error: () => {
                                $status.text('오류').css('color', '#dc3232');
                            }
                        });
                    }, 2000); // 2초 후 재시도
                }
            });
        }

        // Test API
        async testApi(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            
            $button.prop('disabled', true).text('테스트 중...');
            this.showLoading();

            try {
                const response = await fetch(`${this.apiUrl}/test`, {
                    method: 'GET',
                    mode: 'cors'
                });

                const data = await response.json();
                
                if (response.ok) {
                    this.showNotification('API 테스트 성공!', 'success');
                    this.showApiTestResults(data);
                } else {
                    throw new Error(data.message || 'API 테스트 실패');
                }
            } catch (error) {
                this.showNotification(`API 테스트 실패: ${error.message}`, 'error');
            } finally {
                $button.prop('disabled', false).text('API 테스트');
                this.hideLoading();
            }
        }

        // Show API Test Results
        showApiTestResults(data) {
            const modal = this.createModal('API 테스트 결과', `
                <div class="api-test-results">
                    <p><strong>상태:</strong> ${data.status || 'Unknown'}</p>
                    <p><strong>버전:</strong> ${data.version || 'Unknown'}</p>
                    <p><strong>응답 시간:</strong> ${data.response_time || 'Unknown'}</p>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                </div>
            `);
            
            $('body').append(modal);
            modal.show();
        }

        // Export Data
        exportData(e) {
            e.preventDefault();
            
            if (!confirm('모든 데이터를 CSV로 내보내시겠습니까?')) {
                return;
            }

            this.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_export_data',
                    nonce: this.nonce
                },
                xhrFields: {
                    responseType: 'blob'
                },
                success: (blob) => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `3chan-pdf-export-${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    this.showNotification('데이터 내보내기 완료', 'success');
                },
                error: () => {
                    this.showNotification('데이터 내보내기 실패', 'error');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        // Clear Cache
        clearCache(e) {
            e.preventDefault();
            
            if (!confirm('캐시를 정리하시겠습니까?')) {
                return;
            }

            this.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_clear_cache',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('캐시가 정리되었습니다.', 'success');
                    } else {
                        this.showNotification('캐시 정리 실패', 'error');
                    }
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        // Optimize Database
        optimizeDatabase(e) {
            e.preventDefault();
            
            if (!confirm('데이터베이스를 최적화하시겠습니까?')) {
                return;
            }

            const $button = $(e.currentTarget);
            $button.prop('disabled', true).text('최적화 중...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_optimize_db',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('데이터베이스가 최적화되었습니다.', 'success');
                    } else {
                        this.showNotification('최적화 실패', 'error');
                    }
                },
                complete: () => {
                    $button.prop('disabled', false).text('데이터베이스 최적화');
                }
            });
        }

        // Reset Database
        resetDatabase(e) {
            e.preventDefault();
            
            const confirmFirst = confirm('정말로 모든 데이터를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.');
            if (!confirmFirst) return;
            
            const confirmSecond = confirm('한 번 더 확인합니다. 정말로 초기화하시겠습니까?');
            if (!confirmSecond) return;

            const $button = $(e.currentTarget);
            $button.prop('disabled', true).text('초기화 중...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_reset_db',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('데이터베이스가 초기화되었습니다.', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.showNotification('초기화 실패', 'error');
                    }
                },
                complete: () => {
                    $button.prop('disabled', false).text('데이터베이스 초기화');
                }
            });
        }

        // View Logs
        viewLogs(e) {
            e.preventDefault();
            
            this.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_get_logs',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showLogsModal(response.data);
                    } else {
                        this.showNotification('로그 불러오기 실패', 'error');
                    }
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        // Show Logs Modal
        showLogsModal(logs) {
            let logContent = '';
            
            if (logs && logs.length > 0) {
                logs.forEach(log => {
                    logContent += `
                        <div class="log-entry">
                            <span class="log-timestamp">${log.timestamp}</span>
                            <span class="log-level ${log.level}">${log.level.toUpperCase()}</span>
                            <div class="log-message">${log.message}</div>
                        </div>
                    `;
                });
            } else {
                logContent = '<p>로그가 없습니다.</p>';
            }

            const modal = this.createModal('시스템 로그', `
                <div class="log-viewer">
                    ${logContent}
                </div>
            `);
            
            $('body').append(modal);
            modal.show();
        }

        // Clear Logs
        clearLogs(e) {
            e.preventDefault();
            
            if (!confirm('로그를 삭제하시겠습니까?')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_clear_logs',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('로그가 삭제되었습니다.', 'success');
                    } else {
                        this.showNotification('로그 삭제 실패', 'error');
                    }
                }
            });
        }

        // View File
        viewFile(e) {
            e.preventDefault();
            
            const fileId = $(e.currentTarget).data('file-id');
            window.open(`${this.apiUrl}/view/${fileId}`, '_blank');
        }

        // Delete File
        deleteFile(e) {
            e.preventDefault();
            
            if (!confirm('이 파일을 삭제하시겠습니까?')) {
                return;
            }

            const $button = $(e.currentTarget);
            const fileId = $button.data('file-id');
            const $row = $button.closest('tr');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_delete_file',
                    file_id: fileId,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $row.fadeOut(() => {
                            $row.remove();
                        });
                        this.showNotification('파일이 삭제되었습니다.', 'success');
                    } else {
                        this.showNotification('삭제 실패: ' + response.data, 'error');
                    }
                }
            });
        }

        // Save Settings
        saveSettings(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const formData = $form.serialize();

            this.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=3chan_save_settings&nonce=' + this.nonce,
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message, 'success');
                    } else {
                        this.showNotification('설정 저장 실패: ' + response.data, 'error');
                    }
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        // Update Color Preview
        updateColorPreview() {
            const color = $(this).val();
            $('.color-preview').css('background-color', color);
        }

        // Handle Tab Click
        handleTabClick(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const target = $tab.attr('href');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            $('.tab-content').hide();
            $(target).show();
        }

        // Handle Bulk Action
        handleBulkAction() {
            const action = $('#bulk-action-selector').val();
            const checked = $('input[name="bulk-select[]"]:checked');
            
            if (!action) return;
            if (checked.length === 0) {
                alert('항목을 선택하세요.');
                return;
            }

            const ids = checked.map(function() {
                return $(this).val();
            }).get();

            switch (action) {
                case 'delete':
                    this.bulkDelete(ids);
                    break;
                case 'export':
                    this.bulkExport(ids);
                    break;
            }
        }

        // Bulk Delete
        bulkDelete(ids) {
            if (!confirm(`${ids.length}개 항목을 삭제하시겠습니까?`)) {
                return;
            }

            this.showLoading();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: '3chan_bulk_delete',
                    ids: ids,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('항목이 삭제되었습니다.', 'success');
                        window.location.reload();
                    } else {
                        this.showNotification('삭제 실패', 'error');
                    }
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        // Create Modal
        createModal(title, content) {
            const modal = $(`
                <div class="threechan-modal">
                    <div class="threechan-modal-content">
                        <div class="threechan-modal-header">
                            <h2>${title}</h2>
                            <span class="threechan-modal-close">&times;</span>
                        </div>
                        <div class="threechan-modal-body">
                            ${content}
                        </div>
                        <div class="threechan-modal-footer">
                            <button class="button" onclick="$(this).closest('.threechan-modal').remove()">닫기</button>
                        </div>
                    </div>
                </div>
            `);

            return modal;
        }

        // Close Modal
        closeModal() {
            $(this).closest('.threechan-modal').remove();
        }

        // Show Notification
        showNotification(message, type = 'info') {
            const notification = $(`
                <div class="notice threechan-notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">이 알림 무시하기</span>
                    </button>
                </div>
            `);

            $('.wrap h1').after(notification);

            // Auto dismiss after 5 seconds
            setTimeout(() => {
                notification.fadeOut(() => {
                    notification.remove();
                });
            }, 5000);

            // Manual dismiss
            notification.find('.notice-dismiss').on('click', function() {
                notification.fadeOut(() => {
                    notification.remove();
                });
            });
        }

        // Show Loading
        showLoading() {
            if (!$('#threechan-loading').length) {
                $('body').append('<div id="threechan-loading" class="threechan-loading"></div>');
            }
        }

        // Hide Loading
        hideLoading() {
            $('#threechan-loading').remove();
        }

        // Initialize Charts (if needed)
        initCharts() {
            // Placeholder for chart initialization
            // Can be implemented with Chart.js or other library
        }

        // Initialize Tooltips
        initTooltips() {
            $('.threechan-tooltip').each(function() {
                const $this = $(this);
                const text = $this.data('tooltip');
                if (text) {
                    $this.append(`<span class="tooltiptext">${text}</span>`);
                }
            });
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        window.threechanAdmin = new ThreeChanAdminManager();
    });

})(jQuery);