// 까치 쿼리 시스템 - UI 렌더링 및 인터랙션 (유튜브 스타일)
(function(window, document, $) {
    'use strict';
    
    window.KachiUI = {
        // 초기화
        init: function() {
            console.log("✅ Kachi UI initializing...");
            this.bindEvents();
            this.updateFiltersVisibility();
            
            // 로그인한 경우에만 대화 목록 렌더링
            if (window.isUserLoggedIn) {
                // Core 모듈이 대화를 로드한 후에 렌더링
                setTimeout(() => {
                    this.renderConversationList();
                    this.initScrollListener();
                }, 500);
                
                // Note: 대화 복원과 채팅 렌더링은 이제 Core에서 직접 처리됨
                // 더이상 여기서 별도 setTimeout이 필요하지 않음
            } else {
                // 비로그인 사용자에게 메시지 표시
                $('.conversation-list').html('<div class="empty-state">로그인 후 대화 기록을 볼 수 있습니다</div>');
            }
            
            this.initSidebarClick();
            setTimeout(() => this.startTypingPlaceholderAnimation(), 1000);
        },
        
        // 스크롤 리스너 초기화
        initScrollListener: function() {
            const self = this;
            const $list = $('.conversation-list');
            
            if (!$list.length) return;
            
            let scrollTimeout;
            $list.on('scroll', function() {
                // 디바운스
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    const scrollTop = $(this).scrollTop();
                    const scrollHeight = $(this)[0].scrollHeight;
                    const clientHeight = $(this)[0].clientHeight;
                    
                    // 하단에서 100px 이내에 도달하면 더 로드
                    if (scrollTop + clientHeight >= scrollHeight - 100) {
                        if (KachiCore.hasMoreConversations && !KachiCore.isLoadingMore) {
                            console.log("📜 Loading more conversations...");
                            KachiCore.loadMoreConversations();
                        }
                    }
                }, 200);
            });
        },
        
        // 사이드바 클릭 효과 초기화
        initSidebarClick: function() {
            const self = this;
            const $sidebar = $('.conversations-sidebar');
            const $mainContainer = $('body');
            
            // 이벤트 중복 방지를 위해 먼저 unbind
            $(document).off('click.sidebarToggle');
            $(document).off('click.sidebarOpen');
            $(document).off('click.sidebarClose');
            
            // 헤더의 닫기 버튼 클릭 시
            $(document).on('click.sidebarToggle', '.sidebar-header-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $sidebar.removeClass('active');
                $mainContainer.removeClass('sidebar-open');
            });
        },
        
        // 현재 대화를 최상단으로 이동
        moveCurrentConversationToTop: function() {
            if (!KachiCore.currentConversationId) return;
            
            const currentIndex = KachiCore.conversations.findIndex(c => c.id === KachiCore.currentConversationId);
            if (currentIndex > 0) {
                // 현재 대화를 배열에서 제거하고 맨 앞에 추가
                const currentConversation = KachiCore.conversations.splice(currentIndex, 1)[0];
                currentConversation.updatedAt = new Date().toISOString();
                KachiCore.conversations.unshift(currentConversation);
                
                console.log("📍 Moved current conversation to top");
                
                // UI 업데이트
                this.renderConversationList(false);
            }
        },
        
        // 이벤트 바인딩
        bindEvents: function() {
            const self = this;
            const $queryInput = $('#queryInput');
            
            if ($queryInput.length) {
                // 초기 높이 설정
                $queryInput.css('height', '56px');
                
                // 입력 시 높이 자동 조정
                $queryInput.on('input', function() {
                    if (this.value.trim() === '') {
                        this.style.height = '56px';
                    } else {
                        this.style.height = '56px';
                        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                    }
                });
                
                // 포커스 시에도 높이 조정
                $queryInput.on('focus', function() {
                    if (this.value.trim() !== '') {
                        this.style.height = '56px';
                        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                    }
                });
                
                // 엔터키 처리
                $queryInput.on('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        // 편집 모드가 아닌 경우에만 sendQuery 호출
                        if (!KachiCore.editingMessageId) {
                            KachiAPI.sendQuery();
                        }
                    }
                    
                    // ESC로 편집 취소
                    if (e.key === 'Escape' && KachiCore.editingMessageId) {
                        self.cancelEdit();
                    }
                });
            }
            
            // 통합 필터 버튼 이벤트 - 개선된 애니메이션
            $(document).on('click', '.filter-main-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $container = $(this).closest('.filter-button-container');
                const isExpanded = $container.hasClass('expanded');
                
                if (isExpanded) {
                    // 닫기 애니메이션
                    $container.addClass('collapsing').removeClass('expanded');
                    
                    // 애니메이션 완료 후 collapsing 클래스 제거
                    setTimeout(() => {
                        $container.removeClass('collapsing');
                    }, 300);
                } else {
                    // 열기 애니메이션
                    $container.addClass('expanded').removeClass('collapsing');
                }
                
                // 다른 드롭다운 모두 닫기
                $('#tagDropdownContent, #docDropdownContent').hide();
                $('#queryInput').removeClass('open-dropdown');
            });
            
            // 필터 옵션 버튼 클릭 (태그/문서)
            $(document).on('click', '.filter-option-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const type = $(this).data('type');
                
                // 필터 버튼 축소 - 부드러운 애니메이션
                const $container = $('.filter-button-container');
                $container.addClass('collapsing').removeClass('expanded');
                
                setTimeout(() => {
                    $container.removeClass('collapsing');
                }, 300);
                
                // 해당 드롭다운 열기
                self.toggleDropdown(type);
            });
            
            // 사이드바 토글 (모바일)
            $(document).on('click', '.sidebar-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggleSidebar();
            });
            
            // 하단 새 대화 버튼 클릭 - 항상 새 대화 시작
            $(document).on('click', '.sidebar-bottom-new-chat', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // 로그인 체크
                if (!window.isUserLoggedIn) {
                    alert('로그인 후 대화를 시작할 수 있습니다.');
                    return;
                }
                
                // 사이드바 상태와 관계없이 항상 새 대화 시작
                self.startNewChat();
            });
            
            // 새 채팅 버튼 (상단)
            $(document).on('click', '.new-chat-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // 로그인 체크
                if (!window.isUserLoggedIn) {
                    alert('로그인 후 대화를 시작할 수 있습니다.');
                    return;
                }
                
                self.startNewChat();
            });
            
            // 사이드바 닫기 버튼 (모바일)
            $(document).on('click', '.sidebar-close-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeSidebar();
            });
            
            // 모바일에서 오버레이 클릭 시 사이드바 닫기
            $(document).on('click', '.sidebar-overlay', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeSidebar();
            });
            
            // 대화 항목 클릭
            $(document).on('click', '.conversation-item', function(e) {
                // 액션 버튼 클릭이 아닌 경우에만 대화 로드
                if (!$(e.target).closest('.conversation-actions').length) {
                    e.stopPropagation();
                    
                    const $sidebar = $('.conversations-sidebar');
                    const conversationId = $(this).data('id');
                    
                    // 사이드바가 닫혀있으면 먼저 열기
                    if (!$sidebar.hasClass('active')) {
                        $sidebar.addClass('active');
                        $('body').addClass('sidebar-open');
                        console.log("✅ Sidebar opened for conversation load");
                    }
                    
                    // 대화 로드
                    self.loadConversation(conversationId);
                }
            });
            
            // 사이드바가 닫혀있을 때 사이드바 영역 클릭 시 열기 (대화 항목 클릭 제외)
            $(document).on('click', '.conversations-sidebar:not(.active)', function(e) {
                // 새 대화 버튼이나 대화 항목 클릭은 제외
                if ($(e.target).closest('.sidebar-bottom-new-chat').length || 
                    $(e.target).closest('.conversation-item').length) {
                    return;
                }
                
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('active');
                $('body').addClass('sidebar-open');
            });
            
            // 대화 삭제
            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(this).closest('.conversation-item');
                const conversationId = $item.data('id');
                
                if (confirm('이 대화를 삭제하시겠습니까?')) {
                    self.deleteConversation(conversationId);
                }
            });
            
            // 대화 이름 변경
            $(document).on('click', '.rename-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(this).closest('.conversation-item');
                const conversationId = $item.data('id');
                self.renameConversation(conversationId);
            });
            
            // 메시지 편집 버튼
            $(document).on('click', '.edit-message-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const messageId = $(this).data('message-id');
                self.startEditMessage(messageId);
            });
            
            // 메시지 편집 저장
            $(document).on('click', '.message-edit-save', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.saveEditMessage();
            });
            
            // 메시지 편집 취소
            $(document).on('click', '.message-edit-cancel', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.cancelEditMessage();
            });
            
            // 편집 중 textarea 자동 높이 조정
            $(document).on('input', '.message-edit-input', function() {
                self.adjustTextareaHeight(this);
            });
            
            // 편집 중 엔터키 처리
            $(document).on('keydown', '.message-edit-input', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.saveEditMessage();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    self.cancelEditMessage();
                }
            });
            
            // 중지 버튼
            $(document).on('click', '#stopButton', function(e) {
                e.preventDefault();
                e.stopPropagation();
                KachiCore.stopQuery();
                self.resetQueryUI();
            });
            
            // 필터 제거
            $(document).on('click', '.pill-x', function(e) {
                e.stopPropagation();
                const type = $(this).data('type');
                const value = $(this).data('value');
                self.removeSelection(type, value);
            });
            
            // 외부 클릭 시 드롭다운과 필터 버튼 닫기 - 개선된 애니메이션
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.query-input-wrapper').length && 
                    !$(e.target).closest('.filter-button-container').length) {
                    self.closeAllDropdowns();
                    
                    // 필터 버튼도 부드럽게 닫기
                    const $filterContainer = $('.filter-button-container');
                    if ($filterContainer.hasClass('expanded')) {
                        $filterContainer.addClass('collapsing').removeClass('expanded');
                        setTimeout(() => {
                            $filterContainer.removeClass('collapsing');
                        }, 300);
                    }
                }
            });
            
            // ESC 키로 사이드바 닫기 - 개선된 애니메이션
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    const $filterContainer = $('.filter-button-container');
                    if ($filterContainer.hasClass('expanded')) {
                        $filterContainer.addClass('collapsing').removeClass('expanded');
                        setTimeout(() => {
                            $filterContainer.removeClass('collapsing');
                        }, 300);
                    } else if ($('.conversations-sidebar').hasClass('active')) {
                        self.closeSidebar();
                    }
                }
            });
            
            // 전역 함수 등록
            window.filterOptions = function(type) { self.filterOptions(type); };
        },
        
        // 사이드바 토글
        toggleSidebar: function() {
            const $sidebar = $('.conversations-sidebar');
            const $overlay = $('.sidebar-overlay');
            const $body = $('body');
            
            if ($sidebar.length) {
                $sidebar.toggleClass('active');
                $body.toggleClass('sidebar-open');
                
                // 모바일에서만 오버레이 토글
                if ($(window).width() <= 768) {
                    $overlay.toggleClass('active');
                }
                
                console.log("✅ Sidebar toggled");
            }
        },
        
        // 사이드바 닫기
        closeSidebar: function() {
            $('.conversations-sidebar').removeClass('active');
            $('.sidebar-overlay').removeClass('active');
            $('body').removeClass('sidebar-open');
        },
        
        // 채팅 모드로 전환
        enterChatMode: function() {
            const $container = $('.query-box-container');
            $container.addClass('active');
            
            setTimeout(() => {
                this.scrollToBottom();
            }, 500);
        },
        
        // 새 대화가 이미 열려있는지 확인
        isNewChatOpen: function() {
            // 현재 대화가 존재하는지 확인
            if (!KachiCore.currentConversationId) {
                // ID가 없고 채팅 기록도 비어있으면 새 대화 상태
                return KachiCore.chatHistory.length === 0;
            }
            
            // currentConversationId가 있는 경우, 해당 대화가 실제로 존재하는지 확인
            const currentConversation = KachiCore.conversations.find(c => c.id === KachiCore.currentConversationId);
            
            // 대화가 존재하고 메시지가 비어있으면 새 대화 상태
            if (currentConversation) {
                return currentConversation.messages.length === 0 && 
                       currentConversation.title === '새 대화' &&
                       KachiCore.chatHistory.length === 0;
            }
            
            return false;
        },
        
        // 새 채팅 시작
        startNewChat: function() {
            // 이미 새 대화가 열려있는지 확인
            if (this.isNewChatOpen()) {
                console.log("ℹ️ New chat is already open");
                
                // 채팅 모드가 아니면 활성화
                const $container = $('.query-box-container');
                if (!$container.hasClass('active')) {
                    $container.removeClass('active');
                }
                
                // 입력창에 포커스 주기
                const $queryInput = $('#queryInput');
                if ($queryInput.length) {
                    $queryInput.focus();
                }
                
                // 모바일에서는 사이드바 닫기
                if ($(window).width() <= 768) {
                    this.closeSidebar();
                }
                
                return; // 새 대화를 다시 생성하지 않음
            }
            
            const $container = $('.query-box-container');
            
            // 진행 중인 쿼리가 있으면 중지
            if (KachiCore.isQueryInProgress) {
                KachiCore.stopQuery();
                this.resetQueryUI();
            }
            
            // 새 대화 생성
            const newConversationId = KachiCore.createNewConversation();
            
            // 채팅 기록 초기화
            KachiCore.chatHistory = [];
            
            // 페이드 아웃 효과 후 내용 삭제
            $('.chat-messages').fadeOut(300, function() {
                $(this).empty().show();
            });
            
            // 선택된 필터 초기화
            KachiCore.selectedTags = [];
            KachiCore.selectedDocs = [];
            this.updateSelectedPreview('tag');
            this.updateSelectedPreview('doc');
            this.updateFiltersVisibility();
            
            // 입력창 초기화 및 포커스
            $('#queryInput').val('').css('height', '56px').focus();
            
            // 컨테이너 축소
            $container.removeClass('active');
            
            // 대화 목록 업데이트 (전체 다시 렌더링)
            this.renderConversationList(false);
            
            console.log("🆕 New chat started with ID:", newConversationId);
        },
        
        // 대화 불러오기
        loadConversation: function(conversationId) {
            console.log(`📖 Loading conversation: ${conversationId}`);
            
            if (KachiCore.loadConversation(conversationId)) {
                console.log(`✅ Successfully loaded conversation: ${conversationId}, messages: ${KachiCore.chatHistory.length}`);
                this.renderChatHistory();
                this.renderConversationList(false);
                
                // 사이드바 닫기 - 모바일에서만 닫기
                if ($(window).width() <= 768) {
                    this.closeSidebar();
                }
            } else {
                console.error(`❌ Failed to load conversation: ${conversationId}`);
            }
        },
        
        // 대화 삭제
        deleteConversation: function(conversationId) {
            const isCurrentConversation = KachiCore.deleteConversation(conversationId);
            
            if (isCurrentConversation) {
                // 현재 대화가 삭제된 경우 UI만 초기화 (새 대화 생성하지 않음)
                KachiCore.currentConversationId = null;
                KachiCore.chatHistory = [];
                
                // 채팅 메시지 영역 비우기
                $('.chat-messages').empty();
                
                // 채팅 모드 해제
                $('.query-box-container').removeClass('active');
                
                // 입력창 초기화
                $('#queryInput').val('').css('height', '56px');
            }
            
            // 대화 목록 업데이트 (전체 다시 렌더링)
            this.renderConversationList(false);
        },
        
        // 대화 이름 변경
        renameConversation: function(conversationId) {
            const conversation = KachiCore.conversations.find(c => c.id === conversationId);
            
            if (conversation) {
                const newTitle = prompt('새 이름을 입력하세요:', conversation.title);
                if (newTitle && newTitle.trim()) {
                    conversation.title = newTitle.trim();
                    KachiCore.saveConversations();
                    this.renderConversationList(false);
                }
            }
        },
        
        // 채팅 기록 렌더링
        renderChatHistory: function() {
            $('.chat-messages').empty();
            
            console.log(`🔄 Rendering chat history: ${KachiCore.chatHistory.length} messages`);
            
            KachiCore.chatHistory.forEach((message, index) => {
                // 메시지 유효성 검증
                if (!message || !message.type) {
                    console.warn(`⚠️ Invalid message at index ${index}:`, message);
                    return;
                }
                
                // 메시지 구조 상세 디버깅
                if (message.type === 'assistant') {
                    console.log(`🔍 Assistant message ${index}:`, {
                        id: message.id,
                        type: message.type,
                        hasContent: !!message.content,
                        contentLength: message.content ? message.content.length : 0,
                        contentPreview: message.content ? message.content.substring(0, 100) : 'NO CONTENT',
                        hasReferencedDocs: !!message.referencedDocs,
                        referencedDocsLength: message.referencedDocs ? message.referencedDocs.length : 0,
                        referencedDocsPreview: message.referencedDocs ? message.referencedDocs.substring(0, 100) : 'NO REFS'
                    });
                }
                
                // 스트리밍 중 생성된 빈 assistant 메시지만 필터링 (id가 없거나 임시 메시지)
                if (message.type === 'assistant' && (!message.content || message.content.trim() === '')) {
                    // 메시지에 ID가 없거나 임시 메시지인 경우에만 건너뛰기
                    if (!message.id || message.id.includes('temp-') || message.isTemporary) {
                        console.log(`🚫 Filtering empty streaming message:`, message.id);
                        return;
                    }
                    // 정상적인 빈 assistant 메시지는 렌더링 (복원된 메시지일 수 있음)
                    console.log(`✅ Rendering empty assistant message (restored):`, message.id);
                }
                
                const messageHtml = this.createMessageHtml(message);
                $('.chat-messages').append(messageHtml);
            });
            
            // 채팅 모드 활성화
            if (KachiCore.chatHistory.length > 0) {
                $('.query-box-container').addClass('active');
                this.scrollToBottom();
                
                // 모든 assistant 메시지에 대해 MathJax 렌더링
                setTimeout(() => {
                    $('.message.assistant').each(function() {
                        if (window.KachiAPI && window.KachiAPI.renderMathInElement) {
                            window.KachiAPI.renderMathInElement(this);
                        }
                    });
                }, 100);
            } else {
                $('.query-box-container').removeClass('active');
            }
        },
        
        // 메시지 HTML 생성 - 편집 버튼 위치 수정
        createMessageHtml: function(message) {
            const userAvatar = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="8" r="4" fill="white"/>
                    <path d="M20 19C20 16.2386 16.4183 14 12 14C7.58172 14 4 16.2386 4 19V21H20V19Z" fill="white"/>
                </svg>
            `;
            
            // 메시지 내용 처리 - 표시용으로 원본 URL을 프록시 URL로 변환
            let messageContent = message.content;
            if (messageContent && window.KachiAPI && window.KachiAPI.processImageUrlsForDisplay) {
                messageContent = window.KachiAPI.processImageUrlsForDisplay(messageContent);
                console.log("🖼️ Converting stored URLs to proxy URLs for display");
            }
            
            const contentHtml = message.type === 'assistant' && message.referencedDocs ? 
                `${message.referencedDocs}<div class="message-text">${messageContent}</div>` : 
                `<div class="message-text">${messageContent}</div>`;
            
            // 사용자 메시지일 경우 편집 버튼 추가
            let editButtonHtml = '';
            if (message.type === 'user') {
                editButtonHtml = `
                    <button class="edit-message-btn" data-message-id="${message.id}" title="메시지 편집">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                `;
            }
            
            return `
                <div class="message ${message.type}" id="${message.id}">
                    <div class="message-avatar">
                        ${message.type === 'user' ? userAvatar : '🐦'}
                    </div>
                    <div class="message-content">
                        <div class="message-bubble-wrapper">
                            ${editButtonHtml}
                            <div class="message-bubble">
                                ${contentHtml}
                            </div>
                        </div>
                        <div class="message-time">${message.time}</div>
                    </div>
                </div>
            `;
        },
        
        // 메시지 추가 UI
        addMessageUI: function(type, content, isStreaming) {
            // 편집 중인 메시지가 있으면 정리
            if (KachiCore.editingMessageId || $('.message.editing').length > 0) {
                console.warn("⚠️ Cleaning up editing state before adding new message");
                KachiCore.editingMessageId = null;
                $('.message.editing').removeClass('editing');
            }
            
            const messageObj = KachiCore.addMessage(type, content);
            const messageHtml = this.createMessageHtml(messageObj);
            
            // 메시지를 추가하기 전에 채팅 영역이 존재하는지 확인
            const $chatMessages = $('.chat-messages');
            if (!$chatMessages.length) {
                console.error("❌ Chat messages container not found");
                return null;
            }
            
            $chatMessages.append(messageHtml);
            
            // 추가된 메시지 요소 확인
            const addedElement = document.getElementById(messageObj.id);
            if (!addedElement) {
                console.error("❌ Failed to add message to DOM");
                return null;
            }
            
            // 타입 클래스 확인
            if (!addedElement.classList.contains(type)) {
                console.error(`❌ Message element missing type class: ${type}`);
                // 클래스 강제 추가
                addedElement.classList.add(type);
            }
            
            // Assistant 메시지이고 스트리밍이 아닌 경우 MathJax 렌더링
            if (type === 'assistant' && !isStreaming && window.KachiAPI && window.KachiAPI.renderMathInElement) {
                setTimeout(() => {
                    window.KachiAPI.renderMathInElement(addedElement);
                }, 50);
            }
            
            this.scrollToBottom();
            
            // 대화 저장 (스트리밍이 아닌 경우)
            if (!isStreaming) {
                KachiCore.updateCurrentConversation();
                
                // 사용자 메시지인 경우 현재 대화를 최상단으로 이동
                if (type === 'user') {
                    this.moveCurrentConversationToTop();
                } else {
                    // Assistant 메시지도 업데이트하되 위치는 유지
                    this.renderConversationList(false);
                }
            }
            
            return messageObj.id;
        },
        
        // 대화 목록 렌더링
        renderConversationList: function(append = false) {
            const $list = $('.conversation-list');
            if (!$list.length) {
                console.warn("⚠️ Conversation list element not found");
                return;
            }
            
            // 로그인하지 않은 경우
            if (!window.isUserLoggedIn) {
                $list.html('<div class="empty-state">로그인 후 대화 기록을 볼 수 있습니다</div>');
                return;
            }
            
            // 첫 로딩 중인 경우
            if (KachiCore.isLoading && !append) {
                $list.html('<div class="empty-state">대화 목록을 불러오는 중...</div>');
                return;
            }
            
            // 추가 모드가 아니면 리스트 초기화
            if (!append) {
                $list.empty();
            } else {
                // 로딩 인디케이터 제거
                $list.find('.loading-more').remove();
            }
            
            if (KachiCore.conversations.length === 0 && !append) {
                $list.append('<div class="empty-state">대화가 없습니다</div>');
                return;
            }
            
            // 대화 목록 렌더링 (append 모드일 때는 새로운 항목만)
            const startIndex = append ? $list.find('.conversation-item').length : 0;
            const conversationsToRender = KachiCore.conversations.slice(startIndex);
            
            conversationsToRender.forEach((conversation, index) => {
                const actualIndex = startIndex + index;
                const isActive = conversation.id === KachiCore.currentConversationId;
                const date = new Date(conversation.updatedAt);
                const dateStr = KachiCore.formatDate(date);
                
                // 대화 아이콘 생성 (첫 글자 또는 번호)
                const iconText = conversation.title.charAt(0).toUpperCase() || (actualIndex + 1);
                
                const itemHtml = `
                    <div class="conversation-item ${isActive ? 'active' : ''}" 
                         data-id="${conversation.id}"
                         data-title="${KachiCore.escapeHtml(conversation.title)}">
                        <div class="conversation-icon">${iconText}</div>
                        <div class="conversation-info">
                            <div class="conversation-title">${KachiCore.escapeHtml(conversation.title)}</div>
                            <div class="conversation-date">${dateStr}</div>
                        </div>
                        <div class="conversation-actions">
                            <button class="rename-btn" title="이름 변경">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <button class="delete-btn" title="삭제">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
                
                $list.append(itemHtml);
            });
            
            // 더 불러올 수 있으면 로딩 인디케이터 추가
            if (KachiCore.hasMoreConversations) {
                const loadingHtml = `
                    <div class="loading-more ${KachiCore.isLoadingMore ? 'loading' : ''}">
                        ${KachiCore.isLoadingMore ? '불러오는 중...' : '스크롤하여 더 보기'}
                    </div>
                `;
                $list.append(loadingHtml);
            }
        },
        
        // 메시지 편집 시작
        startEditMessage: function(messageId) {
            const message = KachiCore.findMessage(messageId);
            if (!message || message.type !== 'user') {
                console.warn("⚠️ Cannot edit this message");
                return;
            }
            
            // 편집 모드 설정
            KachiCore.editingMessageId = messageId;
            
            // 메시지 요소 찾기
            const $messageElement = $(`#${messageId}`);
            const $messageText = $messageElement.find('.message-text');
            const $messageBubble = $messageElement.find('.message-bubble');
            
            // 원본 텍스트 추출 (시설정의 제외한 원본 메시지만)
            let originalText = '';
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = message.content;
            
            // 시설정의 관련 요소 제거하고 원본 텍스트만 추출
            const clone = tempDiv.cloneNode(true);
            const facilityElements = clone.querySelectorAll('.modified-query-divider, .modified-query-label, .modified-query-text');
            facilityElements.forEach(el => el.remove());
            originalText = clone.textContent || clone.innerText || '';
            
            // originalText가 비어있으면 전체 텍스트 사용 (시설정의가 없는 경우)
            if (!originalText.trim()) {
                originalText = tempDiv.textContent || tempDiv.innerText || '';
            }
            
            // 편집 가능한 textarea로 변경
            const editHtml = `
                <div class="message-edit-wrapper">
                    <div class="message-edit-header">메시지 편집</div>
                    <textarea class="message-edit-input">${KachiCore.escapeHtml(originalText.trim())}</textarea>
                    <div class="message-edit-footer">
                        <span class="message-edit-hint">Enter로 저장, Esc로 취소</span>
                        <div class="message-edit-actions">
                            <button class="message-edit-save" title="저장">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </button>
                            <button class="message-edit-cancel" title="취소">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // 메시지 내용을 편집 UI로 교체
            $messageBubble.html(editHtml);
            
            // textarea 포커스 및 크기 조정
            const $textarea = $messageBubble.find('.message-edit-input');
            $textarea.focus();
            this.adjustTextareaHeight($textarea[0]);
            
            // 메시지에 편집 중 클래스 추가
            $messageElement.addClass('editing');
            
            console.log("📝 Editing message inline:", messageId);
        },
        
        // textarea 높이 자동 조정
        adjustTextareaHeight: function(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
        },
        
        // 메시지 편집 저장
        saveEditMessage: function() {
            if (!KachiCore.editingMessageId) return;
            
            const newContent = $('.message-edit-input').val().trim();
            if (!newContent) {
                alert('메시지를 입력해주세요.');
                return;
            }
            
            const messageId = KachiCore.editingMessageId;
            const message = KachiCore.findMessage(messageId);
            
            if (message) {
                // 편집 모드 먼저 종료
                KachiCore.editingMessageId = null;
                
                // 스트림 버퍼 초기화
                KachiCore.streamBuffer = "";
                
                // Facility 매칭 처리
                const processedQuery = KachiAPI.processFacilityMatching(newContent);
                const isModified = processedQuery !== newContent;
                
                // 메시지 내용 업데이트 (시설정의 표시 포함)
                let messageContent = KachiCore.escapeHtml(newContent);
                if (isModified) {
                    // 체크 마크 SVG 아이콘 사용
                    messageContent += `<div class="modified-query-divider"></div>`;
                    messageContent += `<div class="modified-query-label">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-bottom:3px;">
                            <circle cx="12" cy="12" r="10" stroke-width="2"/>
                            <path d="M9 12l2 2 4-4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        시설 정의 적용:
                    </div>`;
                    messageContent += `<div class="modified-query-text">${KachiCore.escapeHtml(processedQuery)}</div>`;
                }
                
                // 메시지 콘텐츠에 이미지 처리 적용 후 저장 (unrendering 방지)
                if (window.KachiAPI && window.KachiAPI.processImageUrlsForDisplay) {
                    messageContent = window.KachiAPI.processImageUrlsForDisplay(messageContent);
                    console.log("🖼️ Processed images in edited message content before storage");
                }
                message.content = messageContent;
                
                // UI에서 메시지 업데이트
                const $messageElement = $(`#${messageId}`);
                const $messageBubble = $messageElement.find('.message-bubble');
                $messageBubble.html(`<div class="message-text">${messageContent}</div>`);
                $messageElement.removeClass('editing');
                
                // 편집된 메시지 이후의 모든 메시지 삭제
                const removedCount = KachiCore.removeMessagesAfter(messageId);
                console.log(`✂️ Removed ${removedCount} messages after edit`);
                
                // UI에서도 이후 메시지 즉시 삭제
                let foundCurrent = false;
                $('.message').each(function() {
                    const $this = $(this);
                    if ($this.attr('id') === messageId) {
                        foundCurrent = true;
                    } else if (foundCurrent) {
                        $this.remove();
                    }
                });
                
                // 현재 대화 업데이트 및 최상단으로 이동
                KachiCore.updateCurrentConversation();
                this.moveCurrentConversationToTop();
                
                // 입력창 초기화 및 확인
                const $queryInput = $('#queryInput');
                $queryInput.val('').css('height', '56px').blur();
                
                // 처리된 쿼리로 전송
                setTimeout(() => {
                    // 한번 더 입력창 확인
                    if ($queryInput.val()) {
                        $queryInput.val('');
                    }
                    this.sendEditedQuery(processedQuery);
                }, 300);
            }
        },
        
        // 편집된 쿼리 전송 (사용자 메시지 추가 없이)
        sendEditedQuery: async function(query) {
            if (!KachiCore.checkLoginBeforeQuery()) {
                return;
            }

            if (KachiCore.isQueryInProgress) {
                console.warn("⚠️ Query already in progress");
                return;
            }
            
            // 편집 상태 완전 초기화
            KachiCore.editingMessageId = null;
            KachiCore.streamBuffer = "";
            
            // 편집 중인 메시지가 남아있는지 확인
            const $editingMessages = $('.message.editing');
            if ($editingMessages.length > 0) {
                console.log("🧹 Cleaning up editing messages...");
                $editingMessages.removeClass('editing');
                // 편집 UI가 남아있으면 제거
                $editingMessages.find('.message-edit-wrapper').each(function() {
                    const $message = $(this).closest('.message');
                    const messageId = $message.attr('id');
                    const message = KachiCore.findMessage(messageId);
                    if (message) {
                        $(this).closest('.message-bubble').html(`<div class="message-text">${message.content}</div>`);
                    }
                });
            }
            
            // 입력창 완전 초기화
            const $queryInput = $('#queryInput');
            $queryInput.val('').css('height', '56px').blur();
            
            // UI 안정화를 위한 대기
            await new Promise(resolve => setTimeout(resolve, 200));
            
            KachiCore.isQueryInProgress = true;
            
            // 현재 대화가 없으면 새로 생성
            if (!KachiCore.currentConversationId) {
                KachiCore.createNewConversation();
                this.renderConversationList(false);
            }
            
            // 채팅 모드로 전환
            if (!$('.query-box-container').hasClass('active')) {
                this.enterChatMode();
            }
            
            // 입력창 비활성화
            $queryInput.prop('disabled', true).addClass('with-stop');
            $('.filter-main-btn').css('pointer-events', 'none');
            
            console.log("🔁 Sending edited query:", query);
            
            // 직접 스트리밍 처리 (사용자 메시지 추가 없이)
            KachiAPI.handleStreamingQuery(query);
        },
        
        // 메시지 편집 취소
        cancelEditMessage: function() {
            if (!KachiCore.editingMessageId) return;
            
            const messageId = KachiCore.editingMessageId;
            const message = KachiCore.findMessage(messageId);
            
            if (message) {
                // 원래 메시지 내용으로 복원
                const $messageElement = $(`#${messageId}`);
                const $messageBubble = $messageElement.find('.message-bubble');
                
                $messageBubble.html(`<div class="message-text">${message.content}</div>`);
                $messageElement.removeClass('editing');
            }
            
            KachiCore.editingMessageId = null;
            console.log("❌ Edit cancelled");
        },
        
        // 편집 취소 (기존 메서드는 유지 - 하위 호환성)
        cancelEdit: function() {
            this.cancelEditMessage();
        },
        
        // 스크롤 최하단으로
        scrollToBottom: function() {
            const $messages = $('.chat-messages');
            if ($messages.length) {
                $messages.scrollTop($messages[0].scrollHeight);
            }
        },
        
        // 필터 가시성 업데이트
        updateFiltersVisibility: function() {
            const hasFilters = KachiCore.selectedTags.length > 0 || KachiCore.selectedDocs.length > 0;
            const $container = $('.selected-filters-container');
            
            if (hasFilters) {
                $container.addClass('has-filters').removeClass('no-filters');
            } else {
                $container.addClass('no-filters').removeClass('has-filters');
            }
        },
        
        // 선택된 항목 미리보기 업데이트
        updateSelectedPreview: function(type) {
            const $previewBox = $(`#${type === 'tag' ? 'selectedTagPreview' : 'selectedDocPreview'}`);
            const selectedList = type === 'tag' ? KachiCore.selectedTags : KachiCore.selectedDocs;

            if (selectedList.length === 0) {
                $previewBox.html("");
                return;
            }

            const html = selectedList.map((v, idx) => `
                <span class="pill ${type}" title="${v}">
                    <span class="pill-x" data-type="${type}" data-value="${v}">✕</span>
                    ${type === 'tag' ? '#' : '📄'} ${v}
                </span>
            `).join(" ");
            
            $previewBox.html(html);
        },
        
        // 선택 제거
        removeSelection: function(type, value) {
            if (type === 'tag') {
                KachiCore.selectedTags = KachiCore.selectedTags.filter(tag => tag !== value);
            } else if (type === 'doc') {
                KachiCore.selectedDocs = KachiCore.selectedDocs.filter(doc => doc !== value);
            }
            
            // 애니메이션
            $(`.pill.${type}`).each(function() {
                if ($(this).text().includes(value)) {
                    $(this).css('opacity', 0.3);
                }
            });

            this.updateSelectedPreview(type);
            this.updateFiltersVisibility();
        },
        
        // 드롭다운 토글
        toggleDropdown: function(type) {
            const $queryInput = $('#queryInput');
            const $dropdown = $(`#${type}DropdownContent`);
            const $otherDropdown = type === 'tag' ? $('#docDropdownContent') : $('#tagDropdownContent');
            const isVisible = $dropdown.is(':visible');

            // 다른 드롭다운 닫기
            $otherDropdown.hide();

            if (!isVisible) {
                // 드롭다운 열기
                $dropdown.show();
                $queryInput.addClass('open-dropdown');
                
                // 이벤트 전파 중지를 위한 타이머
                setTimeout(() => {
                    if (type === 'tag') {
                        $('#tagSearchInput').val('').focus();
                        if (KachiCore.allTagList.length === 0) {
                            KachiAPI.fetchTagsAndDocs();
                        } else {
                            this.renderTagOptions(KachiCore.allTagList);
                        }
                    } else if (type === 'doc') {
                        $('#docSearchInput').val('').focus();
                        if (KachiCore.allDocList.length > 0) {
                            this.renderDocOptions(KachiCore.allDocList);
                        }
                    }
                }, 10);
            } else {
                // 드롭다운 닫기
                $dropdown.hide();
                $queryInput.removeClass('open-dropdown');
            }
        },
        
        // 모든 드롭다운 닫기
        closeAllDropdowns: function() {
            $('#tagDropdownContent, #docDropdownContent').hide();
            $('#queryInput').removeClass('open-dropdown');
        },
        
        // 옵션 필터링
        filterOptions: function(type) {
            const input = $(`#${type}SearchInput`).val().toLowerCase();
            const $items = $(`#${type}OptionsContainer .option-item`);
            
            $items.each(function() {
                const $item = $(this);
                const text = $item.text().toLowerCase();
                $item.toggle(text.includes(input));
            });
        },
        
        // 태그 옵션 렌더링
        renderTagOptions: function(tags) {
            const container = $('#tagOptionsContainer');
            container.empty();
            
            tags.forEach(tag => {
                const $div = $('<div>', {
                    class: 'option-item',
                    text: tag,
                    click: () => {
                        if (!KachiCore.selectedTags.includes(tag)) {
                            KachiCore.selectedTags.push(tag);
                            this.updateSelectedPreview('tag');
                            this.updateFiltersVisibility();
                        }
                        $('#tagDropdownContent').hide();
                        $('#tagSearchInput').val('');
                        $('#queryInput').removeClass('open-dropdown');
                    }
                });
                container.append($div);
            });
        },
        
        // 문서 옵션 렌더링
        renderDocOptions: function(docs) {
            const container = $('#docOptionsContainer');
            container.empty();
            
            docs.forEach(doc => {
                const $div = $('<div>', {
                    class: 'option-item',
                    text: doc,
                    click: () => {
                        if (!KachiCore.selectedDocs.includes(doc)) {
                            KachiCore.selectedDocs.push(doc);
                        }
                        $('#docSearchInput').val('');
                        $('#docDropdownContent').hide();
                        $('#queryInput').removeClass('open-dropdown');
                        this.updateSelectedPreview('doc');
                        this.updateFiltersVisibility();
                    }
                });
                container.append($div);
            });
        },
        
        // 쿼리 UI 초기화
        resetQueryUI: function() {
            $('#queryInput').prop('disabled', false).removeClass('with-stop');
            $('.filter-main-btn').css('pointer-events', 'auto');
            $('#loadingMessage').hide();
            $('#stopButton').hide();
        },
        
        // 플레이스홀더 애니메이션
        startTypingPlaceholderAnimation: function() {
            const $input = $('#queryInput');
            if (!$input.length) return;

            const placeholders = [
                "🔎︎ 질문을 입력하세요",
                "태그나 문서를 지정하여 질문해보세요.",
                "어떤 질문을 입력할지 고민되나요?",
                "기술문서의 모든 정보를 까치가 찾아드려요!"
            ];

            let currentIndex = 0;

            function typeText(text, callback) {
                let i = 0;
                const interval = setInterval(() => {
                    $input.attr('placeholder', text.slice(0, i + 1));
                    i++;
                    if (i >= text.length) {
                        clearInterval(interval);
                        if (callback) setTimeout(callback, 2500);
                    }
                }, 80);
            }

            function cyclePlaceholders() {
                if (currentIndex === 0) {
                    $input.attr('placeholder', placeholders[currentIndex]);
                    currentIndex++;
                    setTimeout(cyclePlaceholders, 5000);
                } else {
                    typeText(placeholders[currentIndex], () => {
                        currentIndex = (currentIndex + 1) % placeholders.length;
                        cyclePlaceholders();
                    });
                }
            }

            cyclePlaceholders();
        },
        
        // 저장 오류 알림 표시
        showSaveErrorNotification: function() {
            console.warn('⚠️ Showing save error notification to user');
            
            // 기존 알림 제거
            $('.save-error-notification').remove();
            
            const notification = $(`
                <div class="save-error-notification" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #dc3545;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    z-index: 10000;
                    max-width: 300px;
                    font-size: 14px;
                    line-height: 1.4;
                ">
                    <div style="font-weight: bold; margin-bottom: 8px;">💾 저장 오류</div>
                    <div>대화 내용 저장에 실패했습니다. 네트워크 연결을 확인해주세요.</div>
                    <button onclick="$(this).closest('.save-error-notification').fadeOut()" 
                            style="
                                position: absolute;
                                top: 8px;
                                right: 8px;
                                background: none;
                                border: none;
                                color: white;
                                font-size: 18px;
                                cursor: pointer;
                                padding: 0;
                                width: 24px;
                                height: 24px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">×</button>
                </div>
            `);
            
            $('body').append(notification);
            
            // 5초 후 자동 제거
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 5000);
        },
        
        // 저장 성공 알림 표시 (선택적)
        showSaveSuccessNotification: function(message = '대화가 저장되었습니다') {
            console.log('✅ Showing save success notification');
            
            // 기존 알림 제거
            $('.save-success-notification').remove();
            
            const notification = $(`
                <div class="save-success-notification" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #28a745;
                    color: white;
                    padding: 12px 16px;
                    border-radius: 6px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                    z-index: 10000;
                    font-size: 14px;
                    opacity: 0;
                    transform: translateX(20px);
                    transition: all 0.3s ease;
                ">
                    ✅ ${message}
                </div>
            `);
            
            $('body').append(notification);
            
            // 애니메이션으로 표시
            setTimeout(() => {
                notification.css({
                    opacity: 1,
                    transform: 'translateX(0)'
                });
            }, 10);
            
            // 2초 후 자동 제거
            setTimeout(() => {
                notification.css({
                    opacity: 0,
                    transform: 'translateX(20px)'
                });
                setTimeout(() => notification.remove(), 300);
            }, 2000);
        },
        
        // 연결 상태 확인 및 표시
        showConnectionStatus: function(isConnected = true) {
            $('.connection-status').remove();
            
            if (!isConnected) {
                const statusIndicator = $(`
                    <div class="connection-status" style="
                        position: fixed;
                        bottom: 20px;
                        left: 20px;
                        background: #ffc107;
                        color: #212529;
                        padding: 8px 12px;
                        border-radius: 4px;
                        font-size: 12px;
                        z-index: 9999;
                    ">
                        🔌 연결 상태를 확인 중...
                    </div>
                `);
                
                $('body').append(statusIndicator);
                
                // 10초 후 자동 제거
                setTimeout(() => {
                    statusIndicator.fadeOut(() => statusIndicator.remove());
                }, 10000);
            }
        }
    };
    
})(window, document, jQuery);