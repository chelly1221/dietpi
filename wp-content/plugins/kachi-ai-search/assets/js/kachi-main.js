// 까치 쿼리 시스템 - 메인 진입점
(function(window, document) {
    'use strict';
    
    // jQuery 대기 및 초기화
    function waitForJQuery() {
        if (typeof jQuery === 'undefined') {
            console.error('❌ jQuery is not loaded! Waiting...');
            var checkJqueryInterval = setInterval(function() {
                if (typeof jQuery !== 'undefined') {
                    clearInterval(checkJqueryInterval);
                    initializeKachi(jQuery);
                }
            }, 100);
        } else {
            // jQuery가 이미 로드된 경우
            jQuery(document).ready(function() {
                initializeKachi(jQuery);
            });
        }
    }
    
    // 까치 시스템 초기화
    function initializeKachi($) {
        console.log('✅ jQuery loaded, initializing Kachi Query System...');
        
        // 모든 모듈이 로드되었는지 확인
        if (typeof window.KachiCore === 'undefined') {
            console.error('❌ KachiCore is not loaded!');
            return;
        }
        
        if (typeof window.KachiUI === 'undefined') {
            console.error('❌ KachiUI is not loaded!');
            return;
        }
        
        if (typeof window.KachiAPI === 'undefined') {
            console.error('❌ KachiAPI is not loaded!');
            return;
        }
        
        // 페이지에 까치 쿼리가 있는지 확인
        const kachiWrapper = document.querySelector('.kachi-query-wrapper');
        if (!kachiWrapper) {
            console.warn('⚠️ Kachi query wrapper not found on this page');
            return;
        }
        
        // 설정 데이터 가져오기
        const configData = kachiWrapper.dataset.kachiConfig;
        if (configData) {
            try {
                const config = JSON.parse(configData);
                window.isUserLoggedIn = config.is_logged_in;
                window.kachiApiUrl = config.api_url;
                window.userSosok = config.user_sosok;
                window.userSite = config.user_site;
                if (config.is_logged_in && config.facility_definitions) {
                    window.facilityDefinitionsFromPHP = config.facility_definitions;
                }
            } catch (e) {
                console.error('Kachi config parse error:', e);
            }
        }
        
        // 전체화면 모드 처리
        if (kachiWrapper.classList.contains('fullpage-mode')) {
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
        }
        
        // 스크롤 최상단으로
        window.scrollTo(0, 0);
        
        // 모듈 초기화
        console.log("✅ Starting Kachi modules initialization...");
        
        // 1. Core 모듈 초기화 (상태 관리)
        KachiCore.init();
        
        // 2. UI 모듈 초기화 (화면 렌더링)
        KachiUI.init();
        
        // 3. API 모듈 초기화 (데이터 통신)
        KachiAPI.init();
        
        // 전역 함수 노출 (레거시 호환성)
        window.KachiQuery = {
            // Core 메서드 노출
            selectedTags: KachiCore.selectedTags,
            selectedDocs: KachiCore.selectedDocs,
            isQueryInProgress: KachiCore.isQueryInProgress,
            
            // UI 메서드 노출
            toggleDropdown: KachiUI.toggleDropdown.bind(KachiUI),
            filterOptions: KachiUI.filterOptions.bind(KachiUI),
            startNewChat: KachiUI.startNewChat.bind(KachiUI),
            
            // API 메서드 노출
            sendQuery: KachiAPI.sendQuery.bind(KachiAPI),
            stopQuery: function() {
                KachiCore.stopQuery();
                KachiUI.resetQueryUI();
            }
        };
        
        console.log("✅ Kachi Query System fully initialized!");
    }
    
    // 시작
    waitForJQuery();
    
})(window, document);