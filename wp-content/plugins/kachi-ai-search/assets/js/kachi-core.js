// 까치 쿼리 시스템 - 핵심 기능 및 상태 관리
(function(window, document, $) {
    'use strict';
    
    // 전역 상태 관리 객체
    window.KachiCore = {
        // 상태 변수
        selectedTags: [],
        selectedDocs: [],
        allTagList: [],
        allDocList: [],
        isQueryInProgress: false,
        controller: null,
        streamBuffer: "",
        chatHistory: [],
        currentMessageId: 0,
        conversations: [],
        currentConversationId: null,
        editingMessageId: null,
        displayedLength: 0,
        isCharStreaming: false,
        typeTimer: null,
        isSaving: false,
        isLoading: false,
        isLoadingMore: false,
        currentPage: 1,
        hasMoreConversations: true,
        perPage: 20,
        
        // 초기화
        init: function() {
            console.log("✅ Kachi Core initializing...");
            // 로그인한 사용자만 대화 목록 로드
            if (window.isUserLoggedIn) {
                this.loadConversations();
            } else {
                this.conversations = [];
                console.log("⚠️ User not logged in, skipping conversation load");
            }
        },
        
        // 대화 목록 로드 (데이터베이스에서)
        loadConversations: function(append = false) {
            if (this.isLoading || this.isLoadingMore) {
                console.log("⚠️ Already loading conversations...");
                return;
            }
            
            if (!append && this.conversations.length > 0) {
                // 이미 로드된 대화가 있고 새로 로드하는 것이 아니면 스킵
                return;
            }
            
            if (append && !this.hasMoreConversations) {
                console.log("⚠️ No more conversations to load");
                return;
            }
            
            if (append) {
                this.isLoadingMore = true;
            } else {
                this.isLoading = true;
                this.currentPage = 1;
            }
            
            $.ajax({
                url: window.kachi_ajax?.ajax_url,
                type: 'POST',
                data: {
                    action: 'kachi_load_conversations',
                    nonce: window.kachi_ajax?.nonce,
                    page: append ? this.currentPage + 1 : 1,
                    per_page: this.perPage
                },
                success: (response) => {
                    if (response.success && response.data.conversations) {
                        if (append) {
                            // 기존 대화에 추가
                            this.conversations = this.conversations.concat(response.data.conversations);
                            this.currentPage++;
                        } else {
                            // 새로 로드
                            this.conversations = response.data.conversations;
                            this.currentPage = 1;
                        }
                        
                        // 페이지네이션 정보 업데이트
                        if (response.data.pagination) {
                            this.hasMoreConversations = response.data.pagination.has_more;
                        }
                        
                        console.log(`📚 Loaded conversations from DB: ${response.data.conversations.length} items, page ${this.currentPage}`);
                        
                        // UI 업데이트 트리거
                        if (window.KachiUI && window.KachiUI.renderConversationList) {
                            window.KachiUI.renderConversationList(append);
                        }
                    } else {
                        console.error("❌ Failed to load conversations:", response);
                        if (!append) {
                            this.conversations = [];
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error("❌ Error loading conversations:", error);
                    if (!append) {
                        this.conversations = [];
                    }
                },
                complete: () => {
                    if (append) {
                        this.isLoadingMore = false;
                    } else {
                        this.isLoading = false;
                    }
                }
            });
        },
        
        // 더 많은 대화 로드
        loadMoreConversations: function() {
            this.loadConversations(true);
        },
        
        // 대화 저장 (데이터베이스에)
        saveConversations: function() {
            if (!window.isUserLoggedIn) {
                console.warn("⚠️ User not logged in, cannot save conversations");
                return;
            }
            
            if (!this.currentConversationId) {
                console.warn("⚠️ No current conversation to save");
                return;
            }
            
            if (this.isSaving) {
                console.log("⚠️ Already saving...");
                return;
            }
            
            const conversation = this.conversations.find(c => c.id === this.currentConversationId);
            if (!conversation) {
                console.error("❌ Current conversation not found in list");
                return;
            }
            
            this.isSaving = true;
            
            // 현재 대화 저장
            $.ajax({
                url: window.kachi_ajax?.ajax_url,
                type: 'POST',
                data: {
                    action: 'kachi_save_conversation',
                    nonce: window.kachi_ajax?.nonce,
                    conversation_id: conversation.id,
                    title: conversation.title,
                    messages: JSON.stringify(conversation.messages)
                },
                success: (response) => {
                    if (response.success) {
                        console.log("💾 Conversation saved to DB");
                    } else {
                        console.error("❌ Failed to save conversation:", response);
                    }
                },
                error: (xhr, status, error) => {
                    console.error("❌ Error saving conversation:", error);
                },
                complete: () => {
                    this.isSaving = false;
                }
            });
        },
        
        // 새 대화 생성
        createNewConversation: function() {
            const conversationId = 'conv_' + Date.now();
            const conversation = {
                id: conversationId,
                title: '새 대화',
                messages: [],
                createdAt: new Date().toISOString(),
                updatedAt: new Date().toISOString()
            };
            
            this.conversations.unshift(conversation);
            this.currentConversationId = conversationId;
            this.chatHistory = [];
            
            // 로그인한 경우에만 DB에 저장
            if (window.isUserLoggedIn) {
                this.saveConversations();
            }
            
            console.log("🆕 New conversation created:", conversationId);
            return conversationId;
        },
        
        // 현재 대화 업데이트
        updateCurrentConversation: function() {
            if (!this.currentConversationId) {
                this.createNewConversation();
            }
            
            const conversation = this.conversations.find(c => c.id === this.currentConversationId);
            if (conversation) {
                conversation.messages = [...this.chatHistory];
                conversation.updatedAt = new Date().toISOString();
                
                // 첫 메시지로 제목 자동 설정 (원본 메시지 사용)
                if (conversation.title === '새 대화' && this.chatHistory.length > 0) {
                    const firstUserMessage = this.chatHistory.find(m => m.type === 'user');
                    if (firstUserMessage) {
                        // HTML 태그 제거
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = firstUserMessage.content;
                        
                        // 시설정의 관련 요소 제거하고 원본 텍스트만 추출
                        const facilityElements = tempDiv.querySelectorAll('.modified-query-divider, .modified-query-label, .modified-query-text');
                        facilityElements.forEach(el => el.remove());
                        
                        let plainText = tempDiv.textContent || tempDiv.innerText || '';
                        
                        // plainText가 비어있으면 전체 텍스트 사용 (시설정의가 없는 경우)
                        if (!plainText.trim()) {
                            plainText = tempDiv.textContent || tempDiv.innerText || '';
                        }
                        
                        conversation.title = plainText.trim().substring(0, 30) + 
                                           (plainText.trim().length > 30 ? '...' : '');
                    }
                }
                
                // 로그인한 경우에만 DB에 저장
                if (window.isUserLoggedIn) {
                    this.saveConversations();
                }
            }
        },
        
        // 대화 불러오기
        loadConversation: function(conversationId) {
            const conversation = this.conversations.find(c => c.id === conversationId);
            if (conversation) {
                this.currentConversationId = conversationId;
                // 메시지 복사 시 referencedDocs도 포함
                this.chatHistory = conversation.messages.map(msg => ({
                    ...msg,
                    referencedDocs: msg.referencedDocs || null
                }));
                console.log("📖 Loaded conversation:", conversationId, "Messages:", this.chatHistory.length);
                return true;
            }
            return false;
        },
        
        // 대화 삭제 (데이터베이스에서도)
        deleteConversation: function(conversationId) {
            // 메모리에서 먼저 삭제
            const index = this.conversations.findIndex(c => c.id === conversationId);
            if (index === -1) {
                console.error("❌ Conversation not found:", conversationId);
                return false;
            }
            
            this.conversations.splice(index, 1);
            const isCurrentConversation = this.currentConversationId === conversationId;
            
            // 로그인한 경우 DB에서도 삭제
            if (window.isUserLoggedIn) {
                $.ajax({
                    url: window.kachi_ajax?.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kachi_delete_conversation',
                        nonce: window.kachi_ajax?.nonce,
                        conversation_id: conversationId
                    },
                    success: (response) => {
                        if (response.success) {
                            console.log("🗑️ Conversation deleted from DB:", conversationId);
                        } else {
                            console.error("❌ Failed to delete conversation from DB:", response);
                            // DB 삭제 실패 시 메모리에 복구
                            this.loadConversations();
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error("❌ Error deleting conversation:", error);
                        // 에러 시 메모리에 복구
                        this.loadConversations();
                    }
                });
            }
            
            if (isCurrentConversation) {
                console.log("🗑️ Current conversation deleted:", conversationId);
                return true;
            }
            
            console.log("🗑️ Conversation deleted:", conversationId);
            return false;
        },
        
        // 메시지 추가
        addMessage: function(type, content, time, referencedDocs) {
            const messageId = `message-${++this.currentMessageId}`;
            const messageObj = {
                id: messageId,
                type: type,
                content: content,
                time: time || new Date().toLocaleTimeString('ko-KR', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                }),
                referencedDocs: referencedDocs || null
            };
            
            this.chatHistory.push(messageObj);
            return messageObj;
        },
        
        // 메시지 찾기
        findMessage: function(messageId) {
            return this.chatHistory.find(m => m.id === messageId);
        },
        
        // 메시지 이후 삭제
        removeMessagesAfter: function(messageId) {
            const messageIndex = this.chatHistory.findIndex(m => m.id === messageId);
            if (messageIndex !== -1) {
                // 삭제될 메시지 수 계산
                const removedCount = this.chatHistory.length - (messageIndex + 1);
                // 해당 메시지 다음부터 삭제 (해당 메시지는 유지)
                this.chatHistory = this.chatHistory.slice(0, messageIndex + 1);
                return removedCount;
            }
            return 0;
        },
        
        // 로그인 체크
        checkLoginBeforeQuery: function() {
            if (typeof window.isUserLoggedIn !== "undefined" && !window.isUserLoggedIn) {
                const loginMsg = window.kachi_ajax?.strings?.login_required || "로그인이 필요합니다.";
                alert(loginMsg);
                window.location.href = "/?page_id=559";
                return false;
            }
            return true;
        },
        
        // 쿼리 상태 초기화
        resetQueryState: function() {
            this.isQueryInProgress = false;
            this.controller = null;
            this.streamBuffer = "";
            this.displayedLength = 0;
            this.isCharStreaming = false;
            if (this.typeTimer) {
                clearTimeout(this.typeTimer);
                this.typeTimer = null;
            }
        },
        
        // 쿼리 중지
        stopQuery: function() {
            console.log("🛑 Stopping query...");
            
            if (this.controller) {
                console.log("🛑 Aborting controller...");
                this.controller.abort();
                this.controller = null;
            }
            
            this.resetQueryState();
        },
        
        // 날짜 포맷
        formatDate: function(date) {
            const now = new Date();
            const diff = now - date;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            
            if (days === 0) {
                return '오늘';
            } else if (days === 1) {
                return '어제';
            } else if (days < 7) {
                return `${days}일 전`;
            } else {
                return date.toLocaleDateString('ko-KR');
            }
        },
        
        // HTML 이스케이프
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        },
        
        // 정규식 이스케이프
        escapeRegex: function(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        }
    };
    
})(window, document, jQuery);