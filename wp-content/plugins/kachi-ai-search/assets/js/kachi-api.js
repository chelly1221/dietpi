// 까치 쿼리 시스템 - API 통신 및 데이터 처리 (프록시 버전)
(function(window, document, $) {
    'use strict';
    
    window.KachiAPI = {
        // 초기화
        init: function() {
            console.log("✅ Kachi API initializing...");
            this.fetchTagsAndDocs();
            this.loadMathJax();
        },
        
        // MathJax 로드 - 로컬 버전
        loadMathJax: function() {
            if (window.MathJax) {
                console.log("✅ MathJax already loaded");
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
                        console.log("✅ MathJax is ready!");
                        MathJax.startup.defaultReady();
                        MathJax.startup.promise.then(() => {
                            console.log("✅ MathJax startup complete");
                        });
                    }
                }
            };
            
            // MathJax 스크립트 로드 - 로컬 경로 사용
            const script = document.createElement('script');
            script.src = window.kachi_ajax.plugin_url + 'assets/js/mathjax/es5/tex-mml-chtml.js';
            script.async = true;
            script.id = 'MathJax-script';
            
            script.onerror = function() {
                console.error("❌ Failed to load MathJax from local path");
                window.MathJaxDisabled = true;
            };
            
            script.onload = function() {
                console.log("✅ MathJax script loaded successfully from local");
            };
            
            document.head.appendChild(script);
            console.log("✅ MathJax loading initiated from local path");
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
                        console.log("✅ Loaded tags:", KachiCore.allTagList);
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
                    console.log("🔍 Documents response:", response);
                    if (response.success && response.data.documents) {
                        KachiCore.allDocList = response.data.documents.map(doc => 
                            doc.filename || doc.file_id || "❓ unknown"
                        );
                        console.log("✅ Parsed doc list:", KachiCore.allDocList);
                        
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
            
            // 사용자 메시지 추가 (원본과 변형된 쿼리 모두 표시) - 체크 마크 SVG 사용
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
        
        // 스트리밍 쿼리 처리 - 프록시 방식
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
            
            // 스트림 버퍼 초기화
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
                                console.log("✅ Stream completed");
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

                if (KachiCore.streamBuffer) {
                    // 타이머 정리
                    if (KachiCore.typeTimer) {
                        clearTimeout(KachiCore.typeTimer);
                    }
                    KachiCore.isCharStreaming = false;
                    KachiCore.displayedLength = 0;
                    
                    this.tryFlushStreamBuffer(messageElement, true);
                }
                
                // 스트리밍 완료 후 최종 MathJax 렌더링
                this.renderMathInElement(messageElement);
                
                // 스트리밍 완료 후 최종 메시지 업데이트
                // 저장용: 원본 URL을 유지한 콘텐츠 생성
                const rawContent = this.fixImgTags(KachiCore.streamBuffer);
                const storageContent = this.processImageUrlsForStorage(rawContent);
                const finalStorageContent = this.cleanMathJaxContent(storageContent);
                
                const message = KachiCore.findMessage(messageId);
                if (message) {
                    // 저장용 콘텐츠는 원본 URL 유지
                    message.content = finalStorageContent;
                    console.log("💾 Saving content with original URLs for LLM compatibility");
                    // 참조 문서 정보도 저장
                    const referencedDocs = messageElement.querySelector('.referenced-docs');
                    if (referencedDocs) {
                        message.referencedDocs = referencedDocs.outerHTML;
                    }
                    console.log("💾 Message saved:", message);
                    KachiCore.updateCurrentConversation();
                    KachiUI.renderConversationList(false);
                }
                
                // 입력창에 포커스 주기
                setTimeout(() => {
                    const $queryInput = $('#queryInput');
                    if ($queryInput.length && !$queryInput.is(':disabled')) {
                        $queryInput.focus();
                        console.log("✅ Focus set to query input");
                    }
                }, 100);
            } catch (err) {
                const isAbort = err.name === 'AbortError';
                if (isAbort) {
                    console.log("✅ Stream stopped by user");
                    const textElement = messageElement.querySelector('.message-text');
                    if (textElement) {
                        const stoppedMsg = window.kachi_ajax?.strings?.stopped || "사용자에 의해 중지되었습니다.";
                        textElement.innerHTML += `<div style="margin-top:10px; color:#a70638;">■ ${stoppedMsg}</div>`;
                        
                        // 중지된 경우에도 현재까지의 내용을 저장 (원본 URL 유지)
                        const rawPartialContent = this.fixImgTags(KachiCore.streamBuffer);
                        const storagePartialContent = this.processImageUrlsForStorage(rawPartialContent);
                        const finalPartialContent = this.cleanMathJaxContent(storagePartialContent);
                        
                        const message = KachiCore.findMessage(messageId);
                        if (message) {
                            // 저장용 콘텐츠는 원본 URL 유지 (중지된 경우)
                            message.content = finalPartialContent;
                            console.log("💾 Saving partial content with original URLs for LLM compatibility");
                            const referencedDocs = messageElement.querySelector('.referenced-docs');
                            if (referencedDocs) {
                                message.referencedDocs = referencedDocs.outerHTML;
                            }
                            console.log("💾 Partial message saved (stopped):", message);
                            KachiCore.updateCurrentConversation();
                            KachiUI.renderConversationList(false);
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
        
        // MathJax 렌더링된 내용 정리
        cleanMathJaxContent: function(html) {
            // 임시 요소 생성
            const temp = document.createElement('div');
            temp.innerHTML = html;
            
            // MathJax가 렌더링한 요소들 제거
            const mjxElements = temp.querySelectorAll('mjx-container, .MathJax, .MathJax_Display, .MathJax_Preview, .MathJax_CHTML');
            mjxElements.forEach(el => el.remove());
            
            // MathJax 처리 마커 제거
            const scriptElements = temp.querySelectorAll('script[type*="math/tex"]');
            scriptElements.forEach(el => el.remove());
            
            // MathJax data 속성 제거
            const elementsWithData = temp.querySelectorAll('[data-mjx-texclass]');
            elementsWithData.forEach(el => {
                el.removeAttribute('data-mjx-texclass');
            });
            
            return temp.innerHTML;
        },
        
        // 쿼리 문서 정보 가져오기 - 프록시 방식
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
        
        // 완성된 이미지 URL 감지 및 추출
        detectCompleteImages: function(text, processedImageUrls = new Set()) {
            const completeImages = [];
            const lines = text.split('\n');
            
            lines.forEach((line, lineIndex) => {
                // 이미 img 태그가 있는 줄은 건너뛰기
                if (line.includes('<img')) {
                    return;
                }
                
                // 마크다운 스타일 이미지 URL 패턴: something](http://host:8001/images/file)
                const markdownImagePattern = /.*\]\((https?:\/\/[^\)]*)(?:8001\/images\/[^\)\]]+)\)/;
                const markdownMatch = line.match(markdownImagePattern);
                
                if (markdownMatch) {
                    let originalImageUrl = markdownMatch[1] + (markdownMatch[0].match(/:8001\/images\/[^\)\]]+/) || [''])[0];
                    originalImageUrl = this.cleanImageUrl(originalImageUrl);
                    
                    // 이미 처리된 이미지가 아닌 경우에만 추가
                    if (!processedImageUrls.has(originalImageUrl)) {
                        completeImages.push({
                            lineIndex,
                            originalUrl: originalImageUrl,
                            type: 'markdown'
                        });
                        processedImageUrls.add(originalImageUrl);
                    }
                    return;
                }
                
                // 일반적인 이미지 URL 패턴 (http://host:8001/images/file)
                const normalImagePattern = /https?:\/\/[^:\s]+:8001\/images\/[^\s\)\]]+/;
                const normalMatch = line.match(normalImagePattern);
                
                if (normalMatch) {
                    let originalImageUrl = normalMatch[0];
                    originalImageUrl = this.cleanImageUrl(originalImageUrl);
                    
                    // 이미 처리된 이미지가 아닌 경우에만 추가
                    if (!processedImageUrls.has(originalImageUrl)) {
                        completeImages.push({
                            lineIndex,
                            originalUrl: originalImageUrl,
                            type: 'normal'
                        });
                        processedImageUrls.add(originalImageUrl);
                    }
                }
            });
            
            return completeImages;
        },
        
        // 실시간 이미지 처리 - 완성된 이미지만 처리
        processImagesRealtime: function(text, processedImageUrls) {
            const completeImages = this.detectCompleteImages(text, processedImageUrls);
            
            if (completeImages.length === 0) {
                return text; // 새로운 완성된 이미지가 없으면 원본 반환
            }
            
            console.log('🖼️ Found', completeImages.length, 'new complete images for real-time processing');
            
            // 줄 단위로 분리하여 처리
            const lines = text.split('\n');
            
            completeImages.forEach(imageInfo => {
                const { lineIndex, originalUrl } = imageInfo;
                
                if (lines[lineIndex]) {
                    // 프록시 URL로 변환
                    const proxyUrl = this.convertToProxyImageUrl(originalUrl);
                    
                    // 해당 줄을 이미지 태그로 교체
                    const imageTag = `<img src="${proxyUrl}" alt="이미지" style="max-width: 100%; height: auto; display: block; margin: 10px 0;" data-original-url="${originalUrl}">`;
                    lines[lineIndex] = imageTag;
                    
                    console.log('🖼️ Real-time processed image:', originalUrl);
                }
            });
            
            return lines.join('\n');
        },

        // 스트림 버퍼 플러시 - 실시간 이미지 렌더링 개선
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
                // 남은 이미지들을 실시간 처리한 후 최종 포맷팅
                let processedContent = this.processImagesRealtime(KachiCore.streamBuffer, messageElement._processedImageUrls);
                const formattedContent = this.formatResponse(processedContent);
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
                
                // 타이핑 효과를 위한 함수 - 실시간 이미지 처리 포함
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
                        
                        // 실시간 이미지 처리 - 완성된 이미지만 처리
                        let processedText = this.processImagesRealtime(displayText, messageElement._processedImageUrls);
                        
                        // 이미지 태그 완성도 검사 (처리된 텍스트 기준)
                        let safeDisplayText = processedText;
                        const lastImgStart = processedText.lastIndexOf('<img');
                        const lastImgEnd = processedText.lastIndexOf('>');
                        
                        // 미완성 이미지 태그가 있으면 해당 부분을 제외
                        if (lastImgStart !== -1 && (lastImgEnd === -1 || lastImgEnd < lastImgStart)) {
                            // 이미지 URL 패턴이 포함된 줄인지 확인
                            const beforeImg = processedText.substring(0, lastImgStart);
                            const afterImgStart = processedText.substring(lastImgStart);
                            
                            // 현재 줄에 아직 처리되지 않은 이미지 URL 패턴이 있는지 확인
                            if (/https?:\/\/[^\s\)]+:8001\/images\/[^\s\)]+/.test(beforeImg + afterImgStart.split('\n')[0])) {
                                // 완전한 이미지 URL이 있으면 해당 줄 전체를 기다림
                                const lastNewlineIndex = beforeImg.lastIndexOf('\n');
                                safeDisplayText = lastNewlineIndex !== -1 ? beforeImg.substring(0, lastNewlineIndex + 1) : '';
                            } else {
                                // 단순히 미완성 태그만 제외
                                safeDisplayText = beforeImg;
                            }
                        }
                        
                        // 안전한 텍스트로 표시 (추가적인 이미지 처리는 formatResponseWithoutImages에서 제외)
                        if (safeDisplayText.length > 0) {
                            // 실시간으로 처리된 이미지들은 이미 <img> 태그로 변환되었으므로,
                            // formatResponseWithoutImages를 사용하되 이미 처리된 이미지는 유지
                            const formattedText = this.formatResponsePreservingImages(safeDisplayText);
                            textElement.innerHTML = formattedText;
                            
                            // 표시된 길이 업데이트는 원본 텍스트 기준
                            if (safeDisplayText.replace(/<img[^>]*>/g, '').length >= displayText.replace(/<img[^>]*>/g, '').length) {
                                KachiCore.displayedLength += charsToAdd;
                            }
                        }
                        
                        // 수식이 감지되면 즉시 렌더링
                        if (mathDetected) {
                            this.renderMathInElement(messageElement);
                            mathDetected = false;
                        }
                        
                        // 다음 글자 표시를 위한 타이머
                        KachiCore.typeTimer = setTimeout(typeNextChars, 30);
                    } else {
                        // 모든 글자를 표시했으면 실시간 처리 후 최종 포맷팅
                        let processedContent = this.processImagesRealtime(KachiCore.streamBuffer, messageElement._processedImageUrls);
                        const finalContent = this.formatResponse(processedContent);
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
        formatResponsePreservingImages: function(text) {
            // 이미 처리된 이미지 태그를 임시로 보호
            const imagePlaceholders = {};
            let imageCounter = 0;
            
            text = text.replace(/<img[^>]*>/g, function(match) {
                const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
                imagePlaceholders[placeholder] = match;
                return placeholder;
            });
            
            // 기존 포맷팅 로직 적용 (이미지 처리 제외)
            const formatted = this.formatResponseWithoutImages(text);
            
            // 이미지 플레이스홀더를 원래 태그로 복원
            let result = formatted;
            Object.keys(imagePlaceholders).forEach(placeholder => {
                result = result.replace(new RegExp(placeholder, 'g'), imagePlaceholders[placeholder]);
            });
            
            return result;
        },
        
        // 답변 포맷팅 (이미지 처리 제외) - 스트리밍 중 사용
        formatResponseWithoutImages: function(text) {
            // plaintext와 html 코드 블록 문법 제거
            text = text.replace(/```plaintext\s*([\s\S]*?)```/g, '$1');
            text = text.replace(/```html\s*([\s\S]*?)```/g, '$1');
            
            // LaTeX 수식 보호를 위한 플레이스홀더 처리
            const mathPlaceholders = {};
            let mathCounter = 0;
            
            // 블록 수식 (\[...\]) 보호
            text = text.replace(/\\\[([\s\S]*?)\\\]/g, function(match, equation) {
                const placeholder = `__MATH_BLOCK_${mathCounter++}__`;
                mathPlaceholders[placeholder] = `<div class="math-block">\\[${equation}\\]</div>`;
                return placeholder;
            });
            
            // 인라인 수식 (\(...\)) 보호
            text = text.replace(/\\\(([\s\S]*?)\\\)/g, function(match, equation) {
                const placeholder = `__MATH_INLINE_${mathCounter++}__`;
                mathPlaceholders[placeholder] = `<span class="math-inline">\\(${equation}\\)</span>`;
                return placeholder;
            });
            
            // 이미지 URL은 처리하지 않음 (스트리밍 중 깜빡임 방지)
            
            // --- 수평선을 <hr>로 변환 (독립된 줄에 있는 경우)
            text = text.replace(/^---+$/gm, '<hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">');
            
            // # 헤딩을 <h2>로 변환 (줄 시작에 있는 경우만)
            text = text.replace(/^#\s+(.+)$/gm, '<h2 style="margin-top: 28px; margin-bottom: 16px; color: #1a1a1a; font-size: 1.8em; font-weight: 700; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px;">$1</h2>');
            
            // ## 헤딩을 <h3>로 변환 (줄 시작에 있는 경우만)
            text = text.replace(/^##\s+(.+)$/gm, '<h3 style="margin-top: 24px; margin-bottom: 12px; color: #2d2d2d; font-size: 1.4em; font-weight: 600;">$1</h3>');
            
            // ### 헤딩을 <h4>로 변환 (줄 시작에 있는 경우만)
            text = text.replace(/^###\s+(.+)$/gm, '<h4 style="margin-top: 20px; margin-bottom: 10px; color: #333; font-size: 1.1em; font-weight: 600;">$1</h4>');
            
            // #### 헤딩을 <h5>로 변환 (줄 시작에 있는 경우만)
            text = text.replace(/^####\s+(.+)$/gm, '<h5 style="margin-top: 16px; margin-bottom: 8px; color: #333; font-size: 1em; font-weight: 600;">$1</h5>');
            
            // `code` 패턴을 <code>code</code>로 변환 (백틱 처리 - 3단어 이하만)
            text = text.replace(/`([^`]+)`/g, function(match, code) {
                // 공백으로 분리하여 단어 수 계산
                const wordCount = code.trim().split(/\s+/).length;
                
                // 3단어 이하인 경우만 코드 스타일 적용
                if (wordCount <= 3) {
                    return '<code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: \'Consolas\', \'Monaco\', \'Courier New\', monospace; font-size: 0.9em; color: #d73a49;">' + code + '</code>';
                } else {
                    // 3단어 초과인 경우 백틱을 그대로 유지
                    return '`' + code + '`';
                }
            });
            
            // **text** 패턴을 <strong>text</strong>으로 변환
            text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            
            // *text* 패턴도 <strong>text</strong>으로 변환 (single asterisk)
            text = text.replace(/(?<!\*)\*(?!\*)([^*]+)(?<!\*)\*(?!\*)/g, '<strong>$1</strong>');
            
            // 숫자로 시작하는 리스트 항목 처리 - <br> 태그가 이미 없는 경우에만 추가
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n(\d{1,2}\.\s)/gm, '<br>$1');
            
            // 불릿 포인트 처리 - <br> 태그가 이미 없는 경우에만 추가
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n([-•▪]\s)/gm, '<br>$1');
            
            // ▶ 기호 처리 - <br> 태그가 이미 없는 경우에만 추가
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n(▶\s)/gm, '<br>$1');
            
            // 줄바꿈을 <br>로 변환
            text = text.replace(/\n/g, '<br>');
            
            // 연속된 <br> 정리
            text = text.replace(/(<br>){3,}/g, '<br><br>');
            
            // h2, h3, h4, h5 태그 주변의 불필요한 <br> 제거
            text = text.replace(/<\/h([2345])>(<br>)+/g, '</h$1>');
            text = text.replace(/(<br>)+<h([2345])/g, '<h$2');
            
            // hr 태그 주변의 불필요한 <br> 제거
            text = text.replace(/<hr([^>]*)>(<br>)+/g, '<hr$1>');
            text = text.replace(/(<br>)+<hr/g, '<hr');
            
            // 문서 시작 부분의 <br> 제거
            text = text.replace(/^(<br>)+/, '');
            
            // 수식 플레이스홀더를 원래 수식으로 복원
            Object.keys(mathPlaceholders).forEach(placeholder => {
                text = text.replace(new RegExp(placeholder, 'g'), mathPlaceholders[placeholder]);
            });
            
            return text;
        },
        
        // 답변 포맷팅
        formatResponse: function(text) {
            console.log("Formatting text:", text.substring(0, 100) + "...");
            
            // plaintext와 html 코드 블록 문법 제거
            text = text.replace(/```plaintext\s*([\s\S]*?)```/g, '$1');
            text = text.replace(/```html\s*([\s\S]*?)```/g, '$1');
            
            // LaTeX 수식 보호를 위한 플레이스홀더 처리
            const mathPlaceholders = {};
            let mathCounter = 0;
            
            // 블록 수식 (\[...\]) 보호
            text = text.replace(/\\\[([\s\S]*?)\\\]/g, function(match, equation) {
                const placeholder = `__MATH_BLOCK_${mathCounter++}__`;
                mathPlaceholders[placeholder] = `<div class="math-block">\\[${equation}\\]</div>`;
                return placeholder;
            });
            
            // 인라인 수식 (\(...\)) 보호
            text = text.replace(/\\\(([\s\S]*?)\\\)/g, function(match, equation) {
                const placeholder = `__MATH_INLINE_${mathCounter++}__`;
                mathPlaceholders[placeholder] = `<span class="math-inline">\\(${equation}\\)</span>`;
                return placeholder;
            });
            
            // 이미지 URL 패턴 처리 (모든 이미지 형식) - 표시용으로만 행 전체 처리
            text = this.processImageUrlsForDisplay(text);
            
            // --- 수평선을 <hr>로 변환 (독립된 줄에 있는 경우)
            text = text.replace(/^---+$/gm, '<hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">');
            
            // # 헤딩을 <h2>로 변환 (줄 시작에 있는 경우만)
            text = text.replace(/^#\s+(.+)$/gm, '<h2 style="margin-top: 28px; margin-bottom: 16px; color: #1a1a1a; font-size: 1.8em; font-weight: 700; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px;">$1</h2>');
            
            // ## 헤딩을 <h3>로 변환 (줄 시작에 있는 경우만)
            text = text.replace(/^##\s+(.+)$/gm, '<h3 style="margin-top: 24px; margin-bottom: 12px; color: #2d2d2d; font-size: 1.4em; font-weight: 600;">$1</h3>');
            
            // ### 헤딩을 <h4>로 변환 (줄 시작에 있는 경우만)
            text = text.replace(/^###\s+(.+)$/gm, '<h4 style="margin-top: 20px; margin-bottom: 10px; color: #333; font-size: 1.1em; font-weight: 600;">$1</h4>');
            
            // #### 헤딩을 <h5>로 변환 (줄 시작에 있는 경우만)
            text = text.replace(/^####\s+(.+)$/gm, '<h5 style="margin-top: 16px; margin-bottom: 8px; color: #333; font-size: 1em; font-weight: 600;">$1</h5>');
            
            // `code` 패턴을 <code>code</code>로 변환 (백틱 처리 - 3단어 이하만)
            text = text.replace(/`([^`]+)`/g, function(match, code) {
                // 공백으로 분리하여 단어 수 계산
                const wordCount = code.trim().split(/\s+/).length;
                
                // 3단어 이하인 경우만 코드 스타일 적용
                if (wordCount <= 3) {
                    return '<code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: \'Consolas\', \'Monaco\', \'Courier New\', monospace; font-size: 0.9em; color: #d73a49;">' + code + '</code>';
                } else {
                    // 3단어 초과인 경우 백틱을 그대로 유지
                    return '`' + code + '`';
                }
            });
            
            // **text** 패턴을 <strong>text</strong>으로 변환
            text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            
            // *text* 패턴도 <strong>text</strong>으로 변환 (single asterisk)
            text = text.replace(/(?<!\*)\*(?!\*)([^*]+)(?<!\*)\*(?!\*)/g, '<strong>$1</strong>');
            
            // 숫자로 시작하는 리스트 항목 처리 - <br> 태그가 이미 없는 경우에만 추가
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n(\d{1,2}\.\s)/gm, '<br>$1');
            
            // 불릿 포인트 처리 - <br> 태그가 이미 없는 경우에만 추가
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n([-•▪]\s)/gm, '<br>$1');
            
            // ▶ 기호 처리 - <br> 태그가 이미 없는 경우에만 추가
            text = text.replace(/(?<!<br>)(?<!<br\/>)(?<!<br\s*\/>)\n(▶\s)/gm, '<br>$1');
            
            // 줄바꿈을 <br>로 변환 (테이블 내부는 제외)
            // 테이블을 임시로 보호
            const tablePlaceholders = {};
            let tableCounter = 0;
            text = text.replace(/<table[\s\S]*?<\/table>/gi, function(match) {
                const placeholder = `__TABLE_${tableCounter++}__`;
                tablePlaceholders[placeholder] = match;
                return placeholder;
            });
            
            // 줄바꿈을 <br>로 변환
            text = text.replace(/\n/g, '<br>');
            
            // 테이블 복원
            Object.keys(tablePlaceholders).forEach(placeholder => {
                text = text.replace(placeholder, tablePlaceholders[placeholder]);
            });
            
            // 연속된 <br> 정리
            text = text.replace(/(<br>){3,}/g, '<br><br>');
            
            // h2, h3, h4, h5 태그 주변의 불필요한 <br> 제거
            text = text.replace(/<\/h([2345])>(<br>)+/g, '</h$1>');
            text = text.replace(/(<br>)+<h([2345])/g, '<h$2');
            
            // hr 태그 주변의 불필요한 <br> 제거
            text = text.replace(/<hr([^>]*)>(<br>)+/g, '<hr$1>');
            text = text.replace(/(<br>)+<hr/g, '<hr');
            
            // 테이블 태그 주변의 불필요한 <br> 제거
            text = text.replace(/(<br>\s*)+(<table)/gi, '$2');
            text = text.replace(/(<\/table>)\s*(<br>\s*)+/gi, '$1');
            
            // 테이블 내부 요소들 주변의 <br> 제거
            text = text.replace(/(<br>\s*)+(<tr|<td|<th|<thead|<tbody|<tfoot)/gi, '$2');
            text = text.replace(/(<\/tr>|<\/td>|<\/th>|<\/thead>|<\/tbody>|<\/tfoot>)\s*(<br>\s*)+/gi, '$1');
            
            // 문서 시작 부분의 <br> 제거
            text = text.replace(/^(<br>)+/, '');
            
            // 수식 플레이스홀더를 원래 수식으로 복원
            Object.keys(mathPlaceholders).forEach(placeholder => {
                text = text.replace(new RegExp(placeholder, 'g'), mathPlaceholders[placeholder]);
            });
            
            return text;
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
                console.log("✅ MathJax rendering completed");
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
            
            return url;
        },
        
        // 이미지 URL을 프록시 URL로 변환 - 마크다운 처리 개선
        convertToProxyImageUrl: function(imageUrl) {
            // 이미 프록시 URL인 경우 그대로 반환
            if (imageUrl.includes('action=kachi_proxy_image')) {
                console.log("🖼️ Already proxy URL, skipping conversion:", imageUrl);
                return imageUrl;
            }
            
            // 빈 URL이거나 data: URL인 경우 그대로 반환
            if (!imageUrl || imageUrl.startsWith('data:')) {
                return imageUrl;
            }
            
            // URL 정리 (마크다운 문법 제거)
            const cleanUrl = this.cleanImageUrl(imageUrl);
            
            // 정리된 URL이 유효한 http(s) URL인지 확인
            if (!cleanUrl.startsWith('http://') && !cleanUrl.startsWith('https://')) {
                console.warn("⚠️ Invalid URL after cleaning:", cleanUrl);
                return imageUrl;
            }
            
            // API 서버의 이미지 URL 패턴 확인
            const apiPattern = /:8001\/images\/([^?\s)]+)/;
            const match = cleanUrl.match(apiPattern);
            
            if (match && match[1]) {
                // 프록시 URL로 변환
                const imagePath = match[1];
                const proxyUrl = window.kachi_ajax?.ajax_url + 
                    '?action=kachi_proxy_image&path=' + encodeURIComponent(imagePath);
                console.log("🖼️ Converting to proxy URL:", cleanUrl, "->", proxyUrl);
                return proxyUrl;
            }
            
            // 매치하지 않으면 정리된 URL 반환
            console.warn("⚠️ No API pattern match for:", cleanUrl);
            return cleanUrl;
        },
        
        // 이미지 URL 처리 함수 - 마크다운 링크 처리 개선
        processImageUrlsForDisplay: function(text) {
            // 이미 처리된 이미지는 다시 처리하지 않음
            if (text.includes('<img') && text.includes('action=kachi_proxy_image')) {
                console.log("🖼️ Images already processed for display, skipping");
                return text;
            }
            
            // 줄 단위로 처리
            const lines = text.split('\n');
            const processedLines = [];
            
            lines.forEach(line => {
                // 이미 img 태그가 있는 줄은 건너뛰기
                if (line.includes('<img')) {
                    processedLines.push(line);
                    return;
                }
                
                // 마크다운 스타일 이미지 URL 패턴 우선 확인: something](http://host:8001/images/file)
                const markdownImagePattern = /.*\]\((https?:\/\/[^)]*:8001\/images\/[^)\]]+)\)/;
                const markdownMatch = line.match(markdownImagePattern);
                
                if (markdownMatch) {
                    let originalImageUrl = markdownMatch[1];
                    console.log("🖼️ Found markdown image URL:", originalImageUrl);
                    
                    // URL 정리 (끝의 ']' 문자 제거 등)
                    originalImageUrl = this.cleanImageUrl(originalImageUrl);
                    
                    // 프록시 URL로 변환
                    const proxyUrl = this.convertToProxyImageUrl(originalImageUrl);
                    
                    // 전체 줄을 이미지 태그로 교체
                    const imageTag = `<img src="${proxyUrl}" alt="이미지" style="max-width: 100%; height: auto; display: block; margin: 10px 0;" data-original-url="${originalImageUrl}">`;
                    processedLines.push(imageTag);
                    return;
                }
                
                // 일반적인 이미지 URL 패턴 (http://host:8001/images/file) - 끝의 ']' 제외
                const normalImagePattern = /https?:\/\/[^:\s]+:8001\/images\/[^\s)\]]+/;
                const normalMatch = line.match(normalImagePattern);
                
                if (normalMatch) {
                    let originalImageUrl = normalMatch[0];
                    console.log("🖼️ Found normal image URL:", originalImageUrl);
                    
                    // URL 정리 (끝의 ']' 문자 제거 등)
                    originalImageUrl = this.cleanImageUrl(originalImageUrl);
                    
                    // 프록시 URL로 변환
                    const proxyUrl = this.convertToProxyImageUrl(originalImageUrl);
                    
                    // 전체 줄을 이미지 태그로 교체
                    const imageTag = `<img src="${proxyUrl}" alt="이미지" style="max-width: 100%; height: auto; display: block; margin: 10px 0;" data-original-url="${originalImageUrl}">`;
                    processedLines.push(imageTag);
                    return;
                }
                
                // 이미지 URL이 없으면 원래 줄 그대로 유지
                processedLines.push(line);
            });
            
            return processedLines.join('\n');
        },

        // 저장용 이미지 URL 처리 - 원본 URL 유지
        processImageUrlsForStorage: function(text) {
            // 줄 단위로 분리
            const lines = text.split('\n');
            const processedLines = [];
            
            // 이미지 URL 패턴 (backend API URLs) - 끝의 ']' 제외
            const imageUrlPattern = /https?:\/\/[^\s\)\]]+:8001\/images\/[^\s\)\]]+/;
            
            lines.forEach(line => {
                // 현재 줄에 이미지 URL이 포함되어 있는지 확인
                if (imageUrlPattern.test(line)) {
                    // 이미지 URL 추출
                    const match = line.match(imageUrlPattern);
                    if (match) {
                        let originalImageUrl = match[0];
                        console.log("💾 Found image URL for storage:", originalImageUrl);
                        
                        // URL 정리 (끝의 ']' 문자 제거 등)
                        originalImageUrl = this.cleanImageUrl(originalImageUrl);
                        
                        console.log("💾 Keeping cleaned original image URL for storage:", originalImageUrl);
                        
                        // 원본 URL로 이미지 태그 생성 (저장용)
                        processedLines.push(`<img src="${originalImageUrl}" alt="이미지" style="max-width: 100%; height: auto; display: block; margin: 10px 0;">`);
                    } else {
                        processedLines.push(line);
                    }
                } else {
                    processedLines.push(line);
                }
            });
            
            return processedLines.join('\n');
        },
        
        // 이미지 태그 수정 (프록시 URL 적용) - 개선된 버전
        fixImgTags: function(htmlStr) {
            // 이미 프록시 URL로 처리된 이미지가 있는지 확인
            if (htmlStr.includes('action=kachi_proxy_image')) {
                console.log("🖼️ Image tags already processed with proxy URLs, skipping fixImgTags");
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
            htmlStr = htmlStr.replace(/<img\s+([^>]*?)src="([^"]+)"([^>]*?)>/g, function(match, before, src, after) {
                // 이미 프록시 URL이거나 데이터 URL이면 건너뛰기
                if (src.includes('action=kachi_proxy_image') || src.startsWith('data:')) {
                    return match;
                }
                
                // API 서버 이미지 URL인 경우만 변환
                if (src.includes(':8001/images/')) {
                    const proxySrc = self.convertToProxyImageUrl(src);
                    return '<img ' + before + 'src="' + proxySrc + '"' + after + '>';
                }
                
                return match;
            });
            
            return htmlStr;
        }
    };
    
})(window, document, jQuery);