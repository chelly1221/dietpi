/**
 * 3chan PDF Manager - Upload Module
 * 파일 업로드 기능 (PDF 여백 설정 제거됨)
 */

(function($) {
    'use strict';

    // Upload Module
    window.PDFManagerUpload = {
        duplicateFiles: [],
        overwriteDecisions: {},
        pendingFiles: null, // Store files temporarily
        
        init: function() {
            this.bindEvents();
            console.log("✅ PDF Manager Upload Module 초기화 완료");
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
        },

        handleFileSelection: async function(e) {
            console.log("📁 File selection started");
            
            const fileInput = e.target;
            const files = fileInput.files;

            if (!files || files.length === 0) {
                console.log("❌ No files selected");
                return;
            }

            console.log(`📋 Selected ${files.length} files:`, Array.from(files).map(f => f.name));

            // Store files for later use
            this.pendingFiles = files;

            // Validate files
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

            // Check for legacy formats
            if (['doc', 'ppt', 'hwp'].some(x => files[0].name.toLowerCase().endsWith(`.${x}`))) {
                alert("⚠️ .doc, .ppt, .hwp 파일은 업로드할 수 없습니다. 먼저 .docx, .pptx, .hwpx로 변환해주세요.");
                fileInput.value = "";
                this.pendingFiles = null;
                return;
            }

            // Update UI
            const selectedFileInfo = document.getElementById('selectedFileInfo');
            const dropzone = document.querySelector('.upload-dropzone');

            if (files.length > 0) {
                selectedFileInfo.style.display = "block";
                const fileListEl = selectedFileInfo.querySelector('.selected-files-list');
                
                let fileListHTML = '';
                for (let i = 0; i < files.length; i++) {
                    fileListHTML += `<div class="response-filename" title="${files[i].name}">${i + 1}. ${files[i].name}</div>`;
                }
                fileListEl.innerHTML = fileListHTML;
                
                if (dropzone) dropzone.style.display = "none";
            }

            // Check if duplicate check is enabled
            const duplicateCheckEnabled = window.threechan_pdf_ajax.enable_duplicate_check;
            console.log("🔍 Duplicate check enabled:", duplicateCheckEnabled);
            console.log("🔍 Duplicate check type:", typeof duplicateCheckEnabled);

            if (duplicateCheckEnabled === true || duplicateCheckEnabled === 'true' || duplicateCheckEnabled === '1') {
                console.log("✅ Starting duplicate check...");
                await this.checkDuplicates(files);
            } else {
                console.log("⏭️ Duplicate check disabled, proceeding directly");
                // If duplicate check is disabled, proceed directly
                this.proceedWithFileProcessing();
            }
        },

        proceedWithFileProcessing: function() {
            console.log("🚀 Proceeding with file processing");
            
            const files = this.pendingFiles;
            if (!files || files.length === 0) {
                console.error("❌ No pending files to process");
                return;
            }

            const uploadConfirmBox = document.getElementById('uploadConfirmBox');

            // Update UI with duplicate indicators
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

            // Show upload confirm box directly (no PDF margin settings)
            uploadConfirmBox.style.display = "block";
        },

        checkDuplicates: async function(files) {
            const filenames = Array.from(files).map(f => f.name);
            
            console.log('🔍 Checking duplicates for files:', filenames);
            console.log('🏢 Current sosok:', window.userSosok);
            console.log('📍 Current site:', window.userSite);
            console.log('🔐 Nonce:', window.threechan_pdf_ajax.nonce);
            console.log('📡 AJAX URL:', window.threechan_pdf_ajax.ajax_url);
            
            try {
                console.log('📤 Sending duplicate check request...');
                
                // Use fetch instead of jQuery ajax for truly async behavior
                const formData = new FormData();
                formData.append('action', '3chan_check_duplicate');
                formData.append('nonce', window.threechan_pdf_ajax.nonce);
                formData.append('sosok', window.userSosok || '');
                formData.append('site', window.userSite || '');
                
                // Add filenames
                filenames.forEach(filename => {
                    formData.append('filenames[]', filename);
                });

                const response = await fetch(window.threechan_pdf_ajax.ajax_url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                console.log('✅ Response received:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('✅ Parsed response:', data);

                if (data && data.success && data.data && data.data.duplicates && data.data.duplicates.length > 0) {
                    console.log('⚠️ Duplicates found:', data.data.duplicates);
                    this.duplicateFiles = data.data.duplicates;
                    
                    // Show duplicate modal
                    this.showDuplicateModal(data.data.duplicates);
                } else {
                    console.log('✨ No duplicates found');
                    this.duplicateFiles = [];
                    this.overwriteDecisions = {};
                    // Proceed with file processing
                    this.proceedWithFileProcessing();
                }
            } catch (error) {
                console.error('❌ Duplicate check error:', error);
                
                this.duplicateFiles = [];
                // Proceed anyway on error
                this.proceedWithFileProcessing();
            }
        },

        showDuplicateModal: function(duplicates) {
            console.log('📋 Showing duplicate modal for:', duplicates);
            
            // Create modal if it doesn't exist
            let modal = document.getElementById('duplicateCheckModal');
            if (!modal) {
                console.log('🔨 Creating modal element');
                modal = document.createElement('div');
                modal.id = 'duplicateCheckModal';
                modal.className = 'modal duplicate-check-modal';
                modal.innerHTML = `
                    <div class="modal-content duplicate-modal-content">
                        <div class="modal-header duplicate-modal-header">
                            <div class="duplicate-modal-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="10" stroke="#ff9800" stroke-width="2"/>
                                    <path d="M12 8V12" stroke="#ff9800" stroke-width="2" stroke-linecap="round"/>
                                    <circle cx="12" cy="16" r="1" fill="#ff9800"/>
                                </svg>
                            </div>
                            <div class="duplicate-modal-title-group">
                                <h2 class="modal-title duplicate-modal-title">중복 파일 발견</h2>
                                <p class="duplicate-modal-subtitle">동일한 이름의 파일이 이미 존재합니다</p>
                            </div>
                            <button class="close-btn duplicate-close-btn" onclick="PDFManagerUpload.closeDuplicateModal()">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="modal-body duplicate-modal-body">
                            <!-- Quick Actions Bar -->
                            <div class="duplicate-quick-actions">
                                <div class="quick-actions-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 6V18M6 12H18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    빠른 선택
                                </div>
                                <div class="quick-action-buttons">
                                    <button class="quick-action-btn overwrite-all" onclick="PDFManagerUpload.selectAllOverwrite()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M7 7H17V20C17 20.5523 16.5523 21 16 21H8C7.44772 21 7 20.5523 7 20V7Z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M5 7H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M9 3H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                        모두 덮어쓰기
                                    </button>
                                    <button class="quick-action-btn keep-all" onclick="PDFManagerUpload.selectAllKeep()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="6" y="6" width="12" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                                            <path d="M9 3H16C17.1046 3 18 3.89543 18 5V12" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        모두 유지
                                    </button>
                                </div>
                            </div>
                            
                            <div class="duplicate-list"></div>
                        </div>
                        
                        <div class="modal-footer duplicate-modal-footer">
                            <div class="duplicate-summary">
                                <span class="summary-icon">📋</span>
                                <span class="summary-text">중복 파일 <span class="duplicate-count">0</span>개</span>
                            </div>
                            <div class="duplicate-modal-actions">
                                <button class="btn duplicate-btn duplicate-btn-cancel" onclick="PDFManagerUpload.handleDuplicateDecision('cancel')">
                                    취소
                                </button>
                                <button class="btn duplicate-btn duplicate-btn-primary" onclick="PDFManagerUpload.handleDuplicateDecision('apply')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    선택 적용
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                console.log('✅ Modal element created and appended to body');
            }

            // Update duplicate list with enhanced design
            const listEl = modal.querySelector('.duplicate-list');
            let listHTML = '';
            
            // Group duplicates by filename
            const groupedDuplicates = {};
            duplicates.forEach(dup => {
                if (!groupedDuplicates[dup.filename]) {
                    groupedDuplicates[dup.filename] = [];
                }
                groupedDuplicates[dup.filename].push(dup);
            });
            
            // Display grouped duplicates with enhanced design
            let totalDuplicates = 0;
            Object.entries(groupedDuplicates).forEach(([filename, dups]) => {
                totalDuplicates += dups.length;
                const fileExt = filename.split('.').pop().toLowerCase();
                const fileIcon = {
                    pdf: '📕',
                    docx: '📘',
                    pptx: '📙',
                    hwpx: '📗'
                }[fileExt] || '📄';
                
                listHTML += `
                    <div class="duplicate-group">
                        <div class="duplicate-filename">
                            <span class="file-icon">${fileIcon}</span>
                            ${filename}
                            ${dups.length > 1 ? `<span class="duplicate-count-badge">${dups.length}개</span>` : ''}
                        </div>
                `;
                
                // Show all existing duplicates with enhanced design
                dups.forEach((dup, index) => {
                    const uploadDate = dup.upload_date ? new Date(dup.upload_date).toLocaleDateString('ko-KR', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    }) : '날짜 미상';
                    
                    // 파일명만 표시하도록 수정
                    listHTML += `
                        <div class="existing-file-info">
                            <span class="file-number">${index + 1}</span>
                            <div class="file-info-content">
                                <span class="duplicate-date">📅 ${uploadDate}</span>
                                <span style="font-weight: 500; color: #212529;">${dup.filename || filename}</span>
                            </div>
                        </div>
                    `;
                });
                
                listHTML += `
                        <div class="duplicate-item-actions" data-filename="${filename}">
                            <label class="radio-option" onclick="this.classList.add('selected'); this.nextElementSibling.classList.remove('selected');">
                                <input type="radio" name="action-${filename}" value="overwrite" checked>
                                <span class="radio-label">
                                    <span class="radio-icon overwrite">🔄</span>
                                    덮어쓰기
                                </span>
                            </label>
                            <label class="radio-option" onclick="this.classList.add('selected'); this.previousElementSibling.classList.remove('selected');">
                                <input type="radio" name="action-${filename}" value="keep-both">
                                <span class="radio-label">
                                    <span class="radio-icon keep">📄</span>
                                    모두 유지
                                </span>
                            </label>
                        </div>
                    </div>
                `;
            });
            
            listEl.innerHTML = listHTML;
            
            // Update duplicate count
            const countEl = modal.querySelector('.duplicate-count');
            if (countEl) {
                countEl.textContent = totalDuplicates;
            }

            // Show modal with animation
            console.log('🎭 Displaying modal');
            modal.style.display = 'flex';
            
            // Add initial selected state to checked radio options
            setTimeout(() => {
                modal.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                    radio.closest('.radio-option').classList.add('selected');
                });
            }, 100);
        },

        // Helper functions for quick selection
        selectAllOverwrite: function() {
            const radios = document.querySelectorAll('input[type="radio"][value="overwrite"]');
            radios.forEach(radio => {
                radio.checked = true;
                const option = radio.closest('.radio-option');
                if (option) {
                    option.classList.add('selected');
                    const siblingOption = option.nextElementSibling;
                    if (siblingOption) siblingOption.classList.remove('selected');
                }
            });
        },

        selectAllKeep: function() {
            const radios = document.querySelectorAll('input[type="radio"][value="keep-both"]');
            radios.forEach(radio => {
                radio.checked = true;
                const option = radio.closest('.radio-option');
                if (option) {
                    option.classList.add('selected');
                    const siblingOption = option.previousElementSibling;
                    if (siblingOption) siblingOption.classList.remove('selected');
                }
            });
        },

        closeDuplicateModal: function() {
            console.log('🚪 Closing duplicate modal');
            const modal = document.getElementById('duplicateCheckModal');
            if (modal) {
                modal.style.animation = 'modalFadeOut 0.3s ease-out';
                setTimeout(() => {
                    modal.style.display = 'none';
                    modal.style.animation = '';
                }, 300);
            }
        },

        handleDuplicateDecision: function(decision) {
            console.log('📝 Handling duplicate decision:', decision);
            
            this.closeDuplicateModal();

            if (decision === 'cancel') {
                console.log('❌ User cancelled upload');
                this.resetUploadForm();
                return;
            }

            if (decision === 'apply') {
                // Get individual decisions from radio buttons
                const modal = document.getElementById('duplicateCheckModal');
                const groups = modal.querySelectorAll('.duplicate-group');
                
                this.overwriteDecisions = {};
                
                groups.forEach(group => {
                    const filename = group.querySelector('.duplicate-item-actions').getAttribute('data-filename');
                    const selectedRadio = group.querySelector(`input[name="action-${filename}"]:checked`);
                    
                    if (selectedRadio) {
                        this.overwriteDecisions[filename] = selectedRadio.value;
                    }
                });
                
                console.log('📝 Individual overwrite decisions:', this.overwriteDecisions);
            }

            // Continue with file processing
            this.proceedWithFileProcessing();
        },

        // Delete existing files before upload
        deleteExistingFiles: async function(filesToDelete) {
            console.log('🗑️ Deleting existing files:', filesToDelete);
            
            const deletePromises = [];
            
            for (const fileInfo of filesToDelete) {
                if (fileInfo.file_id) {
                    const deletePromise = window.PDFManagerAPI.request('delete-document/', {
                        params: { file_id: fileInfo.file_id },
                        method: 'DELETE'
                    }).then(response => {
                        if (response.ok) {
                            console.log(`✅ Deleted file: ${fileInfo.filename} (${fileInfo.file_id})`);
                            return true;
                        } else {
                            console.error(`❌ Failed to delete file: ${fileInfo.filename}`);
                            return false;
                        }
                    }).catch(error => {
                        console.error(`❌ Error deleting file: ${fileInfo.filename}`, error);
                        return false;
                    });
                    
                    deletePromises.push(deletePromise);
                }
            }
            
            if (deletePromises.length > 0) {
                const results = await Promise.all(deletePromises);
                const successCount = results.filter(r => r).length;
                console.log(`📊 Deleted ${successCount} out of ${deletePromises.length} files`);
                
                // Add a delay to ensure deletion is complete
                await new Promise(resolve => setTimeout(resolve, 500));
            }
            
            return deletePromises.length;
        },

        handleUpload: async function() {
            // Use pendingFiles instead of getting from input again
            const files = this.pendingFiles || document.getElementById('pdfFileEmbedPDF').files;
            if (!files || !files.length) return;
            
            // First, handle deletion for overwrite files
            const filesToDelete = [];
            for (const [filename, decision] of Object.entries(this.overwriteDecisions)) {
                if (decision === 'overwrite') {
                    const duplicateInfo = this.duplicateFiles.filter(dup => dup.filename === filename);
                    filesToDelete.push(...duplicateInfo);
                }
            }
            
            let deletedCount = 0;
            if (filesToDelete.length > 0) {
                console.log('🗑️ Deleting existing files before upload...');
                const loadingBox = document.getElementById('loadingEmbedPDF');
                const loadingText = loadingBox.querySelector('.loading-text');
                if (loadingText) {
                    loadingText.textContent = '기존 파일 삭제 중...';
                }
                loadingBox.style.display = 'block';
                
                deletedCount = await this.deleteExistingFiles(filesToDelete);
                
                if (loadingText) {
                    loadingText.textContent = 'AI 처리 중...';
                }
            }
            
            // Now proceed with upload
            const formData = new FormData();

            // Add files
            for (let i = 0; i < files.length; i++) {
                formData.append("files[]", files[i]);
            }

            // Add metadata
            const tagValue = document.getElementById("tagInput").value.trim();
            if (tagValue) {
                formData.append("tags", tagValue);
            }
            if (window.userSosok) {
                formData.append("sosok", window.userSosok);
            }
            if (window.userSite) {
                formData.append("site", window.userSite);
            }

            // Add overwrite decisions (backend will just log them now)
            if (Object.keys(this.overwriteDecisions).length > 0) {
                formData.append("overwrite_decisions", JSON.stringify(this.overwriteDecisions));
                console.log('📤 Sending overwrite decisions:', this.overwriteDecisions);
            }

            // Update UI
            const loadingBox = document.getElementById('loadingEmbedPDF');
            const responseBox = document.getElementById('responseBoxEmbedPDF');
            const uploadConfirmBox = document.getElementById('uploadConfirmBox');
            
            loadingBox.style.display = 'block';
            responseBox.innerHTML = '';
            responseBox.style.display = 'none';
            uploadConfirmBox.style.display = 'none';

            try {
                console.log("📤 Uploading files...");
                const res = await window.PDFManagerAPI.request('upload-pdf/', {
                    method: 'POST',
                    body: formData
                });

                const contentType = res.headers.get('content-type') || "";

                if (res.ok && contentType.includes('application/json')) {
                    const data = await res.json();
                    console.log("✅ Upload success - Full response:", data);

                    this.displayUploadResults(data, files, responseBox, deletedCount);
                    
                    // Clear form
                    document.getElementById("tagInput").value = '';
                    
                    // Reset duplicate tracking
                    this.duplicateFiles = [];
                    this.overwriteDecisions = {};
                    this.pendingFiles = null;
                    
                    // Refresh file list while preserving ongoing tag updates
                    if (window.PDFManagerDocuments) {
                        // Use skipLoadingAnimation to avoid disrupting the UI
                        window.PDFManagerDocuments.fetchStoredFiles(true);
                        window.PDFManagerDocuments.fetchTags();
                    }
                    
                    // Show notification
                    if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                        window.PDFManagerUI.notification.show('파일이 성공적으로 업로드되었습니다!', 'success');
                    }
                } else {
                    await this.displayUploadError(res, contentType, responseBox);
                }

            } catch (err) {
                console.error("❌ Upload error:", err);
                responseBox.style.color = 'red';
                responseBox.innerHTML = `<div class="upload-error">
                                            <div class="error-icon">❌</div>
                                            <h3>업로드 실패</h3>
                                            <p>${err.message}</p>
                                        </div>`;
                responseBox.style.display = 'block';
            } finally {
                loadingBox.style.display = 'none';
                this.resetUploadForm();
            }
        },

        displayUploadResults: function(data, files, responseBox, deletedCount) {
            if (data.results && Array.isArray(data.results)) {
                console.log(`📊 Processing ${data.results.length} results`);
                let html = '';
                
                data.results.forEach((item, index) => {
                    console.log(`📄 Result ${index + 1}:`, item);
                    const statusIcon = item.status === 'success' || item.status === '성공' ? '✅' : '❌';
                    const overwriteIcon = this.overwriteDecisions[item.original_filename] === 'overwrite' ? '🔄' : statusIcon;
                    
                    html += `
                        <div class="result-item">
                            <div class="result-filename">${overwriteIcon} ${item.original_filename}</div>
                            <div class="result-info">
                                <span class="info-label">상태:</span> ${item.status}
                                ${this.overwriteDecisions[item.original_filename] === 'overwrite' && deletedCount > 0 ? 
                                    `<br><span class="info-label info-overwrite">🔄 기존 파일 ${deletedCount}개를 삭제하고 덮어씀</span>` : ''}
                                ${item.kept_both ? `<br><span class="info-label info-duplicate">📄📄 중복 파일로 저장됨</span>` : ''}
                                ${item.num_pages ? `<br><span class="info-label">분할:</span> ${item.num_pages}개 페이지` : ''}
                                ${item.total_pdf_pages ? `<br><span class="info-label">전체 페이지:</span> ${item.total_pdf_pages}페이지` : ''}
                                ${item.message ? `<br><span class="info-label">메시지:</span> ${item.message}` : ''}
                            </div>
                        </div>
                    `;
                });
                responseBox.innerHTML = html;
            } else if (data.status) {
                console.log("📄 Single result:", data);
                responseBox.innerHTML = `
                    <div class="result-item">
                        <div class="result-filename">📄 ${data.original_filename || files[0].name}</div>
                        <div class="result-info">
                            <span class="info-label">상태:</span> ${data.status}
                            ${data.num_pages ? `<br><span class="info-label">분할:</span> ${data.num_pages}개 페이지` : ''}
                        </div>
                    </div>
                `;
            } else {
                console.warn("⚠️ Unexpected response format:", data);
                responseBox.innerHTML = '<div class="result-item"><div class="result-info">업로드 완료</div></div>';
            }
            
            responseBox.style.display = 'block';
            responseBox.style.color = '#333';
        },

        displayUploadError: async function(res, contentType, responseBox) {
            let errorMsg = '❌ 오류 발생: ';
            if (contentType.includes('application/json')) {
                const errorData = await res.json();
                errorMsg += errorData.detail ? JSON.stringify(errorData.detail, null, 2) : JSON.stringify(errorData);
            } else {
                const errorText = await res.text();
                errorMsg += errorText;
            }
            responseBox.style.color = 'red';
            responseBox.innerHTML = `<div class="upload-error">
                                        <div class="error-icon">❌</div>
                                        <h3>업로드 실패</h3>
                                        <pre>${errorMsg}</pre>
                                    </div>`;
            responseBox.style.display = 'block';
        },

        resetUploadForm: function() {
            const fileInput = document.getElementById('pdfFileEmbedPDF');
            const selectedFileInfo = document.getElementById('selectedFileInfo');
            const uploadConfirmBox = document.getElementById('uploadConfirmBox');
            const dropzone = document.querySelector('.upload-dropzone');
            
            fileInput.value = "";
            selectedFileInfo.style.display = "none";
            uploadConfirmBox.style.display = "none";
            
            if (dropzone) dropzone.style.display = "block";
            
            // Reset duplicate tracking
            this.duplicateFiles = [];
            this.overwriteDecisions = {};
            this.pendingFiles = null;
        },

        handleUploadCancel: function() {
            this.resetUploadForm();
        }
    };

    // Make duplicate modal functions accessible globally
    window.PDFManagerUpload.closeDuplicateModal = window.PDFManagerUpload.closeDuplicateModal.bind(window.PDFManagerUpload);
    window.PDFManagerUpload.handleDuplicateDecision = window.PDFManagerUpload.handleDuplicateDecision.bind(window.PDFManagerUpload);
    window.PDFManagerUpload.selectAllOverwrite = window.PDFManagerUpload.selectAllOverwrite.bind(window.PDFManagerUpload);
    window.PDFManagerUpload.selectAllKeep = window.PDFManagerUpload.selectAllKeep.bind(window.PDFManagerUpload);

})(jQuery);