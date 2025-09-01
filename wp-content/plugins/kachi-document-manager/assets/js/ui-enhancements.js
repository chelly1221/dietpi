/**
 * 3chan PDF Manager - UI Enhancement Functions
 */

(function($) {
    'use strict';

    // Notification System
    class NotificationManager {
        constructor() {
            this.container = this.createContainer();
        }

        createContainer() {
            const container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
            document.body.appendChild(container);
            return container;
        }

        show(message, type = 'info', duration = 3000) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <span class="notification-icon">${this.getIcon(type)}</span>
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.remove()">✕</button>
            `;

            this.container.appendChild(notification);

            // Auto remove
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }

        getIcon(type) {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            return icons[type] || icons.info;
        }
    }

    // File Type Detection and Icons
    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            pdf: '📕',
            docx: '📘',
            pptx: '📙',
            hwpx: '📗'
        };
        return icons[ext] || '📄';
    }

    // Progress Bar Manager
    class ProgressManager {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            this.progressBar = null;
        }

        show() {
            if (!this.container) return;
            
            this.container.innerHTML = `
                <div class="upload-progress-enhanced">
                    <div class="progress-spinner"></div>
                    <div class="progress-text">업로드 준비 중...</div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-percentage">0%</div>
                </div>
            `;
            
            this.progressBar = this.container.querySelector('.progress-bar-fill');
            this.simulateProgress();
        }

        update(percentage, text) {
            if (!this.progressBar) return;
            
            this.progressBar.style.width = percentage + '%';
            this.container.querySelector('.progress-percentage').textContent = percentage + '%';
            if (text) {
                this.container.querySelector('.progress-text').textContent = text;
            }
        }

        simulateProgress() {
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) {
                    clearInterval(interval);
                    progress = 90;
                }
                this.update(Math.min(progress, 90), '파일 처리 중...');
            }, 500);
        }

        complete() {
            this.update(100, '완료!');
            setTimeout(() => this.hide(), 1000);
        }

        hide() {
            if (this.container) {
                this.container.innerHTML = '';
            }
        }
    }

    // Enhanced File Preview
    function createFilePreview(files) {
        const preview = document.createElement('div');
        preview.className = 'file-preview-grid';
        
        Array.from(files).forEach(file => {
            const item = document.createElement('div');
            item.className = 'file-preview-item';
            item.innerHTML = `
                <div class="file-preview-icon">${getFileIcon(file.name)}</div>
                <div class="file-preview-info">
                    <div class="file-preview-name" title="${file.name}">${file.name}</div>
                    <div class="file-preview-size">${formatFileSize(file.size)}</div>
                </div>
                <button class="file-preview-remove" data-filename="${file.name}">✕</button>
            `;
            preview.appendChild(item);
        });
        
        return preview;
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Search Highlighting
    function highlightSearchTerm(text, searchTerm) {
        if (!searchTerm) return text;
        
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    // Keyboard Navigation
    function enableKeyboardNavigation() {
        let currentFocus = -1;
        const focusableElements = '.document-list li, .btn, .form-input, .search-input';
        
        document.addEventListener('keydown', (e) => {
            const elements = document.querySelectorAll(focusableElements);
            
            if (e.key === 'Tab') {
                // Default tab behavior
                return;
            }
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentFocus++;
                if (currentFocus >= elements.length) currentFocus = 0;
                elements[currentFocus]?.focus();
            }
            
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentFocus--;
                if (currentFocus < 0) currentFocus = elements.length - 1;
                elements[currentFocus]?.focus();
            }
            
            if (e.key === 'Enter' && e.target.classList.contains('clickable-filename')) {
                e.target.click();
            }
        });
    }

    // Drag and Drop Visual Feedback
    function enhanceDragAndDrop() {
        const dropzone = document.querySelector('.upload-dropzone');
        if (!dropzone) return;
        
        let dragCounter = 0;
        
        dropzone.addEventListener('dragenter', (e) => {
            e.preventDefault();
            dragCounter++;
            dropzone.classList.add('drag-over');
            dropzone.innerHTML = `
                <div class="dropzone-content">
                    <div class="upload-icon animate-bounce">📥</div>
                    <div class="upload-text">
                        <p class="upload-main-text">파일을 여기에 놓으세요!</p>
                    </div>
                </div>
            `;
        });
        
        dropzone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dragCounter--;
            if (dragCounter === 0) {
                dropzone.classList.remove('drag-over');
                resetDropzoneContent();
            }
        });
        
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dragCounter = 0;
            dropzone.classList.remove('drag-over');
            resetDropzoneContent();
        });
        
        function resetDropzoneContent() {
            dropzone.innerHTML = `
                <div class="dropzone-content">
                    <div class="upload-icon">📁</div>
                    <div class="upload-text">
                        <p class="upload-main-text">파일을 선택하거나 여기에 드래그하세요</p>
                        <p class="upload-sub-text">PDF, DOCX, PPTX, HWPX 파일 지원 (여러 개 가능)</p>
                    </div>
                </div>
            `;
        }
    }

    // Auto-save functionality
    class AutoSave {
        constructor() {
            this.saveTimeout = null;
            this.savedData = {};
        }

        track(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            
            field.addEventListener('input', () => {
                this.scheduleSave(fieldId, field.value);
            });
            
            // Restore saved data
            const saved = localStorage.getItem(`autosave_${fieldId}`);
            if (saved) {
                field.value = saved;
            }
        }

        scheduleSave(fieldId, value) {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => {
                localStorage.setItem(`autosave_${fieldId}`, value);
                this.showSaveIndicator();
            }, 1000);
        }

        showSaveIndicator() {
            const indicator = document.createElement('div');
            indicator.className = 'autosave-indicator';
            indicator.textContent = '자동 저장됨';
            document.body.appendChild(indicator);
            
            setTimeout(() => indicator.remove(), 2000);
        }

        clear(fieldId) {
            localStorage.removeItem(`autosave_${fieldId}`);
        }
    }

    // Export functions
    window.PDFManagerUI = {
        notification: new NotificationManager(),
        progress: new ProgressManager('loadingEmbedPDF'),
        autoSave: new AutoSave(),
        getFileIcon,
        createFilePreview,
        formatFileSize,
        highlightSearchTerm,
        enableKeyboardNavigation,
        enhanceDragAndDrop
    };

    // Initialize UI enhancements
    $(document).ready(function() {
        PDFManagerUI.enableKeyboardNavigation();
        PDFManagerUI.enhanceDragAndDrop();
        PDFManagerUI.autoSave.track('tagInput');
    });

})(jQuery);