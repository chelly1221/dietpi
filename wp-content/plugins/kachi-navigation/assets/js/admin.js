jQuery(document).ready(function($) {
    'use strict';
    
    // 메뉴 목록 정렬 가능하게 설정
    var $menuList = $('#smm-menu-list');
    
    if ($menuList.length) {
        $menuList.sortable({
            handle: '.smm-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            forcePlaceholderSize: true,
            update: function(event, ui) {
                updateMenuOrder();
            }
        });
    }
    
    // 메뉴 순서 업데이트
    function updateMenuOrder() {
        var menuOrder = [];
        
        $menuList.find('tr').each(function() {
            var menuId = $(this).data('menu-id');
            if (menuId) {
                menuOrder.push(menuId);
            }
        });
        
        $.ajax({
            url: smm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'smm_update_menu_order',
                menu_order: menuOrder,
                nonce: smm_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('메뉴 순서가 업데이트되었습니다.');
                } else {
                    alert('순서 업데이트에 실패했습니다.');
                    location.reload();
                }
            },
            error: function() {
                alert('오류가 발생했습니다.');
                location.reload();
            }
        });
    }
    
    // 폼 검증
    $('form').on('submit', function(e) {
        var $form = $(this);
        var $menuLabel = $form.find('#menu_label');
        var $menuUrl = $form.find('#menu_url');
        
        if ($menuLabel.length && $menuUrl.length) {
            // 레이블 검증
            if ($menuLabel.val().trim() === '') {
                alert('메뉴 레이블을 입력해주세요.');
                $menuLabel.focus();
                e.preventDefault();
                return false;
            }
            
            // URL 검증
            if ($menuUrl.val().trim() === '') {
                alert('링크 URL을 입력해주세요.');
                $menuUrl.focus();
                e.preventDefault();
                return false;
            }
        }
    });
    
    // 삭제 확인
    $(document).on('click', '.smm-delete-btn', function(e) {
        if (!confirm('이 메뉴를 삭제하시겠습니까?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // 색상 입력 필드에 컬러 피커 스타일 추가
    $('input[id$="_color"]').each(function() {
        $(this).css('cursor', 'pointer');
        
        // HTML5 color input으로 변경
        $(this).attr('type', 'color');
        $(this).css({
            'width': '60px',
            'height': '36px',
            'padding': '2px',
            'border': '1px solid #8c8f94'
        });
    });
    
    // 알림 메시지 자동 숨김
    setTimeout(function() {
        $('.smm-admin-wrap .notice').not('.notice-info').fadeOut(500);
    }, 5000);
    
    // 테이블 행 호버 효과
    $menuList.on('mouseenter', 'tr', function() {
        $(this).css('background-color', '#f0f0f1');
    }).on('mouseleave', 'tr', function() {
        $(this).css('background-color', '');
    });
    
    // 메뉴 레이블 중복 확인
    $('#menu_label').on('blur', function() {
        var label = $(this).val().trim();
        var currentMenuId = $('input[name="menu_id"]').val();
        var isDuplicate = false;
        
        $menuList.find('tr').each(function() {
            var menuId = $(this).data('menu-id');
            var existingLabel = $(this).find('td:nth-child(2)').text().trim();
            
            // 수정 모드에서는 자기 자신은 제외
            if (existingLabel === label && menuId != currentMenuId) {
                isDuplicate = true;
                return false;
            }
        });
        
        if (isDuplicate) {
            $(this).css('border-color', '#dc3232');
            if (!$(this).next('.description').length) {
                $(this).after('<p class="description" style="color: #dc3232;">이미 사용 중인 메뉴 레이블입니다.</p>');
            }
        } else {
            $(this).css('border-color', '');
            $(this).next('.description').remove();
        }
    });
});