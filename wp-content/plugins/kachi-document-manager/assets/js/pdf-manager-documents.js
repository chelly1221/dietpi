/**
 * 3chan PDF Manager - Documents Module
 * 문서 목록 관리, 검색, 필터링, 페이지 뷰어 기능
 * 이미지 URL은 저장하지 않고 표시할 때만 프록시로 변환
 */

(function($) {
    'use strict';

    // Documents Module
    window.PDFManagerDocuments = {
        init: function() {
            this.bindEvents();
            console.log("✅ PDF Manager Documents Module 초기화 완료");
        },

        bindEvents: function() {
            // Global function registration for inline onclick handlers
            window.filterFiles = this.filterFiles.bind(this);
            window.fetchStoredFiles = this.fetchStoredFiles.bind(this);
            window.fetchTags_manage = this.fetchTags.bind(this);
            window.deleteSelectedDocuments = this.deleteSelectedDocuments.bind(this);
            window.changePageSize = this.changePageSize.bind(this);
            window.prevPage = this.prevPage.bind(this);
            window.nextPage = this.nextPage.bind(this);
            window.removeSelectedTag_manage = this.removeSelectedTag.bind(this);
            window.clearSelectedTags_manage = this.clearSelectedTags.bind(this);
            window.toggleTagFilterMode = this.toggleTagFilterMode.bind(this);
            window.prevPopupPage = this.prevPopupPage.bind(this);
            window.nextPopupPage = this.nextPopupPage.bind(this);
            window.fetchPageContent = this.fetchPageContent.bind(this);
            window.openPageViewer = this.openPageViewer.bind(this);
            window.closePageViewer = this.closePageViewer.bind(this);
            window.toggleFilterPanel = this.toggleFilterPanel.bind(this);
            window.filterTagOptions_manage = this.filterTagOptions.bind(this);
            window.openTagEditModal = this.openTagEditModal.bind(this);
            window.updateDocumentTags = this.updateDocumentTags.bind(this);
            window.openTagManageModal = this.openTagManageModal.bind(this);
            window.saveTagManagement = this.saveTagManagement.bind(this);
            window.openTagRenameModal = this.openTagRenameModal.bind(this);
            window.renameTag = this.renameTag.bind(this);
            window.deleteTag = this.deleteTag.bind(this);
        },

        // Process image URLs in content to use proxy - 표시 전용, 원본 데이터 변경하지 않음
        processImageUrlsForDisplay: function(content) {
            if (!content) return content;
            
            // 원본 content의 복사본을 만들어 처리
            let displayContent = content;
            
            // Regular expression to find image URLs with :8001/images pattern (plural) including file extensions
            const imageUrlPattern = /(https?:\/\/[^:]+):8001\/images\/([a-zA-Z0-9_-]+\.[a-zA-Z]+)/g;
            
            // Replace image URLs with HTML img tags using proxy URLs for display only
            displayContent = displayContent.replace(imageUrlPattern, (match, baseUrl, imageFilename) => {
                // Reconstruct the full URL with file extension
                const fullUrl = baseUrl + ':8001/images/' + imageFilename;
                const proxyUrl = this.createProxyImageUrl(fullUrl);
                console.log('🖼️ Converting image URL to HTML img tag:', match, '→', proxyUrl);
                return `<img src="${proxyUrl}" alt="Document Image" style="max-width: 100%; height: auto; display: block; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;" loading="lazy">`;
            });
            
            // Also handle img tags with src attributes - updated for /images path with file extensions
            displayContent = displayContent.replace(/<img([^>]+)src=["']([^"']+:8001\/images\/[^"'\.]+\.[^"']+)["']([^>]*)>/gi, (match, before, imageUrl, after) => {
                const proxyUrl = this.createProxyImageUrl(imageUrl);
                return `<img ${before}src="${proxyUrl}" ${after}>`;
            });
            
            return displayContent;
        },

        // Create proxy URL for images
        createProxyImageUrl: function(originalUrl) {
            const config = window.PDFManagerConfig;
            
            // Manually construct URL parameters to avoid double encoding
            // URLSearchParams automatically encodes the image_url parameter
            const encodedUrl = encodeURIComponent(originalUrl);
            const params = `action=3chan_proxy_image&nonce=${config.NONCE}&image_url=${encodedUrl}`;
            
            // Return the proxy URL
            return config.AJAX_URL + '?' + params;
        },

        // Fetch stored files
        fetchStoredFiles: async function(skipLoadingAnimation = false) {
            console.log("📥 Fetching stored files...");
            const listEl = document.getElementById("storedFilesList_manage");
            if (!listEl) return;
            
            // Don't show loading animation if there are ongoing tag updates
            if (!skipLoadingAnimation && this.ongoingTagUpdates.size === 0) {
                window.PDFManagerUtils.showLoadingAnimation();
            }

            try {
                const params = {};
                if (window.userSosok) params.sosok = window.userSosok;
                if (window.userSite) params.site = window.userSite;

                const response = await window.PDFManagerAPI.request('list-documents/', { params });
                const data = await response.json();
                console.log("✅ Documents received:", data);

                const state = window.PDFManagerState;
                
                // Store ongoing updates before updating the file list
                const ongoingUpdates = new Map();
                if (this.ongoingTagUpdates.size > 0) {
                    this.ongoingTagUpdates.forEach(fileId => {
                        const fileItem = Array.from(document.querySelectorAll('.document-list li')).find(li => {
                            const checkbox = li.querySelector('input[data-file-id]');
                            return checkbox && checkbox.getAttribute('data-file-id') === fileId;
                        });
                        if (fileItem) {
                            const tagContainer = fileItem.querySelector('.tag-pill-container');
                            if (tagContainer && tagContainer.querySelector('.tag-update-loading')) {
                                ongoingUpdates.set(fileId, tagContainer.innerHTML);
                            }
                        }
                    });
                }
                
                if (data.documents && data.documents.length > 0) {
                    state.allFiles = data.documents;
                    state.filteredFiles = [...state.allFiles];
                    state.currentPage = 1;
                    window.PDFManagerUtils.updateDocumentCount();
                    this.renderPage();
                    
                    // Restore loading states for ongoing updates
                    if (ongoingUpdates.size > 0) {
                        setTimeout(() => {
                            ongoingUpdates.forEach((loadingHtml, fileId) => {
                                const fileItem = Array.from(document.querySelectorAll('.document-list li')).find(li => {
                                    const checkbox = li.querySelector('input[data-file-id]');
                                    return checkbox && checkbox.getAttribute('data-file-id') === fileId;
                                });
                                if (fileItem) {
                                    const tagContainer = fileItem.querySelector('.tag-pill-container');
                                    if (tagContainer) {
                                        tagContainer.innerHTML = loadingHtml;
                                    }
                                }
                            });
                        }, 10);
                    }
                } else {
                    listEl.innerHTML = `
                        <div class="document-list-loading">
                            <div class="loading-container">
                                <div class="folder-loading-icon">
                                    <div class="folder-icon-base" style="animation: none;"></div>
                                </div>
                                <div class="loading-text-animated" style="color: var(--text-secondary);">
                                    📂 저장된 파일이 없습니다.
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById("paginationControls_manage").style.display = "none";
                    window.PDFManagerUtils.updateDocumentCount();
                }
            } catch (err) {
                console.error("❌ Document list error:", err);
                listEl.innerHTML = `
                    <div class="document-list-loading">
                        <div class="loading-container">
                            <div class="loading-text-animated" style="color: var(--danger-color);">
                                📛 목록 불러오기 실패: ${err.message}
                            </div>
                        </div>
                    </div>
                `;
                window.PDFManagerUtils.updateDocumentCount();
            }
        },

        // Fetch tags
        fetchTags: async function() {
            console.log("📥 Fetching tags...");
            const params = {};
            if (window.userSosok) params.sosok = window.userSosok;
            if (window.userSite) params.site = window.userSite;

            try {
                const res = await window.PDFManagerAPI.request('list-tags/', { params });
                const data = await res.json();
                console.log("✅ Tags received:", data);

                if (Array.isArray(data.tags)) {
                    window.allTagOptions = data.tags;
                    this.renderTagOptions(data.tags);
                } else {
                    console.warn("⚠️ 예상치 못한 태그 응답 구조:", data);
                    this.renderTagOptions([]);
                }
            } catch (err) {
                console.error("❌ 태그 목록 불러오기 실패:", err);
                this.renderTagOptions([]);
            }
        },

        // Filter files by search query
        filterFiles: function() {
            const state = window.PDFManagerState;
            
            if (!Array.isArray(state.allFiles) || state.allFiles.length === 0) {
                console.warn("🔍 검색: allFiles 목록이 비어있음. 목록 다시 로드합니다.");
                this.fetchStoredFiles();
                return;
            }
            
            const searchInput = document.getElementById("searchInput_manage");
            if (!searchInput) return;
            
            const query = searchInput.value.toLowerCase();
            state.filteredFiles = state.allFiles.filter(file => {
                const filename = file.filename || file.original_filename || '';
                return filename.toLowerCase().includes(query);
            });
            state.currentPage = 1;
            this.renderPage();
        },

        // Filter by tags
        filterByTags: async function() {
            const state = window.PDFManagerState;
            
            if (state.selectedTags.length === 0) {
                this.clearSelectedTags();
                return;
            }

            const params = {};
            state.selectedTags.forEach(tag => {
                if (!params.tags) params.tags = [];
                params.tags.push(tag);
            });
            if (window.userSosok) params.sosok = window.userSosok;
            if (window.userSite) params.site = window.userSite;

            try {
                const res = await window.PDFManagerAPI.request('filter-documents-by-tags/', { params });
                const data = await res.json();
                
                console.log("✅ Filter response:", data);
                console.log("📊 Number of documents:", data.documents?.length);

                state.allFiles = data.documents || [];
                state.filteredFiles = [...state.allFiles];
                state.currentPage = 1;
                window.PDFManagerUtils.updateDocumentCount();
                this.renderPage();

            } catch (err) {
                alert("❌ 태그로 필터링 실패: " + err.message);
            }
        },

        // Render current page
        renderPage: function() {
            const state = window.PDFManagerState;
            const listEl = document.getElementById("storedFilesList_manage");
            const pageInfo = document.getElementById("pageInfo_manage");
            const paginationControls = document.getElementById("paginationControls_manage");
            
            if (!listEl) return;
            
            const startIndex = (state.currentPage - 1) * state.pageSize;
            const endIndex = startIndex + state.pageSize;
            const filesToShow = state.filteredFiles.slice(startIndex, endIndex);

            listEl.innerHTML = "";
            filesToShow.forEach(file => {
                const li = document.createElement("li");

                const tags = file.tags || [];
                const tagsHtml = tags.length > 0
                    ? `<div class="tag-pill-container">
                        ${tags.map(tag => `<span class="tag-pill">#${tag}</span>`).join(" ")}
                        <button class="tag-edit-btn" onclick="openTagEditModal('${file.file_id}', '${(file.filename || file.original_filename || 'Unnamed File').replace(/'/g, "\\'")}', ${JSON.stringify(tags).replace(/"/g, '&quot;')})">
                            <span class="edit-icon">✏️</span>
                        </button>
                       </div>`
                    : `<div class="tag-pill-container">
                        <span class="no-tags">태그 없음</span>
                        <button class="tag-edit-btn" onclick="openTagEditModal('${file.file_id}', '${(file.filename || file.original_filename || 'Unnamed File').replace(/'/g, "\\'")}', [])">
                            <span class="edit-icon">➕</span>
                        </button>
                       </div>`;
                    
                const displayName = file.filename || file.original_filename || 'Unnamed File';
                
                li.innerHTML = `
                    <label class="checkbox-container" style="margin:0px;">
                        <input type="checkbox" name="delete-checkbox" data-file-id="${file.file_id}" />
                        <span class="custom-checkbox"></span>
                        <span title="${displayName}" class="clickable-filename" onclick="openPageViewer('${file.file_id}', '${displayName}')">${displayName}</span>
                    </label>
                    ${tagsHtml}
                `;
                listEl.appendChild(li);
            });

            const totalPages = Math.ceil(state.filteredFiles.length / state.pageSize);
            
            if (pageInfo) {
                pageInfo.innerText = `${state.currentPage} / ${totalPages}`;
            }
            
            if (paginationControls) {
                paginationControls.style.display = totalPages >= 2 ? "flex" : "none";
            }
            
            const tagContainers = document.querySelectorAll(".tag-pill-container");
            tagContainers.forEach(container => container.style.display = state.tagFilterEnabled ? "block" : "none");
        },

        // Change page size
        changePageSize: function() {
            const select = document.getElementById("pageSizeSelect_manage");
            if (select) {
                const state = window.PDFManagerState;
                state.pageSize = parseInt(select.value, 10);
                state.currentPage = 1;
                this.renderPage();
            }
        },

        // Pagination
        nextPage: function() {
            const state = window.PDFManagerState;
            if (state.currentPage < Math.ceil(state.filteredFiles.length / state.pageSize)) {
                state.currentPage++;
                this.renderPage();
            }
        },

        prevPage: function() {
            const state = window.PDFManagerState;
            if (state.currentPage > 1) {
                state.currentPage--;
                this.renderPage();
            }
        },

        // Delete selected documents
        deleteSelectedDocuments: async function() {
            const checkboxes = document.querySelectorAll('input[name="delete-checkbox"]:checked');
            if (checkboxes.length === 0) {
                alert("삭제할 파일을 선택하세요.");
                return;
            }

            const fileIds = Array.from(checkboxes).map(cb => cb.getAttribute("data-file-id"));

            if (!confirm(`선택한 ${fileIds.length}개의 파일을 삭제하시겠습니까?`)) return;

            const listBox = document.getElementById("storedFilesList_manage");
            const deleteAnim = document.getElementById("deletingAnimationBox");
            if (listBox) listBox.style.display = "none";
            if (deleteAnim) deleteAnim.style.display = "block";

            try {
                for (const fileId of fileIds) {
                    console.log("🗑️ Deleting file with ID:", fileId);
                    const endpoint = `delete-document/?file_id=${encodeURIComponent(fileId)}`;
                    console.log("📡 Delete endpoint:", endpoint);
                    
                    const res = await window.PDFManagerAPI.request(endpoint, {
                        method: 'DELETE'
                    });
                    const data = await res.json();
                    console.log("✅ Delete response:", data);
                    console.log(data.message || "File deleted");
                }

                alert("선택한 파일 삭제 완료.");
                
                if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                    window.PDFManagerUI.notification.show('파일이 삭제되었습니다.', 'success');
                }
            } catch (err) {
                alert("❌ 삭제 중 오류 발생: " + err.message);
            } finally {
                if (deleteAnim) deleteAnim.style.display = "none";
                if (listBox) listBox.style.display = "block";
                this.fetchStoredFiles();
            }
        },

        // Track ongoing tag updates
        ongoingTagUpdates: new Set(),
        
        // Update document tags
        updateDocumentTags: async function(fileId, tags) {
            // Add to ongoing updates
            this.ongoingTagUpdates.add(fileId);
            
            // Show loading state in the tag area
            this.showTagLoadingState(fileId);
            
            try {
                console.log("🏷️ Updating tags for file:", fileId, "Tags:", tags);
                
                // 프록시 사용 여부에 따라 다른 처리
                if (window.PDFManagerConfig.USE_PROXY) {
                    // WordPress AJAX 프록시를 통한 PUT 요청
                    const formData = new FormData();
                    formData.append('action', '3chan_proxy_api');
                    formData.append('nonce', window.PDFManagerConfig.NONCE);
                    formData.append('endpoint', `update-document-tags/?file_id=${encodeURIComponent(fileId)}`);
                    formData.append('method', 'PUT');
                    formData.append('tags', tags);
                    
                    const response = await fetch(window.PDFManagerConfig.AJAX_URL, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error("❌ Server response:", errorText);
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    console.log("✅ Tag update response:", data);
                    
                    if (data.status === 'success') {
                        this.handleTagUpdateSuccess(fileId, tags);
                    } else {
                        throw new Error(data.message || '태그 수정 실패');
                    }
                } else {
                    // 직접 API 호출
                    const params = new URLSearchParams();
                    params.append('tags', tags);
                    
                    const res = await fetch(`${window.PDFManagerConfig.DIRECT_API_URL}/update-document-tags/?file_id=${encodeURIComponent(fileId)}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params
                    });
                    
                    if (!res.ok) {
                        const errorText = await res.text();
                        console.error("❌ Server response:", errorText);
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    
                    const data = await res.json();
                    console.log("✅ Tag update response:", data);
                    
                    if (data.status === 'success') {
                        this.handleTagUpdateSuccess(fileId, tags);
                    } else {
                        throw new Error(data.message || '태그 수정 실패');
                    }
                }
            } catch (err) {
                console.error("❌ Error updating tags:", err);
                // Remove from ongoing updates
                this.ongoingTagUpdates.delete(fileId);
                // Update only this specific file's tag display on error
                this.updateSingleFileTagDisplay(fileId);
                alert("태그 수정 중 오류가 발생했습니다: " + err.message);
            }
        },
        
        // Handle successful tag update
        handleTagUpdateSuccess: function(fileId, tags) {
            // Update local state
            const state = window.PDFManagerState;
            state.allFiles.forEach(file => {
                if (file.file_id === fileId) {
                    file.tags = tags.split(',').map(tag => tag.trim()).filter(tag => tag);
                }
            });
            
            // Remove from ongoing updates
            this.ongoingTagUpdates.delete(fileId);
            
            // Update only the specific file's tag display
            this.updateSingleFileTagDisplay(fileId);
            
            // Check if all updates are complete before refreshing the page
            if (this.ongoingTagUpdates.size === 0) {
                // All updates complete, refresh tags list
                this.fetchTags();
            }
            
            // Show notification
            if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                window.PDFManagerUI.notification.show('태그가 성공적으로 수정되었습니다.', 'success');
            }
        },
        
        // Update single file's tag display without re-rendering entire page
        updateSingleFileTagDisplay: function(fileId) {
            const state = window.PDFManagerState;
            const file = state.allFiles.find(f => f.file_id === fileId);
            if (!file) return;
            
            // Find the file's list item
            const fileItem = Array.from(document.querySelectorAll('.document-list li')).find(li => {
                const checkbox = li.querySelector('input[data-file-id]');
                return checkbox && checkbox.getAttribute('data-file-id') === fileId;
            });
            
            if (!fileItem) return;
            
            const tagContainer = fileItem.querySelector('.tag-pill-container');
            if (!tagContainer) return;
            
            // Update the tag display
            const tags = file.tags || [];
            const tagsHtml = tags.length > 0
                ? `${tags.map(tag => `<span class="tag-pill">#${tag}</span>`).join(" ")}
                   <button class="tag-edit-btn" onclick="openTagEditModal('${file.file_id}', '${(file.filename || file.original_filename || 'Unnamed File').replace(/'/g, "\\'")}', ${JSON.stringify(tags).replace(/"/g, '&quot;')})">
                       <span class="edit-icon">✏️</span>
                   </button>`
                : `<span class="no-tags">태그 없음</span>
                   <button class="tag-edit-btn" onclick="openTagEditModal('${file.file_id}', '${(file.filename || file.original_filename || 'Unnamed File').replace(/'/g, "\\'")}', [])">
                       <span class="edit-icon">➕</span>
                   </button>`;
            
            tagContainer.innerHTML = tagsHtml;
            
            // Apply visibility based on tag filter mode
            tagContainer.style.display = state.tagFilterEnabled ? "block" : "none";
        },
        
        // Show loading state for tags
        showTagLoadingState: function(fileId) {
            const state = window.PDFManagerState;
            // Find the document list item
            const fileItem = Array.from(document.querySelectorAll('.document-list li')).find(li => {
                const checkbox = li.querySelector('input[data-file-id]');
                return checkbox && checkbox.getAttribute('data-file-id') === fileId;
            });
            
            if (fileItem) {
                const tagContainer = fileItem.querySelector('.tag-pill-container');
                if (tagContainer) {
                    // Store original content
                    tagContainer.setAttribute('data-original-content', tagContainer.innerHTML);
                    
                    // Show loading state
                    tagContainer.innerHTML = `
                        <div class="tag-update-loading">
                            <div class="tag-spinner"></div>
                            <span class="tag-loading-text">태그 업데이트 중...</span>
                        </div>
                    `;
                }
            }
        },

        // Open tag edit modal
        openTagEditModal: function(fileId, filename, currentTags) {
            const modal = document.getElementById('tagEditModal');
            const filenameEl = document.getElementById('tagEditFilename');
            const input = document.getElementById('tagEditInput');
            
            if (!modal || !filenameEl || !input) {
                console.error('Tag edit modal elements not found');
                return;
            }
            
            // Store file ID for saving
            modal.setAttribute('data-file-id', fileId);
            
            // Set filename
            filenameEl.textContent = '📄 ' + filename;
            
            // Set current tags
            input.value = Array.isArray(currentTags) ? currentTags.join(', ') : currentTags || '';
            
            // Update preview
            this.updateTagEditPreview();
            
            // Show modal
            modal.style.display = 'flex';
            
            // Focus input
            setTimeout(() => input.focus(), 100);
        },
        
        // Update tag edit preview
        updateTagEditPreview: function() {
            const input = document.getElementById('tagEditInput');
            const preview = document.getElementById('tagEditPreview');
            
            if (!input || !preview) return;
            
            const tags = input.value.split(',')
                .map(tag => tag.trim())
                .filter(tag => tag.length > 0);
            
            if (tags.length === 0) {
                preview.innerHTML = '<span class="tag-preview-empty">태그가 없습니다</span>';
            } else {
                preview.innerHTML = tags.map(tag => 
                    `<span class="tag-pill">#${tag}</span>`
                ).join(' ');
            }
        },

        // Tag management modal functions
        openTagManageModal: function(tagName) {
            const modal = document.getElementById('tagManageModal');
            const tagNameEl = document.getElementById('tagManageTagName');
            const documentsList = document.getElementById('tagManageDocumentsList');
            
            if (!modal || !tagNameEl || !documentsList) {
                console.error('Tag manage modal elements not found');
                return;
            }
            
            // Store tag name for saving
            modal.setAttribute('data-tag-name', tagName);
            
            // Set tag name
            tagNameEl.textContent = tagName;
            
            // Load all documents and show which ones have this tag
            const state = window.PDFManagerState;
            const allDocs = state.allFiles;
            
            // Sort documents by whether they have the tag
            const sortedDocs = allDocs.sort((a, b) => {
                const aHasTag = (a.tags || []).includes(tagName);
                const bHasTag = (b.tags || []).includes(tagName);
                if (aHasTag && !bHasTag) return -1;
                if (!aHasTag && bHasTag) return 1;
                return 0;
            });
            
            // Render documents list
            documentsList.innerHTML = '';
            sortedDocs.forEach(doc => {
                const hasTag = (doc.tags || []).includes(tagName);
                const li = document.createElement('li');
                li.className = 'tag-manage-document-item';
                
                const displayName = doc.filename || doc.original_filename || 'Unnamed File';
                const otherTags = (doc.tags || []).filter(tag => tag !== tagName);
                const otherTagsHtml = otherTags.length > 0 
                    ? `<span class="other-tags">${otherTags.map(tag => `#${tag}`).join(' ')}</span>`
                    : '';
                
                li.innerHTML = `
                    <label class="tag-manage-checkbox-container">
                        <input type="checkbox" 
                               data-file-id="${doc.file_id}" 
                               ${hasTag ? 'checked' : ''} />
                        <span class="document-info">
                            <span class="document-name">${displayName}</span>
                            ${otherTagsHtml}
                        </span>
                    </label>
                `;
                
                documentsList.appendChild(li);
            });
            
            // Update count
            this.updateTagManageCount();
            
            // Show modal
            modal.style.display = 'flex';
            
            // Add event listener for checkbox changes
            documentsList.addEventListener('change', () => this.updateTagManageCount());
        },
        
        updateTagManageCount: function() {
            const checkedCount = document.querySelectorAll('#tagManageDocumentsList input[type="checkbox"]:checked').length;
            const totalCount = document.querySelectorAll('#tagManageDocumentsList input[type="checkbox"]').length;
            const countEl = document.getElementById('tagManageSelectedCount');
            
            if (countEl) {
                countEl.textContent = `${checkedCount}/${totalCount}개 문서 선택됨`;
            }
        },
        
        saveTagManagement: async function() {
            const modal = document.getElementById('tagManageModal');
            const tagName = modal.getAttribute('data-tag-name');
            const saveBtn = document.getElementById('tagManageSaveBtn');
            
            if (!tagName) return;
            
            // Disable save button
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="btn-icon">⏳</span> 저장 중...';
            
            // Get all checkbox states
            const checkboxes = document.querySelectorAll('#tagManageDocumentsList input[type="checkbox"]');
            const updates = [];
            
            checkboxes.forEach(checkbox => {
                const fileId = checkbox.getAttribute('data-file-id');
                const shouldHaveTag = checkbox.checked;
                
                // Find the document
                const doc = window.PDFManagerState.allFiles.find(f => f.file_id === fileId);
                if (!doc) return;
                
                const currentTags = doc.tags || [];
                const hasTag = currentTags.includes(tagName);
                
                // Check if update is needed
                if (shouldHaveTag && !hasTag) {
                    // Add tag
                    const newTags = [...currentTags, tagName];
                    updates.push({ fileId, tags: newTags.join(', ') });
                } else if (!shouldHaveTag && hasTag) {
                    // Remove tag
                    const newTags = currentTags.filter(tag => tag !== tagName);
                    updates.push({ fileId, tags: newTags.join(', ') });
                }
            });
            
            // Process updates
            if (updates.length === 0) {
                // No changes
                this.closeTagManageModal();
                if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                    window.PDFManagerUI.notification.show('변경사항이 없습니다.', 'info');
                }
                return;
            }
            
            try {
                // Update each document
                let successCount = 0;
                
                for (const update of updates) {
                    try {
                        await this.updateDocumentTags(update.fileId, update.tags);
                        successCount++;
                    } catch (err) {
                        console.error(`Failed to update tags for ${update.fileId}:`, err);
                    }
                }
                
                // Close modal
                this.closeTagManageModal();
                
                // Refresh the document list
                await this.fetchStoredFiles();
                
                // Show notification
                if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                    window.PDFManagerUI.notification.show(
                        `${successCount}개 문서의 태그가 업데이트되었습니다.`, 
                        'success'
                    );
                }
                
            } catch (err) {
                console.error('Error during batch tag update:', err);
                alert('태그 업데이트 중 오류가 발생했습니다.');
            } finally {
                // Re-enable save button
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<span class="btn-icon">✔</span> 저장';
            }
        },
        
        closeTagManageModal: function() {
            const modal = document.getElementById('tagManageModal');
            if (modal) {
                modal.style.display = 'none';
                modal.removeAttribute('data-tag-name');
            }
        },

        // Other tag management functions...
        updateTagPreview: function() {
            const state = window.PDFManagerState;
            const previewBox = document.getElementById("selectedTagPreview_manage");
            if (!previewBox) return;
            
            if (state.selectedTags.length === 0) {
                previewBox.innerHTML = "";
                return;
            }

            previewBox.innerHTML = state.selectedTags
                .map(tag => `
                    <span class="tag-pill tag-removable">
                        <span class="remove-tag" onclick="removeSelectedTag_manage('${tag}')">✖</span>
                        #${tag}
                    </span>
                `)
                .join(" ");
        },

        updateSelectedTagsDisplay: function() {
            const state = window.PDFManagerState;
            const displayBar = document.getElementById("selectedTagsDisplay");
            const tagsList = document.getElementById("selectedTagsList");
            
            if (!displayBar || !tagsList) return;
            
            if (state.selectedTags.length === 0) {
                displayBar.style.display = "none";
                return;
            }
            
            displayBar.style.display = "block";
            tagsList.innerHTML = state.selectedTags
                .map(tag => `
                    <span class="tag-pill tag-removable">
                        <span class="remove-tag" onclick="removeSelectedTag_manage('${tag}')">✖</span>
                        #${tag}
                    </span>
                `)
                .join(" ");
        },

        removeSelectedTag: function(tagToRemove) {
            const state = window.PDFManagerState;
            state.selectedTags = state.selectedTags.filter(tag => tag !== tagToRemove);
            this.updateTagPreview();
            this.updateSelectedTagsDisplay();
            this.filterByTags();
        },

        clearSelectedTags: function() {
            const state = window.PDFManagerState;
            state.selectedTags = [];
            this.updateTagPreview();
            this.updateSelectedTagsDisplay();
            this.fetchStoredFiles();
        },

        renderTagOptions: function(tags) {
            const container = document.getElementById("tagOptionsContainer_manage");
            if (!container) return;
            
            container.innerHTML = "";

            if (!Array.isArray(tags)) {
                console.warn("🚫 renderTagOptions: tags is not array:", tags);
                return;
            }

            tags.forEach(tag => {
                const div = document.createElement("div");
                div.className = "tag-option-item";
                
                // Create inner structure with tag name and action buttons
                div.innerHTML = `
                    <span class="tag-option-name" onclick="PDFManagerDocuments.selectTag('${tag}')">${tag}</span>
                    <div class="tag-option-actions">
                        <button class="tag-action-btn tag-manage-btn" onclick="openTagManageModal('${tag}')" title="이 태그의 문서 관리">
                            <span class="action-icon">⚙️</span>
                        </button>
                        <button class="tag-action-btn tag-rename-btn" onclick="openTagRenameModal('${tag}')" title="태그 이름 변경">
                            <span class="action-icon">✏️</span>
                        </button>
                        <button class="tag-action-btn tag-delete-btn" onclick="deleteTag('${tag}')" title="태그 삭제">
                            <span class="action-icon">🗑️</span>
                        </button>
                    </div>
                `;

                container.appendChild(div);
            });
        },
        
        selectTag: function(tag) {
            const state = window.PDFManagerState;
            if (!state.selectedTags.includes(tag)) {
                state.selectedTags.push(tag);
                this.updateTagPreview();
                this.updateSelectedTagsDisplay();
                this.filterByTags();
            }
            
            const panel = document.getElementById("filterPanel");
            if (panel) panel.style.display = "none";
            
            const searchInput = document.getElementById("tagSearchInput_manage");
            if (searchInput) searchInput.value = "";
            this.renderTagOptions(window.allTagOptions || []);
        },

        filterTagOptions: function() {
            const input = document.getElementById("tagSearchInput_manage");
            if (!input) return;
            
            const searchText = input.value.toLowerCase();
            const items = document.querySelectorAll(".tag-option-item");
            items.forEach(item => {
                const tagName = item.querySelector('.tag-option-name').textContent;
                item.style.display = tagName.toLowerCase().includes(searchText) ? "flex" : "none";
            });
        },

        toggleFilterPanel: function() {
            const panel = document.getElementById('filterPanel');
            if (panel) {
                if (panel.style.display === 'none' || !panel.style.display) {
                    panel.style.display = 'block';
                    if (!window.allTagOptions || window.allTagOptions.length === 0) {
                        this.fetchTags();
                    }
                } else {
                    panel.style.display = 'none';
                }
            }
        },

        toggleTagFilterMode: function() {
            const checkbox = document.getElementById("tagToggleInput");
            if (!checkbox) return;
            
            const state = window.PDFManagerState;
            const isChecked = checkbox.checked;
            const tagContainers = document.querySelectorAll(".tag-pill-container");

            state.tagFilterEnabled = isChecked;
            tagContainers.forEach(container => container.style.display = isChecked ? "block" : "none");
        },

        // Page viewer - 이미지 URL을 표시할 때만 프록시로 변환
        openPageViewer: function(fileId, filename) {
            const state = window.PDFManagerState;
            state.currentPopupFileId = fileId;
            state.currentPopupPageNumber = 1;
            
            const titleEl = document.getElementById("popupFileTitle");
            if (titleEl) titleEl.innerText = "📄 " + filename;
            
            const file = state.allFiles.find(f => f.file_id === fileId);
            const totalPages = file?.total_pdf_pages || file?.num_pages || 1;
            
            const pageInfoEl = document.getElementById("popupPageInfo");
            if (pageInfoEl) pageInfoEl.innerText = `${state.currentPopupPageNumber} / ${totalPages}`;
            
            const pageInputEl = document.getElementById("popupPageInput");
            if (pageInputEl) pageInputEl.value = state.currentPopupPageNumber;
            
            const pageCountEl = document.getElementById("popupPageCount");
            if (pageCountEl) pageCountEl.innerText = `총 ${totalPages} 페이지`;
            
            const contentEl = document.getElementById("popupPageContent");
            if (contentEl) contentEl.innerText = "";
            
            const popupEl = document.getElementById("pageViewerPopup");
            if (popupEl) {
                popupEl.style.display = "block";
                setTimeout(() => {
                    this.fetchPageContent();
                }, 100);
            }
        },

        closePageViewer: function() {
            const popupEl = document.getElementById("pageViewerPopup");
            if (popupEl) popupEl.style.display = "none";
        },

        fetchPageContent: async function() {
            const state = window.PDFManagerState;
            const inputEl = document.getElementById("popupPageInput");
            if (!inputEl) return;
            
            let pageNum = parseInt(inputEl.value || state.currentPopupPageNumber, 10);
            if (!pageNum || isNaN(pageNum)) {
                alert("유효한 페이지 번호를 입력하세요.");
                return;
            }

            const loadingBox = document.getElementById("popupLoading");
            if (loadingBox) loadingBox.style.display = "block";

            state.currentPopupPageNumber = pageNum;
            try {
                const res = await window.PDFManagerAPI.request('get-page-content/', {
                    params: {
                        file_id: state.currentPopupFileId,
                        page_number: pageNum
                    }
                });
                const data = await res.json();
                
                const contentEl = document.getElementById("popupPageContent");
                if (!contentEl) return;
                
                if (data.status === "success") {
                    const sections = data.sections || [];
                    const html = sections.map(s => {
                        // 원본 content를 저장하지 않고 표시할 때만 변환
                        let displayContent = s.content || '';
                        
                        // 1. 먼저 일반적인 콘텐츠 처리 (줄바꿈, 리스트 등)
                        displayContent = window.PDFManagerUtils.processContent(displayContent);
                        
                        // 2. 그 다음 이미지 URL을 프록시로 변환 (표시 전용)
                        displayContent = this.processImageUrlsForDisplay(displayContent);
                        
                        return `
                            <div class="content-section">
                                <div class="section-title">${s.title || ''}</div>
                                <div class="section-content">${displayContent}</div>
                            </div>
                        `;
                    }).join("");

                    contentEl.innerHTML = html || '<div class="no-content">이 페이지에는 내용이 없습니다.</div>';
                    
                    const file = state.allFiles.find(f => f.file_id === state.currentPopupFileId);
                    const totalPages = file?.total_pdf_pages || file?.num_pages || 1;
                    
                    const pageInfoEl = document.getElementById("popupPageInfo");
                    if (pageInfoEl) pageInfoEl.innerText = `${state.currentPopupPageNumber} / ${totalPages}`;
                } else {
                    contentEl.innerText = "❌ " + (data.message || "페이지를 불러올 수 없습니다.");
                }
            } catch (err) {
                const contentEl = document.getElementById("popupPageContent");
                if (contentEl) contentEl.innerText = "❌ 오류 발생: " + err.message;
            } finally {
                if (loadingBox) loadingBox.style.display = "none";
            }
        },

        previewHasValidContent: async function(pageNum) {
            const state = window.PDFManagerState;
            try {
                const res = await window.PDFManagerAPI.request('get-page-content/', {
                    params: {
                        file_id: state.currentPopupFileId,
                        page_number: pageNum
                    }
                });
                const data = await res.json();
                if (data.status === "success" && Array.isArray(data.sections)) {
                    return data.sections.some(s => s.content && s.content.trim().length > 0);
                }
            } catch (err) {
                console.warn("⚠️ Preview content check failed:", err.message);
            }
            return false;
        },

        prevPopupPage: async function() {
            const state = window.PDFManagerState;
            const file = state.allFiles.find(f => f.file_id === state.currentPopupFileId);
            const maxPage = file?.total_pdf_pages || file?.num_pages || 1;

            const loadingBox = document.getElementById("popupLoading");
            if (loadingBox) loadingBox.style.display = "block";

            let nextPage = state.currentPopupPageNumber - 1;

            while (nextPage >= 1) {
                const hasContent = await this.previewHasValidContent(nextPage);
                if (hasContent) {
                    state.currentPopupPageNumber = nextPage;
                    const inputEl = document.getElementById("popupPageInput");
                    if (inputEl) inputEl.value = state.currentPopupPageNumber;
                    await this.fetchPageContent();
                    if (loadingBox) loadingBox.style.display = "none";
                    return;
                }
                nextPage--;
            }

            if (loadingBox) loadingBox.style.display = "none";
            alert("이전 페이지가 없습니다.");
        },

        nextPopupPage: async function() {
            const state = window.PDFManagerState;
            const file = state.allFiles.find(f => f.file_id === state.currentPopupFileId);
            const maxPage = file?.total_pdf_pages || file?.num_pages || 1;

            const loadingBox = document.getElementById("popupLoading");
            if (loadingBox) loadingBox.style.display = "block";

            let nextPage = state.currentPopupPageNumber + 1;

            while (nextPage <= maxPage) {
                const hasContent = await this.previewHasValidContent(nextPage);
                if (hasContent) {
                    state.currentPopupPageNumber = nextPage;
                    const inputEl = document.getElementById("popupPageInput");
                    if (inputEl) inputEl.value = state.currentPopupPageNumber;
                    await this.fetchPageContent();
                    if (loadingBox) loadingBox.style.display = "none";
                    return;
                }
                nextPage++;
            }

            if (loadingBox) loadingBox.style.display = "none";
            alert("다음 페이지가 없습니다.");
        },
        
        // Tag rename modal functions
        openTagRenameModal: function(oldTagName) {
            const modal = document.getElementById('tagRenameModal');
            const oldNameEl = document.getElementById('tagRenameOldName');
            const input = document.getElementById('tagRenameInput');
            
            if (!modal || !oldNameEl || !input) {
                console.error('Tag rename modal elements not found');
                return;
            }
            
            modal.setAttribute('data-old-tag', oldTagName);
            oldNameEl.textContent = oldTagName;
            input.value = oldTagName;
            modal.style.display = 'flex';
            
            setTimeout(() => {
                input.focus();
                input.select();
            }, 100);
        },
        
        renameTag: async function() {
            const modal = document.getElementById('tagRenameModal');
            const oldTag = modal.getAttribute('data-old-tag');
            const newTag = document.getElementById('tagRenameInput').value.trim();
            const saveBtn = document.getElementById('tagRenameSaveBtn');
            
            if (!oldTag || !newTag) {
                alert('새로운 태그 이름을 입력하세요.');
                return;
            }
            
            if (oldTag === newTag) {
                this.closeTagRenameModal();
                return;
            }
            
            if (window.allTagOptions && window.allTagOptions.includes(newTag)) {
                alert(`"${newTag}" 태그가 이미 존재합니다.`);
                return;
            }
            
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="btn-icon">⏳</span> 변경 중...';
            
            try {
                const docsWithTag = window.PDFManagerState.allFiles.filter(doc => 
                    (doc.tags || []).includes(oldTag)
                );
                
                if (docsWithTag.length === 0) {
                    this.closeTagRenameModal();
                    if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                        window.PDFManagerUI.notification.show('변경할 문서가 없습니다.', 'info');
                    }
                    return;
                }
                
                let successCount = 0;
                
                for (const doc of docsWithTag) {
                    const currentTags = doc.tags || [];
                    const newTags = currentTags.map(tag => tag === oldTag ? newTag : tag);
                    
                    try {
                        await this.updateDocumentTags(doc.file_id, newTags.join(', '));
                        successCount++;
                    } catch (err) {
                        console.error(`Failed to update tags for ${doc.file_id}:`, err);
                    }
                }
                
                this.closeTagRenameModal();
                await this.fetchStoredFiles();
                await this.fetchTags();
                
                if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                    window.PDFManagerUI.notification.show(
                        `${successCount}개 문서의 태그를 "${oldTag}"에서 "${newTag}"로 변경했습니다.`, 
                        'success'
                    );
                }
                
            } catch (err) {
                console.error('Error during tag rename:', err);
                alert('태그 이름 변경 중 오류가 발생했습니다.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<span class="btn-icon">✔</span> 변경';
            }
        },
        
        deleteTag: async function(tagName) {
            if (!confirm(`"${tagName}" 태그를 모든 문서에서 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`)) {
                return;
            }
            
            try {
                if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                    window.PDFManagerUI.notification.show('태그 삭제 중...', 'info');
                }
                
                const docsWithTag = window.PDFManagerState.allFiles.filter(doc => 
                    (doc.tags || []).includes(tagName)
                );
                
                if (docsWithTag.length === 0) {
                    await this.fetchTags();
                    if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                        window.PDFManagerUI.notification.show('삭제할 태그가 없습니다.', 'info');
                    }
                    return;
                }
                
                let successCount = 0;
                
                for (const doc of docsWithTag) {
                    const currentTags = doc.tags || [];
                    const newTags = currentTags.filter(tag => tag !== tagName);
                    
                    try {
                        await this.updateDocumentTags(doc.file_id, newTags.join(', '));
                        successCount++;
                    } catch (err) {
                        console.error(`Failed to remove tag from ${doc.file_id}:`, err);
                    }
                }
                
                await this.fetchStoredFiles();
                await this.fetchTags();
                
                if (window.PDFManagerUI && window.PDFManagerUI.notification) {
                    window.PDFManagerUI.notification.show(
                        `${successCount}개 문서에서 "${tagName}" 태그를 삭제했습니다.`, 
                        'success'
                    );
                }
                
            } catch (err) {
                console.error('Error during tag deletion:', err);
                alert('태그 삭제 중 오류가 발생했습니다.');
            }
        },
        
        closeTagRenameModal: function() {
            const modal = document.getElementById('tagRenameModal');
            if (modal) {
                modal.style.display = 'none';
                modal.removeAttribute('data-old-tag');
            }
        }
    };

})(jQuery);