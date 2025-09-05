// 까치 쿼리 시스템 API
(function(window, document, $) {
    'use strict';
    
    window.KachiAPI = {
        // 초기화
        init: function() {
            this.fetchTagsAndDocs();
            this.loadMathJax();
        },
        
        // MathJax 로드
        loadMathJax: function() {
            if (window.MathJax) {
                return;
            }
            
            // MathJax 설정
            window.MathJax = {
                // 로컬 폰트 경로 설정
                chtml: {
                    fontURL: window.kachi_ajax.plugin_url + 'assets/js/mathjax/es5/output/chtml/fonts/woff-v2'
                },
                tex: {
                    inlineMath: [['\\(', '\\)'], ['$', '$']],
                    displayMath: [['\\[', '\\]'], ['$$', '$$']],
                    processEscapes: true,
                    processEnvironments: true,
                    packages: {'[+]': ['base', 'ams', 'newcommand', 'noundefined']},
                    processError: function (math, doc, error) {
                        console.error('MathJax TeX Error:', error);
                    }
                },
                options: {
                    skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre'],
                    ignoreHtmlClass: 'tex2jax_ignore',
                    processHtmlClass: 'tex2jax_process',
                    compileError: function (doc, math, err) {
                        console.error('MathJax Compile Error:', err);
                    },
                    typesetError: function (doc, math, err) {
                        console.error('MathJax Typeset Error:', err);
                    }
                },
                startup: {
                    ready: function() {
                        MathJax.startup.defaultReady();
                        MathJax.startup.promise.then(() => {
                        });
                    }
                }
            };
            
            // MathJax 스크립트 로드
            const script = document.createElement('script');
            script.src = window.kachi_ajax.plugin_url + 'assets/js/mathjax/es5/tex-mml-chtml.js';
            script.async = true;
            script.id = 'MathJax-script';
            
            script.onerror = function() {
                console.error("❌ Failed to load MathJax from local path");
                window.MathJaxDisabled = true;
            };
            
            script.onload = function() {
            };
            
            document.head.appendChild(script);
        },
        
        // 태그와 문서 목록 가져오기
        fetchTagsAndDocs: function() {
            // 태그 가져오기
            $.ajax({
                url: window.kachi_ajax?.ajax_url,
                type: 'POST',
                data: {
                    action: 'kachi_get_tags',
                    nonce: window.kachi_ajax?.nonce
                },
                success: (response) => {
                    if (response.success && response.data.tags) {
                        KachiCore.allTagList = response.data.tags;
                        if (KachiCore.allTagList.length > 0) {
                            KachiUI.renderTagOptions(KachiCore.allTagList);
                        } else {
                            $('#tagOptionsContainer').html(
                                "<div style='padding:10px; font-size:13px; color:black;'>❌ 태그 없음</div>"
                            );
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error("❌ Error loading tags:", error);
                    $('#tagOptionsContainer').html(
                        "<div style='padding:10px; font-size:13px;'>❌ 태그 불러오기 실패</div>"
                    );
                }
            });
            
            // 문서 가져오기
            $.ajax({
                url: window.kachi_ajax?.ajax_url,
                type: 'POST',
                data: {
                    action: 'kachi_get_documents',
                    nonce: window.kachi_ajax?.nonce
                },
                success: (response) => {
                    if (response.success && response.data.documents) {
                        KachiCore.allDocList = response.data.documents.map(doc => 
                            doc.filename || doc.file_id || "❓ unknown"
                        );
                        
                        if (KachiCore.allDocList.length > 0) {
                            KachiUI.renderDocOptions(KachiCore.allDocList);
                        } else {
                            $('#docOptionsContainer').html(
                                "<div style='padding:10px; font-size:13px;'>❌ 문서 없음</div>"
                            );
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error("❌ Error loading documents:", error);
                    $('#docOptionsContainer').html(
                        "<div style='padding:10px; font-size:13px;'>❌ 문서 불러오기 실패</div>"
                    );
                }
            });
        },
        
        // 쿼리 전송
        sendQuery: async function() {
            if (!KachiCore.checkLoginBeforeQuery()) {
                KachiCore.isQueryInProgress = false;
                return;
            }

            if (KachiCore.isQueryInProgress) return;
            KachiCore.isQueryInProgress = true;

            const $queryInput = $('#queryInput');
            const userQueryOriginal = $queryInput.val().trim();
            
            if (!userQueryOriginal) {
                alert('질문을 입력해주세요.');
                KachiCore.isQueryInProgress = false;
                return;
            }
            
            // 현재 대화가 없으면 새로 생성
            if (!KachiCore.currentConversationId) {
                KachiCore.createNewConversation();
                KachiUI.renderConversationList(false);
            }
            
            // 채팅 모드로 전환 (처음 검색 시)
            if (!$('.query-box-container').hasClass('active')) {
                KachiUI.enterChatMode();
            }
            
            // Facility 매칭 처리
            let userQuery = this.processFacilityMatching(userQueryOriginal);
            
            // 변형된 쿼리가 있는지 확인
            const isModified = userQuery !== userQueryOriginal;
            
            // 사용자 메시지 추가
            let messageContent = KachiCore.escapeHtml(userQueryOriginal);
            if (isModified) {
                messageContent += `<div class="modified-query-divider"></div>`;
                messageContent += `<div class="modified-query-label">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-bottom:3px;">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path d="M9 12l2 2 4-4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    시설 정의 적용
                </div>`;
                messageContent += `<div class="modified-query-text">${KachiCore.escapeHtml(userQuery)}</div>`;
            }
            
            const messageObj = KachiUI.addMessageUI('user', messageContent);
            
            // 입력창 초기화
            $queryInput.val('').css('height', '56px');

            console.log("🔁 Modified Query:", userQuery);

            // 스트리밍 처리
            this.handleStreamingQuery(userQuery);

            $queryInput.prop('disabled', true).addClass('with-stop');
            $('.dropdown-header').css('pointer-events', 'none');
        },
        
        // Facility 매칭 처리
        processFacilityMatching: function(userQuery) {
            if (typeof window.facilityDefinitionsFromPHP !== "object" || !window.facilityDefinitionsFromPHP) {
                return userQuery;
            }

            const defs = window.facilityDefinitionsFromPHP;
            const facilityKeys = Object.keys(defs)
                .map(key => ({
                    original: key,
                    compact: key.replace(/\s+/g, "").toLowerCase(),
                    values: defs[key]
                }))
                .sort((a, b) => b.compact.length - a.compact.length);

            const replacedIndices = Array(userQuery.length).fill(false);
            const replacements = [];

            facilityKeys.forEach(facility => {
                const pattern = new RegExp(KachiCore.escapeRegex(facility.compact), 'gi');
                const compactUserQuery = userQuery.replace(/\s+/g, "").toLowerCase();
                let match;

                while ((match = pattern.exec(compactUserQuery)) !== null) {
                    let start = match.index;
                    let end = start + facility.compact.length;

                    let origStart = -1, origEnd = -1, compactCounter = 0;

                    for (let i = 0; i < userQuery.length; i++) {
                        if (userQuery[i].match(/\s/)) continue;
                        if (compactCounter === start) origStart = i;
                        if (compactCounter === end - 1) origEnd = i + 1;
                        compactCounter++;
                    }

                    if (origStart < 0 || origEnd < 0) continue;
                    if (replacedIndices.slice(origStart, origEnd).some(used => used)) continue;

                    replacements.push({ origStart, origEnd, facility });
                    for (let i = origStart; i < origEnd; i++) replacedIndices[i] = true;
                }
            });

            replacements.sort((a, b) => b.origStart - a.origStart).forEach(({ origStart, origEnd, facility }) => {
                const originalText = userQuery.slice(origStart, origEnd);
                userQuery = userQuery.slice(0, origStart) +
                    `${originalText}(${facility.values.join(", ")})` +
                    userQuery.slice(origEnd);
            });

            const compoundDefs = Object.entries(defs).filter(([key]) => key.includes(" "));
            compoundDefs.forEach(([compoundKey, values]) => {
                const parts = compoundKey.split(" ");
                const pattern = new RegExp(
                    parts.map(p => `${KachiCore.escapeRegex(p)}\\([^\\)]*\\)`).join("\\s*"),
                    "gi"
                );

                userQuery = userQuery.replace(pattern, () => {
                    const compact = parts.join("");
                    return `${compact}(${values.join(", ")})`;
                });
            });

            const singleKeys = Object.keys(defs).filter(k => !k.includes(" "));
            const keyPairs = [];

            for (let i = 0; i < singleKeys.length; i++) {
                for (let j = 0; j < singleKeys.length; j++) {
                    if (i === j) continue;
                    keyPairs.push([singleKeys[i], singleKeys[j]]);
                }
            }

            keyPairs.forEach(([k1, k2]) => {
                const values1 = defs[k1];
                const values2 = defs[k2];
                const intersection = values1.filter(v => values2.includes(v));
                if (intersection.length === 0) return;

                const pattern = new RegExp(
                    `${KachiCore.escapeRegex(k1)}\\([^\\)]*\\)\\s*${KachiCore.escapeRegex(k2)}\\([^\\)]*\\)`,
                    "gi"
                );

                userQuery = userQuery.replace(pattern, () => {
                    const compact = `${k1}${k2}`;
                    return `${compact}(${intersection.join(", ")})`;
                });
            });

            return userQuery;
        },
        
        // 스트리밍 쿼리 처리
        handleStreamingQuery: async function(userQuery) {
            // 편집 모드가 활성화되어 있으면 대기
            if (KachiCore.editingMessageId) {
                console.warn("⚠️ Edit mode is still active, waiting...");
                await new Promise(resolve => setTimeout(resolve, 200));
            }
            
            // 편집 중인 메시지 요소가 있는지 확인
            if ($('.message.editing').length > 0) {
                console.warn("⚠️ Found editing message, cleaning up...");
                $('.message.editing').removeClass('editing');
                await new Promise(resolve => setTimeout(resolve, 100));
            }

            console.log("🚀 Stream Request to WordPress proxy");

            // 어시스턴트 메시지 추가 (스트리밍용)
            const messageId = KachiUI.addMessageUI('assistant', '', true);
            
            if (!messageId) {
                console.error("❌ Failed to create assistant message");
                KachiCore.resetQueryState();
                KachiUI.resetQueryUI();
                return;
            }
            
            // 약간의 지연 후 메시지 요소 가져오기 (DOM 업데이트 대기)
            await new Promise(resolve => setTimeout(resolve, 50));
            
            const messageElement = document.getElementById(messageId);
            
            if (!messageElement) {
                console.error("❌ Message element not found in DOM:", messageId);
                KachiCore.resetQueryState();
                KachiUI.resetQueryUI();
                return;
            }
            
            if (!messageElement.classList.contains('assistant')) {
                console.warn("⚠️ Message element missing assistant class, adding it");
                messageElement.classList.add('assistant');
            }
            
            // 먼저 문서 정보 가져오기
            await this.fetchQueryDocuments(userQuery, messageElement);
            
            // 스트림 버퍼 초기화 및 콘텐츠 보존 준비
            KachiCore.streamBuffer = "";
            KachiCore.displayedLength = 0;
            KachiCore.isCharStreaming = false;
            if (KachiCore.typeTimer) {
                clearTimeout(KachiCore.typeTimer);
            }
            
            KachiCore.controller = new AbortController();
            $('#stopButton').show();

            // 마지막 수식 렌더링 시간 추적
            let lastMathRenderTime = 0;
            const MATH_RENDER_INTERVAL = 500;

            try {
                // WordPress AJAX를 통한 스트리밍 요청
                const formData = new FormData();
                formData.append('action', 'kachi_query');
                formData.append('nonce', window.kachi_ajax?.nonce);
                formData.append('query', userQuery);
                
                // 태그와 문서 추가
                KachiCore.selectedTags.forEach(tag => formData.append('tags[]', tag));
                KachiCore.selectedDocs.forEach(doc => formData.append('docs[]', doc));
                
                const res = await fetch(window.kachi_ajax?.ajax_url, {
                    method: 'POST',
                    body: formData,
                    signal: KachiCore.controller.signal
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const reader = res.body.getReader();
                const decoder = new TextDecoder("utf-8");
                let buffer = "";

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    buffer += decoder.decode(value, { stream: true });
                    const parts = buffer.split("\n\n");
                    buffer = parts.pop();

                    for (const chunk of parts) {
                        const line = chunk.trim();
                        if (line.startsWith("data:")) {
                            const text = line.replace("data:", "").trim();
                            
                            // 스트림 종료 체크
                            if (text === "[DONE]") {
                                break;
                            }
                            
                            try {
                                const parsedChunk = JSON.parse(text);
                                
                                // 에러 체크
                                if (parsedChunk.error) {
                                    throw new Error(parsedChunk.error);
                                }
                                
                                const content = parsedChunk.content || parsedChunk.text || "";
                                let safeHTML = content;

                                if (safeHTML.startsWith('"') && safeHTML.endsWith('"')) {
                                    safeHTML = safeHTML.slice(1, -1);
                                }

                                KachiCore.streamBuffer += safeHTML;
                                this.tryFlushStreamBuffer(messageElement);
                                KachiUI.scrollToBottom();
                                

                                // 주기적으로 수식 렌더링 실행
                                const currentTime = Date.now();
                                if (currentTime - lastMathRenderTime > MATH_RENDER_INTERVAL) {
                                    this.renderMathInElement(messageElement);
                                    lastMathRenderTime = currentTime;
                                }
                            } catch (e) {
                                console.warn("⚠️ JSON parse error in chunk:", text);
                            }
                        }
                    }
                }

                // 스트리밍 버퍼가 비어있지 않은 경우에만 최종 처리
                if (KachiCore.streamBuffer && KachiCore.streamBuffer.trim()) {
                    // 타이머 정리
                    if (KachiCore.typeTimer) {
                        clearTimeout(KachiCore.typeTimer);
                    }
                    KachiCore.isCharStreaming = false;
                    KachiCore.displayedLength = 0;
                    
                    this.tryFlushStreamBuffer(messageElement, true);
                } else {
                    console.warn("⚠️ Warning: Stream buffer is empty at completion, using fallback content extraction");
                    
                    // 스트리밍 버퍼가 비어있으면 DOM에서 콘텐츠 추출 시도
                    const textElement = messageElement.querySelector('.message-text');
                    if (textElement) {
                        const extractedContent = textElement.textContent || textElement.innerText || textElement.innerHTML;
                        if (extractedContent && extractedContent.trim()) {
                            KachiCore.streamBuffer = extractedContent;
                        }
                    }
                }
                
                // 스트리밍 완료 후 최종 MathJax 렌더링
                this.renderMathInElement(messageElement);
                
                // 스트리밍 완료 후 최종 메시지 업데이트 (개선된 버전)
                let finalContent = this._captureStreamingContent(messageElement, messageId);
                
                const message = KachiCore.findMessage(messageId);
                if (message) {
                    if (finalContent) {
                        // 업데이트된 콘텐츠를 메시지에 저장하기 전에 이미지 처리
                        if (window.KachiAPI && window.KachiAPI.processImageUrlsForDisplay) {
                            finalContent = window.KachiAPI.processImageUrlsForDisplay(finalContent);
                        }
                        message.content = finalContent;
                        console.log('✅ Message content updated:', messageId, finalContent.substring(0, 100) + '...');
                    } else {
                        console.warn('⚠️ No content captured for message:', messageId);
                    }
                    
                    // 참조 문서 정보 수집
                    const referencedDocs = messageElement.querySelector('.referenced-docs');
                    if (referencedDocs) {
                        message.referencedDocs = referencedDocs.outerHTML;
                        console.log('📄 Referenced docs captured for message');
                    }
                    
                    // 상태 전파 및 저장 (디바운싱 적용)
                    this._finalizeStreamingMessage(message);
                } else {
                    console.error('❌ Failed to capture streaming content or find message');
                }
                
                // 입력창에 포커스 주기
                setTimeout(() => {
                    const $queryInput = $('#queryInput');
                    if ($queryInput.length && !$queryInput.is(':disabled')) {
                        $queryInput.focus();
                    }
                }, 100);
            } catch (err) {
                const isAbort = err.name === 'AbortError';
                if (isAbort) {
                    const textElement = messageElement.querySelector('.message-text');
                    if (textElement) {
                        const stoppedMsg = window.kachi_ajax?.strings?.stopped || "사용자에 의해 중지되었습니다.";
                        textElement.innerHTML += `<div style="margin-top:10px; color:#a70638;">■ ${stoppedMsg}</div>`;
                        
                        // 중지된 경우도 새로운 캡처 시스템 사용
                        let partialContent = this._captureStreamingContent(messageElement, messageId, true);
                        console.log('⚠️ Capturing partial content after user stop:', {
                            hasContent: !!partialContent,
                            contentLength: partialContent ? partialContent.length : 0
                        });
                        
                        const message = KachiCore.findMessage(messageId);
                        if (message && partialContent) {
                            // 부분 콘텐츠에도 이미지 처리 적용
                            if (window.KachiAPI && window.KachiAPI.processImageUrlsForDisplay) {
                                partialContent = window.KachiAPI.processImageUrlsForDisplay(partialContent);
                            }
                            message.content = partialContent;
                            
                            // 참조 문서 정보 수집
                            const referencedDocs = messageElement.querySelector('.referenced-docs');
                            if (referencedDocs) {
                                message.referencedDocs = referencedDocs.outerHTML;
                            }
                            
                            
                            // 부분 콘텐츠 저장
                            this._finalizeStreamingMessage(message);
                        } else {
                            console.warn('⚠️ Failed to save partial content after stop');
                        }
                    }
                } else {
                    console.error("❌ Stream Error:", err);
                    const errorMsg = window.kachi_ajax?.strings?.error || "오류가 발생했습니다.";
                    KachiUI.addMessageUI('assistant', `<span style="color: #dc3545;">❌ ${errorMsg}: ${err.message}</span>`);
                }
            } finally {
                KachiCore.resetQueryState();
                KachiUI.resetQueryUI();
                
                setTimeout(() => {
                    const $queryInput = $('#queryInput');
                    if ($queryInput.length && !$queryInput.is(':disabled')) {
                        $queryInput.focus();
                    }
                }, 200);
            }
        },
        
        // MathJax 렌더링된 내용 정리 (개선된 텍스트 보존)
        cleanMathJaxContent: function(html) {
            
            // 빈 내용이면 그대로 반환
            if (!html || html.trim() === '') {
                console.log("⚠️ Warning: cleanMathJaxContent received empty input");
                return html;
            }
            
            // 원본 백업
            const originalHtml = html;
            let textPreserved = 0;
            
            try {
                // 임시 요소 생성
                const temp = document.createElement('div');
                temp.innerHTML = html;
                
                // 모든 텍스트 노드 먼저 수집 (보존용)
                const allTextContent = temp.textContent || temp.innerText || '';
                
                // MathJax가 렌더링한 요소들 제거 (텍스트 콘텐츠 보존)
                const mjxElements = temp.querySelectorAll('mjx-container, .MathJax, .MathJax_Display, .MathJax_Preview, .MathJax_CHTML');
                mjxElements.forEach(el => {
                    // 요소를 제거하기 전에 텍스트 내용이 있는지 확인
                    const textContent = el.textContent || el.innerText;
                    if (textContent && textContent.trim()) {
                        // 부모 요소에 텍스트 추가 (MathJax 렌더링 대신)
                        try {
                            const textNode = document.createTextNode(' ' + textContent + ' ');
                            if (el.parentNode) {
                                el.parentNode.insertBefore(textNode, el);
                                textPreserved++;
                            }
                        } catch (insertError) {
                            console.warn('⚠️ Failed to preserve text from MathJax element:', insertError);
                        }
                    }
                    try {
                        el.remove();
                    } catch (removeError) {
                        console.warn('⚠️ Failed to remove MathJax element:', removeError);
                    }
                });
                
                // MathJax 처리 마커 제거 (하지만 수식 자체는 보존)
                const scriptElements = temp.querySelectorAll('script[type*="math/tex"]');
                scriptElements.forEach(el => {
                    try {
                        el.remove();
                    } catch (error) {
                        console.warn('⚠️ Failed to remove script element:', error);
                    }
                });
                
                // MathJax data 속성 제거
                const elementsWithData = temp.querySelectorAll('[data-mjx-texclass]');
                elementsWithData.forEach(el => {
                    try {
                        el.removeAttribute('data-mjx-texclass');
                    } catch (error) {
                        console.warn('⚠️ Failed to remove data attribute:', error);
                    }
                });
                
                const result = temp.innerHTML;
                const finalTextContent = temp.textContent || temp.innerText || '';
                
                // 텍스트 손실 검증
                const originalLength = allTextContent.length;
                const finalLength = finalTextContent.length;
                const significantLoss = originalLength > 50 && finalLength < (originalLength * 0.5);
                
                if (significantLoss) {
                    console.warn('⚠️ Significant text loss in MathJax cleaning:', {
                        originalLength: originalLength,
                        finalLength: finalLength,
                        lossPercentage: Math.round((1 - finalLength / originalLength) * 100) + '%',
                        textPreserved: textPreserved
                    });
                    
                    // 손실이 심각하면 원본 반환
                    if (finalLength < (originalLength * 0.2)) {
                        console.warn('⚠️ Excessive text loss, returning original HTML');
                        return originalHtml;
                    }
                }
                
                return result || originalHtml;
            } catch (error) {
                console.error('❌ Error in cleanMathJaxContent:', error);
                return originalHtml; // 오류 시 원본 반환
            }
        },
        
        // 쿼리 문서 정보 가져오기
        fetchQueryDocuments: async function(userQuery, messageElement) {
            try {
                const response = await $.ajax({
                    url: window.kachi_ajax?.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kachi_get_query_documents',
                        nonce: window.kachi_ajax?.nonce,
                        query: userQuery,
                        tags: KachiCore.selectedTags,
                        docs: KachiCore.selectedDocs
                    }
                });
                
                if (response.success && response.data.documents && response.data.documents.length > 0) {
                    // 문서 정보를 메시지에 추가
                    const docsHtml = this.renderDocumentInfo(response.data.documents);
                    const messageBubble = messageElement.querySelector('.message-bubble');
                    
                    // 문서 정보를 메시지 상단에 추가
                    const docsContainer = document.createElement('div');
                    docsContainer.className = 'referenced-docs';
                    docsContainer.innerHTML = docsHtml;
                    messageBubble.insertBefore(docsContainer, messageBubble.firstChild);
                }
            } catch (err) {
                console.error("❌ Error fetching document info:", err);
            }
        },
        
        // 문서 정보 렌더링
        renderDocumentInfo: function(documents) {
            let html = '<div class="docs-header">📚 참조 문서</div>';
            html += '<div class="docs-list">';
            
            documents.forEach((doc, index) => {
                const filename = doc.filename || 'Unknown';
                const pageInfo = doc.page_number ? ` - ${doc.page_number}페이지` : '';
                const sectionInfo = doc.section_title ? ` (${doc.section_title})` : '';
                
                html += `
                    <div class="doc-item">
                        <span class="doc-number">${index + 1}.</span>
                        <span class="doc-filename">${KachiCore.escapeHtml(filename)}</span>
                        <span class="doc-page">${pageInfo}</span>
                        <span class="doc-section">${KachiCore.escapeHtml(sectionInfo)}</span>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        },
        
        // 완성된 이미지 URL 감지
        // 텍스트 블록 추출
        extractTextBlocks: function(html) {
            const blocks = [];
            // HTML 태그 사이의 텍스트 콘텐츠를 추출 (태그 자체는 제외)
            const tagContentRegex = />([^<]*)/g;
            let match;
            let blockIndex = 0;
            
            while ((match = tagContentRegex.exec(html)) !== null) {
                const content = match[1].trim();
                if (content.length > 0) { // 빈 콘텐츠는 무시
                    blocks.push({
                        content: content,
                        startPos: match.index + 1,
                        endPos: match.index + match[0].length - 1,
                        blockIndex: blockIndex++,
                        originalMatch: match[0]
                    });
                }
            }
            
            console.log('🧱 Extracted', blocks.length, 'text blocks from HTML');
            return blocks;
        },
        
        // 텍스트 블록 완성도 검사
        isTextBlockComplete: function(block, fullText) {
            // 블록이 HTML 태그 경계에서 끝나거나 스트림 끝에 있는지 확인
            const afterBlockPos = block.endPos + 1;
            
            // 스트림 끝에 도달한 경우
            if (afterBlockPos >= fullText.length) {
                return true;
            }
            
            // 다음 문자가 HTML 태그 시작('<')인지 확인  
            const nextChar = fullText[afterBlockPos];
            const isAtTagBoundary = nextChar === '<';
            
            return isAtTagBoundary;
        },

        detectCompleteImages: function(text, processedImageUrls = new Set()) {
            const completeImages = [];
            
            // 줄 단위로 처리 (HTML 블록 추출 대신 라인 기반 처리)
            const lines = text.split('\n');
            
            
            lines.forEach((line, lineIndex) => {
                // 이미 img 태그가 있는 줄은 건너뛰기
                if (line.includes('<img')) {
                    console.log('⏭️ Skipping line with existing img tag');
                    return;
                }
                
                
                // 이중 URL 패턴: [URL](URL)
                const doubleUrlPattern = /\[(https?:\/\/[^:\s]+:8001\/images\/[^\]]+)\]\((https?:\/\/[^)]*:8001\/images\/[^)]+)\)/;
                const doubleUrlMatch = line.match(doubleUrlPattern);
                
                if (doubleUrlMatch) {
                    const urlInBrackets = doubleUrlMatch[1];
                    const urlInParentheses = doubleUrlMatch[2];
                    
                    // 두 URL이 동일한지 확인 (진정한 이중 URL인지 검증)
                    if (urlInBrackets === urlInParentheses) {
                        let originalImageUrl = urlInParentheses;
                        
                        originalImageUrl = this.cleanImageUrl(originalImageUrl);
                        console.log('🧹 Cleaned double URL:', originalImageUrl);
                        
                        // 이미 처리된 이미지가 아닌 경우에만 추가
                        if (!processedImageUrls.has(originalImageUrl)) {
                            completeImages.push({
                                lineIndex: lineIndex,
                                originalUrl: originalImageUrl,
                                type: 'double-url',
                                fullLine: line
                            });
                            processedImageUrls.add(originalImageUrl);
                        } else {
                            console.log('⏭️ Skipping already processed double URL');
                        }
                        return;
                    } else {
                        console.log('⚠️ URL mismatch in double pattern, treating as regular markdown');
                    }
                }
                
                // 마크다운 패턴: [text](URL)
                const markdownImagePattern = /.*\]\((https?:\/\/[^)]*:8001\/images\/[^)]+)\)/;
                const markdownMatch = line.match(markdownImagePattern);
                
                if (markdownMatch) {
                    let originalImageUrl = markdownMatch[1];
                    
                    originalImageUrl = this.cleanImageUrl(originalImageUrl);
                    console.log('🧹 Cleaned markdown URL:', originalImageUrl);
                    
                    // 이미 처리된 이미지가 아닌 경우에만 추가
                    if (!processedImageUrls.has(originalImageUrl)) {
                        completeImages.push({
                            lineIndex: lineIndex,
                            originalUrl: originalImageUrl,
                            type: 'markdown',
                            fullLine: line
                        });
                        processedImageUrls.add(originalImageUrl);
                    } else {
                        console.log('⏭️ Skipping already processed markdown image');
                    }
                    return;
                }
                
                // 일반 URL 패턴
                const normalImagePattern = /https?:\/\/[^:\s]+:8001\/images\/[^\s\)\]]+/;
                const normalMatch = line.match(normalImagePattern);
                
                if (normalMatch) {
                    let originalImageUrl = normalMatch[0];
                    
                    originalImageUrl = this.cleanImageUrl(originalImageUrl);
                    console.log('🧹 Cleaned URL:', originalImageUrl);
                    
                    // 이미 처리된 이미지가 아닌 경우에만 추가
                    if (!processedImageUrls.has(originalImageUrl)) {
                        completeImages.push({
                            lineIndex: lineIndex,
                            originalUrl: originalImageUrl,
                            type: 'normal',
                            fullLine: line
                        });
                        processedImageUrls.add(originalImageUrl);
                    } else {
                        console.log('⏭️ Skipping already processed normal image');
                    }
                }
            });
            
            return completeImages;
        },
        
        // 실시간 이미지 처리
        processImagesRealtime: function(text, processedImageUrls) {
            const completeImages = this.detectCompleteImages(text, processedImageUrls);
            
            if (completeImages.length === 0) {
                return { processedText: text }; // 새로운 완성된 이미지가 없으면 원본 반환
            }
            
            
            let processedText = text;
            
            // 각 이미지를 라인별로 처리
            completeImages.forEach(imageInfo => {
                const { originalUrl, fullLine, type } = imageInfo;
                
                // 프록시 URL로 변환
                const proxyUrl = this.convertToProxyImageUrl(originalUrl);
                
                // 이미지 태그 생성
                const imageTag = `<img src="${proxyUrl}" alt="이미지" style="max-width: 100%; height: auto; display: block; margin: 10px 0;" data-original-url="${originalUrl}">`;
                
                // 원본 라인에서 URL을 이미지 태그로 교체
                let updatedLine;
                if (type === 'double-url') {
                    // 이중 URL 패턴 교체
                    const doubleUrlPattern = new RegExp(`\\[${originalUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\]\\(${originalUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\)`, 'g');
                    updatedLine = fullLine.replace(doubleUrlPattern, imageTag);
                } else if (type === 'markdown') {
                    // 일반 마크다운 패턴 교체  
                    const markdownPattern = new RegExp(`\\[.*?\\]\\(${originalUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\)`, 'g');
                    updatedLine = fullLine.replace(markdownPattern, imageTag);
                } else {
                    // 일반 URL 패턴 교체
                    const plainUrlPattern = new RegExp(originalUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                    updatedLine = fullLine.replace(plainUrlPattern, imageTag);
                }
                
                // 전체 텍스트에서 원본 라인을 업데이트된 라인으로 교체
                processedText = processedText.replace(fullLine, updatedLine);
                
            });
            
            
            return { processedText };
        },

        // 스트림 버퍼 플러시
        tryFlushStreamBuffer: function(messageElement, isFinal = false) {
            // messageElement가 유효한지 확인
            if (!messageElement || !messageElement.querySelector) {
                console.error("❌ Invalid message element for stream buffer");
                return;
            }
            
            const textElement = messageElement.querySelector('.message-text');
            if (!textElement) {
                console.error("❌ Message text element not found");
                return;
            }
            
            // 입력창에 잘못 출력되는 것을 방지
            const $queryInput = $('#queryInput');
            if ($queryInput.val() && KachiCore.streamBuffer) {
                console.warn("⚠️ Clearing query input to prevent stream buffer conflict");
                $queryInput.val('');
            }
            
            // 처리된 이미지 URL 추적을 위한 Set 초기화 (메시지별로)
            if (!messageElement._processedImageUrls) {
                messageElement._processedImageUrls = new Set();
            }
            
            // 최종 플러시인 경우 전체 내용을 포맷팅
            if (isFinal && KachiCore.streamBuffer) {
                // 최종 포맷팅 (이미지 처리 포함)
                const formattedContent = this.formatResponsePreservingImages(KachiCore.streamBuffer);
                textElement.innerHTML = formattedContent;
                KachiCore.streamBuffer = '';
                return;
            }
            
            // 스트리밍 중에는 글자 단위로 부드럽게 표시
            if (KachiCore.streamBuffer && !KachiCore.isCharStreaming) {
                KachiCore.isCharStreaming = true;
                
                // 현재 표시된 텍스트 길이
                if (!KachiCore.displayedLength) {
                    KachiCore.displayedLength = 0;
                }
                
                // 수식 감지를 위한 변수
                let mathDetected = false;
                
                // 타이핑 효과
                const typeNextChars = () => {
                    if (KachiCore.displayedLength < KachiCore.streamBuffer.length) {
                        // 한 번에 표시할 글자 수 (한글은 1글자, 영문은 2-3글자)
                        let charsToAdd = 1;
                        const nextChar = KachiCore.streamBuffer[KachiCore.displayedLength];
                        
                        // 영문이나 공백인 경우 더 많은 글자를 한 번에 표시
                        if (/[a-zA-Z0-9\s]/.test(nextChar)) {
                            charsToAdd = Math.min(3, KachiCore.streamBuffer.length - KachiCore.displayedLength);
                        }
                        
                        // 현재까지의 텍스트 + 새로운 글자들
                        const displayText = KachiCore.streamBuffer.substring(0, KachiCore.displayedLength + charsToAdd);
                        
                        // 수식 시작/종료 감지
                        if (displayText.includes('\\[') || displayText.includes('\\]') || 
                            displayText.includes('\\(') || displayText.includes('\\)')) {
                            mathDetected = true;
                        }
                        
                        // 점진적 표시를 위한 안전한 길이 계산
                        const targetLength = Math.min(displayText.length, KachiCore.displayedLength + charsToAdd);
                        let safeDisplayText = displayText.substring(0, targetLength);
                        
                        // 이미지 태그 완성도 검사 - 미완성 태그 방지 (더 강화된 검사)
                        const lastImgStart = safeDisplayText.lastIndexOf('<img');
                        const lastImgEnd = safeDisplayText.lastIndexOf('>');
                        
                        // 미완성 이미지 태그가 있으면 해당 부분을 제외
                        if (lastImgStart !== -1 && (lastImgEnd === -1 || lastImgEnd < lastImgStart)) {
                            const beforeImg = safeDisplayText.substring(0, lastImgStart);
                            console.log('⚠️ Incomplete img tag detected, truncating at:', lastImgStart);
                            safeDisplayText = beforeImg;
                        }
                        
                        // 안전한 텍스트로 표시
                        if (safeDisplayText.length > 0) {
                            const formattedText = this.formatResponsePreservingImages(safeDisplayText);
                            textElement.innerHTML = formattedText;
                            
                            // 표시된 길이 업데이트
                            KachiCore.displayedLength = Math.min(KachiCore.displayedLength + charsToAdd, KachiCore.streamBuffer.length);
                        }
                        
                        // 수식이 감지되면 즉시 렌더링
                        if (mathDetected) {
                            this.renderMathInElement(messageElement);
                            mathDetected = false;
                        }
                        
                        // 다음 글자 표시를 위한 타이머
                        KachiCore.typeTimer = setTimeout(typeNextChars, 30);
                    } else {
                        // 모든 글자를 표시했으면 최종 포맷팅
                        const finalContent = this.formatResponsePreservingImages(KachiCore.streamBuffer);
                        textElement.innerHTML = finalContent;
                        KachiCore.isCharStreaming = false;
                        
                        // 마지막 수식 렌더링
                        this.renderMathInElement(messageElement);
                    }
                };
                
                // 타이핑 시작
                typeNextChars();
            }
        },
        
        // 이미지를 보존하면서 나머지 포맷팅 수행 - 실시간 스트리밍용
        // 통합된 텍스트 포맷팅 함수
        _formatTextContent: function(text, processImages = false) {
            if (!text || typeof text !== 'string') return text || '';
            
            let imagePlaceholders = {};
            let imageCounter = 0;
            
            // 이미지 처리 (선택적)
            if (processImages) {
                const self = this;
                
                // 기존 이미지 태그 보호
                text = text.replace(/<img[^>]*>/g, function(match) {
                    const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
                    imagePlaceholders[placeholder] = match;
                    return placeholder;
                });
                
                // 이중 URL 패턴 처리
                text = text.replace(/\[(https?:\/\/[^\]]+\.(jpg|jpeg|png|gif|webp|bmp|svg)[^\]]*)\]\((https?:\/\/[^\)]+\.(jpg|jpeg|png|gif|webp|bmp|svg)[^\)]*)\)/gi, function(match, url1, ext1, url2, ext2) {
                    if (url1 === url2 || Math.abs(url1.length - url2.length) <= 3) {
                        const finalUrl = url1.length >= url2.length ? url1 : url2;
                        const proxyUrl = self.convertToProxyImageUrl(finalUrl);
                        const imgTag = `<img src="${proxyUrl}" alt="Image" style="max-width: 100%; height: auto; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" loading="lazy" onerror="this.style.display='none'">`;
                        const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
                        imagePlaceholders[placeholder] = imgTag;
                        return placeholder;
                    }
                    return match;
                });
                
                // 마크다운 이미지 패턴 처리
                text = text.replace(/!\[([^\]]*)\]\((https?:\/\/[^\)]+\.(jpg|jpeg|png|gif|webp|bmp|svg)[^\)]*)\)/gi, function(match, alt, url, ext) {
                    const proxyUrl = self.convertToProxyImageUrl(url);
                    const imgTag = `<img src="${proxyUrl}" alt="${alt || 'Image'}" style="max-width: 100%; height: auto; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" loading="lazy" onerror="this.style.display='none'">`;
                    const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
                    imagePlaceholders[placeholder] = imgTag;
                    return placeholder;
                });
                
                // 단순 URL 패턴 처리
                text = text.replace(/^\s*(https?:\/\/\S+\.(jpg|jpeg|png|gif|webp|bmp|svg)\S*)\s*$/gmi, function(match, url, ext) {
                    const proxyUrl = self.convertToProxyImageUrl(url);
                    const imgTag = `<img src="${proxyUrl}" alt="Image" style="max-width: 100%; height: auto; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" loading="lazy" onerror="this.style.display='none'">`;
                    const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
                    imagePlaceholders[placeholder] = imgTag;
                    return placeholder;
                });
            }
            
            // 기본 텍스트 포맷팅
            text = text.replace(/```plaintext\s*([\s\S]*?)```/g, '$1');
            text = text.replace(/```html\s*([\s\S]*?)```/g, '$1');
            
            // LaTeX 수식 보호
            const mathPlaceholders = {};
            let mathCounter = 0;
            text = text.replace(/\\\[([\s\S]*?)\\\]/g, function(match, equation) {
                const placeholder = `__MATH_BLOCK_${mathCounter++}__`;
                mathPlaceholders[placeholder] = `<div class="math-block">\\[${equation}\\]</div>`;
                return placeholder;
            });
            text = text.replace(/\\\(([\s\S]*?)\\\)/g, function(match, equation) {
                const placeholder = `__MATH_INLINE_${mathCounter++}__`;
                mathPlaceholders[placeholder] = `<span class="math-inline">\\(${equation}\\)</span>`;
                return placeholder;
            });
            
            // 마크다운 포맷팅
            text = text.replace(/^---+$/gm, '<hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">');
            text = text.replace(/^#\s+(.+)$/gm, '<h2 style="margin-top: 28px; margin-bottom: 16px; color: #1a1a1a; font-size: 1.8em; font-weight: 700; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px;">$1</h2>');
            text = text.replace(/^##\s+(.+)$/gm, '<h3 style="margin-top: 24px; margin-bottom: 12px; color: #2d2d2d; font-size: 1.4em; font-weight: 600;">$1</h3>');
            text = text.replace(/^###\s+(.+)$/gm, '<h4 style="margin-top: 20px; margin-bottom: 10px; color: #333; font-size: 1.1em; font-weight: 600;">$1</h4>');
            text = text.replace(/^####\s+(.+)$/gm, '<h5 style="margin-top: 16px; margin-bottom: 8px; color: #333; font-size: 1em; font-weight: 600;">$1</h5>');
            
            text = text.replace(/`([^`]+)`/g, function(match, code) {
                const wordCount = code.trim().split(/\s+/).length;
                return wordCount <= 3 ? 
                    '<code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: \'Consolas\', \'Monaco\', \'Courier New\', monospace; font-size: 0.9em; color: #d73a49;">' + code + '</code>' :
                    '`' + code + '`';
            });
            
            text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/(?<!\*)\*(?!\*)([^*]+)(?<!\*)\*(?!\*)/g, '<strong>$1</strong>');
            
            // 리스트 처리
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n(\d{1,2}\.\s)/gm, '<br>$1');
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n([-•▪]\s)/gm, '<br>$1');
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n(▶\s)/gm, '<br>$1');
            
            // 테이블 보호 및 줄바꿈 처리
            const tablePlaceholders = {};
            let tableCounter = 0;
            text = text.replace(/<table[\s\S]*?<\/table>/gi, function(match) {
                const placeholder = `__TABLE_${tableCounter++}__`;
                tablePlaceholders[placeholder] = match;
                return placeholder;
            });
            
            text = text.replace(/\n/g, '<br>');
            
            // 테이블 복원
            Object.keys(tablePlaceholders).forEach(placeholder => {
                text = text.replace(placeholder, tablePlaceholders[placeholder]);
            });
            
            // HTML 정리
            text = text.replace(/(<br>){3,}/g, '<br><br>');
            text = text.replace(/<\/h([2345])>(<br>)+/g, '</h$1>');
            text = text.replace(/(<br>)+<h([2345])/g, '<h$2');
            text = text.replace(/<hr([^>]*)>(<br>)+/g, '<hr$1>');
            text = text.replace(/(<br>)+<hr/g, '<hr');
            text = text.replace(/(<br>\s*)+(<table)/gi, '$2');
            text = text.replace(/(<\/table>)\s*(<br>\s*)+/gi, '$1');
            text = text.replace(/^(<br>)+/, '');
            
            // 수식 복원
            Object.keys(mathPlaceholders).forEach(placeholder => {
                text = text.replace(new RegExp(placeholder, 'g'), mathPlaceholders[placeholder]);
            });
            
            // 이미지 복원
            if (processImages) {
                Object.keys(imagePlaceholders).forEach(placeholder => {
                    text = text.replace(new RegExp(placeholder, 'g'), imagePlaceholders[placeholder]);
                });
            }
            
            return text;
        },

        formatResponsePreservingImages: function(text) {
            return this._formatTextContent(text, true);
        },
        
        // 답변 포맷팅 (이미지 처리 제외) - 스트리밍 중 사용
        formatResponseWithoutImages: function(text) {
            return this._formatTextContent(text, false);
        },
        
        formatResponse: function(text) {
            return this._formatTextContent(text, false);
        },
        
        // MathJax로 수식 렌더링
        renderMathInElement: function(element) {
            // MathJax가 비활성화되었거나 로드되지 않은 경우 스킵
            if (window.MathJaxDisabled || !window.MathJax || !window.MathJax.typesetPromise) {
                console.warn("⚠️ MathJax is not available, skipping math rendering");
                return;
            }
            
            // 이미 렌더링된 수식은 제외하고 새로운 수식만 렌더링
            window.MathJax.typesetClear([element]);
            window.MathJax.typesetPromise([element]).then(() => {
            }).catch((e) => {
                console.error("❌ MathJax rendering error:", e);
            });
        },
        
        // URL 정리 함수 - 마크다운 문법 제거
        cleanImageUrl: function(url) {
            // 마크다운 링크 문법 감지: filename](actual_url) - 추가 ']'도 처리
            const markdownMatch = url.match(/^[^)]+\]\((https?:\/\/[^)]+)\)[\]]*$/);
            if (markdownMatch) {
                let cleanedUrl = markdownMatch[1];
                // 추출한 URL 끝에도 ']'가 있을 수 있으므로 추가 정리
                if (cleanedUrl.endsWith(']')) {
                    cleanedUrl = cleanedUrl.slice(0, -1);
                }
                console.log("🧹 Cleaning markdown URL:", url, "->", cleanedUrl);
                return cleanedUrl;
            }
            
            // 불완전한 마크다운 문법 감지: partial_url](actual_url
            const partialMarkdownMatch = url.match(/.*\]\((https?:\/\/[^)]+)$/);
            if (partialMarkdownMatch) {
                let cleanedUrl = partialMarkdownMatch[1];
                // 추출한 URL 끝에도 ']'가 있을 수 있으므로 추가 정리
                if (cleanedUrl.endsWith(']')) {
                    cleanedUrl = cleanedUrl.slice(0, -1);
                }
                console.log("🧹 Cleaning partial markdown URL:", url, "->", cleanedUrl);
                return cleanedUrl;
            }
            
            // http: -> http:// 수정
            if (url.startsWith('http:') && !url.startsWith('http://')) {
                url = url.replace('http:', 'http://');
                console.log("🧹 Fixed protocol:", url);
            }
            
            // 끝에 ']' 문자가 있으면 제거
            if (url.endsWith(']')) {
                url = url.slice(0, -1);
                console.log("🧹 Removed trailing ']':", url);
            }
            
            // HTML 태그 및 HTML 콘텐츠 제거 (이미지 URL 뒤에 HTML이 붙는 경우)
            const htmlMatch = url.match(/^(https?:\/\/[^<>\s]+)/);
            if (htmlMatch && htmlMatch[1] !== url) {
                const cleanedUrl = htmlMatch[1];
                console.log("🧹 Removed HTML content:", url, "->", cleanedUrl);
                url = cleanedUrl;
            }
            
            return url;
        },
        
        // 이미지 URL을 프록시 URL로 변환 - 마크다운 처리 개선
        convertToProxyImageUrl: function(imageUrl) {
            // 이미 프록시 URL인 경우 그대로 반환
            if (imageUrl.includes('action=kachi_proxy_image')) {
                return imageUrl;
            }
            
            // 빈 URL이거나 data: URL인 경우 그대로 반환
            if (!imageUrl || imageUrl.startsWith('data:')) {
                return imageUrl;
            }
            
            // URL 정리 (마크다운 문법 제거)
            const cleanUrl = this.cleanImageUrl(imageUrl);
            
            if (imageUrl !== cleanUrl) {
                console.log('🧹 URL cleaned:', imageUrl, '->', cleanUrl);
            }
            
            // 정리된 URL이 유효한 http(s) URL인지 확인
            if (!cleanUrl.startsWith('http://') && !cleanUrl.startsWith('https://')) {
                console.warn("⚠️ Invalid URL after cleaning:", cleanUrl);
                return imageUrl;
            }
            
            // API 서버의 이미지 URL 패턴 확인 (HTML 태그에서 중단)
            const apiPattern = /:8001\/images\/([a-zA-Z0-9._-]+(?:\.[a-zA-Z]{2,5})?)/;
            const match = cleanUrl.match(apiPattern);
            
            if (match && match[1]) {
                // 프록시 URL로 변환 (WordPress AJAX 엔드포인트 사용)
                const imagePath = match[1];
                console.log('🖼️ Extracted clean image path:', imagePath, 'from URL:', cleanUrl);
                const proxyUrl = window.kachi_ajax?.ajax_url + '?action=kachi_proxy_image&path=' + encodeURIComponent(imagePath);
                return proxyUrl;
            }
            
            // 매치하지 않으면 정리된 URL 반환
            console.warn("⚠️ No API pattern match for:", cleanUrl);
            return cleanUrl;
        },
        
        // 이미지 URL 처리 함수 - 중복 URL 및 마크다운 링크 처리 개선
        processImageUrlsForDisplay: function(text) {
            if ((text.includes('<img') && text.includes('action=kachi_proxy_image')) || 
                text.includes('/?action=kachi_proxy_image&url=')) {
                return text;
            }
            
            const lines = text.split('\n');
            const processedUrls = new Set();
            
            return lines.map(line => {
                if (line.includes('<img')) return line;
                return this._processImageLine(line, processedUrls);
            }).join('\n');
        },
        
        // 단일 라인의 이미지 URL 처리
        _processImageLine: function(line, processedUrls) {
            // 먼저 마크다운 링크의 URL을 프록시 URL로 변경 (링크 형태 유지)
            line = line.replace(/\[([^\]]+)\]\((https?:\/\/[^)]*:8001\/images\/[^)]+)\)/g, (match, text, url) => {
                const cleanUrl = this.cleanImageUrl(url);
                if (!processedUrls.has(cleanUrl)) {
                    processedUrls.add(cleanUrl);
                    const proxyUrl = this.convertToProxyImageUrl(cleanUrl);
                    console.log('🔗 Converting markdown link URL:', url, '->', proxyUrl);
                    return `[${text}](${proxyUrl})`;
                }
                return match;
            });
            
            const patterns = [
                // 이중 URL 패턴: [URL](URL)
                {
                    regex: /\[(https?:\/\/[^:\s]+:8001\/images\/[^\]]+)\]\((https?:\/\/[^)]*:8001\/images\/[^)]+)\)/,
                    extract: (match) => match[1] === match[2] ? match[1] : null
                },
                // 단순 URL 패턴 (마크다운이 아닌 경우만)
                {
                    regex: /https?:\/\/[^:\s]+:8001\/images\/[^\s)\]]+/,
                    extract: (match) => match[0]
                }
            ];
            
            for (let pattern of patterns) {
                const match = line.match(pattern.regex);
                if (match) {
                    const url = pattern.extract(match);
                    if (url) {
                        const cleanUrl = this.cleanImageUrl(url);
                        if (!processedUrls.has(cleanUrl)) {
                            processedUrls.add(cleanUrl);
                            const proxyUrl = this.convertToProxyImageUrl(cleanUrl);
                            const imgTag = `<img src="${proxyUrl}" alt="이미지" style="max-width: 100%; height: auto; display: block; margin: 10px 0;" data-original-url="${cleanUrl}">`;
                            return imgTag;
                        }
                    }
                }
            }
            return line;
        },

        
        // 이미지 태그 수정 (프록시 URL 적용) - 개선된 버전
        fixImgTags: function(htmlStr) {
            console.log('🔧 fixImgTags called with content:', htmlStr.substring(0, 200) + '...');
            
            // 이미 프록시 URL로 처리된 이미지가 있는지 확인
            if (htmlStr.includes('action=kachi_proxy_image')) {
                console.log('✅ Already contains proxy URLs, skipping processing');
                return htmlStr;
            }
            
            // 잘못된 이미지 태그 패턴들을 수정
            // 1. src 속성 뒤에 따옴표가 없는 경우
            htmlStr = htmlStr.replace(/src="([^"]+)(?=\s+style=)/g, 'src="$1"');
            
            // 2. style 속성 값에 따옴표가 잘못된 경우
            htmlStr = htmlStr.replace(/style="\s*([^"]+?)"">/g, 'style="$1">');
            
            // 3. 일반적인 이미지 태그 수정 (속성 사이에 공백 추가)
            htmlStr = htmlStr.replace(/<img\s+src="([^"]+)"style="/g, '<img src="$1" style="');
            
            // 4. 중복된 따옴표 제거
            htmlStr = htmlStr.replace(/""+/g, '"');
            
            // 5. style 속성이 제대로 닫히지 않은 경우
            htmlStr = htmlStr.replace(/style="([^"]*?)"\s*">/g, 'style="$1">');
            
            // 6. API 서버의 이미지 URL만 프록시 URL로 변환
            const self = this;
            let imageReplacements = 0;
            htmlStr = htmlStr.replace(/<img\s+([^>]*?)src="([^"]+)"([^>]*?)>/g, function(match, before, src, after) {
                // 이미 프록시 URL이거나 데이터 URL이면 건너뛰기
                if (src.includes('action=kachi_proxy_image') || src.startsWith('data:')) {
                    return match;
                }
                
                // API 서버 이미지 URL인 경우만 변환
                if (src.includes(':8001/images/')) {
                    const proxySrc = self.convertToProxyImageUrl(src);
                    imageReplacements++;
                    console.log('🖼️ Converting image URL:', src, '->', proxySrc);
                    return '<img ' + before + 'src="' + proxySrc + '"' + after + '>';
                }
                
                return match;
            });
            
            if (imageReplacements > 0) {
                console.log('✅ Converted', imageReplacements, 'image URLs to proxy format');
            } else {
                console.log('ℹ️ No API server images found to convert');
            }
            
            return htmlStr;
        },
        
        // 스트리밍 콘텐츠 캡처 (DOM 추출 방식)
        _captureStreamingContent: function(messageElement, messageId, isPartial = false) {
            try {
                // DOM에서 직접 HTML 콘텐츠 추출 (이미지 태그 보존을 위해 innerHTML 우선)
                const textElement = messageElement.querySelector('.message-text');
                if (textElement) {
                    // HTML 구조를 보존하기 위해 innerHTML을 우선적으로 사용
                    let domContent = textElement.innerHTML;
                    if (!domContent || !domContent.trim()) {
                        // innerHTML이 없는 경우에만 텍스트 콘텐츠 사용
                        domContent = textElement.textContent || textElement.innerText || '';
                    }
                    
                    if (domContent && domContent.trim()) {
                        const isHtmlContent = domContent.includes('<');
                        console.log('📄 Captured content from DOM (' + (isHtmlContent ? 'HTML' : 'TEXT') + '):', domContent.substring(0, 100) + '...');
                        return this._processStreamContent(domContent);
                    }
                }
                
                console.warn('⚠️ No content found in DOM for message:', messageId);
                return '';
            } catch (error) {
                console.error('❌ Error during content capture:', error);
                return '';
            }
        },
        
        // 스트림 콘텐츠 처리 (강화된 검증 버전)
        _processStreamContent: function(streamBuffer) {
            try {
                if (!streamBuffer || !streamBuffer.trim()) {
                    console.warn('⚠️ Stream buffer is empty or contains only whitespace');
                    return '';
                }
                
                // 1. 이미지 태그 수정
                const fixedContent = this.fixImgTags(streamBuffer);
                if (!fixedContent || (streamBuffer.length > 50 && fixedContent.length < streamBuffer.length * 0.1)) {
                    console.warn('⚠️ Content loss in fixImgTags, using original');
                    return streamBuffer;
                }
                
                // 2. MathJax 콘텐츠 정리
                const cleanedContent = this.cleanMathJaxContent(fixedContent);
                if (!cleanedContent || (fixedContent.length > 50 && cleanedContent.length < fixedContent.length * 0.1)) {
                    console.warn('⚠️ Content loss in cleanMathJaxContent, using previous step');
                    return fixedContent;
                }
                
                return cleanedContent || streamBuffer;
            } catch (error) {
                console.error('❌ Error processing stream content:', error);
                return streamBuffer; // 원본 반환
            }
        },
        
        // 통합된 콘텐츠 추출 함수
        _extractContent: function(source) {
            try {
                let element;
                
                // source가 문자열이면 HTML로 파싱, 아니면 DOM 요소로 사용
                if (typeof source === 'string') {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = source;
                    element = tempDiv.querySelector('.message-text') || tempDiv;
                } else {
                    element = source;
                }
                
                // innerHTML 우선 시도
                let content = element.innerHTML;
                if (content && content.trim()) {
                    return this._processStreamContent(content);
                }
                
                // textContent 시도
                content = element.textContent || element.innerText;
                return content && content.trim() ? content : '';
            } catch (error) {
                console.error('❌ Error extracting content:', error);
                return '';
            }
        },
        
        // 스트리밍 메시지 최종 처리
        _finalizeStreamingMessage: function(message) {
            try {
                
                // LLM 응답 저장 성공 여부 메트릭 기록
                if (KachiCore.debug && message.role === 'assistant') {
                    const hasValidContent = message.content && message.content.trim().length > 0;
                    const isErrorMessage = message.content && (
                        message.content.includes('❌') || 
                        message.content.includes('오류가 발생했습니다') ||
                        message.content.includes('콘텐츠를 불러올 수 없습니다')
                    );
                    
                    if (hasValidContent && !isErrorMessage) {
                        KachiCore.debug.recordLLMResponseSaved();
                    } else {
                        KachiCore.debug.recordLLMResponseLost();
                        console.warn('⚠️ LLM response lost or invalid:', {
                            messageId: message.id,
                            hasContent: hasValidContent,
                            isError: isErrorMessage
                        });
                    }
                }
                
                // 대화 업데이트 (디바운싱 적용)
                setTimeout(() => {
                    KachiCore.updateCurrentConversation({ skipSave: false });
                    
                    // UI 업데이트
                    if (window.KachiUI && window.KachiUI.renderConversationList) {
                        window.KachiUI.renderConversationList(false);
                    }
                }, 100);
                
            } catch (error) {
                console.error('❌ Error finalizing streaming message:', error);
            }
        },
    };
    
})(window, document, jQuery);