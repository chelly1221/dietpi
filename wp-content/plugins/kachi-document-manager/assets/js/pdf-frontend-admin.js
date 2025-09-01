/**
 * 3chan PDF Manager - Frontend Admin JavaScript
 */

(function($) {
    'use strict';

    // Admin Manager Class
    class PDFAdminManager {
        constructor() {
            this.init();
        }

        init() {
            // DOM이 완전히 로드된 후 실행
            $(window).on('load', () => {
                this.bindEvents();
                this.loadDocuments();
                this.checkApiStatus();
            });
        }

        bindEvents() {
            // Tab Navigation
            $(document).on('click', '.pdf-fa-tab-item', this.handleTabClick.bind(this));
            
            // Settings Form
            $(document).on('submit', '#pdf-settings-form', this.saveSettings.bind(this));
            
            // Settings Form - Auto Save
            $(document).on('change', '#pdf-settings-form input, #pdf-settings-form select', this.autoSaveSettings.bind(this));
            
            // Color Picker
            $(document).on('change', '#primary_color', this.updateColorPreview);
            
            // Documents Actions
            $(document).on('click', '#refresh-documents', this.loadDocuments.bind(this));
            $(document).on('click', '#delete-selected', this.deleteSelectedDocuments.bind(this));
            
            // Quick Actions
            $(document).on('click', '#test-api-connection', this.testApiConnection.bind(this));
            $(document).on('click', '#clear-all-data', this.clearAllData.bind(this));
            
            // Prevent form submission on enter
            $(document).on('keypress', '#pdf-settings-form', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    return false;
                }
            });
        }

        // Handle Tab Click
        handleTabClick(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const section = $tab.data('section');
            
            if (!section) return;
            
            // Update tab active state
            $('.pdf-fa-tab-item').removeClass('active');
            $tab.addClass('active');
            
            // Show corresponding section
            $('.pdf-fa-section').removeClass('active');
            $(`.pdf-fa-section[data-section="${section}"]`).addClass('active');
        }

        // Load Documents
        loadDocuments() {
            const $container = $('#documentsListContainer');
            
            // Show loading
            $container.html(`
                <div class="pdf-fa-loading">
                    <div class="loading-spinner"></div>
                    <p>문서 목록을 불러오는 중...</p>
                </div>
            `);

            $.ajax({
                url: pdf_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: '3chan_fa_get_documents',
                    nonce: pdf_frontend.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayDocuments(response.data);
                        this.updateStats(response.data);
                    } else {
                        this.showNotification(response.data || '문서 목록을 불러올 수 없습니다.', 'error');
                        $container.html('<p>문서를 불러올 수 없습니다.</p>');
                    }
                },
                error: () => {
                    this.showNotification('문서 목록을 불러오는 중 오류가 발생했습니다.', 'error');
                    $container.html('<p>오류가 발생했습니다.</p>');
                }
            });
        }

        // Display Documents
        displayDocuments(data) {
            const $container = $('#documentsListContainer');
            const documents = data.documents || [];
            
            if (documents.length === 0) {
                $container.html(`
                    <div class="pdf-fa-empty-state">
                        <span class="pdf-fa-icon" style="font-size: 48px;">📭</span>
                        <p>업로드된 문서가 없습니다.</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            documents.forEach((doc, index) => {
                const fileIcon = this.getFileIcon(doc.file_type);
                const fileSize = this.formatFileSize(doc.file_size);
                const uploadDate = this.formatDate(doc.upload_date);
                const tags = doc.tags ? doc.tags.split(',').map(tag => `<span class="pdf-fa-tag">#${tag.trim()}</span>`).join(' ') : '';
                
                html += `
                    <div class="pdf-fa-document-item">
                        <input type="checkbox" class="pdf-fa-document-checkbox" value="${doc.file_id}">
                        <div class="pdf-fa-document-info">
                            <div class="pdf-fa-document-name">${fileIcon} ${doc.original_filename || doc.filename}</div>
                            <div class="pdf-fa-document-meta">
                                <span>📅 ${uploadDate}</span>
                                <span>💾 ${fileSize}</span>
                                ${doc.sosok ? `<span>🏢 ${doc.sosok}</span>` : ''}
                                ${doc.site ? `<span>📍 ${doc.site}</span>` : ''}
                            </div>
                            ${tags ? `<div class="pdf-fa-document-tags">${tags}</div>` : ''}
                        </div>
                        <div class="pdf-fa-document-actions">
                            <button class="pdf-fa-btn-icon view-document" data-file-id="${doc.file_id}" title="보기">
                                <span class="pdf-fa-icon">👁️</span>
                            </button>
                            <button class="pdf-fa-btn-icon delete-document" data-file-id="${doc.file_id}" title="삭제">
                                <span class="pdf-fa-icon">🗑️</span>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            $container.html(html);
        }

        // Update Stats
        updateStats(data) {
            $('#totalDocuments').text(data.total || 0);
            $('#totalSize').text(this.formatFileSize(data.total_size || 0));
            $('#totalTags').text(data.tags || 0);
        }

        // Delete Selected Documents
        deleteSelectedDocuments() {
            const selectedIds = [];
            $('.pdf-fa-document-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) {
                this.showNotification('삭제할 문서를 선택하세요.', 'error');
                return;
            }
            
            if (!confirm(`선택한 ${selectedIds.length}개의 문서를 삭제하시겠습니까?`)) {
                return;
            }
            
            $.ajax({
                url: pdf_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: '3chan_fa_delete_documents',
                    nonce: pdf_frontend.nonce,
                    file_ids: selectedIds
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message || '문서가 삭제되었습니다.', 'success');
                        this.loadDocuments();
                    } else {
                        this.showNotification(response.data || '삭제 실패', 'error');
                    }
                }
            });
        }

        // Save Settings
        saveSettings(e) {
            if (e) e.preventDefault();
            
            const formData = $('#pdf-settings-form').serialize();
            
            $.ajax({
                url: pdf_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: '3chan_fa_save_settings',
                    nonce: pdf_frontend.nonce,
                    form: formData
                },
                success: (response) => {
                    if (response.success) {
                        this.showSaveStatus();
                    } else {
                        this.showNotification(response.data || pdf_frontend.messages.save_error, 'error');
                    }
                }
            });
        }

        // Auto Save Settings
        autoSaveSettings() {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => {
                this.saveSettings();
            }, 1000);
        }

        // Show Save Status
        showSaveStatus() {
            const $status = $('.pdf-fa-save-status');
            $status.fadeIn(200);
            
            setTimeout(() => {
                $status.fadeOut(400);
            }, 3000);
        }

        // Check API Status
        checkApiStatus() {
            const $status = $('#apiStatus');
            $status.text('확인중...');
            
            $.ajax({
                url: pdf_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: '3chan_fa_test_api',
                    nonce: pdf_frontend.nonce
                },
                timeout: 10000,
                success: (response) => {
                    if (response.success) {
                        $status.text('정상').css('color', '#22c55e');
                    } else {
                        $status.text('오류').css('color', '#ef4444');
                    }
                },
                error: () => {
                    $status.text('오류').css('color', '#ef4444');
                }
            });
        }

        // Test API Connection
        testApiConnection() {
            const $button = $('#test-api-connection');
            $button.prop('disabled', true).text('테스트 중...');
            
            $.ajax({
                url: pdf_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: '3chan_fa_test_api',
                    nonce: pdf_frontend.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(pdf_frontend.messages.test_success, 'success');
                        this.checkApiStatus();
                    } else {
                        this.showNotification(response.data || pdf_frontend.messages.test_error, 'error');
                    }
                },
                complete: () => {
                    $button.prop('disabled', false).html('<span class="pdf-fa-icon">🔌</span><span>API 연결 테스트</span>');
                }
            });
        }

        // Clear All Data
        clearAllData() {
            if (!confirm(pdf_frontend.messages.clear_confirm)) {
                return;
            }
            
            if (!confirm('한 번 더 확인합니다. 정말로 모든 데이터를 삭제하시겠습니까?')) {
                return;
            }
            
            const $button = $('#clear-all-data');
            $button.prop('disabled', true).text('삭제 중...');
            
            $.ajax({
                url: pdf_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: '3chan_fa_clear_data',
                    nonce: pdf_frontend.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(pdf_frontend.messages.clear_success, 'success');
                        this.loadDocuments();
                    } else {
                        this.showNotification(response.data || '삭제 실패', 'error');
                    }
                },
                complete: () => {
                    $button.prop('disabled', false).html('<span class="pdf-fa-icon">🔥</span><span>모든 데이터 삭제</span>');
                }
            });
        }

        // Update Color Preview
        updateColorPreview() {
            const color = $(this).val();
            $('.color-preview').css('background-color', color);
        }

        // Show Notification
        showNotification(message, type = 'info') {
            const $notice = $('.pdf-fa-notice');
            
            $notice.removeClass('success error info').addClass(type);
            $notice.html(`
                <span class="pdf-fa-icon">${this.getNotificationIcon(type)}</span>
                <span>${message}</span>
            `);
            
            $notice.fadeIn(200);
            
            clearTimeout(this.notificationTimeout);
            this.notificationTimeout = setTimeout(() => {
                $notice.fadeOut(400);
            }, 5000);
        }

        // Get Notification Icon
        getNotificationIcon(type) {
            const icons = {
                success: '✅',
                error: '❌',
                info: 'ℹ️',
                warning: '⚠️'
            };
            return icons[type] || icons.info;
        }

        // Get File Icon
        getFileIcon(fileType) {
            const icons = {
                'pdf': '📕',
                'docx': '📘',
                'pptx': '📙',
                'hwpx': '📗'
            };
            return icons[fileType] || '📄';
        }

        // Format File Size
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Format Date
        formatDate(dateStr) {
            if (!dateStr) return '-';
            
            const date = new Date(dateStr);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            
            return `${year}-${month}-${day}`;
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        window.pdfAdminManager = new PDFAdminManager();
    });

})(jQuery);