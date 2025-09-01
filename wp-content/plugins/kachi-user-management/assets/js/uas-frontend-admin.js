// UAS Frontend Admin JavaScript
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
        $('.uas-fa-tab-item').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            if (!$this.hasClass('active')) {
                $('.uas-fa-tab-item').removeClass('active');
                $this.addClass('active');
                
                var section = $this.data('section');
                if (section) {
                    switchSection(section);
                }
            }
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
        
        // 배경 설정 저장 버튼
        $('#save-background-settings').on('click', function(e) {
            e.preventDefault();
            saveBackgroundSettings();
        });
    }
    
    // 섹션 전환
    function switchSection(section) {
        state.currentSection = section;
        
        // 모든 섹션 숨기기
        $('.uas-fa-section').removeClass('active');
        
        // 선택된 섹션 표시
        $('.uas-fa-section[data-section="' + section + '"]').addClass('active');
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
    }
    
    // 배경 이미지 제거
    function removeBackgroundImage() {
        $('#background-image-url').val('');
        $('#background-image-preview').hide();
        $('#remove-background-image').hide();
    }
    
    // 배경 설정 저장
    function saveBackgroundSettings() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        var $button = $('#save-background-settings');
        $button.prop('disabled', true).addClass('uas-fa-loading');
        
        var settings = {
            action: 'uas_frontend_save_background',
            nonce: uas_frontend.nonce,
            background_image: $('#background-image-url').val(),
            background_position: $('#background-position').val(),
            background_size: $('#background-size').val()
        };
        
        $.ajax({
            url: uas_frontend.ajax_url,
            type: 'POST',
            data: settings,
            success: function(response) {
                if (response.success) {
                    // 저장 성공 애니메이션
                    showSaveSuccess();
                } else {
                    alert(response.data || uas_frontend.messages.save_error);
                }
            },
            error: function() {
                alert(uas_frontend.messages.save_error);
            },
            complete: function() {
                state.isLoading = false;
                $button.prop('disabled', false).removeClass('uas-fa-loading');
            }
        });
    }
    
    // 저장 성공 표시
    function showSaveSuccess() {
        var $button = $('#save-background-settings');
        var originalText = $button.find('span:last').text();
        
        $button.find('span:last').text('저장되었습니다!');
        $button.css('background', '#198754');
        
        setTimeout(function() {
            $button.find('span:last').text(originalText);
            $button.css('background', '');
        }, 2000);
    }
    
})(jQuery);