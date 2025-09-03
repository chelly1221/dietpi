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
        saveTimeoutId: null,
        lastSaveTime: 0,
        savePending: false,
        saveDebounceDelay: 2000,
        
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
                        
                        // 페이지 로드 시 가장 최근 대화 자동 복원 (append가 아닌 경우에만)
                        if (!append && this.conversations.length > 0 && !this.currentConversationId) {
                            const mostRecentConversation = this.conversations[0]; // 가장 최근에 업데이트된 대화
                            this.currentConversationId = mostRecentConversation.id;
                            
                            // 원본 데이터베이스 메시지 구조 디버깅
                            console.log(`📦 Raw conversation from DB:`, {
                                id: mostRecentConversation.id,
                                title: mostRecentConversation.title,
                                messagesCount: mostRecentConversation.messages ? mostRecentConversation.messages.length : 'NO MESSAGES',
                                messagesRaw: mostRecentConversation.messages
                            });
                            
                            // 메시지 복원 시 유효성 검증 추가
                            this.chatHistory = mostRecentConversation.messages.map((msg, index) => {
                                // 메시지 구조 검증
                                if (!msg || typeof msg !== 'object') {
                                    console.warn(`⚠️ Invalid message at index ${index}:`, msg);
                                    return null;
                                }
                                
                                // 콘텐츠 복구 로직 - 여러 필드에서 콘텐츠 찾기
                                let recoveredContent = msg.content || '';
                                
                                // 대체 콘텐츠 필드 확인
                                if (!recoveredContent && msg.text) {
                                    recoveredContent = msg.text;
                                    console.log(`🔄 Recovered content from 'text' field for message ${index}`);
                                }
                                
                                // 메시지 텍스트에서 추출 시도
                                if (!recoveredContent && msg.message) {
                                    recoveredContent = msg.message;
                                    console.log(`🔄 Recovered content from 'message' field for message ${index}`);
                                }
                                
                                // referencedDocs에서 실제 응답 텍스트 추출 시도 
                                if (!recoveredContent && msg.referencedDocs && msg.type === 'assistant') {
                                    const tempDiv = document.createElement('div');
                                    tempDiv.innerHTML = msg.referencedDocs;
                                    const messageText = tempDiv.querySelector('.message-text');
                                    if (messageText && messageText.textContent.trim()) {
                                        recoveredContent = messageText.innerHTML;
                                        console.log(`🔄 Recovered content from referencedDocs for message ${index}`);
                                    }
                                }
                                
                                console.log(`📋 Message ${index} content recovery:`, {
                                    originalContent: !!msg.content,
                                    recoveredContent: !!recoveredContent,
                                    recoveredLength: recoveredContent.length,
                                    type: msg.type,
                                    id: msg.id
                                });

                                return {
                                    ...msg,
                                    id: msg.id || `restored-${Date.now()}-${index}`,
                                    type: msg.type || 'unknown',
                                    content: recoveredContent,
                                    time: msg.time || new Date().toLocaleTimeString('ko-KR'),
                                    referencedDocs: msg.referencedDocs || null
                                };
                            }).filter(msg => msg !== null); // null 메시지 제거
                            
                            console.log(`🔄 Auto-restored conversation: ${mostRecentConversation.id} with ${this.chatHistory.length} valid messages`);
                            
                            // 복원된 메시지 구조 디버깅
                            this.chatHistory.forEach((msg, idx) => {
                                if (msg.type === 'assistant') {
                                    console.log(`📨 Restored assistant message ${idx}:`, {
                                        id: msg.id,
                                        hasContent: !!msg.content,
                                        contentLength: msg.content ? msg.content.length : 0,
                                        contentPreview: msg.content ? msg.content.substring(0, 50) : 'EMPTY',
                                        hasReferencedDocs: !!msg.referencedDocs,
                                        referencedDocsPreview: msg.referencedDocs ? msg.referencedDocs.substring(0, 50) : 'NONE'
                                    });
                                }
                            });
                            
                            // 즉시 UI 렌더링 트리거 (setTimeout 없이)
                            if (window.KachiUI && window.KachiUI.renderChatHistory) {
                                console.log(`🎨 Triggering immediate chat UI render`);
                                setTimeout(() => {
                                    window.KachiUI.renderChatHistory();
                                }, 100); // 최소한의 지연만
                            }
                        }
                        
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
        
        // 대화 저장 (디바운싱 적용)
        saveConversations: function(options = {}) {
            const { immediate = false, retryCount = 0, force = false } = options;
            
            if (!window.isUserLoggedIn) {
                console.warn("⚠️ User not logged in, cannot save conversations");
                return Promise.reject('User not logged in');
            }
            
            if (!this.currentConversationId) {
                console.warn("⚠️ No current conversation to save");
                return Promise.reject('No current conversation');
            }
            
            // 강제 저장이 아니고 저장이 진행 중인 경우
            if (this.isSaving && !force && retryCount === 0) {
                console.log("⚠️ Save in progress, marking as pending...");
                this.savePending = true;
                return Promise.reject('Save in progress');
            }
            
            // 디바운싱 처리 (즉시 저장이 아닌 경우)
            if (!immediate && !force) {
                if (this.saveTimeoutId) {
                    clearTimeout(this.saveTimeoutId);
                }
                
                this.saveTimeoutId = setTimeout(() => {
                    this.saveConversations({ immediate: true, retryCount });
                }, this.saveDebounceDelay);
                
                console.log(`⏱️ Save debounced for ${this.saveDebounceDelay}ms`);
                return Promise.resolve('Debounced');
            }
            
            return this._performSave(retryCount);
        },
        
        // 실제 저장 수행
        _performSave: function(retryCount = 0) {
            return new Promise((resolve, reject) => {
                // 메트릭 기록
                if (retryCount === 0) {
                    this.debug.recordSaveAttempt();
                }
                
                const conversation = this.conversations.find(c => c.id === this.currentConversationId);
                if (!conversation) {
                    const error = "❌ Current conversation not found in list";
                    console.error(error);
                    this.debug.recordSaveFailure();
                    reject(error);
                    return;
                }
                
                // 상태 유효성 검증
                if (!this._validateSaveData(conversation)) {
                    this.debug.recordSaveFailure();
                    reject('Invalid save data');
                    return;
                }
                
                this.isSaving = true;
                this.lastSaveTime = Date.now();
                console.log("💾 Starting conversation save to database (attempt " + (retryCount + 1) + "/5)...");
                
                // 안전한 메시지 복사 및 검증
                const validMessages = this._validateAndCleanMessages(this.chatHistory);
                conversation.messages = validMessages;
                
                // 저장할 메시지 내용 디버깅
                console.log("🔍 Content being saved to database:", {
                    conversationId: this.currentConversationId,
                    originalChatHistoryCount: this.chatHistory.length,
                    validMessageCount: validMessages.length,
                    conversationMessageCount: conversation.messages.length,
                    messages: validMessages.map(msg => ({
                        id: msg.id,
                        type: msg.type,
                        hasContent: !!msg.content,
                        contentLength: msg.content ? msg.content.length : 0,
                        contentPreview: msg.content ? msg.content.substring(0, 50) + '...' : 'NO CONTENT',
                        isValid: this._isValidMessage(msg)
                    }))
                });
            
                // AJAX 요청 데이터 준비
                const ajaxData = {
                    action: 'kachi_save_conversation',
                    nonce: window.kachi_ajax?.nonce,
                    conversation_id: conversation.id,
                    title: conversation.title,
                    messages: JSON.stringify(conversation.messages)
                };
                
                // 데이터 크기 체크
                const dataSize = JSON.stringify(ajaxData).length;
                if (dataSize > 1048576) { // 1MB
                    console.warn(`⚠️ Large save data detected: ${Math.round(dataSize/1024)}KB`);
                }
                
                $.ajax({
                    url: window.kachi_ajax?.ajax_url,
                    type: 'POST',
                    data: ajaxData,
                    timeout: 30000, // 30초 타임아웃
                    success: (response) => {
                        const duration = Date.now() - this.lastSaveTime;
                        
                        if (response.success) {
                            this.debug.recordSaveSuccess(duration);
                            console.log("✅ Conversation successfully saved to database:", {
                                conversationId: this.currentConversationId,
                                messageCount: this.chatHistory.length,
                                validMessageCount: validMessages.length,
                                saveTime: duration + 'ms',
                                response: response
                            });
                            resolve(response);
                            
                            // 대기 중인 저장이 있으면 실행
                            if (this.savePending) {
                                this.savePending = false;
                                setTimeout(() => {
                                    this.saveConversations({ immediate: true });
                                }, 500);
                            }
                        } else {
                            this.debug.recordSaveFailure();
                            console.error("❌ Database save failed:", {
                                conversationId: this.currentConversationId,
                                error: response,
                                messageCount: this.chatHistory.length,
                                retryCount: retryCount,
                                saveTime: duration + 'ms'
                            });
                            
                            this._handleSaveRetry(retryCount, reject, 'Server error');
                        }
                    },
                    error: (xhr, status, error) => {
                        this.debug.recordSaveFailure();
                        const duration = Date.now() - this.lastSaveTime;
                        
                        console.error("❌ AJAX error during save:", {
                            conversationId: this.currentConversationId,
                            error: error,
                            status: status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText,
                            messageCount: this.chatHistory.length,
                            retryCount: retryCount,
                            saveTime: duration + 'ms'
                        });
                        
                        this._handleSaveRetry(retryCount, reject, `AJAX error: ${error}`);
                    },
                    complete: () => {
                        this.isSaving = false;
                    }
                });
            });
        },
        
        // 재시도 처리 로직
        _handleSaveRetry: function(retryCount, reject, errorReason) {
            const maxRetries = 4; // 최대 5번 시도
            const backoffDelay = Math.min(1000 * Math.pow(2, retryCount), 10000); // 지수 백오프, 최대 10초
            
            if (retryCount < maxRetries) {
                console.log(`🔄 Retrying save in ${backoffDelay}ms... (attempt ${retryCount + 2}/${maxRetries + 1})`);
                setTimeout(() => {
                    this._performSave(retryCount + 1)
                        .then(() => console.log('✅ Retry succeeded'))
                        .catch((err) => console.error('❌ Final retry failed:', err));
                }, backoffDelay);
            } else {
                console.error('❌ Max retries exceeded, save failed permanently');
                reject(`Save failed after ${maxRetries + 1} attempts: ${errorReason}`);
                
                // 사용자에게 알림
                if (window.KachiUI && window.KachiUI.showSaveErrorNotification) {
                    window.KachiUI.showSaveErrorNotification();
                }
            }
        },
        
        // 메시지 유효성 검증 및 정리
        _validateAndCleanMessages: function(messages) {
            if (!Array.isArray(messages)) {
                console.warn('⚠️ ChatHistory is not an array, returning empty array');
                return [];
            }
            
            return messages.filter(msg => this._isValidMessage(msg)).map(msg => {
                // 메시지 정리
                return {
                    id: msg.id || `fallback-${Date.now()}-${Math.random()}`,
                    type: msg.type || 'unknown',
                    content: this._sanitizeContent(msg.content || ''),
                    time: msg.time || new Date().toLocaleTimeString('ko-KR'),
                    referencedDocs: msg.referencedDocs || null
                };
            });
        },
        
        // 메시지 유효성 검증
        _isValidMessage: function(msg) {
            if (!msg || typeof msg !== 'object') {
                return false;
            }
            
            // 필수 필드 체크
            if (!msg.id || !msg.type) {
                return false;
            }
            
            // 콘텐츠가 비어있는 assistant 메시지는 임시 메시지일 가능성
            if (msg.type === 'assistant' && (!msg.content || msg.content.trim() === '')) {
                // ID가 임시인지 확인
                return !msg.id.includes('temp-') && !msg.isTemporary;
            }
            
            return true;
        },
        
        // 콘텐츠 정리
        _sanitizeContent: function(content) {
            if (typeof content !== 'string') {
                return String(content || '');
            }
            
            // 기본 정리 - 과도한 공백 제거
            return content.trim();
        },
        
        // 저장 데이터 유효성 검증
        _validateSaveData: function(conversation) {
            if (!conversation || !conversation.id) {
                console.error('❌ Invalid conversation object');
                return false;
            }
            
            if (!this.chatHistory || !Array.isArray(this.chatHistory)) {
                console.error('❌ Invalid chatHistory');
                return false;
            }
            
            // 대화에 유효한 메시지가 있는지 확인
            const validMessages = this.chatHistory.filter(msg => this._isValidMessage(msg));
            if (validMessages.length === 0) {
                console.warn('⚠️ No valid messages to save');
            }
            
            return true;
        },
        
        // 대화 상태 동기화
        _syncConversationState: function(conversation) {
            try {
                // 현재 chatHistory를 안전하게 복사 및 검증
                const validMessages = this._validateAndCleanMessages(this.chatHistory);
                
                // 깊은 복사로 메시지 업데이트
                conversation.messages = validMessages.map(msg => ({
                    id: msg.id,
                    type: msg.type,
                    content: msg.content || '',
                    time: msg.time || new Date().toLocaleTimeString('ko-KR'),
                    referencedDocs: msg.referencedDocs || null
                }));
                
                // 업데이트 시간 갱신
                conversation.updatedAt = new Date().toISOString();
                
                // 제목 자동 생성 (새 대화인 경우)
                this._updateConversationTitle(conversation);
                
                return {
                    success: true,
                    validMessageCount: validMessages.length,
                    totalMessageCount: this.chatHistory.length
                };
            } catch (error) {
                console.error('❌ Error syncing conversation state:', error);
                return {
                    success: false,
                    error: error.message
                };
            }
        },
        
        // 대화 제목 업데이트
        _updateConversationTitle: function(conversation) {
            if (conversation.title === '새 대화' && this.chatHistory.length > 0) {
                const firstUserMessage = this.chatHistory.find(m => m.type === 'user');
                if (firstUserMessage && firstUserMessage.content) {
                    try {
                        // HTML 태그 제거 및 시설정의 제거
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = firstUserMessage.content;
                        
                        // 시설정의 관련 요소 제거
                        const facilityElements = tempDiv.querySelectorAll('.modified-query-divider, .modified-query-label, .modified-query-text');
                        facilityElements.forEach(el => el.remove());
                        
                        let plainText = tempDiv.textContent || tempDiv.innerText || '';
                        
                        // 텍스트가 비어있으면 전체 텍스트 사용
                        if (!plainText.trim()) {
                            plainText = tempDiv.textContent || tempDiv.innerText || '';
                        }
                        
                        if (plainText.trim()) {
                            conversation.title = plainText.trim().substring(0, 30) + 
                                               (plainText.trim().length > 30 ? '...' : '');
                        }
                    } catch (error) {
                        console.warn('⚠️ Error updating conversation title:', error);
                    }
                }
            }
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
            
            // 로그인한 경우에만 DB에 저장 (즉시 저장)
            if (window.isUserLoggedIn) {
                this.saveConversations({ immediate: true })
                    .then(() => console.log('✅ New conversation saved to database'))
                    .catch(err => console.error('❌ Failed to save new conversation:', err));
            }
            
            console.log("🆕 New conversation created:", conversationId);
            return conversationId;
        },
        
        // 현재 대화 업데이트 (개선된 버전)
        updateCurrentConversation: function(options = {}) {
            const { skipSave = false } = options;
            
            if (!this.currentConversationId) {
                this.createNewConversation();
                return;
            }
            
            const conversation = this.conversations.find(c => c.id === this.currentConversationId);
            if (conversation) {
                // 상태 검증 및 동기화
                const syncResult = this._syncConversationState(conversation);
                if (!syncResult.success) {
                    console.error('❌ Failed to sync conversation state:', syncResult.error);
                    return;
                }
                
                console.log("🔄 updateCurrentConversation - State synced:", {
                    conversationId: this.currentConversationId,
                    currentChatHistoryLength: this.chatHistory.length,
                    syncedMessagesLength: conversation.messages.length,
                    hasValidMessages: syncResult.validMessageCount > 0,
                    skipSave: skipSave,
                    messagesPreview: conversation.messages.slice(-3).map(msg => ({
                        id: msg.id,
                        type: msg.type,
                        hasContent: !!msg.content,
                        contentLength: msg.content ? msg.content.length : 0
                    }))
                });
                
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
                
                // 로그인한 경우에만 DB에 저장 (skipSave 옵션 고려)
                if (window.isUserLoggedIn && !skipSave) {
                    this.saveConversations()
                        .then(() => console.log('✅ Conversation updated and saved'))
                        .catch(err => console.warn('⚠️ Failed to save conversation update:', err));
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
        },
        
        // 디버깅 및 모니터링 유틸리티
        debug: {
            // 성능 메트릭
            metrics: {
                saveAttempts: 0,
                saveSuccesses: 0,
                saveFailures: 0,
                avgSaveTime: 0,
                lastSaveTime: 0,
                contentLosses: 0,
                recoveries: 0
            },
            
            // 메트릭 업데이트
            recordSaveAttempt: function() {
                this.metrics.saveAttempts++;
                this.metrics.lastSaveTime = Date.now();
            },
            
            recordSaveSuccess: function(duration) {
                this.metrics.saveSuccesses++;
                this.updateAvgSaveTime(duration);
            },
            
            recordSaveFailure: function() {
                this.metrics.saveFailures++;
            },
            
            recordContentLoss: function() {
                this.metrics.contentLosses++;
            },
            
            recordRecovery: function() {
                this.metrics.recoveries++;
            },
            
            updateAvgSaveTime: function(duration) {
                const total = this.metrics.avgSaveTime * (this.metrics.saveSuccesses - 1) + duration;
                this.metrics.avgSaveTime = Math.round(total / this.metrics.saveSuccesses);
            },
            
            // 현재 상태 보고
            getHealthReport: function() {
                const total = this.metrics.saveAttempts;
                const successRate = total > 0 ? Math.round((this.metrics.saveSuccesses / total) * 100) : 0;
                
                return {
                    overallHealth: successRate >= 95 ? 'EXCELLENT' : successRate >= 85 ? 'GOOD' : successRate >= 70 ? 'FAIR' : 'POOR',
                    successRate: successRate + '%',
                    totalAttempts: this.metrics.saveAttempts,
                    successful: this.metrics.saveSuccesses,
                    failed: this.metrics.saveFailures,
                    avgResponseTime: this.metrics.avgSaveTime + 'ms',
                    contentIssues: this.metrics.contentLosses,
                    recoveries: this.metrics.recoveries,
                    lastSave: this.metrics.lastSaveTime ? new Date(this.metrics.lastSaveTime).toLocaleString('ko-KR') : 'Never'
                };
            },
            
            // 콘솔에 상태 출력
            printHealthReport: function() {
                const report = this.getHealthReport();
                console.log('📊 KACHI Chat History Health Report:', report);
                return report;
            },
            
            // 시스템 진단
            runDiagnostic: function() {
                console.log('🔍 Running KACHI system diagnostic...');
                
                const diagnostics = {
                    timestamp: new Date().toISOString(),
                    core: {
                        conversations: KachiCore.conversations.length,
                        currentId: KachiCore.currentConversationId,
                        chatHistory: KachiCore.chatHistory.length,
                        isSaving: KachiCore.isSaving,
                        savePending: KachiCore.savePending
                    },
                    browser: {
                        userAgent: navigator.userAgent,
                        language: navigator.language,
                        online: navigator.onLine,
                        localStorage: typeof(Storage) !== "undefined"
                    },
                    performance: this.getHealthReport(),
                    lastMessages: KachiCore.chatHistory.slice(-3).map(msg => ({
                        id: msg.id,
                        type: msg.type,
                        hasContent: !!msg.content,
                        contentLength: msg.content ? msg.content.length : 0
                    }))
                };
                
                console.log('📋 Diagnostic Report:', diagnostics);
                return diagnostics;
            },
            
            // 자동 모니터링 시작
            startMonitoring: function(intervalMinutes = 10) {
                if (this.monitoringInterval) {
                    clearInterval(this.monitoringInterval);
                }
                
                this.monitoringInterval = setInterval(() => {
                    const report = this.getHealthReport();
                    if (report.overallHealth === 'POOR' || report.overallHealth === 'FAIR') {
                        console.warn('⚠️ KACHI Health Alert:', report);
                    }
                }, intervalMinutes * 60 * 1000);
                
                console.log(`📊 Monitoring started (every ${intervalMinutes} minutes)`);
            },
            
            // 모니터링 중지
            stopMonitoring: function() {
                if (this.monitoringInterval) {
                    clearInterval(this.monitoringInterval);
                    this.monitoringInterval = null;
                    console.log('📊 Monitoring stopped');
                }
            }
        },
        
        // 개발자 도구용 전역 함수 노출
        exposeDebugTools: function() {
            // 전역 객체에 디버그 도구 노출
            window.KachiDebug = {
                health: () => this.debug.printHealthReport(),
                diagnostic: () => this.debug.runDiagnostic(),
                monitor: (interval) => this.debug.startMonitoring(interval),
                stopMonitor: () => this.debug.stopMonitoring(),
                conversations: () => this.conversations,
                chatHistory: () => this.chatHistory,
                forceSync: () => this.updateCurrentConversation({ skipSave: false }),
                metrics: () => this.debug.metrics
            };
            
            console.log('🛠️ Debug tools available: KachiDebug.health(), KachiDebug.diagnostic(), etc.');
        }
    };
    
})(window, document, jQuery);