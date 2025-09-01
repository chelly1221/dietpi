jQuery(document).ready(function($) {
    'use strict';
    
    // 메뉴 정렬 가능하게 만들기 (관리자용)
    function initSortableMenu() {
        // WordPress의 jQuery UI sortable 종속성 확인 및 로드
        if (typeof jQuery.ui === 'undefined' || typeof jQuery.ui.sortable === 'undefined') {
            // WordPress 스크립트 로더를 통해 정확히 로드
            if (typeof wp !== 'undefined' && wp.scriptLoader) {
                // jQuery UI Core 먼저 로드
                wp.scriptLoader.load('jquery-ui-core', function() {
                    // 그 다음 jQuery UI Widget 로드
                    wp.scriptLoader.load('jquery-ui-widget', function() {
                        // 마지막으로 jQuery UI Sortable 로드
                        wp.scriptLoader.load('jquery-ui-sortable', function() {
                            makeSortable();
                        });
                    });
                });
            } else {
                // 대체 방법: 직접 스크립트 로드
                var scripts = [
                    '/wp-includes/js/jquery/ui/core.min.js',
                    '/wp-includes/js/jquery/ui/widget.min.js',
                    '/wp-includes/js/jquery/ui/mouse.min.js',
                    '/wp-includes/js/jquery/ui/sortable.min.js'
                ];
                
                var loadedScripts = 0;
                
                scripts.forEach(function(src, index) {
                    var script = document.createElement('script');
                    script.src = src;
                    script.onload = function() {
                        loadedScripts++;
                        if (loadedScripts === scripts.length) {
                            makeSortable();
                        }
                    };
                    script.onerror = function() {
                        console.error('Failed to load: ' + src);
                    };
                    document.head.appendChild(script);
                });
            }
        } else {
            makeSortable();
        }
    }
    
    // 정렬 기능 적용
    function makeSortable() {
        if (!$.fn.sortable) {
            console.error('jQuery UI Sortable is not available');
            return;
        }
        
        $('.smm-menu-list').addClass('smm-sortable').sortable({
            handle: '.smm-drag-handle',
            placeholder: 'smm-sortable-placeholder',
            axis: 'y',
            opacity: 0.8,
            update: function(event, ui) {
                updateMenuOrder();
            }
        });
        
        // 드래그 핸들이 없는 경우에만 추가
        $('.smm-menu-item').each(function() {
            if (!$(this).find('.smm-drag-handle').length) {
                $('<div class="smm-drag-handle"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 6H16M8 12H16M8 18H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>').prependTo(this);
            }
        });
    }
    
    // 메뉴 순서 업데이트
    function updateMenuOrder() {
        var menuOrder = [];
        
        $('.smm-menu-item').each(function() {
            var menuId = $(this).data('menu-id');
            if (menuId) {
                menuOrder.push(menuId);
            }
        });
        
        $.ajax({
            url: smm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'smm_update_menu_order',
                menu_order: menuOrder,
                nonce: smm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('메뉴 순서가 변경되었습니다.', 'success');
                } else {
                    showNotification('순서 변경에 실패했습니다.', 'error');
                    location.reload();
                }
            },
            error: function() {
                showNotification('오류가 발생했습니다.', 'error');
                location.reload();
            }
        });
    }
    
    // 사이드바가 있으면 body에 클래스 추가 및 레이아웃 조정
    if ($('.smm-container').length) {
        var $container = $('.smm-container');
        var width = $container.find('.smm-sidebar').css('width') || '250px';
        var position = $container.attr('data-position') || 'left';
        
        // CSS 변수로 사이드바 너비 설정
        $('body').css('--smm-sidebar-width', width);
        
        // body에 클래스 추가
        $('body').addClass('smm-sidebar-active');
        
        if (position === 'right') {
            $('body').addClass('smm-sidebar-right');
        }
        
        // 관리자인 경우 추가 기능 활성화
        if (smm_ajax.is_admin) {
            $('body').addClass('smm-admin-mode');
            initSortableMenu();
        }
        
        // 추가적인 레이아웃 조정을 위한 스타일 주입
        var styleId = 'smm-dynamic-styles';
        $('#' + styleId).remove();
        
        var styles = '<style id="' + styleId + '">';
        
        // viewport 단위를 사용하여 더 강력하게 적용
        if (position === 'left') {
            styles += 'html { margin-left: ' + width + ' !important; }';
            styles += 'body { width: calc(100vw - ' + width + ') !important; max-width: calc(100vw - ' + width + ') !important; }';
            styles += '#page, .site, .site-content, .content-area, #content, #main, .container, .wrapper { width: 100% !important; max-width: 100% !important; }';
            styles += '.entry-content > * { max-width: 100% !important; }';
            // 전체 너비 요소들
            styles += '.alignfull { width: calc(100vw - ' + width + ') !important; max-width: calc(100vw - ' + width + ') !important; margin-left: calc(50% - 50vw + ' + width + ' / 2) !important; margin-right: calc(50% - 50vw + ' + width + ' / 2) !important; }';
        } else {
            styles += 'html { margin-right: ' + width + ' !important; }';
            styles += 'body { width: calc(100vw - ' + width + ') !important; max-width: calc(100vw - ' + width + ') !important; }';
            styles += '#page, .site, .site-content, .content-area, #content, #main, .container, .wrapper { width: 100% !important; max-width: 100% !important; }';
            styles += '.entry-content > * { max-width: 100% !important; }';
            // 전체 너비 요소들
            styles += '.alignfull { width: calc(100vw - ' + width + ') !important; max-width: calc(100vw - ' + width + ') !important; margin-left: calc(50% - 50vw + ' + width + ' / 2) !important; margin-right: calc(50% - 50vw + ' + width + ' / 2) !important; }';
        }
        
        // 고정 위치 요소들 조정
        styles += '.wp-block-cover__inner-container { max-width: 100% !important; }';
        styles += '.wp-block-group__inner-container { max-width: 100% !important; }';
        
        styles += '</style>';
        
        // 모바일이 아닐 때만 적용
        if ($(window).width() > 768) {
            $('head').append(styles);
        }
        
        // 모바일에서 오버레이 추가
        if ($(window).width() <= 768) {
            addMobileOverlay();
        }
    }
    
    // 모바일 오버레이 추가 함수
    function addMobileOverlay() {
        if (!$('.smm-mobile-overlay').length) {
            $('<div class="smm-mobile-overlay"></div>').appendTo('body');
            $('<button class="smm-mobile-close"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>').appendTo('.smm-container');
        }
    }
    
    // 모바일 오버레이 클릭 시 사이드바 숨기기
    $(document).on('click', '.smm-mobile-overlay, .smm-mobile-close', function() {
        $('.smm-container').hide();
        $('.smm-mobile-overlay').hide();
    });
    
    // 창 크기 변경 시 처리
    $(window).resize(function() {
        if ($(window).width() <= 768) {
            addMobileOverlay();
            $('#smm-dynamic-styles').remove();
        } else {
            $('.smm-mobile-overlay').remove();
            $('.smm-mobile-close').remove();
            $('.smm-container').show();
            
            // 데스크톱에서 다시 스타일 적용
            if ($('.smm-container').length) {
                var $container = $('.smm-container');
                var width = $container.find('.smm-sidebar').css('width') || '250px';
                var position = $container.attr('data-position') || 'left';
                
                var styleId = 'smm-dynamic-styles';
                $('#' + styleId).remove();
                
                var styles = '<style id="' + styleId + '">';
                
                // viewport 단위를 사용하여 더 강력하게 적용
                if (position === 'left') {
                    styles += 'html { margin-left: ' + width + ' !important; }';
                    styles += 'body { width: calc(100vw - ' + width + ') !important; max-width: calc(100vw - ' + width + ') !important; }';
                    styles += '#page, .site, .site-content, .content-area, #content, #main, .container, .wrapper { width: 100% !important; max-width: 100% !important; }';
                    styles += '.entry-content > * { max-width: 100% !important; }';
                    // 전체 너비 요소들
                    styles += '.alignfull { width: calc(100vw - ' + width + ') !important; max-width: calc(100vw - ' + width + ') !important; margin-left: calc(50% - 50vw + ' + width + ' / 2) !important; margin-right: calc(50% - 50vw + ' + width + ' / 2) !important; }';
                } else {
                    styles += 'html { margin-right: ' + width + ' !important; }';
                    styles += 'body { width: calc(100vw - ' + width + ') !important; max-width: calc(100vw - ' + width + ') !important; }';
                    styles += '#page, .site, .site-content, .content-area, #content, #main, .container, .wrapper { width: 100% !important; max-width: 100% !important; }';
                    styles += '.entry-content > * { max-width: 100% !important; }';
                    // 전체 너비 요소들
                    styles += '.alignfull { width: calc(100vw - ' + width + ') !important; max-width: calc(100vw - ' + width + ') !important; margin-left: calc(50% - 50vw + ' + width + ' / 2) !important; margin-right: calc(50% - 50vw + ' + width + ' / 2) !important; }';
                }
                
                // 고정 위치 요소들 조정
                styles += '.wp-block-cover__inner-container { max-width: 100% !important; }';
                styles += '.wp-block-group__inner-container { max-width: 100% !important; }';
                
                styles += '</style>';
                $('head').append(styles);
            }
        }
    });
    
    // 메뉴 추가 버튼 클릭
    $(document).on('click', '.smm-add-menu-btn', function(e) {
        e.preventDefault();
        openMenuModal();
    });
    
    // 메뉴 수정 버튼 클릭
    $(document).on('click', '.smm-menu-edit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $menuItem = $(this).closest('.smm-menu-item');
        var menuId = $menuItem.data('menu-id');
        
        // 메뉴 데이터 가져오기
        $.ajax({
            url: smm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'smm_get_menu_data',
                menu_id: menuId,
                nonce: smm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    openMenuModal(response.data.menu);
                } else {
                    alert('메뉴 데이터를 불러올 수 없습니다.');
                }
            },
            error: function() {
                alert('오류가 발생했습니다.');
            }
        });
    });
    
    // 메뉴 삭제 버튼 클릭
    $(document).on('click', '.smm-menu-delete', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $menuItem = $(this).closest('.smm-menu-item');
        var menuId = $menuItem.data('menu-id');
        
        $('#smm-delete-menu-id').val(menuId);
        $('#smm-delete-modal').fadeIn(300);
    });
    
    // 삭제 확인
    $(document).on('click', '#smm-confirm-delete', function(e) {
        e.preventDefault();
        
        var menuId = $('#smm-delete-menu-id').val();
        var $btn = $(this);
        var originalText = $btn.html();
        
        // 버튼 비활성화
        $btn.prop('disabled', true).text('삭제 중...');
        
        $.ajax({
            url: smm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'smm_delete_menu_frontend',
                menu_id: menuId,
                nonce: smm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // 메뉴 항목 제거
                    $('.smm-menu-item[data-menu-id="' + menuId + '"]').fadeOut(300, function() {
                        $(this).remove();
                        
                        // 메뉴가 없는 경우 메시지 표시
                        if ($('.smm-menu-item').length === 0) {
                            $('.smm-menu-list').html('<li class="smm-no-menu">등록된 메뉴가 없습니다.</li>');
                        }
                    });
                    
                    // 모달 닫기
                    $('#smm-delete-modal').fadeOut(300);
                    
                    // 성공 메시지
                    showNotification(response.data.message, 'success');
                } else {
                    alert(response.data || '메뉴 삭제에 실패했습니다.');
                }
            },
            error: function() {
                alert('오류가 발생했습니다.');
            },
            complete: function() {
                // 버튼 활성화
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // 메뉴 모달 열기 함수
    function openMenuModal(menuData) {
        var isEdit = menuData && menuData.id;
        
        // 모달 타이틀 및 버튼 텍스트 설정
        $('#smm-modal-title').text(isEdit ? '메뉴 수정' : '새 메뉴 추가');
        $('#smm-submit-text').text(isEdit ? '수정' : '추가');
        
        // 폼 초기화
        $('#smm-menu-form')[0].reset();
        
        // 수정 모드인 경우 데이터 채우기
        if (isEdit) {
            $('#smm-menu-id').val(menuData.id);
            $('#smm-menu-label').val(menuData.label);
            $('#smm-menu-url').val(menuData.url);
            $('#smm-menu-target').val(menuData.target);
        } else {
            $('#smm-menu-id').val('');
        }
        
        $('#smm-menu-modal').fadeIn(300);
        $('#smm-menu-label').focus();
    }
    
    // 모달 닫기
    $(document).on('click', '.smm-modal-close, .smm-btn-cancel', function(e) {
        e.preventDefault();
        $(this).closest('.smm-modal').fadeOut(300);
        $('#smm-menu-form')[0].reset();
    });
    
    // 모달 외부 클릭 시 닫기
    $(document).on('click', '.smm-modal', function(e) {
        if ($(e.target).hasClass('smm-modal')) {
            $(this).fadeOut(300);
            $('#smm-menu-form')[0].reset();
        }
    });
    
    // 메뉴 폼 제출
    $(document).on('submit', '#smm-menu-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('.smm-btn-primary');
        var originalText = $submitBtn.html();
        var menuId = $('#smm-menu-id').val();
        var isEdit = menuId !== '';
        
        // 버튼 비활성화
        $submitBtn.prop('disabled', true).html('<span class="smm-spinner"></span> 처리 중...');
        
        var data = {
            action: isEdit ? 'smm_edit_menu_frontend' : 'smm_add_menu_frontend',
            menu_label: $form.find('#smm-menu-label').val(),
            menu_url: $form.find('#smm-menu-url').val(),
            menu_target: $form.find('#smm-menu-target').val(),
            nonce: smm_ajax.nonce
        };
        
        if (isEdit) {
            data.menu_id = menuId;
        }
        
        $.ajax({
            url: smm_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // 페이지 새로고침하여 메뉴 목록 업데이트
                    location.reload();
                } else {
                    alert(response.data || '작업에 실패했습니다.');
                }
            },
            error: function() {
                alert('오류가 발생했습니다.');
            },
            complete: function() {
                // 버튼 활성화
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // 알림 표시 함수
    function showNotification(message, type) {
        var $notification = $('<div class="smm-notification smm-notification-' + type + '">' + message + '</div>');
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.addClass('show');
        }, 100);
        
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 3000);
    }
    
    // 키보드 단축키
    $(document).on('keydown', function(e) {
        // ESC 키로 모달 닫기
        if (e.keyCode === 27) {
            $('.smm-modal:visible').fadeOut(300);
        }
    });
});