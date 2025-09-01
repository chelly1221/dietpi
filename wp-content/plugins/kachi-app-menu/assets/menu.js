// Google Themed Menu JavaScript
(function($) {
    'use strict';
    
    let isAnimating = false;
    
    // 메뉴 토글 함수
    window.toggleAppMenu = function() {
        if (isAnimating) return;
        
        const wrapper = document.getElementById('gtmMenuWrapper');
        const isExpanded = wrapper.classList.contains('expanded');
        
        if (isExpanded) {
            closeAppMenu();
        } else {
            openAppMenu();
        }
    };
    
    // 메뉴 열기
    window.openAppMenu = function() {
        if (isAnimating) return;
        isAnimating = true;
        
        const wrapper = document.getElementById('gtmMenuWrapper');
        wrapper.classList.add('expanded');
        
        // 메뉴 아이템 순차적 애니메이션
        const items = wrapper.querySelectorAll('.gtm-menu-item');
        items.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                item.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, 50 + (index * 30));
        });
        
        setTimeout(() => {
            isAnimating = false;
        }, 400);
    };
    
    // 메뉴 닫기
    window.closeAppMenu = function() {
        if (isAnimating) return;
        isAnimating = true;
        
        const wrapper = document.getElementById('gtmMenuWrapper');
        
        // 메뉴 아이템 역순 애니메이션
        const items = wrapper.querySelectorAll('.gtm-menu-item');
        const itemsArray = Array.from(items).reverse();
        
        itemsArray.forEach((item, index) => {
            setTimeout(() => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
            }, index * 20);
        });
        
        setTimeout(() => {
            wrapper.classList.remove('expanded');
            
            // 애니메이션 리셋
            items.forEach(item => {
                item.style.opacity = '';
                item.style.transform = '';
                item.style.transition = '';
            });
            
            isAnimating = false;
        }, 300);
    };
    
    // 화이트모드/다크모드 적용 함수
    function applyThemeMode() {
        if (typeof gtm_data === 'undefined') {
            console.log('GTM Debug - gtm_data is undefined');
            return;
        }
        
        const whiteModePages = gtm_data.white_mode_pages || [];
        const currentPageId = gtm_data.current_page_id;
        const isFrontPage = gtm_data.is_front_page;
        
        console.log('GTM Debug - White mode pages:', whiteModePages);
        console.log('GTM Debug - Current page ID:', currentPageId);
        console.log('GTM Debug - Is front page:', isFrontPage);
        
        // 화이트모드 페이지인지 확인
        let isWhiteMode = false;
        
        // 홈페이지 확인
        if (isFrontPage && whiteModePages.includes('front_page')) {
            isWhiteMode = true;
            console.log('GTM Debug - Front page is in white mode');
        } 
        // 일반 페이지 확인 - 문자열과 숫자 모두 확인
        else if (currentPageId) {
            if (whiteModePages.includes(currentPageId.toString()) || whiteModePages.includes(currentPageId)) {
                isWhiteMode = true;
                console.log('GTM Debug - Current page is in white mode');
            }
        }
        
        // 기존 테마 클래스 제거
        document.documentElement.classList.remove('gtm-dark-theme', 'gtm-white-theme');
        
        // 테마 클래스 적용
        if (isWhiteMode) {
            document.documentElement.classList.add('gtm-white-theme');
            console.log('GTM Debug - Applied white theme');
        } else {
            document.documentElement.classList.add('gtm-dark-theme');
            console.log('GTM Debug - Applied dark theme (default)');
        }
    }
    
    // DOM 로드 완료 시
    $(document).ready(function() {
        // 테마 모드 적용
        applyThemeMode();
        
        // 사용자 역할에 따른 메뉴 표시/숨김
        updateMenuVisibility();
        
        // 메뉴 외부 클릭 시 닫기
        $(document).on('click', function(e) {
            const wrapper = $('#gtmMenuWrapper');
            
            if (!wrapper.is(e.target) && wrapper.has(e.target).length === 0) {
                if (wrapper.hasClass('expanded')) {
                    closeAppMenu();
                }
            }
        });
        
        // ESC 키로 메뉴 닫기
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // ESC key
                const wrapper = $('#gtmMenuWrapper');
                if (wrapper.hasClass('expanded')) {
                    closeAppMenu();
                }
            }
        });
        
        // 메뉴 아이템 클릭 시 부드럽게 닫기
        $('.gtm-menu-item').on('click', function(e) {
            e.preventDefault();
            
            const $this = $(this);
            const targetUrl = $this.attr('href');
            
            // 클릭 효과
            $this.css('transform', 'scale(0.95)');
            
            setTimeout(() => {
                closeAppMenu();
                
                // 페이지 이동
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 200);
            }, 100);
        });
        
        // 인증 버튼 클릭 처리 (로그인/로그아웃)
        $('.gtm-auth-link').on('click', function(e) {
            e.preventDefault();
            
            const $this = $(this);
            const targetUrl = $this.attr('href');
            
            // 클릭 효과
            $this.css('transform', 'scale(0.9)');
            
            setTimeout(() => {
                closeAppMenu();
                
                // 페이지 이동
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 200);
            }, 100);
        });
        
        // 트리거 버튼 호버 효과 강화
        $('.gtm-menu-trigger').on('mouseenter', function() {
            // 호버 효과는 CSS에서 처리
        }).on('mouseleave', function() {
            // 호버 효과는 CSS에서 처리
        });
        
        // 터치 디바이스 최적화
        if ('ontouchstart' in window) {
            $('.gtm-menu-trigger').on('touchstart', function(e) {
                $(this).addClass('touch-active');
            }).on('touchend', function(e) {
                $(this).removeClass('touch-active');
            });
            
            // 터치 디바이스용 추가 스타일
            $('<style>')
                .prop('type', 'text/css')
                .html(`
                    .gtm-menu-trigger.touch-active .gtm-icon-wrapper {
                        transform: scale(0.95) !important;
                    }
                    
                    .gtm-menu-item:active {
                        background: rgba(255, 255, 255, 0.1) !important;
                    }
                    
                    .gtm-auth-link:active {
                        transform: scale(0.85) !important;
                    }
                `)
                .appendTo('head');
        }
        
        // 키보드 네비게이션
        let currentFocus = -1;
        
        $(document).on('keydown', function(e) {
            const wrapper = $('#gtmMenuWrapper');
            if (!wrapper.hasClass('expanded')) return;
            
            const items = wrapper.find('.gtm-menu-item:visible');
            
            if (e.keyCode === 40) { // Arrow Down
                e.preventDefault();
                currentFocus++;
                if (currentFocus >= items.length) currentFocus = 0;
                items.eq(currentFocus).focus();
            } else if (e.keyCode === 38) { // Arrow Up
                e.preventDefault();
                currentFocus--;
                if (currentFocus < 0) currentFocus = items.length - 1;
                items.eq(currentFocus).focus();
            } else if (e.keyCode === 13) { // Enter
                if (currentFocus >= 0 && currentFocus < items.length) {
                    items.eq(currentFocus).click();
                }
            }
        });
        
        // AJAX로 메뉴 동적 업데이트 (필요시)
        if (window.gtm_update_menu) {
            updateMenuItems();
        }
    });
    
    // 사용자 권한에 따른 메뉴 가시성 업데이트
    function updateMenuVisibility() {
        if (!gtm_data.menu_permissions) return;
        
        const userRoles = gtm_data.user_roles || [];
        const isLoggedIn = gtm_data.is_user_logged_in;
        
        // 각 메뉴 아이템 확인
        $('.gtm-menu-item[data-menu-id]').each(function() {
            const $item = $(this);
            const menuId = $item.data('menu-id');
            const permissions = gtm_data.menu_permissions[menuId] || ['all'];
            let canSee = false;
            
            // 권한 확인
            if (permissions.includes('guest')) {
                canSee = true;
            } else if (permissions.includes('all') && isLoggedIn) {
                canSee = true;
            } else if (isLoggedIn) {
                userRoles.forEach(function(role) {
                    if (permissions.includes(role)) {
                        canSee = true;
                    }
                });
            }
            
            // 메뉴 아이템 표시/숨김
            if (canSee) {
                $item.show();
            } else {
                $item.hide();
            }
        });
    }
    
    // AJAX로 메뉴 아이템 업데이트
    function updateMenuItems() {
        const wrapper = $('#gtmMenuWrapper');
        wrapper.addClass('loading');
        
        $.ajax({
            url: gtm_data.ajax_url,
            type: 'POST',
            data: {
                action: 'gtm_get_menu_html',
                nonce: gtm_data.nonce
            },
            success: function(response) {
                $('.gtm-menu-items').html(response);
                updateMenuVisibility();
                
                // 이벤트 리바인딩
                $('.gtm-menu-item').off('click').on('click', function(e) {
                    e.preventDefault();
                    
                    const $this = $(this);
                    const targetUrl = $this.attr('href');
                    
                    $this.css('transform', 'scale(0.95)');
                    
                    setTimeout(() => {
                        closeAppMenu();
                        setTimeout(() => {
                            window.location.href = targetUrl;
                        }, 200);
                    }, 100);
                });
            },
            complete: function() {
                wrapper.removeClass('loading');
            }
        });
    }
    
    // 페이지 모드 클래스 토글 함수 (필요시 외부에서 호출 가능)
    window.togglePageMode = function() {
        const html = document.documentElement;
        if (html.classList.contains('gtm-dark-theme')) {
            html.classList.remove('gtm-dark-theme');
            html.classList.add('gtm-white-theme');
        } else {
            html.classList.remove('gtm-white-theme');
            html.classList.add('gtm-dark-theme');
        }
    };
    
    // 성능 최적화를 위한 디바운스 함수
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // 윈도우 리사이즈 시 메뉴 위치 조정
    const handleResize = debounce(function() {
        const wrapper = $('#gtmMenuWrapper');
        if (wrapper.hasClass('expanded')) {
            // 모바일에서 메뉴가 화면을 벗어나지 않도록 조정
            const rect = wrapper[0].getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                wrapper.css('right', '10px');
            }
        }
    }, 250);
    
    $(window).on('resize', handleResize);
    
})(jQuery);