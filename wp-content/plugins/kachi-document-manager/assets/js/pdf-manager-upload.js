/**
 * 3chan PDF Manager - Upload Module V3
 * WebSocket 전용 실시간 업로드 상태 관리 + 작업 취소 기능
 * 파일 업로드 완료 후 즉시 초기화
 * 성공/실패 메시지 제거 버전
 */

(function($) {
    'use strict';

    // Upload Module with WebSocket Support
    window.PDFManagerUpload = {
        duplicateFiles: [],
        overwriteDecisions: {},
        pendingFiles: null,
        activeTasks: new Map(),
        updateInterval: null,
        cancellingTasks: new Set(),
        isUploading: false,
        
        init: function() {
            this.bindEvents();
            this.createTasksContainer();
            this.createDuplicateModal();
            this.fetchInitialTasks();
            this.startPeriodicUpdate();
            console.log("✅ PDF Manager Upload Module V3 초기화 완료");
        },
        
        // 중복 체크 모달 생성
        createDuplicateModal: function() {
            // 이미 모달이 존재하면 생성하지 않음
            if (document.getElementById('duplicateCheckModal')) {
                return;
            }
            
            const modal = document.createElement('div');
            modal.id = 'duplicateCheckModal';
            modal.className = 'modal duplicate-check-modal';
            modal.style.display = 'none';
            document.body.appendChild(modal);
            console.log("✅ Duplicate check modal created");
        },
        
        startPeriodicUpdate: function() {
            // 더 빠른 업데이트를 위해 500ms로 변경
            this.updateInterval = setInterval(() => {
                this.fetchInitialTasks(true);
            }, 500);
        },
        
        stopPeriodicUpdate: function() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
                this.updateInterval = null;
            }
        },
        
        fetchInitialTasks: async function(silent = false) {
            try {
                const response = await window.PDFManagerAPI.request('tasks/', { 
                    params: {
                        sosok: window.userSosok || '',
                        site: window.userSite || ''
                    }
                });
                const data = await response.json();
                
                if (data.tasks) {
                    if (!silent) {
                        console.log("📋 Tasks loaded:", data.tasks.length);
                    }
                    this.updateTaskList(data.tasks);
                }
            } catch (error) {
                if (!silent) {
                    console.error("❌ Failed to fetch tasks:", error);
                }
            }
        },

        cancelTask: async function(taskId) {
            if (this.cancellingTasks.has(taskId)) {
                console.log("⏳ Already cancelling task:", taskId);
                return;
            }
            
            if (!confirm('이 작업을 취소하시겠습니까?')) {
                return;
            }
            
            this.cancellingTasks.add(taskId);
            
            const cancelBtn = document.querySelector(`[data-cancel-task-id="${taskId}"]`);
            if (cancelBtn) {
                cancelBtn.disabled = true;
                cancelBtn.innerHTML = '<span class="btn-icon">⏳</span>';
            }
            
            try {
                console.log("🚫 Cancelling task:", taskId);
                
                const response = await window.PDFManagerAPI.request(`cancel-task/${taskId}`, {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    await this.fetchInitialTasks();
                    
                    if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                        window.PDFManagerUI.notification.show('작업이 취소되었습니다.', 'info');
                    }
                } else {
                    throw new Error(data.message || '작업 취소 실패');
                }
                
            } catch (error) {
                console.error('❌ Failed to cancel task:', error);
                alert('작업 취소 중 오류가 발생했습니다: ' + error.message);
                
                if (cancelBtn) {
                    cancelBtn.disabled = false;
                    cancelBtn.innerHTML = '<span class="btn-icon">✕</span>';
                }
            } finally {
                this.cancellingTasks.delete(taskId);
            }
        },

        updateConnectionStatus: function(connected) {
            const indicator = document.querySelector('.connection-indicator');
            if (indicator) {
                indicator.className = `connection-indicator ${connected ? 'connected' : 'disconnected'}`;
            }
        },

        updateTaskList: function(tasks) {
            const container = document.getElementById('activeTasksContainer');
            const taskListEl = document.getElementById('activeTasksList');
            
            if (!container || !taskListEl) return;

            // 작업이 있으면 무조건 컨테이너 표시
            const hasTasks = tasks.length > 0;
            
            // 처리 중인 작업(uploading, queued, processing)이 있으면 항상 표시
            const hasActiveTasks = tasks.some(task => 
                ['uploading', 'queued', 'processing'].includes(task.status)
            );
            
            // 활성 작업이 있거나 전체 작업이 있으면 표시
            container.style.display = (hasTasks || hasActiveTasks) ? 'block' : 'none';

            const taskMap = new Map(tasks.map(task => [task.id, task]));
            
            const existingElements = taskListEl.querySelectorAll('.task-item');
            existingElements.forEach(el => {
                const taskId = el.getAttribute('data-task-id');
                if (!taskMap.has(taskId)) {
                    el.classList.add('task-removing');
                    setTimeout(() => el.remove(), 300);
                    this.activeTasks.delete(taskId);
                }
            });

            tasks.forEach(task => {
                const existingEl = taskListEl.querySelector(`[data-task-id="${task.id}"]`);
                
                if (existingEl) {
                    this.updateTaskElement(existingEl, task);
                } else {
                    const taskEl = this.createTaskElement(task);
                    taskListEl.appendChild(taskEl);
                    
                    // 새로운 작업이 추가되면 애니메이션 효과
                    taskEl.style.animation = 'slideInUp 0.3s ease-out';
                }
                
                // Check if task is completed
                if (task.status === 'completed' && !this.activeTasks.has(task.id)) {
                    this.onTaskCompleted(task);
                }
                
                this.activeTasks.set(task.id, task);
            });

            this.updateClearButtonVisibility();
        },

        createTaskElement: function(task) {
            const taskEl = document.createElement('div');
            taskEl.className = 'task-item';
            taskEl.setAttribute('data-task-id', task.id);
            this.updateTaskElement(taskEl, task);
            return taskEl;
        },

        updateTaskElement: function(taskEl, task) {
            const statusIcon = this.getStatusIcon(task.status);
            const statusClass = this.getStatusClass(task.status);
            const progressPercent = task.progress || 0;
            
            const canCancel = ['uploading', 'queued'].includes(task.status);
            const isCancelling = this.cancellingTasks.has(task.id);

            taskEl.className = `task-item ${statusClass}`;

            taskEl.innerHTML = `
                <div class="task-header">
                    <span class="task-icon">${statusIcon}</span>
                    <span class="task-filename" title="${task.filename}">${task.filename}</span>
                    ${canCancel ? `
                        <button class="task-cancel-btn" 
                                data-cancel-task-id="${task.id}"
                                onclick="PDFManagerUpload.cancelTask('${task.id}')"
                                ${isCancelling ? 'disabled' : ''}
                                title="작업 취소">
                            <span class="btn-icon">${isCancelling ? '⏳' : '✕'}</span>
                        </button>
                    ` : ''}
                </div>
                <div class="task-status">
                    <span class="status-text">${task.message || '대기 중...'}</span>
                    ${task.processed_pages && task.total_pages ? 
                        `<span class="page-progress">(${task.processed_pages}/${task.total_pages} 페이지)</span>` : 
                        ''}
                </div>
                <div class="task-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progressPercent}%"></div>
                    </div>
                    <span class="progress-text">${progressPercent}%</span>
                </div>
            `;

            if (taskEl.classList.contains('task-status-processing')) {
                taskEl.classList.add('task-updated');
                setTimeout(() => taskEl.classList.remove('task-updated'), 300);
            }
        },

        onTaskCompleted: function(task) {
            // 완료 알림만 표시 (성공 메시지는 제거)
            if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                window.PDFManagerUI.notification.show(
                    `"${task.filename}" 처리가 완료되었습니다.`, 
                    'success'
                );
            }

            // 문서 목록 새로고침
            if (window.PDFManagerDocuments && window.PDFManagerDocuments.fetchStoredFiles) {
                window.PDFManagerDocuments.fetchStoredFiles();
            }
        },

        createTasksContainer: function() {
            if (document.getElementById('activeTasksContainer')) {
                return;
            }

            const uploadForm = document.querySelector('.upload-form');
            if (!uploadForm) return;

            const container = document.createElement('div');
            container.id = 'activeTasksContainer';
            container.className = 'active-tasks-container';
            container.style.display = 'none';
            container.innerHTML = `
                <div class="tasks-header">
                    <h3>
                        <span class="tasks-icon">⏳</span> 처리 중인 작업
                        <span class="connection-indicator disconnected"></span>
                    </h3>
                    <button id="clearCompletedBtn" class="clear-completed-btn" onclick="PDFManagerUpload.clearCompletedTasks()" style="display: none;">
                        완료 작업 모두 제거
                    </button>
                </div>
                <div id="activeTasksList" class="active-tasks-list"></div>
            `;

            uploadForm.parentNode.insertBefore(container, uploadForm.nextSibling);
        },

        updateClearButtonVisibility: function() {
            const clearBtn = document.getElementById('clearCompletedBtn');
            if (!clearBtn) return;
            
            const hasCompletedTasks = Array.from(this.activeTasks.values()).some(task => 
                task.status === 'completed' || task.status === 'failed'
            );
            
            clearBtn.style.display = hasCompletedTasks ? 'block' : 'none';
        },

        clearCompletedTasks: async function() {
            console.log("🗑️ Clearing completed tasks...");
            
            const completedTaskIds = [];
            this.activeTasks.forEach((task, taskId) => {
                if (task.status === 'completed' || task.status === 'failed') {
                    completedTaskIds.push(taskId);
                }
            });
            
            if (completedTaskIds.length === 0) {
                console.log("ℹ️ No completed tasks to clear");
                return;
            }
            
            console.log(`🗑️ Removing ${completedTaskIds.length} completed tasks from UI`);
            
            completedTaskIds.forEach(taskId => {
                const taskEl = document.querySelector(`[data-task-id="${taskId}"]`);
                if (taskEl) {
                    taskEl.classList.add('task-removing');
                }
            });
            
            setTimeout(() => {
                completedTaskIds.forEach(taskId => {
                    const taskEl = document.querySelector(`[data-task-id="${taskId}"]`);
                    if (taskEl) {
                        taskEl.remove();
                    }
                    this.activeTasks.delete(taskId);
                });
                
                this.updateTaskListVisibility();
                this.updateClearButtonVisibility();
            }, 300);
            
            try {
                console.log("📡 Sending dismiss request to server...");
                console.log("sosok:", window.userSosok, "site:", window.userSite);
                
                const formData = new FormData();
                formData.append('sosok', window.userSosok || '');
                formData.append('site', window.userSite || '');
                
                const response = await window.PDFManagerAPI.request('dismiss-completed-tasks', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log("✅ Server response:", data);
                
                if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                    window.PDFManagerUI.notification.show(
                        `${completedTaskIds.length}개의 완료된 작업이 제거되었습니다.`, 
                        'info'
                    );
                }
                
            } catch (error) {
                console.error('❌ Failed to dismiss tasks on server:', error);
            }
        },
        
        updateTaskListVisibility: function() {
            const container = document.getElementById('activeTasksContainer');
            if (!container) return;
            
            const hasTasks = this.activeTasks.size > 0;
            container.style.display = hasTasks ? 'block' : 'none';
        },

        getStatusIcon: function(status) {
            const icons = {
                'uploading': '📤',
                'queued': '⏳',
                'processing': '⚙️',
                'completed': '✅',
                'failed': '❌'
            };
            return icons[status] || '📄';
        },

        getStatusClass: function(status) {
            return `task-status-${status}`;
        },

        bindEvents: function() {
            const fileInput = document.getElementById('pdfFileEmbedPDF');
            const uploadConfirmBtn = document.getElementById('uploadConfirmBtn');
            const uploadCancelBtn = document.getElementById('uploadCancelBtn');
            
            if (fileInput) {
                fileInput.addEventListener("change", this.handleFileSelection.bind(this));
            }
            
            if (uploadConfirmBtn) {
                uploadConfirmBtn.addEventListener("click", this.handleUpload.bind(this));
            }
            
            if (uploadCancelBtn) {
                uploadCancelBtn.addEventListener('click', this.handleUploadCancel.bind(this));
            }

            window.addEventListener('beforeunload', () => {
                this.stopPeriodicUpdate();
            });
        },

        handleFileSelection: async function(e) {
            const fileInput = e.target;
            const files = fileInput.files;

            if (!files || files.length === 0) return;

            this.pendingFiles = files;

            const allowedExts = ['pdf', 'docx', 'pptx', 'hwpx'];
            const extSet = new Set();

            for (let f of files) {
                const ext = f.name.split('.').pop().toLowerCase();
                extSet.add(ext);
            }

            if (extSet.size > 1) {
                alert("❌ 서로 다른 형식의 파일은 동시에 업로드할 수 없습니다.");
                fileInput.value = "";
                this.pendingFiles = null;
                return;
            }

            const ext = [...extSet][0];
            if (!allowedExts.includes(ext)) {
                alert("❌ 지원되지 않는 파일 형식입니다.");
                fileInput.value = "";
                this.pendingFiles = null;
                return;
            }

            const selectedFileInfo = document.getElementById('selectedFileInfo');
            const dropzone = document.querySelector('.upload-dropzone');

            if (files.length > 0) {
                selectedFileInfo.style.display = "block";
                const fileListEl = selectedFileInfo.querySelector('.selected-files-list');
                
                let fileListHTML = '';
                for (let i = 0; i < files.length; i++) {
                    fileListHTML += `
                        <div class="response-filename" title="${files[i].name}">
                            <span class="file-number">${i + 1}.</span> 
                            <span class="file-name">${files[i].name}</span>
                            <div class="file-progress-bar" style="display: none;">
                                <div class="file-progress-fill"></div>
                            </div>
                        </div>`;
                }
                fileListEl.innerHTML = fileListHTML;
                
                if (dropzone) dropzone.style.display = "none";
            }

            // 중복 체크 설정 확인
            const duplicateCheckEnabled = window.threechan_pdf_ajax?.enable_duplicate_check;
            console.log("🔍 Duplicate check enabled:", duplicateCheckEnabled);
            
            if (duplicateCheckEnabled === true || duplicateCheckEnabled === 'true' || duplicateCheckEnabled === '1') {
                await this.checkDuplicates(files);
            } else {
                this.proceedWithFileProcessing();
            }
        },

        proceedWithFileProcessing: function() {
            const files = this.pendingFiles;
            if (!files || files.length === 0) return;

            const uploadConfirmBox = document.getElementById('uploadConfirmBox');
            const selectedFileInfo = document.getElementById('selectedFileInfo');
            const fileListEl = selectedFileInfo.querySelector('.selected-files-list');
            
            let fileListHTML = '';
            for (let i = 0; i < files.length; i++) {
                const isDuplicate = this.duplicateFiles.some(dup => dup.filename === files[i].name);
                const duplicateClass = isDuplicate ? 'duplicate-file' : '';
                const duplicateIcon = isDuplicate ? '⚠️ ' : '';
                fileListHTML += `<div class="response-filename ${duplicateClass}" title="${files[i].name}">${i + 1}. ${duplicateIcon}${files[i].name}</div>`;
            }
            fileListEl.innerHTML = fileListHTML;

            uploadConfirmBox.style.display = "block";
        },

        checkDuplicates: async function(files) {
            const filenames = Array.from(files).map(f => f.name);
            console.log("🔍 Checking duplicates for files:", filenames);
            
            try {
                const formData = new FormData();
                formData.append('action', '3chan_check_duplicate');
                formData.append('nonce', window.threechan_pdf_ajax.nonce);
                formData.append('sosok', window.userSosok || '');
                formData.append('site', window.userSite || '');
                
                filenames.forEach(filename => {
                    formData.append('filenames[]', filename);
                });

                console.log("📡 Sending duplicate check request...");
                const response = await fetch(window.threechan_pdf_ajax.ajax_url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();
                console.log("📡 Duplicate check response:", data);

                if (data?.success && data?.data?.duplicates && data.data.duplicates.length > 0) {
                    console.log("⚠️ Duplicates found:", data.data.duplicates);
                    this.duplicateFiles = data.data.duplicates;
                    this.showDuplicateModal(data.data.duplicates);
                } else {
                    console.log("✅ No duplicates found");
                    this.duplicateFiles = [];
                    this.overwriteDecisions = {};
                    this.proceedWithFileProcessing();
                }
            } catch (error) {
                console.error('❌ Duplicate check error:', error);
                // 에러 발생 시에도 업로드는 진행
                this.duplicateFiles = [];
                this.proceedWithFileProcessing();
            }
        },

        showDuplicateModal: function(duplicates) {
            console.log("🔔 Showing duplicate modal for:", duplicates);
            
            let modal = document.getElementById('duplicateCheckModal');
            if (!modal) {
                console.log("⚠️ Modal not found, creating new one");
                this.createDuplicateModal();
                modal = document.getElementById('duplicateCheckModal');
            }

            if (!modal) {
                console.error("❌ Failed to create modal");
                // 모달 생성 실패 시 기본 confirm 사용
                if (confirm(`다음 파일들이 이미 존재합니다:\n${duplicates.map(d => d.filename).join('\n')}\n\n덮어쓰시겠습니까?`)) {
                    duplicates.forEach(dup => {
                        this.overwriteDecisions[dup.filename] = 'overwrite';
                    });
                } else {
                    this.resetUploadForm();
                    return;
                }
                this.proceedWithFileProcessing();
                return;
            }

            modal.innerHTML = `
                <div class="modal-content" style="
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                    max-width: 600px;
                    width: 90%;
                    max-height: 90vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                ">
                    <div class="modal-header" style="
                        padding: 15px 20px;
                        border-bottom: 1px solid #e0e0e0;
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        background-color: #f8f9fa;
                    ">
                        <div>
                            <h2 class="modal-title" style="margin: 0; font-size: 1.5rem; font-weight: 600;">중복 파일 발견</h2>
                            <p class="modal-subtitle" style="margin: 4px 0 0 0; font-size: 0.9rem; color: #666;">동일한 이름의 파일이 이미 존재합니다</p>
                        </div>
                        <button class="close-btn" onclick="PDFManagerUpload.closeDuplicateModal()" style="
                            background: none;
                            border: none;
                            font-size: 1.5rem;
                            cursor: pointer;
                            color: #666;
                            padding: 0;
                            width: 30px;
                            height: 30px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">✕</button>
                    </div>
                    <div class="modal-body" style="
                        padding: 20px;
                        overflow-y: auto;
                        flex: 1;
                    ">
                        <div class="duplicate-warning" style="
                            background-color: #fff3cd;
                            border: 1px solid #ffeaa7;
                            border-radius: 8px;
                            padding: 15px;
                            margin-bottom: 20px;
                            display: flex;
                            align-items: center;
                            gap: 15px;
                        ">
                            <span class="warning-icon" style="font-size: 2rem; flex-shrink: 0;">⚠️</span>
                            <div class="warning-text">
                                <p style="margin: 0; color: #856404;">다음 파일들이 이미 존재합니다. 어떻게 처리할까요?</p>
                            </div>
                        </div>
                        <div class="duplicate-list" style="max-height: 300px; overflow-y: auto;">
                            ${this.renderDuplicateList(duplicates)}
                        </div>
                    </div>
                    <div class="modal-footer" style="
                        padding: 15px 20px;
                        border-top: 1px solid #e0e0e0;
                        display: flex;
                        justify-content: flex-end;
                        gap: 10px;
                        background-color: #f8f9fa;
                    ">
                        <div class="duplicate-actions" style="display: flex; gap: 10px;">
                            <button class="btn btn-secondary" onclick="PDFManagerUpload.handleDuplicateDecision('cancel')" style="
                                padding: 8px 20px;
                                border: 1px solid #ccc;
                                background: white;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 1rem;
                            ">
                                취소
                            </button>
                            <button class="btn btn-primary" onclick="PDFManagerUpload.handleDuplicateDecision('apply')" style="
                                padding: 8px 20px;
                                border: none;
                                background: #ff9800;
                                color: white;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 1rem;
                            ">
                                선택 적용
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // 모달 스타일 강제 적용
            modal.style.cssText = `
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background-color: rgba(0, 0, 0, 0.5) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                z-index: 9999 !important;
                backdrop-filter: blur(4px);
            `;
            
            console.log("✅ Modal displayed with inline styles");
        },

        renderDuplicateList: function(duplicates) {
            // 파일명별로 그룹화
            const grouped = {};
            duplicates.forEach(dup => {
                if (!grouped[dup.filename]) grouped[dup.filename] = [];
                grouped[dup.filename].push(dup);
            });

            let html = '';
            Object.entries(grouped).forEach(([filename, dups]) => {
                // 첫 번째 중복 파일의 정보만 사용 (여러 개가 있어도)
                const firstDup = dups[0];
                const uploadDate = firstDup.upload_date ? 
                    new Date(firstDup.upload_date).toLocaleDateString('ko-KR') : 
                    '날짜 정보 없음';
                
                html += `
                    <div class="duplicate-item" style="
                        background-color: #f8f9fa;
                        border: 1px solid #dee2e6;
                        border-radius: 6px;
                        padding: 15px;
                        margin-bottom: 10px;
                    ">
                        <div class="duplicate-filename" style="
                            font-weight: 600;
                            font-size: 1rem;
                            margin-bottom: 8px;
                            color: #333;
                        ">📄 ${filename}</div>
                        <div class="duplicate-info" style="
                            font-size: 0.85rem;
                            color: #666;
                            margin-bottom: 10px;
                        ">
                            <span class="duplicate-date">업로드: ${uploadDate}</span>
                        </div>
                        <div class="duplicate-item-actions" style="
                            display: flex;
                            gap: 20px;
                        ">
                            <label class="radio-option" style="
                                display: flex;
                                align-items: center;
                                gap: 6px;
                                cursor: pointer;
                                font-size: 0.9rem;
                            ">
                                <input type="radio" name="action-${filename}" value="overwrite" checked style="cursor: pointer;">
                                <span>덮어쓰기</span>
                            </label>
                            <label class="radio-option" style="
                                display: flex;
                                align-items: center;
                                gap: 6px;
                                cursor: pointer;
                                font-size: 0.9rem;
                            ">
                                <input type="radio" name="action-${filename}" value="keep-both">
                                <span>모두 유지</span>
                            </label>
                        </div>
                    </div>
                `;
            });
            return html;
        },

        closeDuplicateModal: function() {
            const modal = document.getElementById('duplicateCheckModal');
            if (modal) {
                modal.style.display = 'none';
                console.log("✅ Modal closed");
            }
        },

        handleDuplicateDecision: function(decision) {
            console.log("🔔 Duplicate decision:", decision);
            this.closeDuplicateModal();

            if (decision === 'cancel') {
                this.resetUploadForm();
                return;
            }

            if (decision === 'apply') {
                const modal = document.getElementById('duplicateCheckModal');
                const radios = modal.querySelectorAll('input[type="radio"]:checked');
                
                this.overwriteDecisions = {};
                radios.forEach(radio => {
                    const filename = radio.name.replace('action-', '');
                    this.overwriteDecisions[filename] = radio.value;
                    console.log(`📝 Decision for ${filename}: ${radio.value}`);
                });
            }

            this.proceedWithFileProcessing();
        },

        handleUpload: async function() {
            // 이미 업로드 중이면 무시
            if (this.isUploading) {
                console.log("Already uploading...");
                return;
            }
            
            const files = this.pendingFiles || document.getElementById('pdfFileEmbedPDF').files;
            if (!files || !files.length) return;
            
            // 업로드 중 상태 설정
            this.isUploading = true;
            
            // Get all UI elements
            const loadingBox = document.getElementById('loadingEmbedPDF');
            const loadingTextEl = loadingBox ? loadingBox.querySelector('.loading-text') : null;
            const responseBox = document.getElementById('responseBoxEmbedPDF');
            const uploadConfirmBox = document.getElementById('uploadConfirmBox');
            const selectedFileInfo = document.getElementById('selectedFileInfo');
            const dropzone = document.querySelector('.upload-dropzone');
            
            // Hide upload confirm box immediately
            if (uploadConfirmBox) uploadConfirmBox.style.display = 'none';
            if (selectedFileInfo) selectedFileInfo.style.display = 'none';
            
            // Show loading for file upload ONLY
            if (loadingTextEl) {
                loadingTextEl.textContent = '파일 업로드 중...';
            }
            if (loadingBox) {
                loadingBox.style.display = 'block';
            }
            
            try {
                // Delete existing files if overwrite selected
                const filesToDelete = [];
                for (const [filename, decision] of Object.entries(this.overwriteDecisions)) {
                    if (decision === 'overwrite') {
                        const duplicateInfo = this.duplicateFiles.filter(dup => dup.filename === filename);
                        filesToDelete.push(...duplicateInfo);
                    }
                }
                
                if (filesToDelete.length > 0) {
                    console.log("🗑️ Deleting duplicate files:", filesToDelete);
                    for (const fileInfo of filesToDelete) {
                        if (fileInfo.file_id) {
                            await window.PDFManagerAPI.request('delete-document/', {
                                params: { file_id: fileInfo.file_id },
                                method: 'DELETE'
                            });
                        }
                    }
                }
                
                // Prepare upload
                const formData = new FormData();
                for (let i = 0; i < files.length; i++) {
                    formData.append("files[]", files[i]);
                }

                const tagValue = document.getElementById("tagInput").value.trim();
                if (tagValue) formData.append("tags", tagValue);
                if (window.userSosok) formData.append("sosok", window.userSosok);
                if (window.userSite) formData.append("site", window.userSite);

                if (Object.keys(this.overwriteDecisions).length > 0) {
                    formData.append("overwrite_decisions", JSON.stringify(this.overwriteDecisions));
                }

                // Send files to server with progress tracking
                console.log("📤 Sending files to server with progress tracking...");
                
                const data = await this.uploadWithProgress(formData);
                
                console.log("📡 Upload completed with data:", data);

                if (data.status === 'accepted' && data.task_ids) {
                    // SUCCESS - Upload completed, reset immediately
                    console.log("✅ Files uploaded successfully, resetting immediately");
                    
                    // Update progress to 100% 
                    this.updateProgressBars(100);
                    
                    // Hide loading immediately
                    if (loadingBox) {
                        loadingBox.style.display = 'none';
                    }
                    
                    // Reset form immediately
                    this.resetUploadForm();
                    
                    // Show dropzone again immediately
                    if (dropzone) {
                        dropzone.style.display = 'block';
                    }
                    
                    // Clear tag input immediately
                    const tagInput = document.getElementById("tagInput");
                    if (tagInput) {
                        tagInput.value = '';
                    }
                    
                    // Hide response box immediately
                    if (responseBox) {
                        responseBox.style.display = 'none';
                        responseBox.innerHTML = '';
                    }
                    
                    console.log("📝 Upload form reset - ready for new files");
                    
                    // Show brief success notification
                    this.showUploadSuccessNotification(data.task_ids.length);
                    
                    // Fetch tasks in background - NO WAITING, completely non-blocking
                    this.fetchInitialTasks().catch(err => {
                        console.warn("Background task fetch failed:", err);
                    });
                    
                    // Schedule additional refreshes in background
                    setTimeout(() => {
                        this.fetchInitialTasks().catch(err => {
                            console.warn("Scheduled task fetch failed:", err);
                        });
                    }, 500);
                    
                } else {
                    throw new Error(data.message || '업로드 실패');
                }

            } catch (err) {
                console.error("❌ Upload error:", err);
                
                // Hide loading immediately on error
                if (loadingBox) {
                    loadingBox.style.display = 'none';
                }
                
                // Reset form on error too
                this.resetUploadForm();
                
                // Show dropzone
                if (dropzone) {
                    dropzone.style.display = 'block';
                }
                
                // Hide response box (no error message)
                if (responseBox) {
                    responseBox.style.display = 'none';
                    responseBox.innerHTML = '';
                }
                
                // Only show alert for critical errors
                if (!err.message.includes('타임아웃') && !err.message.includes('timeout')) {
                    alert(`업로드 오류: ${err.message}`);
                }
                
            } finally {
                // ALWAYS reset upload state
                this.isUploading = false;
            }
        },

        resetUploadForm: function() {
            const fileInput = document.getElementById('pdfFileEmbedPDF');
            const selectedFileInfo = document.getElementById('selectedFileInfo');
            const uploadConfirmBox = document.getElementById('uploadConfirmBox');
            const dropzone = document.querySelector('.upload-dropzone');
            const loadingBox = document.getElementById('loadingEmbedPDF');
            
            // 파일 입력 초기화
            if (fileInput) fileInput.value = "";
            
            // UI 요소 초기화
            if (selectedFileInfo) selectedFileInfo.style.display = "none";
            if (uploadConfirmBox) uploadConfirmBox.style.display = "none";
            if (loadingBox) loadingBox.style.display = "none";
            
            // 드롭존 다시 표시
            if (dropzone) dropzone.style.display = "block";
            
            // 내부 상태 초기화
            this.duplicateFiles = [];
            this.overwriteDecisions = {};
            this.pendingFiles = null;
            
            // 업로드 중 상태 해제
            this.isUploading = false;
            
            console.log("✅ Upload form reset");
        },

        handleUploadCancel: function() {
            this.resetUploadForm();
        },
        
        uploadWithProgress: function(formData) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                
                // Track upload progress
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        console.log(`📈 Upload progress: ${percentComplete}%`);
                        this.updateUploadProgress(percentComplete);
                    }
                });
                
                // Handle completion
                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            // Handle WordPress AJAX response format
                            if (response.success && response.data) {
                                // Start task polling for uploaded files
                                if (response.data.files && window.PDFManagerTaskPolling) {
                                    const fileIds = response.data.files.map(file => file.file_id);
                                    window.PDFManagerTaskPolling.startPollingForFiles(fileIds);
                                }
                                resolve(response.data);
                            } else if (response.status === 'uploaded') {
                                // Direct API response from simple upload
                                if (response.files && window.PDFManagerTaskPolling) {
                                    const fileIds = response.files.map(file => file.file_id);
                                    window.PDFManagerTaskPolling.startPollingForFiles(fileIds);
                                }
                                resolve(response);
                            } else {
                                reject(new Error(response.message || '업로드 실패'));
                            }
                        } catch (e) {
                            console.error("❌ Failed to parse response:", e);
                            reject(new Error('서버 응답 파싱 실패'));
                        }
                    } else {
                        reject(new Error(`HTTP ${xhr.status}: 업로드 실패`));
                    }
                });
                
                // Handle errors
                xhr.addEventListener('error', () => {
                    reject(new Error('네트워크 오류'));
                });
                
                xhr.addEventListener('timeout', () => {
                    reject(new Error('업로드 타임아웃'));
                });
                
                // Configure request
                xhr.timeout = 300000; // 5 minutes for large files
                
                // Prepare form data for WordPress AJAX
                const ajaxFormData = new FormData();
                ajaxFormData.append('action', '3chan_proxy_api');
                ajaxFormData.append('nonce', window.threechan_pdf_ajax.nonce);
                ajaxFormData.append('endpoint', 'upload-simple/');
                ajaxFormData.append('method', 'POST');
                
                // Copy only files for simple upload
                for (let [key, value] of formData.entries()) {
                    if (key === 'files') {
                        ajaxFormData.append(key, value);
                    }
                }
                
                // Start upload
                xhr.open('POST', window.threechan_pdf_ajax.ajax_url);
                xhr.send(ajaxFormData);
            });
        },
        
        updateUploadProgress: function(percent) {
            // Update loading text with progress
            const loadingTextEl = document.querySelector('#loadingEmbedPDF .loading-text');
            if (loadingTextEl) {
                loadingTextEl.textContent = `파일 업로드 중... ${percent}%`;
            }
            
            // Update any progress bars (will be implemented next)
            this.updateProgressBars(percent);
        },
        
        updateProgressBars: function(percent) {
            // Update the main progress bar in loading section
            const progressFill = document.querySelector('#loadingEmbedPDF .progress-fill');
            if (progressFill) {
                progressFill.style.width = percent + '%';
            }
            
            // Show and update individual file progress bars
            const fileProgressBars = document.querySelectorAll('.file-progress-bar');
            const fileProgressFills = document.querySelectorAll('.file-progress-fill');
            
            fileProgressBars.forEach(bar => {
                bar.style.display = 'block';
            });
            
            fileProgressFills.forEach(fill => {
                fill.style.width = percent + '%';
            });
            
            // Update loading text with progress
            const loadingTextEl = document.querySelector('#loadingEmbedPDF .loading-text');
            if (loadingTextEl) {
                if (percent < 100) {
                    loadingTextEl.textContent = `파일 업로드 중... ${percent}%`;
                } else {
                    loadingTextEl.textContent = '업로드 완료!';
                }
            }
        },
        
        showUploadSuccessNotification: function(fileCount) {
            // Create temporary success notification
            const notification = document.createElement('div');
            notification.className = 'upload-success-notification';
            notification.innerHTML = `
                <div class="notification-content">
                    <span class="notification-icon">✅</span>
                    <span class="notification-text">${fileCount}개 파일 전송됨</span>
                </div>
            `;
            
            // Add to page
            const container = document.querySelector('.upload-section');
            if (container) {
                container.appendChild(notification);
                
                // Show with animation
                setTimeout(() => {
                    notification.classList.add('show');
                }, 100);
                
                // Remove after 3 seconds
                setTimeout(() => {
                    notification.classList.add('hide');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 500);
                }, 3000);
            }
        }
    };

    // Make functions accessible globally
    window.PDFManagerUpload.closeDuplicateModal = window.PDFManagerUpload.closeDuplicateModal.bind(window.PDFManagerUpload);
    window.PDFManagerUpload.handleDuplicateDecision = window.PDFManagerUpload.handleDuplicateDecision.bind(window.PDFManagerUpload);
    window.PDFManagerUpload.clearCompletedTasks = window.PDFManagerUpload.clearCompletedTasks.bind(window.PDFManagerUpload);
    window.PDFManagerUpload.cancelTask = window.PDFManagerUpload.cancelTask.bind(window.PDFManagerUpload);

})(jQuery);