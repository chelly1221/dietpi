<!-- pdf-manager.php - Updated with Tag Edit and Tag Management Features -->
<!-- 3chan PDF Manager Template - Full Width Layout -->
<div id="threechan-pdf-manager-wrapper" class="pdf-manager-container">
    
    <!-- Fixed Header Section at Top -->
    <div class="pdf-manager-header">
        <h1 class="pdf-manager-title">
            <span class="title-icon">📚</span>
            문서 관리 시스템
        </h1>
    </div>

    <!-- Full Width Main Content Area -->
    <div class="pdf-manager-main">
        
        <!-- Upload Section - Left Panel -->
        <div class="upload-section">
            <div class="section-header">
                <h2><span class="section-icon">📤</span> 문서 업로드</h2>
            </div>
            
            <form id="uploadFormEmbedPDF" enctype="multipart/form-data" method="post" class="upload-form">
                
                <!-- Tag Input - Moved to top -->
                <div class="tag-input-section">
                    <label for="tagInput" class="input-label">
                        <span class="label-icon">🏷️</span> 태그 지정
                    </label>
                    <input type="text" 
                           id="tagInput" 
                           name="tags" 
                           class="form-input tag-input" 
                           placeholder="예: 항행안전시설, 인계인수">
                    <p class="input-hint">문서 분류를 위한 태그를 입력하세요. (쉼표로 구분)</p>
                </div>
                
                <!-- File Upload Area -->
                <div class="upload-area">
                    <input type="file" id="pdfFileEmbedPDF" name="files" accept=".pdf,.docx,.pptx,.hwpx" multiple required style="display: none;" />
                    
                    <label for="pdfFileEmbedPDF" class="upload-dropzone">
                        <div class="dropzone-content">
                            <div class="upload-icon">📁</div>
                            <div class="upload-text">
                                <p class="upload-main-text">파일을 선택하거나 드래그하세요</p>
                                <p class="upload-sub-text">PDF, DOCX, PPTX, HWPX 지원</p>
                            </div>
                        </div>
                    </label>
                    
                    <!-- Upload Confirm Section - Moved here -->
                    <div id="uploadConfirmBox" class="upload-confirm" style="display: none;">
                        <div class="confirm-content">
                            <p class="confirm-text">선택한 파일을 업로드하시겠습니까?</p>
                            <div class="confirm-buttons">
                                <button type="button" id="uploadConfirmBtn" class="btn btn-primary">
                                    <span class="btn-icon">✓</span> 업로드
                                </button>
                                <button type="button" id="uploadCancelBtn" class="btn btn-secondary">
                                    <span class="btn-icon">✕</span> 취소
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upload Progress - Moved inside upload area -->
                    <div id="loadingEmbedPDF" class="upload-progress" style="display:none;">
                        <div class="loading-text">AI 처리 중...</div>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <!-- Bird Animation with 10 birds -->
                        <div class="bird-animation-wrapper">
                            <div class="bird-container bird-container-one"><div class="bird bird-one"></div></div>
                            <div class="bird-container bird-container-two"><div class="bird bird-two"></div></div>
                            <div class="bird-container bird-container-three"><div class="bird bird-three"></div></div>
                            <div class="bird-container bird-container-four"><div class="bird bird-four"></div></div>
                            <div class="bird-container bird-container-five"><div class="bird bird-five"></div></div>
                            <div class="bird-container bird-container-six"><div class="bird bird-six"></div></div>
                            <div class="bird-container bird-container-seven"><div class="bird bird-seven"></div></div>
                            <div class="bird-container bird-container-eight"><div class="bird bird-eight"></div></div>
                            <div class="bird-container bird-container-nine"><div class="bird bird-nine"></div></div>
                            <div class="bird-container bird-container-ten"><div class="bird bird-ten"></div></div>
                        </div>
                    </div>
                    
                    <!-- Selected Files Display -->
                    <div id="selectedFileInfo" class="selected-files" style="display: none;">
                        <h3 class="selected-files-title">선택된 파일</h3>
                        <div class="selected-files-list"></div>
                    </div>
                </div>
                
                <!-- Upload Result - Moved here right after upload-area -->
                <div id="responseBoxEmbedPDF" class="upload-result" style="display: none;"></div>
            </form>
        </div>
        
        <!-- Document List Section - Right Panel -->
        <div class="document-section">
            <div class="section-header">
                <h2><span class="section-icon">📂</span> 문서 목록</h2>
                <div class="section-controls">
                    <span class="document-count" id="documentCount">총 0개</span>
                </div>
            </div>
            
            <!-- Search and Filter Bar -->
            <div class="search-filter-bar">
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <span class="search-icon">🔍</span>
                        <input type="text" 
                               id="searchInput_manage" 
                               class="search-input" 
                               placeholder="파일명 검색..."
                               oninput="filterFiles()">
                        <!-- Filter button inside search input -->
                        <button class="filter-btn-inline" id="filterToggleBtn" onclick="toggleFilterPanel()" title="태그 필터">
                            <span class="filter-icon">필터 +</span>
                        </button>
                    </div>
                    
                    <div class="filter-controls">
                        <div class="tag-toggle-wrapper">
                            <label class="toggle-switch">
                                <input type="checkbox" id="tagToggleInput" onchange="toggleTagFilterMode()" />
                                <span class="slider">
                                    <span class="slider-text" data-on="태그" data-off="태그"></span>
                                </span>
                            </label>
                        </div>
                        
                        <select id="pageSizeSelect_manage" class="page-size-select" onchange="changePageSize()">
                            <option value="10">10</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                </div>
                
                <!-- Filter Panel -->
                <div id="filterPanel" class="filter-panel" style="display: none;">
                    <div class="filter-panel-header">
                        <h3>태그 필터링</h3>
                        <button class="close-btn" onclick="toggleFilterPanel()">✕</button>
                    </div>
                    <div class="filter-panel-content">
                        <input type="text" 
                               id="tagSearchInput_manage" 
                               class="tag-search-input" 
                               placeholder="태그 검색..."
                               oninput="filterTagOptions_manage()">
                        <div id="tagOptionsContainer_manage" class="tag-options"></div>
                        <div id="selectedTagPreview_manage" class="selected-tags"></div>
                    </div>
                </div>
            </div>
            
            <!-- Document List Wrapper -->
            <div class="document-list-wrapper">
                <div class="list-actions">
                    <button id="selectAllBtn" class="btn btn-sm" onclick="toggleSelectAll()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="btn-icon">
                            <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                            <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        전체선택
                    </button>
                    <button id="deletebomb" class="btn btn-sm btn-danger" onclick="deleteSelectedDocuments()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="btn-icon">
                            <path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        선택삭제
                    </button>
                </div>
                
                <!-- Selected Tags Display Area - NEW -->
                <div id="selectedTagsDisplay" class="selected-tags-bar" style="display: none;">
                    <div class="selected-tags-container">
                        <span class="selected-tags-label">필터링된 태그:</span>
                        <div id="selectedTagsList" class="selected-tags-list"></div>
                        <button class="btn btn-sm btn-secondary" onclick="clearSelectedTags_manage()">
                            <span class="btn-icon">✕</span> 필터 초기화
                        </button>
                    </div>
                </div>
                
                <ul id="storedFilesList_manage" class="document-list">
                    <li class="list-loading">문서 목록을 불러오는 중...</li>
                </ul>
                
                <!-- Deletion Animation -->
                <div id="deletingAnimationBox" class="deletion-animation" style="display:none;">
                    <div class="deletion-icon">🗑️</div>
                    <div class="deletion-text">파일을 삭제하는 중입니다...</div>
                </div>
            </div>
            
            <!-- Pagination - Fixed at Bottom -->
            <div id="paginationControls_manage" class="pagination-controls" style="display: none;">
                <button class="pagination-btn prev" onclick="prevPage()">
                    <span>◀</span> 이전
                </button>
                <span id="pageInfo_manage" class="page-info">1 / 1</span>
                <button class="pagination-btn next" onclick="nextPage()">
                    다음 <span>▶</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Page Viewer Modal -->
    <div id="pageViewerPopup" class="modal page-viewer-modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <div class="viewer-header-info">
                    <h2 id="popupFileTitle" class="viewer-title"></h2>
                    <div class="viewer-meta">
                        <span id="popupPageCount" class="page-count"></span>
                    </div>
                </div>
                <button class="close-btn" onclick="closePageViewer()">✕</button>
            </div>
            
            <div class="modal-body">
                <div class="viewer-controls">
                    <div class="page-input-group">
                        <label>페이지:</label>
                        <input type="number" id="popupPageInput" min="1" class="page-input">
                        <button class="btn btn-sm" onclick="fetchPageContent()">이동</button>
                    </div>
                    
                    <div class="page-navigation">
                        <button class="nav-btn" onclick="prevPopupPage()">◀ 이전</button>
                        <span id="popupPageInfo" class="current-page">1 / 1</span>
                        <button class="nav-btn" onclick="nextPopupPage()">다음 ▶</button>
                    </div>
                </div>
                
                <div id="popupLoading" class="viewer-loading" style="display:none;">
                    <div class="spinner"></div>
                    <p>페이지를 불러오는 중...</p>
                </div>
                
                <div id="popupPageContent" class="page-content"></div>
            </div>
        </div>
    </div>
    
    <!-- Tag Edit Modal -->
    <div id="tagEditModal" class="modal tag-edit-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">태그 수정</h2>
                <button class="close-btn" onclick="closeTagEditModal()">✕</button>
            </div>
            <div class="modal-body">
                <div class="tag-edit-info">
                    <p class="tag-edit-filename" id="tagEditFilename"></p>
                    <p class="tag-edit-hint">쉼표로 구분하여 태그를 입력하세요</p>
                </div>
                <div class="tag-edit-input-wrapper">
                    <input type="text" 
                           id="tagEditInput" 
                           class="form-input tag-edit-input" 
                           placeholder="예: 항행안전시설, 인계인수">
                </div>
                <div class="tag-edit-preview" id="tagEditPreview"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="saveTagEdit()">
                    <span class="btn-icon">✓</span> 저장
                </button>
                <button class="btn btn-secondary" onclick="closeTagEditModal()">
                    <span class="btn-icon">✕</span> 취소
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tag Management Modal - NEW -->
    <div id="tagManageModal" class="modal tag-manage-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">태그별 문서 관리</h2>
                <button class="close-btn" onclick="closeTagManageModal()">✕</button>
            </div>
            <div class="modal-body">
                <div class="tag-manage-info">
                    <span class="tag-manage-tag-name">#<span id="tagManageTagName"></span></span>
                    <span class="tag-manage-count" id="tagManageSelectedCount">0개 문서 선택됨</span>
                    <p class="tag-manage-hint">이 태그를 적용할 문서를 선택하세요. 체크를 해제하면 해당 문서에서 태그가 제거됩니다.</p>
                </div>
                <div class="tag-manage-documents-wrapper">
                    <ul id="tagManageDocumentsList" class="tag-manage-documents-list">
                        <!-- Documents will be populated here -->
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="tagManageSaveBtn" onclick="saveTagManagement()">
                    <span class="btn-icon">✓</span> 저장
                </button>
                <button class="btn btn-secondary" onclick="closeTagManageModal()">
                    <span class="btn-icon">✕</span> 취소
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tag Rename Modal - NEW -->
    <div id="tagRenameModal" class="modal tag-rename-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">태그 이름 변경</h2>
                <button class="close-btn" onclick="closeTagRenameModal()">✕</button>
            </div>
            <div class="modal-body">
                <div class="tag-rename-info">
                    <div class="tag-rename-label">현재 태그 이름:</div>
                    <div class="tag-rename-old-name" id="tagRenameOldName"></div>
                </div>
                <div class="tag-rename-input-wrapper">
                    <label for="tagRenameInput" class="input-label">
                        <span class="label-icon">✏️</span> 새로운 태그 이름
                    </label>
                    <input type="text" 
                           id="tagRenameInput" 
                           class="form-input tag-rename-input" 
                           placeholder="새로운 태그 이름을 입력하세요">
                    <p class="tag-rename-hint">이 태그가 적용된 모든 문서의 태그가 일괄 변경됩니다.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="tagRenameSaveBtn" onclick="renameTag()">
                    <span class="btn-icon">✓</span> 변경
                </button>
                <button class="btn btn-secondary" onclick="closeTagRenameModal()">
                    <span class="btn-icon">✕</span> 취소
                </button>
            </div>
        </div>
    </div>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 전체화면 모드 처리
    const wrapper = document.querySelector('.threechan-pdf-wrapper');
    if (wrapper && wrapper.classList.contains('fullpage-mode')) {
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
    }
    
    // 스크롤 최상단으로
    window.scrollTo(0, 0);
});

// 추가 UI 개선 스크립트
document.addEventListener('DOMContentLoaded', function() {
    // 파일 드래그 앤 드롭 지원
    const dropzone = document.querySelector('.upload-dropzone');
    if (dropzone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            dropzone.classList.add('highlight');
        }
        
        function unhighlight(e) {
            dropzone.classList.remove('highlight');
        }
        
        dropzone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('pdfFileEmbedPDF').files = files;
            const event = new Event('change', { bubbles: true });
            document.getElementById('pdfFileEmbedPDF').dispatchEvent(event);
        }
    }
    
    // Tag edit input preview
    const tagEditInput = document.getElementById('tagEditInput');
    if (tagEditInput) {
        tagEditInput.addEventListener('input', function() {
            updateTagEditPreview();
        });
    }
});

// 필터 패널 토글
function toggleFilterPanel() {
    const panel = document.getElementById('filterPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

// 전체 선택 토글
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('input[name="delete-checkbox"]');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => cb.checked = !allChecked);
    
    // SVG 아이콘 유지하면서 텍스트만 변경
    const btnText = allChecked ? '전체선택' : '선택해제';
    const btnIcon = selectAllBtn.querySelector('.btn-icon');
    selectAllBtn.innerHTML = '';
    selectAllBtn.appendChild(btnIcon);
    selectAllBtn.appendChild(document.createTextNode(' ' + btnText));
}

// Tag edit modal functions
function openTagEditModal(fileId, filename, currentTags) {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.openTagEditModal) {
        window.PDFManagerDocuments.openTagEditModal(fileId, filename, currentTags);
    }
}

function closeTagEditModal() {
    const modal = document.getElementById('tagEditModal');
    modal.style.display = 'none';
    modal.removeAttribute('data-file-id');
}

function updateTagEditPreview() {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.updateTagEditPreview) {
        window.PDFManagerDocuments.updateTagEditPreview();
    }
}

function saveTagEdit() {
    const modal = document.getElementById('tagEditModal');
    const fileId = modal.getAttribute('data-file-id');
    const input = document.getElementById('tagEditInput');
    
    if (!fileId) return;
    
    const tags = input.value.split(',')
        .map(tag => tag.trim())
        .filter(tag => tag.length > 0)
        .join(', ');
    
    // Close modal immediately
    closeTagEditModal();
    
    // Call update function (which will show loading state)
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.updateDocumentTags) {
        window.PDFManagerDocuments.updateDocumentTags(fileId, tags);
    }
}

// Tag management modal functions - NEW
function openTagManageModal(tagName) {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.openTagManageModal) {
        window.PDFManagerDocuments.openTagManageModal(tagName);
    }
}

function closeTagManageModal() {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.closeTagManageModal) {
        window.PDFManagerDocuments.closeTagManageModal();
    }
}

function saveTagManagement() {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.saveTagManagement) {
        window.PDFManagerDocuments.saveTagManagement();
    }
}

// Tag rename modal functions - NEW
function openTagRenameModal(tagName) {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.openTagRenameModal) {
        window.PDFManagerDocuments.openTagRenameModal(tagName);
    }
}

function closeTagRenameModal() {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.closeTagRenameModal) {
        window.PDFManagerDocuments.closeTagRenameModal();
    }
}

function renameTag() {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.renameTag) {
        window.PDFManagerDocuments.renameTag();
    }
}

// Delete tag function - NEW
function deleteTag(tagName) {
    if (window.PDFManagerDocuments && window.PDFManagerDocuments.deleteTag) {
        window.PDFManagerDocuments.deleteTag(tagName);
    }
}

// 취소 버튼 이벤트
document.getElementById('uploadCancelBtn')?.addEventListener('click', function() {
    document.getElementById('pdfFileEmbedPDF').value = '';
    document.getElementById('selectedFileInfo').style.display = 'none';
    document.getElementById('uploadConfirmBox').style.display = 'none';
    // Show dropzone again
    const dropzone = document.querySelector('.upload-dropzone');
    if (dropzone) dropzone.style.display = 'block';
});

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display !== 'none') {
                if (modal.id === 'tagEditModal') {
                    closeTagEditModal();
                } else if (modal.id === 'tagManageModal') {
                    closeTagManageModal();
                } else if (modal.id === 'tagRenameModal') {
                    closeTagRenameModal();
                } else {
                    modal.style.display = 'none';
                }
            }
        });
    }
});
</script>