// Kachi Query System Frontend Admin JavaScript
(function($) {
    'use strict';
    
    // 상태 관리
    var state = {
        currentSection: 'info',
        isLoading: false
    };
    
    // 문서 준비 완료 시 실행
    $(document).ready(function() {
        bindEvents();
    });
    
    // 이벤트 바인딩
    function bindEvents() {
        // 탭 네비게이션
        $('.kachi-fa-tab-item').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            if (!$this.hasClass('active')) {
                $('.kachi-fa-tab-item').removeClass('active');
                $this.addClass('active');
                
                var section = $this.data('section');
                if (section) {
                    switchSection(section);
                }
            }
        });
        
        // API 테스트 버튼
        $('#test-api-connection').on('click', function(e) {
            e.preventDefault();
            testApiConnection();
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
        
        // 일반 설정 저장 버튼
        $('#save-general-settings').on('click', function(e) {
            e.preventDefault();
            saveGeneralSettings();
        });
        
        // 디자인 설정 저장 버튼
        $('#save-design-settings').on('click', function(e) {
            e.preventDefault();
            saveDesignSettings();
        });
        
        // Range 입력 실시간 업데이트
        $('#background-overlay').on('input', function() {
            $('.kachi-fa-range-value').text($(this).val());
        });
    }
    
    // 섹션 전환
    function switchSection(section) {
        state.currentSection = section;
        
        // 모든 섹션 숨기기
        $('.kachi-fa-section').removeClass('active');
        
        // 선택된 섹션 표시
        $('.kachi-fa-section[data-section="' + section + '"]').addClass('active');
    }
    
    // API 연결 테스트
    function testApiConnection() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        var $button = $('#test-api-connection');
        var $result = $('#test-result');
        
        $button.prop('disabled', true).addClass('kachi-fa-loading');
        $result.removeClass('success error').addClass('loading');
        $result.html(kachi_frontend.messages.test_in_progress);
        
        $.ajax({
            url: kachi_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'kachi_test_api',
                nonce: kachi_frontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('loading error').addClass('success');
                    $result.html('✅ ' + response.data);
                } else {
                    $result.removeClass('loading success').addClass('error');
                    $result.html('❌ ' + kachi_frontend.messages.test_error + ' ' + response.data);
                }
            },
            error: function() {
                $result.removeClass('loading success').addClass('error');
                $result.html('❌ ' + kachi_frontend.messages.test_error + ' 네트워크 오류');
            },
            complete: function() {
                state.isLoading = false;
                $button.prop('disabled', false).removeClass('kachi-fa-loading');
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
            setBackgroundImage(attachment.id, attachment.url);
        });
        
        mediaUploader.open();
    }
    
    // 배경 이미지 설정
    function setBackgroundImage(id, url) {
        $('#background-image-id').val(id);
        
        var $preview = $('#background-image-preview');
        if ($preview.find('img').length === 0) {
            $preview.html('<img src="" alt="배경 이미지 미리보기">');
        }
        $preview.find('img').attr('src', url);
        $preview.show();
        
        // 제거 버튼 표시
        if ($('#remove-background-image').length === 0) {
            $('#upload-background-image').after(
                '<button type="button" class="kachi-fa-btn-secondary" id="remove-background-image">' +
                '<span class="kachi-fa-icon">❌</span>이미지 제거</button>'
            );
            $('#remove-background-image').on('click', function(e) {
                e.preventDefault();
                removeBackgroundImage();
            });
        } else {
            $('#remove-background-image').show();
        }
    }
    
    // 배경 이미지 제거
    function removeBackgroundImage() {
        $('#background-image-id').val('');
        $('#background-image-preview').hide();
        $('#remove-background-image').hide();
    }
    
    // 일반 설정 저장
    function saveGeneralSettings() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        var $button = $('#save-general-settings');
        $button.prop('disabled', true).addClass('kachi-fa-loading');
        
        var settings = {
            action: 'kachi_save_settings',
            nonce: kachi_frontend.nonce,
            api_url: $('#api-url').val(),
            require_login: $('#require-login').is(':checked') ? 'true' : 'false',
            max_query_length: $('#max-query-length').val()
        };
        
        $.ajax({
            url: kachi_frontend.ajax_url,
            type: 'POST',
            data: settings,
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data);
                    // API URL 업데이트
                    if (settings.api_url) {
                        kachi_frontend.api_url = settings.api_url;
                    }
                } else {
                    showNotice('error', response.data || kachi_frontend.messages.save_error);
                }
            },
            error: function() {
                showNotice('error', kachi_frontend.messages.save_error);
            },
            complete: function() {
                state.isLoading = false;
                $button.prop('disabled', false).removeClass('kachi-fa-loading');
            }
        });
    }
    
    // 디자인 설정 저장
    function saveDesignSettings() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        var $button = $('#save-design-settings');
        $button.prop('disabled', true).addClass('kachi-fa-loading');
        
        var settings = {
            action: 'kachi_save_settings',
            nonce: kachi_frontend.nonce,
            background_image: $('#background-image-id').val(),
            background_overlay: $('#background-overlay').val()
        };
        
        $.ajax({
            url: kachi_frontend.ajax_url,
            type: 'POST',
            data: settings,
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data);
                } else {
                    showNotice('error', response.data || kachi_frontend.messages.save_error);
                }
            },
            error: function() {
                showNotice('error', kachi_frontend.messages.save_error);
            },
            complete: function() {
                state.isLoading = false;
                $button.prop('disabled', false).removeClass('kachi-fa-loading');
            }
        });
    }
    
    // 알림 표시
    function showNotice(type, message) {
        var $notice = $('.kachi-fa-notice');
        
        $notice.removeClass('success error').addClass(type);
        $notice.html(message);
        $notice.show();
        
        // 3초 후 자동 숨김
        setTimeout(function() {
            $notice.fadeOut(300);
        }, 3000);
    }
    
})(jQuery);