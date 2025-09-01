// Version Notice Frontend Admin JavaScript
(function($) {
    'use strict';
    
    // 상태 관리
    var state = {
        currentSection: 'notices',
        isLoading: false,
        editingId: null,
        saveTimeout: null
    };
    
    // 문서 준비 완료 시 실행
    $(document).ready(function() {
        bindEvents();
    });
    
    // 이벤트 바인딩
    function bindEvents() {
        // 탭 네비게이션
        $('.vn-fa-tab-item').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            if (!$this.hasClass('active')) {
                $('.vn-fa-tab-item').removeClass('active');
                $this.addClass('active');
                
                var section = $this.data('section');
                if (section) {
                    switchSection(section);
                }
            }
        });
        
        // 새 공지사항 추가 버튼
        $('#add-new-notice').on('click', function() {
            showNoticeForm();
        });
        
        // 폼 제출
        $('#vn-notice-form').on('submit', function(e) {
            e.preventDefault();
            saveNotice();
        });
        
        // 취소 버튼
        $('#cancel-edit').on('click', function() {
            hideNoticeForm();
            resetForm();
        });
        
        // 수정 버튼
        $(document).on('click', '.edit-notice', function() {
            var noticeId = $(this).data('id');
            editNotice(noticeId);
        });
        
        // 삭제 버튼
        $(document).on('click', '.delete-notice', function() {
            var noticeId = $(this).data('id');
            deleteNotice(noticeId);
        });
        
        // 이미지 업로드 버튼
        $('#upload-background-image').on('click', function(e) {
            e.preventDefault();
            openMediaUploader();
        });
        
        // 이미지 제거 버튼
        $('#remove-background-image').on('click', function(e) {
            e.preventDefault();
            removeBackgroundImage();
        });
        
        // 모든 페이지 적용 체크박스
        $('#apply-all-pages').on('change', function() {
            if ($(this).is(':checked')) {
                $('#page-selection').slideUp();
                $('input[name="background_pages[]"]').prop('checked', false);
            } else {
                $('#page-selection').slideDown();
            }
            saveSettings();
        });
        
        // 페이지 선택 체크박스
        $('input[name="background_pages[]"]').on('change', function() {
            saveSettings();
        });
    }
    
    // 섹션 전환
    function switchSection(section) {
        state.currentSection = section;
        
        // 모든 섹션 숨기기
        $('.vn-fa-section').removeClass('active');
        
        // 선택된 섹션 표시
        $('.vn-fa-section[data-section="' + section + '"]').addClass('active');
    }
    
    // 공지사항 폼 표시
    function showNoticeForm(isEdit) {
        $('.vn-fa-notice-form').slideDown();
        $('#form-title').text(isEdit ? '공지사항 수정' : '새 공지사항 추가');
        
        // 스크롤 이동
        $('html, body').animate({
            scrollTop: $('.vn-fa-notice-form').offset().top - 100
        }, 500);
    }
    
    // 공지사항 폼 숨기기
    function hideNoticeForm() {
        $('.vn-fa-notice-form').slideUp();
    }
    
    // 폼 초기화
    function resetForm() {
        $('#vn-notice-form')[0].reset();
        $('#notice-id').val('');
        state.editingId = null;
    }
    
    // 공지사항 저장
    function saveNotice() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        var $submitBtn = $('#vn-notice-form button[type="submit"]');
        $submitBtn.prop('disabled', true).html('<span class="vn-fa-icon">⏳</span><span>저장 중...</span>');
        
        var formData = {
            action: 'vn_frontend_save_notice',
            nonce: vn_frontend.nonce,
            id: $('#notice-id').val(),
            version_number: $('#version-number').val(),
            notice_date: $('#notice-date').val(),
            title: $('#title').val(),
            content: $('#content').val(),
            year_month: $('#year-month').val(),
            sort_order: $('#sort-order').val()
        };
        
        $.ajax({
            url: vn_frontend.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // 알림 제거 - 바로 페이지 새로고침
                    location.reload();
                } else {
                    showNotice('error', response.data || vn_frontend.messages.save_error);
                }
            },
            error: function() {
                showNotice('error', vn_frontend.messages.save_error);
            },
            complete: function() {
                state.isLoading = false;
                $submitBtn.prop('disabled', false).html('<span class="vn-fa-icon">💾</span><span>저장</span>');
            }
        });
    }
    
    // 공지사항 수정
    function editNotice(noticeId) {
        if (state.isLoading) return;
        
        state.isLoading = true;
        state.editingId = noticeId;
        
        $.ajax({
            url: vn_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'vn_frontend_get_notice',
                nonce: vn_frontend.nonce,
                id: noticeId
            },
            success: function(response) {
                if (response.success) {
                    var notice = response.data;
                    
                    // 폼에 데이터 채우기
                    $('#notice-id').val(notice.id);
                    $('#version-number').val(notice.version_number);
                    $('#notice-date').val(notice.notice_date);
                    $('#title').val(notice.title);
                    $('#content').val(notice.content);
                    $('#year-month').val(notice.year_month);
                    $('#sort-order').val(notice.sort_order);
                    
                    // 폼 표시
                    showNoticeForm(true);
                } else {
                    showNotice('error', response.data || '공지사항을 불러올 수 없습니다.');
                }
            },
            error: function() {
                showNotice('error', '공지사항을 불러올 수 없습니다.');
            },
            complete: function() {
                state.isLoading = false;
            }
        });
    }
    
    // 공지사항 삭제
    function deleteNotice(noticeId) {
        if (!confirm(vn_frontend.messages.delete_confirm)) {
            return;
        }
        
        if (state.isLoading) return;
        
        state.isLoading = true;
        var $deleteBtn = $('.delete-notice[data-id="' + noticeId + '"]');
        $deleteBtn.prop('disabled', true).html('<span class="vn-fa-icon">⏳</span>');
        
        $.ajax({
            url: vn_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'vn_frontend_delete_notice',
                nonce: vn_frontend.nonce,
                id: noticeId
            },
            success: function(response) {
                if (response.success) {
                    // 알림 제거 - 항목 제거 애니메이션만 실행
                    var $item = $('.vn-fa-notice-item[data-id="' + noticeId + '"]');
                    $item.fadeOut(400, function() {
                        $(this).remove();
                        
                        // 그룹이 비었는지 확인
                        $('.vn-fa-notice-group').each(function() {
                            if ($(this).find('.vn-fa-notice-item').length === 0) {
                                $(this).fadeOut(400, function() {
                                    $(this).remove();
                                    
                                    // 전체 목록이 비었는지 확인
                                    if ($('.vn-fa-notice-group').length === 0) {
                                        location.reload();
                                    }
                                });
                            }
                        });
                    });
                } else {
                    showNotice('error', response.data || '삭제 실패');
                }
            },
            error: function() {
                showNotice('error', '삭제 실패');
            },
            complete: function() {
                state.isLoading = false;
                $deleteBtn.prop('disabled', false).html('<span class="vn-fa-icon">🗑️</span>');
            }
        });
    }
    
    // 미디어 업로더
    var mediaUploader;
    
    function openMediaUploader() {
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: '배경 이미지 선택',
            button: {
                text: '이 이미지 사용'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            setBackgroundImage(attachment.url);
        });
        
        mediaUploader.open();
    }
    
    // 배경 이미지 설정
    function setBackgroundImage(url) {
        $('#background-image-url').val(url);
        
        var $preview = $('#background-image-preview');
        if ($preview.find('img').length === 0) {
            $preview.html('<img src="" alt="배경 이미지 미리보기">');
        }
        $preview.find('img').attr('src', url);
        $preview.show();
        
        $('#remove-background-image').show();
        
        // 설정 저장
        saveSettings();
    }
    
    // 배경 이미지 제거
    function removeBackgroundImage() {
        $('#background-image-url').val('');
        $('#background-image-preview').hide();
        $('#remove-background-image').hide();
        
        // 설정 저장
        saveSettings();
    }
    
    // 설정 저장
    function saveSettings() {
        if (state.saveTimeout) {
            clearTimeout(state.saveTimeout);
        }
        
        showSaveStatus('saving');
        
        state.saveTimeout = setTimeout(function() {
            var settings = {
                action: 'vn_frontend_save_settings',
                nonce: vn_frontend.nonce,
                background_image: $('#background-image-url').val(),
                background_pages: []
            };
            
            // 선택된 페이지 수집
            if (!$('#apply-all-pages').is(':checked')) {
                $('input[name="background_pages[]"]:checked').each(function() {
                    settings.background_pages.push($(this).val());
                });
            }
            
            $.ajax({
                url: vn_frontend.ajax_url,
                type: 'POST',
                data: settings,
                success: function(response) {
                    if (response.success) {
                        showSaveStatus('saved');
                    } else {
                        showNotice('error', response.data || vn_frontend.messages.save_error);
                    }
                },
                error: function() {
                    showNotice('error', vn_frontend.messages.save_error);
                }
            });
        }, 1000);
    }
    
    // 저장 상태 표시
    function showSaveStatus(status) {
        var $status = $('.vn-fa-save-status');
        
        if (status === 'saving') {
            $status.html('<span class="vn-fa-icon">⏳</span><span>' + vn_frontend.messages.saving + '</span>').fadeIn(200);
        } else if (status === 'saved') {
            $status.html('<span class="vn-fa-icon">✅</span><span>' + vn_frontend.messages.saved + '</span>').fadeIn(200);
            
            // 3초 후 서서히 사라지기
            setTimeout(function() {
                $status.fadeOut(400);
            }, 3000);
        }
    }
    
    // 알림 표시
    function showNotice(type, message) {
        // 기존 알림 제거
        $('.vn-fa-notice').remove();
        
        var noticeHtml = '<div class="vn-fa-notice ' + type + '">' +
            '<span class="vn-fa-icon">' + (type === 'success' ? '✅' : '⚠️') + '</span>' +
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