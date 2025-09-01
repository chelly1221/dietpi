// Google Themed Menu - Frontend Admin JavaScript
(function($) {
    'use strict';
    
    // 상태 관리
    var state = {
        currentEditId: null,
        isLoading: false,
        searchTimeout: null,
        currentSection: 'menus',
        savedPermissions: {},
        whiteModePages: []
    };
    
    // 문서 준비 완료 시 실행
    $(document).ready(function() {
        initSavedData();
        initSortable();
        bindEvents();
    });
    
    // 저장된 데이터 초기화
    function initSavedData() {
        // 권한 데이터 저장
        $('.gtm-fa-menu-item').each(function() {
            var $item = $(this);
            var menuId = $item.data('id');
            var permissions = $item.data('permissions');
            
            if (typeof permissions === 'string') {
                try {
                    permissions = JSON.parse(permissions);
                } catch(e) {
                    permissions = ['all'];
                }
            }
            
            state.savedPermissions[menuId] = permissions || ['all'];
        });
        
        // 화이트모드 페이지 저장
        state.whiteModePages = [];
        $('.white-mode-page:checked').each(function() {
            state.whiteModePages.push($(this).val());
        });
    }
    
    // Sortable 초기화
    function initSortable() {
        $('.gtm-fa-menu-grid').sortable({
            items: '.gtm-fa-menu-item',
            handle: '.gtm-fa-item-handle',
            placeholder: 'sortable-placeholder',
            tolerance: 'pointer',
            cursor: 'move',
            containment: 'parent',
            forcePlaceholderSize: true,
            revert: 200,
            start: function(event, ui) {
                ui.placeholder.height(ui.item.outerHeight());
                ui.item.addClass('is-dragging');
            },
            stop: function(event, ui) {
                ui.item.removeClass('is-dragging');
                updateMenuOrder();
            }
        });
    }
    
    // 이벤트 바인딩
    function bindEvents() {
        // 상단 탭 네비게이션
        $('.gtm-fa-tab-item').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            if (!$this.hasClass('active')) {
                $('.gtm-fa-tab-item').removeClass('active');
                $this.addClass('active');
                
                // 섹션 전환
                var section = $this.data('section');
                if (section) {
                    switchSection(section);
                }
            }
        });
        
        // 메뉴 추가
        $(document).on('click', '.gtm-fa-add-menu', function(e) {
            e.preventDefault();
            openAddModal();
        });
        
        // 메뉴 편집
        $(document).on('click', '.gtm-fa-edit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var menuId = $(this).closest('.gtm-fa-menu-item').data('id');
            openEditModal(menuId);
        });
        
        // 메뉴 삭제
        $(document).on('click', '.gtm-fa-delete', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var menuId = $(this).closest('.gtm-fa-menu-item').data('id');
            deleteMenu(menuId);
        });
        
        // 검색
        $('.gtm-fa-search-input').on('input', function() {
            var searchTerm = $(this).val();
            
            clearTimeout(state.searchTimeout);
            state.searchTimeout = setTimeout(function() {
                filterMenuItems(searchTerm);
            }, 300);
        });
        
        // 모달 이벤트
        $(document).on('click', '.gtm-fa-modal-close, .gtm-fa-modal-cancel', function(e) {
            e.preventDefault();
            closeModal();
        });
        
        $(document).on('click', '.gtm-fa-modal', function(e) {
            if ($(e.target).hasClass('gtm-fa-modal')) {
                closeModal();
            }
        });
        
        // 메뉴 저장
        $(document).on('click', '.gtm-fa-save-menu', function(e) {
            e.preventDefault();
            saveMenu();
        });
        
        // 권한 체크박스
        $(document).on('change', '.gtm-fa-permission-checkbox', function() {
            handlePermissionChange($(this));
        });
        
        // 로그인 리다이렉트 설정
        $('#login-redirect').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#custom-login-redirect-group').show();
            } else {
                $('#custom-login-redirect-group').hide();
            }
        });
        
        // 화이트모드 페이지 선택
        $(document).on('change', '.white-mode-page', function() {
            var value = $(this).val();
            if ($(this).is(':checked')) {
                if (state.whiteModePages.indexOf(value) === -1) {
                    state.whiteModePages.push(value);
                }
            } else {
                state.whiteModePages = state.whiteModePages.filter(function(v) {
                    return v !== value;
                });
            }
            
            // 자동 저장
            saveAllSettings();
        });
        
        // 고급 설정 버튼들
        $('#export-settings').on('click', function(e) {
            e.preventDefault();
            exportSettings();
        });
        
        $('#import-settings').on('click', function(e) {
            e.preventDefault();
            if (confirm(window.gtm_frontend.messages.import_confirm)) {
                $('<input type="file" accept=".json">').on('change', function(e) {
                    importSettings(e.target.files[0]);
                }).click();
            }
        });
        
        $('#reset-settings').on('click', function(e) {
            e.preventDefault();
            if (confirm(window.gtm_frontend.messages.reset_confirm)) {
                resetSettings();
            }
        });
        
        // 키보드 단축키
        $(document).on('keydown', function(e) {
            handleKeyboardShortcuts(e);
        });
        
        // 모바일 탭 스크롤
        if (window.matchMedia('(max-width: 768px)').matches) {
            initMobileTabScroll();
        }
    }
    
    // 모바일 탭 스크롤 초기화
    function initMobileTabScroll() {
        var $tabsNav = $('.gtm-fa-tabs-nav');
        var startX, scrollLeft;
        
        $tabsNav.on('touchstart', function(e) {
            startX = e.originalEvent.touches[0].pageX - $tabsNav.offset().left;
            scrollLeft = $tabsNav.scrollLeft();
        });
        
        $tabsNav.on('touchmove', function(e) {
            if (!startX) return;
            var x = e.originalEvent.touches[0].pageX - $tabsNav.offset().left;
            var walk = (x - startX) * 2;
            $tabsNav.scrollLeft(scrollLeft - walk);
        });
    }
    
    // 섹션 전환
    function switchSection(section) {
        state.currentSection = section;
        
        // 모든 섹션 숨기기
        $('.gtm-fa-section').removeClass('active');
        
        // 선택된 섹션 표시
        $('.gtm-fa-section[data-section="' + section + '"]').addClass('active');
        
        // 섹션별 초기화
        if (section === 'menus') {
            // 메뉴 목록이 보이면 sortable 새로고침
            setTimeout(function() {
                $('.gtm-fa-menu-grid').sortable('refresh');
            }, 100);
        }
    }
    
    // 메뉴 추가 모달
    function openAddModal() {
        state.currentEditId = null;
        resetModal();
        $('.gtm-fa-modal-title').text('새 메뉴 추가');
        showModal();
    }
    
    // 메뉴 편집 모달
    function openEditModal(menuId) {
        state.currentEditId = menuId;
        var $menuItem = $('.gtm-fa-menu-item[data-id="' + menuId + '"]');
        
        resetModal();
        
        // 데이터 가져오기
        var menuData = {
            icon: $menuItem.data('icon'),
            text: $menuItem.data('text'),
            url: $menuItem.data('url'),
            guest_action: $menuItem.data('guest-action') || 'redirect_login',
            no_permission_action: $menuItem.data('no-permission-action') || 'show_message',
            no_permission_message: $menuItem.data('no-permission-message') || '이 페이지에 접근할 권한이 없습니다.',
            permissions: state.savedPermissions[menuId] || ['all']
        };
        
        // 폼 데이터 설정
        $('.gtm-fa-menu-icon').val(menuData.icon);
        $('.gtm-fa-menu-text').val(menuData.text);
        $('.gtm-fa-menu-url').val(menuData.url);
        $('.gtm-fa-guest-action').val(menuData.guest_action);
        $('.gtm-fa-no-permission-action').val(menuData.no_permission_action);
        $('.gtm-fa-no-permission-message').val(menuData.no_permission_message);
        
        // 권한 체크박스
        $('.gtm-fa-permission-checkbox').prop('checked', false);
        if (Array.isArray(menuData.permissions)) {
            menuData.permissions.forEach(function(perm) {
                $('.gtm-fa-permission-checkbox[value="' + perm + '"]').prop('checked', true);
            });
        }
        
        $('.gtm-fa-modal-title').text('메뉴 편집');
        showModal();
    }
    
    // 모달 표시
    function showModal() {
        $('.gtm-fa-modal').fadeIn(200);
        setTimeout(function() {
            $('.gtm-fa-menu-icon').focus();
        }, 300);
    }
    
    // 모달 닫기
    function closeModal() {
        $('.gtm-fa-modal').fadeOut(200);
        setTimeout(function() {
            resetModal();
        }, 200);
    }
    
    // 모달 초기화
    function resetModal() {
        $('.gtm-fa-menu-id').val('');
        $('.gtm-fa-menu-icon').val('');
        $('.gtm-fa-menu-text').val('');
        $('.gtm-fa-menu-url').val('');
        $('.gtm-fa-guest-action').val('redirect_login');
        $('.gtm-fa-no-permission-action').val('show_message');
        $('.gtm-fa-no-permission-message').val('이 페이지에 접근할 권한이 없습니다.');
        $('.gtm-fa-permission-checkbox').prop('checked', false);
        $('.gtm-fa-permission-checkbox[value="all"]').prop('checked', true);
    }
    
    // 메뉴 저장
    function saveMenu() {
        if (state.isLoading) return;
        
        // 데이터 수집
        var menuData = {
            icon: $('.gtm-fa-menu-icon').val().trim(),
            text: $('.gtm-fa-menu-text').val().trim(),
            url: $('.gtm-fa-menu-url').val().trim(),
            guest_action: $('.gtm-fa-guest-action').val(),
            no_permission_action: $('.gtm-fa-no-permission-action').val(),
            no_permission_message: $('.gtm-fa-no-permission-message').val(),
            no_permission_redirect: '/',
            permissions: []
        };
        
        // 권한 수집
        $('.gtm-fa-permission-checkbox:checked').each(function() {
            menuData.permissions.push($(this).val());
        });
        
        // 검증
        if (!menuData.icon || !menuData.text || !menuData.url) {
            showNotice('error', window.gtm_frontend.messages.required_fields);
            return;
        }
        
        // 권한 처리
        if (menuData.permissions.length === 0) {
            menuData.permissions = ['all'];
        }
        
        if (menuData.permissions.indexOf('guest') !== -1) {
            menuData.permissions = ['guest'];
        }
        
        state.isLoading = true;
        var $saveBtn = $('.gtm-fa-save-menu');
        $saveBtn.addClass('gtm-fa-loading').prop('disabled', true);
        
        $.ajax({
            url: window.gtm_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'gtm_frontend_save_menu',
                nonce: window.gtm_frontend.nonce,
                menu_id: state.currentEditId || '',
                menu_data: menuData
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // 권한 저장
                    var menuId = response.data.menu_id;
                    state.savedPermissions[menuId] = response.data.permissions;
                    
                    if (state.currentEditId) {
                        updateMenuItemUI(state.currentEditId, response.data.menu_data, response.data.permissions);
                    } else {
                        addMenuItemUI(response.data.menu_id, response.data.menu_data, response.data.permissions);
                    }
                    
                    closeModal();
                } else {
                    showNotice('error', response.data || window.gtm_frontend.messages.save_error);
                }
            },
            error: function() {
                showNotice('error', window.gtm_frontend.messages.save_error);
            },
            complete: function() {
                state.isLoading = false;
                $saveBtn.removeClass('gtm-fa-loading').prop('disabled', false);
            }
        });
    }
    
    // 메뉴 삭제
    function deleteMenu(menuId) {
        if (!confirm(window.gtm_frontend.messages.delete_confirm)) {
            return;
        }
        
        if (state.isLoading) return;
        
        state.isLoading = true;
        var $menuItem = $('.gtm-fa-menu-item[data-id="' + menuId + '"]');
        $menuItem.addClass('gtm-fa-loading');
        
        $.ajax({
            url: window.gtm_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'gtm_frontend_delete_menu',
                nonce: window.gtm_frontend.nonce,
                menu_id: menuId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data || window.gtm_frontend.messages.delete_success);
                    
                    // 상태에서 제거
                    delete state.savedPermissions[menuId];
                    
                    // 애니메이션 후 제거
                    $menuItem.fadeOut(300, function() {
                        $(this).remove();
                        checkEmptyState();
                    });
                } else {
                    showNotice('error', response.data || window.gtm_frontend.messages.save_error);
                    $menuItem.removeClass('gtm-fa-loading');
                }
            },
            error: function() {
                showNotice('error', window.gtm_frontend.messages.save_error);
                $menuItem.removeClass('gtm-fa-loading');
            },
            complete: function() {
                state.isLoading = false;
            }
        });
    }
    
    // 메뉴 순서 업데이트
    function updateMenuOrder() {
        var order = [];
        $('.gtm-fa-menu-item').each(function() {
            order.push($(this).data('id'));
        });
        
        $.ajax({
            url: window.gtm_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'gtm_frontend_update_order',
                nonce: window.gtm_frontend.nonce,
                order: order
            }
        });
    }
    
    // 모든 설정 저장
    function saveAllSettings() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        
        // 메뉴 데이터 수집
        var menuItems = [];
        var permissions = {};
        
        $('.gtm-fa-menu-item').each(function(index) {
            var $item = $(this);
            var menuId = $item.data('id');
            
            menuItems.push({
                id: menuId,
                icon: $item.data('icon'),
                text: $item.data('text'),
                url: $item.data('url'),
                order: index + 1,
                guest_action: $item.data('guest-action') || 'redirect_login',
                no_permission_action: $item.data('no-permission-action') || 'show_message',
                no_permission_message: $item.data('no-permission-message') || '이 페이지에 접근할 권한이 없습니다.',
                no_permission_redirect: $item.data('no-permission-redirect') || '/'
            });
            
            if (state.savedPermissions[menuId]) {
                permissions[menuId] = state.savedPermissions[menuId];
            }
        });
        
        // 로그인 설정 수집
        var loginSettings = {
            login_page_id: $('#login-page-id').val(),
            login_redirect: $('#login-redirect').val(),
            custom_login_redirect: $('#custom-login-redirect').val(),
            logout_redirect: $('#logout-redirect').val()
        };
        
        $.ajax({
            url: window.gtm_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'gtm_frontend_save_all',
                nonce: window.gtm_frontend.nonce,
                menu_items: menuItems,
                permissions: permissions,
                white_mode_pages: state.whiteModePages,
                login_settings: loginSettings
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data || window.gtm_frontend.messages.save_error);
                }
            },
            error: function() {
                showNotice('error', window.gtm_frontend.messages.save_error);
            },
            complete: function() {
                state.isLoading = false;
            }
        });
    }
    
    // 설정 내보내기
    function exportSettings() {
        $.ajax({
            url: window.gtm_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'gtm_frontend_export_settings',
                nonce: window.gtm_frontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    var dataStr = JSON.stringify(response.data, null, 2);
                    var dataBlob = new Blob([dataStr], {type: 'application/json'});
                    var url = URL.createObjectURL(dataBlob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'gtm-settings-' + new Date().toISOString().slice(0, 10) + '.json';
                    link.click();
                    URL.revokeObjectURL(url);
                    
                    showNotice('success', '설정을 내보냈습니다.');
                } else {
                    showNotice('error', response.data || '내보내기 실패');
                }
            },
            error: function() {
                showNotice('error', '서버 오류가 발생했습니다.');
            }
        });
    }
    
    // 설정 가져오기
    function importSettings(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var settings = JSON.parse(e.target.result);
                
                $.ajax({
                    url: window.gtm_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gtm_frontend_import_settings',
                        nonce: window.gtm_frontend.nonce,
                        settings: settings
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('success', '설정을 가져왔습니다. 페이지를 새로고침합니다...');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            showNotice('error', response.data || '가져오기 실패');
                        }
                    },
                    error: function() {
                        showNotice('error', '서버 오류가 발생했습니다.');
                    }
                });
            } catch (error) {
                showNotice('error', '잘못된 파일 형식입니다.');
            }
        };
        reader.readAsText(file);
    }
    
    // 설정 초기화
    function resetSettings() {
        $.ajax({
            url: window.gtm_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'gtm_frontend_reset_settings',
                nonce: window.gtm_frontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', '설정이 초기화되었습니다. 페이지를 새로고침합니다...');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice('error', response.data || '초기화 실패');
                }
            },
            error: function() {
                showNotice('error', '서버 오류가 발생했습니다.');
            }
        });
    }
    
    // UI 업데이트 함수
    function updateMenuItemUI(menuId, menuData, permissions) {
        var $item = $('.gtm-fa-menu-item[data-id="' + menuId + '"]');
        
        // data attributes 업데이트
        $item.attr('data-icon', menuData.icon);
        $item.attr('data-text', menuData.text);
        $item.attr('data-url', menuData.url);
        $item.attr('data-guest-action', menuData.guest_action);
        $item.attr('data-no-permission-action', menuData.no_permission_action);
        $item.attr('data-no-permission-message', menuData.no_permission_message);
        $item.attr('data-permissions', JSON.stringify(permissions));
        
        // UI 업데이트
        $item.find('.gtm-fa-item-icon').text(menuData.icon);
        $item.find('.gtm-fa-item-title').text(menuData.text);
        $item.find('.gtm-fa-item-url').text(menuData.url);
        
        // 권한 태그 업데이트
        updatePermissionTags($item, permissions);
    }
    
    function addMenuItemUI(menuId, menuData, permissions) {
        var itemHtml = createMenuItemHtml(menuId, menuData, permissions);
        
        // 빈 상태 메시지 제거
        $('.gtm-fa-empty').remove();
        
        // 새 아이템 추가
        $('.gtm-fa-menu-grid').append(itemHtml);
        
        // Sortable 새로고침
        $('.gtm-fa-menu-grid').sortable('refresh');
    }
    
    function createMenuItemHtml(menuId, menuData, permissions) {
        var permissionHtml = getPermissionHtml(permissions);
        
        var html = '<div class="gtm-fa-menu-item" data-id="' + escapeHtml(menuId) + '" ' +
            'data-icon="' + escapeHtml(menuData.icon) + '" ' +
            'data-text="' + escapeHtml(menuData.text) + '" ' +
            'data-url="' + escapeHtml(menuData.url) + '" ' +
            'data-guest-action="' + escapeHtml(menuData.guest_action || 'redirect_login') + '" ' +
            'data-no-permission-action="' + escapeHtml(menuData.no_permission_action || 'show_message') + '" ' +
            'data-no-permission-message="' + escapeHtml(menuData.no_permission_message || '이 페이지에 접근할 권한이 없습니다.') + '" ' +
            'data-no-permission-redirect="' + escapeHtml(menuData.no_permission_redirect || '/') + '" ' +
            'data-permissions="' + escapeHtml(JSON.stringify(permissions)) + '">' +
            '<div class="gtm-fa-item-header">' +
                '<div class="gtm-fa-item-handle">' +
                    '<span>⋮⋮</span>' +
                '</div>' +
                '<div class="gtm-fa-item-icon">' + escapeHtml(menuData.icon) + '</div>' +
                '<div class="gtm-fa-item-content">' +
                    '<h4 class="gtm-fa-item-title">' + escapeHtml(menuData.text) + '</h4>' +
                    '<div class="gtm-fa-item-url">' + escapeHtml(menuData.url) + '</div>' +
                    '<div class="gtm-fa-item-permissions">' + permissionHtml + '</div>' +
                '</div>' +
                '<div class="gtm-fa-item-actions">' +
                    '<button class="gtm-fa-btn-icon gtm-fa-edit" title="편집" type="button">' +
                        '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                            '<path d="M14.166 2.5009C14.3849 2.28203 14.6447 2.10842 14.9307 1.98996C15.2167 1.87151 15.5232 1.81055 15.8327 1.81055C16.1422 1.81055 16.4487 1.87151 16.7347 1.98996C17.0206 2.10842 17.2805 2.28203 17.4993 2.5009C17.7182 2.71977 17.8918 2.97961 18.0103 3.26558C18.1287 3.55154 18.1897 3.85804 18.1897 4.16757C18.1897 4.4771 18.1287 4.7836 18.0103 5.06956C17.8918 5.35553 17.7182 5.61537 17.4993 5.83424L6.24935 17.0842L1.66602 18.3342L2.91602 13.7509L14.166 2.5009Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                        '</svg>' +
                    '</button>' +
                    '<button class="gtm-fa-btn-icon gtm-fa-delete" title="삭제" type="button">' +
                        '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                            '<path d="M2.5 5H4.16667H17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                            '<path d="M6.66602 4.99984V3.33317C6.66602 2.89114 6.84161 2.46722 7.15417 2.15466C7.46673 1.8421 7.89066 1.6665 8.33268 1.6665H11.666C12.108 1.6665 12.532 1.8421 12.8445 2.15466C13.1571 2.46722 13.3327 2.89114 13.3327 3.33317V4.99984M15.8327 4.99984V16.6665C15.8327 17.1085 15.6571 17.5325 15.3445 17.845C15.032 18.1576 14.608 18.3332 14.166 18.3332H5.83268C5.39066 18.3332 4.96673 18.1576 4.65417 17.845C4.34161 17.5325 4.16602 17.1085 4.16602 16.6665V4.99984H15.8327Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                            '<path d="M8.33398 9.1665V14.1665" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                            '<path d="M11.666 9.1665V14.1665" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
            '</div>' +
        '</div>';
        
        return html;
    }
    
    function updatePermissionTags($item, permissions) {
        var $permissionsDiv = $item.find('.gtm-fa-item-permissions');
        $permissionsDiv.html(getPermissionHtml(permissions));
    }
    
    function getPermissionHtml(permissions) {
        var html = '';
        
        if (permissions.indexOf('guest') !== -1) {
            html = '<span class="gtm-fa-tag gtm-fa-tag-guest">모든 사용자</span>';
        } else if (permissions.indexOf('all') !== -1) {
            html = '<span class="gtm-fa-tag gtm-fa-tag-all">로그인 사용자</span>';
        } else {
            permissions.forEach(function(perm) {
                var label = getRoleLabel(perm);
                html += '<span class="gtm-fa-tag">' + escapeHtml(label) + '</span>';
            });
        }
        
        return html;
    }
    
    function getRoleLabel(role) {
        var labels = {
            'administrator': '관리자',
            'editor': '편집자',
            'author': '글쓴이',
            'contributor': '기여자',
            'subscriber': '구독자',
            'approver': '승인자'
        };
        
        return labels[role] || role;
    }
    
    // 검색 필터링
    function filterMenuItems(searchTerm) {
        searchTerm = searchTerm.toLowerCase();
        var hasResults = false;
        
        $('.gtm-fa-menu-item').each(function() {
            var $item = $(this);
            var title = $item.find('.gtm-fa-item-title').text().toLowerCase();
            var url = $item.find('.gtm-fa-item-url').text().toLowerCase();
            var icon = $item.data('icon').toLowerCase();
            
            if (title.indexOf(searchTerm) !== -1 || 
                url.indexOf(searchTerm) !== -1 || 
                icon.indexOf(searchTerm) !== -1) {
                $item.show();
                hasResults = true;
            } else {
                $item.hide();
            }
        });
        
        // 검색 결과가 없을 때
        if (!hasResults && searchTerm) {
            if (!$('.gtm-fa-no-results').length) {
                var noResultsHtml = '<div class="gtm-fa-no-results">' +
                    '<span class="gtm-fa-icon">🔍</span>' +
                    '<h3>검색 결과가 없습니다</h3>' +
                    '<p>"' + escapeHtml(searchTerm) + '"에 대한 메뉴를 찾을 수 없습니다.</p>' +
                '</div>';
                $('.gtm-fa-menu-grid').append(noResultsHtml);
            }
        } else {
            $('.gtm-fa-no-results').remove();
        }
        
        // 검색어가 없으면 모두 표시
        if (!searchTerm) {
            $('.gtm-fa-menu-item').show();
        }
    }
    
    // 권한 체크박스 처리
    function handlePermissionChange($checkbox) {
        var value = $checkbox.val();
        
        if (value === 'guest' && $checkbox.is(':checked')) {
            // guest를 선택하면 다른 모든 체크박스 해제
            $('.gtm-fa-permission-checkbox').not($checkbox).prop('checked', false);
        } else if ($checkbox.is(':checked')) {
            // 다른 권한을 선택하면 guest 해제
            $('.gtm-fa-permission-checkbox[value="guest"]').prop('checked', false);
        }
        
        // 최소 하나는 선택되어야 함
        if ($('.gtm-fa-permission-checkbox:checked').length === 0) {
            $('.gtm-fa-permission-checkbox[value="all"]').prop('checked', true);
        }
    }
    
    // 빈 상태 확인
    function checkEmptyState() {
        if ($('.gtm-fa-menu-item').length === 0) {
            var emptyHtml = '<div class="gtm-fa-empty">' +
                '<span class="gtm-fa-empty-icon">📭</span>' +
                '<h3>메뉴가 없습니다</h3>' +
                '<p>새 메뉴를 추가하여 시작하세요.</p>' +
                '<button class="gtm-fa-btn-primary gtm-fa-add-menu" type="button">' +
                    '<span class="gtm-fa-icon">' +
                        '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                            '<path d="M8 1V15M1 8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
                        '</svg>' +
                    '</span>' +
                    '첫 메뉴 추가하기' +
                '</button>' +
            '</div>';
            $('.gtm-fa-menu-grid').html(emptyHtml);
        }
    }
    
    // 알림 표시
    function showNotice(type, message) {
        // 기존 알림 제거
        $('.gtm-fa-notice').remove();
        
        var noticeHtml = '<div class="gtm-fa-notice ' + type + '">' +
            '<span class="gtm-fa-icon">' + (type === 'success' ? '✅' : '⚠️') + '</span>' +
            '<span>' + escapeHtml(message) + '</span>' +
        '</div>';
        
        var $notice = $(noticeHtml);
        $('body').append($notice);
        
        // 위치 조정
        setTimeout(function() {
            $notice.fadeIn(200);
        }, 10);
        
        // 3초 후 자동 제거
        setTimeout(function() {
            $notice.fadeOut(200, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // 키보드 단축키 처리
    function handleKeyboardShortcuts(e) {
        // ESC로 모달 닫기
        if (e.keyCode === 27) {
            if ($('.gtm-fa-modal').is(':visible')) {
                closeModal();
            }
        }
        
        // Ctrl/Cmd + S로 저장
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
            e.preventDefault();
            if ($('.gtm-fa-modal').is(':visible')) {
                saveMenu();
            } else {
                saveAllSettings();
            }
        }
        
        // Alt + 1~4로 탭 전환
        if (e.altKey && e.keyCode >= 49 && e.keyCode <= 52) {
            e.preventDefault();
            var tabIndex = e.keyCode - 49;
            $('.gtm-fa-tab-item').eq(tabIndex).click();
        }
    }
    
    // HTML 이스케이프
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return String(text).replace(/[&<>"']/g, function(m) {
            return map[m];
        });
    }
    
})(jQuery);