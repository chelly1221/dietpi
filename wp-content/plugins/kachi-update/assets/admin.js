jQuery(document).ready(function($) {
    // 페이지 로드 시에는 AJAX 로드하지 않음 (PHP에서 이미 렌더링됨)
    // Edit, Delete, Save 후에만 AJAX로 리로드
    
    // Form submission
    $('#version-notice-form').on('submit', function(e) {
        e.preventDefault();
        
        var $submitButton = $(this).find('button[type="submit"]');
        $submitButton.prop('disabled', true).text('Saving...');
        
        var formData = {
            action: 'version_notice_save',
            nonce: versionNoticeAjax.nonce,
            id: $('#notice-id').val(),
            version_number: $('#version-number').val(),
            date: $('#date').val(),
            title: $('#title').val(),
            content: $('#content').val(),
            year_month: $('#year-month').val(),
            sort_order: $('#sort-order').val()
        };
        
        $.post(versionNoticeAjax.ajaxurl, formData, function(response) {
            if (response.success) {
                // 성공 메시지 표시 후 새로고침
                $submitButton.text('Saved!');
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else {
                alert('Error: ' + response.data);
                $submitButton.prop('disabled', false).text('Save Notice');
            }
        }).fail(function() {
            alert('Network error. Please try again.');
            $submitButton.prop('disabled', false).text('Save Notice');
        });
    });
    
    // Settings form submission
    $('#version-notice-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'version_notice_save_settings',
            nonce: versionNoticeAjax.nonce,
            background_image: $('#background-image-id').val(),
            background_pages: []
        };
        
        // Get selected pages if not applying to all
        if (!$('#apply-all-pages').is(':checked')) {
            $('input[name="background_pages[]"]:checked').each(function() {
                formData.background_pages.push($(this).val());
            });
        }
        
        $.post(versionNoticeAjax.ajaxurl, formData, function(response) {
            if (response.success) {
                alert(response.data);
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Media uploader for background image
    var mediaUploader;
    
    $('#upload-background-image').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: 'Select Background Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#background-image-id').val(attachment.url);
            $('#background-image-preview').html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto;">');
            $('#remove-background-image').show();
        });
        
        mediaUploader.open();
    });
    
    // Remove background image
    $('#remove-background-image').on('click', function(e) {
        e.preventDefault();
        $('#background-image-id').val('');
        $('#background-image-preview').html('');
        $(this).hide();
    });
    
    // Toggle page selection
    $('#apply-all-pages').on('change', function() {
        if ($(this).is(':checked')) {
            $('#page-selection').hide();
            $('input[name="background_pages[]"]').prop('checked', false);
        } else {
            $('#page-selection').show();
        }
    });
    
    // Cancel edit
    $('#cancel-edit').on('click', function() {
        resetForm();
    });
    
    // Edit notice
    $(document).on('click', '.edit-notice', function() {
        var noticeId = $(this).data('id');
        
        $.post(versionNoticeAjax.ajaxurl, {
            action: 'version_notice_get',
            nonce: versionNoticeAjax.nonce,
            id: noticeId
        }, function(response) {
            if (response.success) {
                var notice = response.data;
                $('#notice-id').val(notice.id);
                $('#version-number').val(notice.version_number);
                $('#date').val(notice.notice_date);
                $('#title').val(notice.title);
                $('#content').val(notice.content);
                $('#year-month').val(notice.year_month);
                $('#sort-order').val(notice.sort_order);
                
                $('#version-notice-form').addClass('editing');
                $('html, body').animate({
                    scrollTop: $('#version-notice-form').offset().top - 50
                }, 500);
            }
        });
    });
    
    // Delete notice
    $(document).on('click', '.delete-notice', function() {
        if (!confirm('Are you sure you want to delete this notice?')) {
            return;
        }
        
        var noticeId = $(this).data('id');
        var $button = $(this);
        
        $button.prop('disabled', true).text('Deleting...');
        
        $.post(versionNoticeAjax.ajaxurl, {
            action: 'version_notice_delete',
            nonce: versionNoticeAjax.nonce,
            id: noticeId
        }, function(response) {
            if (response.success) {
                // 삭제 성공 시 바로 새로고침
                location.reload();
            } else {
                alert('Error: ' + response.data);
                $button.prop('disabled', false).text('Delete');
            }
        }).fail(function() {
            alert('Network error. Please try again.');
            $button.prop('disabled', false).text('Delete');
        });
    });
    
    // Load all notices
    function loadNotices() {
        $('#notices-list').html('<p>Loading...</p>');
        
        $.ajax({
            url: versionNoticeAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'version_notice_list',
                nonce: versionNoticeAjax.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response && response.success) {
                    displayNotices(response.data);
                } else {
                    $('#notices-list').html('<p>Error loading notices: ' + (response.data || 'Unknown error') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                $('#notices-list').html('<p>Error loading notices: ' + error + '</p>');
            }
        });
    }
    
    // Display notices grouped by month
    function displayNotices(notices) {
        if (!notices || notices.length === 0) {
            $('#notices-list').html('<p>No notices found.</p>');
            return;
        }
        
        var grouped = {};
        notices.forEach(function(notice) {
            if (!grouped[notice.year_month]) {
                grouped[notice.year_month] = [];
            }
            grouped[notice.year_month].push(notice);
        });
        
        var html = '';
        Object.keys(grouped).sort().reverse().forEach(function(yearMonth) {
            var parts = yearMonth.split('-');
            var year = parts[0];
            var month = parseInt(parts[1]);
            
            html += '<div class="notice-group">';
            html += '<div class="notice-group-header">' + year + '년 ' + month + '월</div>';
            
            grouped[yearMonth].forEach(function(notice) {
                html += '<div class="notice-item">';
                html += '<div class="notice-header">';
                html += '<div class="notice-meta">';
                html += '<span class="notice-version">' + notice.version_number + '</span>';
                html += '<span class="notice-date">' + notice.notice_date + '</span>';
                html += '<span class="notice-title">' + notice.title + '</span>';
                html += '</div>';
                html += '<div class="notice-actions">';
                html += '<button class="button button-small edit-notice" data-id="' + notice.id + '">Edit</button>';
                html += '<button class="button button-small delete-notice" data-id="' + notice.id + '">Delete</button>';
                html += '</div>';
                html += '</div>';
                html += '<div class="notice-content">' + notice.content + '</div>';
                html += '</div>';
            });
            
            html += '</div>';
        });
        
        $('#notices-list').html(html);
    }
    
    // Reset form
    function resetForm() {
        $('#version-notice-form')[0].reset();
        $('#notice-id').val('');
        $('#version-notice-form').removeClass('editing');
    }
});