// KAC Login Frontend Admin JavaScript
(function($) {
    'use strict';
    
    // 상태 관리
    var state = {
        currentSection: 'organization',
        isLoading: false,
        saveTimeout: null
    };
    
    // 문서 준비 완료 시 실행
    $(document).ready(function() {
        bindEvents();
        
        // 조직 데이터가 있으면 표시
        if (typeof organizationData !== 'undefined') {
            displaySosokList();
        }
        
        // 화면 크기에 따른 조직 목록 높이 조정
        adjustOrgListHeight();
        
        // 윈도우 리사이즈 시 높이 재조정
        $(window).on('resize', function() {
            adjustOrgListHeight();
        });
    });
    
    // 조직 목록 높이 자동 조정
    function adjustOrgListHeight() {
        var viewportHeight = $(window).height();
        var headerHeight = $('.kac-fa-header-wrapper').outerHeight() || 140;
        var sectionHeaderHeight = $('.kac-fa-section-header').outerHeight() || 80;
        var contentPadding = 64; // 상하 패딩
        
        // 조직 컨테이너 높이 계산
        var containerHeight = viewportHeight - headerHeight - sectionHeaderHeight - contentPadding;
        
        // 최소 높이 설정
        if (containerHeight < 400) {
            containerHeight = 400;
        }
        
        // 조직 컨테이너에 높이 적용
        $('.kac-fa-org-container').css('height', containerHeight + 'px');
        
        // 각 섹션 내부의 목록 높이 계산
        var sectionPadding = 48; // 섹션 내부 패딩
        var sectionHeaderHeight = 34; // h3 높이
        var labelHeight = 28; // 선택된 레이블 높이
        var inputHeight = 60; // 입력 필드 영역 높이
        var listMargin = 16; // 목록 하단 마진
        
        var listHeight = containerHeight - sectionPadding - sectionHeaderHeight - labelHeight - inputHeight - listMargin;
        
        // 최소 높이 설정
        if (listHeight < 200) {
            listHeight = 200;
        }
        
        // 모든 조직 목록에 높이 적용
        $('.kac-fa-org-list').css('height', listHeight + 'px');
    }
    
    // 이벤트 바인딩
    function bindEvents() {
        // 탭 네비게이션
        $('.kac-fa-tab-item').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            if (!$this.hasClass('active')) {
                $('.kac-fa-tab-item').removeClass('active');
                $this.addClass('active');
                
                var section = $this.data('section');
                if (section) {
                    switchSection(section);
                }
            }
        });
        
        // 자동 저장 입력 필드
        $('.auto-save').on('input change', function() {
            var $this = $(this);
            
            // 기존 타이머 취소
            if (state.saveTimeout) {
                clearTimeout(state.saveTimeout);
            }
            
            // 저장 중 표시
            showSaveStatus('saving');
            
            // 1초 후 저장
            state.saveTimeout = setTimeout(function() {
                saveSettings();
            }, 1000);
        });
        
        // 이미지 업로드 버튼
        $('.kac-upload-button').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var targetInput = $(button.data('target'));
            
            var customUploader = wp.media({
                title: '이미지 선택',
                button: {
                    text: '이미지 사용'
                },
                multiple: false
            });
            
            customUploader.on('select', function() {
                var attachment = customUploader.state().get('selection').first().toJSON();
                targetInput.val(attachment.url).trigger('change');
                
                // 미리보기 업데이트
                var previewContainer = targetInput.closest('.kac-fa-form-group').find('.kac-fa-image-preview');
                if (previewContainer.length === 0) {
                    targetInput.closest('.kac-fa-form-group').append(
                        '<div class="kac-fa-image-preview"><img src="' + attachment.url + '" alt="미리보기"></div>'
                    );
                } else {
                    previewContainer.find('img').attr('src', attachment.url);
                }
            });
            
            customUploader.open();
        });
    }
    
    // 섹션 전환
    function switchSection(section) {
        state.currentSection = section;
        
        // 모든 섹션 숨기기
        $('.kac-fa-section').removeClass('active');
        
        // 선택된 섹션 표시
        $('.kac-fa-section[data-section="' + section + '"]').addClass('active');
        
        // 조직 관리 섹션으로 전환 시 높이 재조정
        if (section === 'organization') {
            setTimeout(function() {
                adjustOrgListHeight();
            }, 100);
        }
    }
    
    // 저장 상태 표시
    function showSaveStatus(status) {
        var $status = $('.kac-fa-save-status');
        
        if (status === 'saving') {
            $status.html('<span class="kac-fa-icon">⏳</span><span>' + kac_frontend.messages.saving + '</span>').fadeIn(200);
        } else if (status === 'saved') {
            $status.html('<span class="kac-fa-icon">✅</span><span>' + kac_frontend.messages.saved + '</span>').fadeIn(200);
            
            // 3초 후 서서히 사라지기
            setTimeout(function() {
                $status.fadeOut(400);
            }, 3000);
        }
    }
    
    // 설정 저장
    function saveSettings() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        
        var settings = {
            logo_url: $('#logo_url').val(),
            background_url: $('#background_url').val(),
            complete_background_url: $('#complete_background_url').val(),
            complete_image_url: $('#complete_image_url').val(),
            default_redirect: $('#default_redirect').val(),
            terms_url: $('#terms_url').val(),
            privacy_url: $('#privacy_url').val()
        };
        
        $.ajax({
            url: kac_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'kac_frontend_save_settings',
                nonce: kac_frontend.nonce,
                ...settings
            },
            success: function(response) {
                if (response.success) {
                    showSaveStatus('saved');
                } else {
                    showNotice('error', response.data || kac_frontend.messages.save_error);
                }
            },
            error: function() {
                showNotice('error', kac_frontend.messages.save_error);
            },
            complete: function() {
                state.isLoading = false;
            }
        });
    }
    
    // 알림 표시
    function showNotice(type, message) {
        // 기존 알림 제거
        $('.kac-fa-notice').remove();
        
        var noticeHtml = '<div class="kac-fa-notice ' + type + '">' +
            '<span class="kac-fa-icon">' + (type === 'success' ? '✅' : '⚠️') + '</span>' +
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
    
    // 조직 관리 관련 함수들
    window.displaySosokList = function() {
        var sosokList = document.getElementById('sosok-list');
        if (!sosokList) return;
        
        sosokList.innerHTML = '';
        
        for (var sosok in organizationData) {
            var item = document.createElement('div');
            item.className = 'kac-fa-org-item';
            if (sosok === '관리자') {
                item.className += ' admin-item';
            }
            
            var innerHTML = '<span>' + sosok;
            if (sosok === '관리자') {
                innerHTML += '<span class="admin-label">관리자</span>';
            }
            innerHTML += '</span>';
            
            if (sosok !== '관리자') {
                innerHTML += '<span class="item-actions">';
                innerHTML += '<button class="edit-btn" onclick="editSosok(\'' + sosok + '\')" title="수정"><svg><use href="#kac-icon-edit"></use></svg></button>';
                innerHTML += '<button class="remove-btn" onclick="removeSosok(\'' + sosok + '\', event)" title="삭제"><svg><use href="#kac-icon-delete"></use></svg></button>';
                innerHTML += '</span>';
            } else {
                innerHTML += '<span style="width: 60px;"></span>';
            }
            
            item.innerHTML = innerHTML;
            item.onclick = function(s) {
                return function(e) {
                    if (!e.target.closest('.edit-btn') && !e.target.closest('.remove-btn')) {
                        selectSosok(s);
                    }
                };
            }(sosok);
            sosokList.appendChild(item);
        }
    };
    
    window.selectSosok = function(sosok) {
        selectedSosok = sosok;
        selectedBuseo = null;
        
        // UI 업데이트
        document.querySelectorAll('#sosok-list .kac-fa-org-item').forEach(function(item) {
            item.classList.remove('selected');
            if (item.querySelector('span').textContent.includes(sosok)) {
                item.classList.add('selected');
            }
        });
        
        document.getElementById('selected-sosok').textContent = '선택된 소속: ' + sosok;
        document.getElementById('selected-buseo').textContent = '부서를 선택하세요';
        
        // 부서 입력 활성화
        document.getElementById('new-buseo').disabled = false;
        document.getElementById('add-buseo-btn').disabled = false;
        
        // 현장 입력 비활성화
        document.getElementById('new-site').disabled = true;
        document.getElementById('add-site-btn').disabled = true;
        
        displayBuseoList();
        document.getElementById('site-list').innerHTML = '';
    };
    
    window.displayBuseoList = function() {
        var buseoList = document.getElementById('buseo-list');
        if (!buseoList) return;
        
        buseoList.innerHTML = '';
        
        if (!selectedSosok || !organizationData[selectedSosok]) return;
        
        for (var buseo in organizationData[selectedSosok]) {
            var item = document.createElement('div');
            item.className = 'kac-fa-org-item';
            if (selectedSosok === '관리자' && buseo === '관리자') {
                item.className += ' admin-item';
            }
            
            var innerHTML = '<span>' + buseo;
            if (selectedSosok === '관리자' && buseo === '관리자') {
                innerHTML += '<span class="admin-label">관리자</span>';
            }
            innerHTML += '</span>';
            
            if (!(selectedSosok === '관리자' && buseo === '관리자')) {
                innerHTML += '<span class="item-actions">';
                innerHTML += '<button class="edit-btn" onclick="editBuseo(\'' + buseo + '\')" title="수정"><svg><use href="#kac-icon-edit"></use></svg></button>';
                innerHTML += '<button class="remove-btn" onclick="removeBuseo(\'' + buseo + '\', event)" title="삭제"><svg><use href="#kac-icon-delete"></use></svg></button>';
                innerHTML += '</span>';
            } else {
                innerHTML += '<span style="width: 60px;"></span>';
            }
            
            item.innerHTML = innerHTML;
            item.onclick = function(b) {
                return function(e) {
                    if (!e.target.closest('.edit-btn') && !e.target.closest('.remove-btn')) {
                        selectBuseo(b);
                    }
                };
            }(buseo);
            buseoList.appendChild(item);
        }
    };
    
    window.selectBuseo = function(buseo) {
        selectedBuseo = buseo;
        
        // UI 업데이트
        document.querySelectorAll('#buseo-list .kac-fa-org-item').forEach(function(item) {
            item.classList.remove('selected');
            if (item.querySelector('span').textContent.includes(buseo)) {
                item.classList.add('selected');
            }
        });
        
        document.getElementById('selected-buseo').textContent = '선택된 부서: ' + buseo;
        
        // 현장 입력 활성화
        document.getElementById('new-site').disabled = false;
        document.getElementById('add-site-btn').disabled = false;
        
        displaySiteList();
    };
    
    window.displaySiteList = function() {
        var siteList = document.getElementById('site-list');
        if (!siteList) return;
        
        siteList.innerHTML = '';
        
        if (!selectedSosok || !selectedBuseo || !organizationData[selectedSosok][selectedBuseo]) return;
        
        organizationData[selectedSosok][selectedBuseo].forEach(function(site, index) {
            var item = document.createElement('div');
            item.className = 'kac-fa-org-item';
            if (selectedSosok === '관리자' && selectedBuseo === '관리자' && site === '관리자') {
                item.className += ' admin-item';
            }
            
            var innerHTML = '<span>' + site;
            if (selectedSosok === '관리자' && selectedBuseo === '관리자' && site === '관리자') {
                innerHTML += '<span class="admin-label">전체 접근</span>';
            }
            innerHTML += '</span>';
            
            if (!(selectedSosok === '관리자' && selectedBuseo === '관리자' && site === '관리자')) {
                innerHTML += '<span class="item-actions">';
                innerHTML += '<button class="edit-btn" onclick="editSite(' + index + ')" title="수정"><svg><use href="#kac-icon-edit"></use></svg></button>';
                innerHTML += '<button class="remove-btn" onclick="removeSite(' + index + ', event)" title="삭제"><svg><use href="#kac-icon-delete"></use></svg></button>';
                innerHTML += '</span>';
            } else {
                innerHTML += '<span style="width: 60px;"></span>';
            }
            
            item.innerHTML = innerHTML;
            siteList.appendChild(item);
        });
    };
    
    // 실시간 저장 함수
    function saveOrganizationItem(actionType, itemType, oldValue, newValue, parentSosok, parentBuseo) {
        showSaveStatus('saving');
        
        $.ajax({
            url: kac_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'kac_frontend_update_organization_item',
                nonce: kac_frontend.nonce,
                action_type: actionType,
                item_type: itemType,
                old_value: oldValue,
                new_value: newValue,
                parent_sosok: parentSosok || '',
                parent_buseo: parentBuseo || ''
            },
            success: function(response) {
                if (response.success) {
                    showSaveStatus('saved');
                } else {
                    showNotice('error', response.data || kac_frontend.messages.save_error);
                }
            },
            error: function() {
                showNotice('error', kac_frontend.messages.save_error);
            }
        });
    }
    
    // 소속 수정
    window.editSosok = function(sosok) {
        var newName = prompt(kac_frontend.messages.edit_prompt, sosok);
        if (newName && newName !== sosok) {
            if (organizationData[newName]) {
                alert('이미 존재하는 소속입니다.');
                return;
            }
            
            organizationData[newName] = organizationData[sosok];
            delete organizationData[sosok];
            
            if (selectedSosok === sosok) {
                selectedSosok = newName;
            }
            
            displaySosokList();
            if (selectedSosok === newName) {
                selectSosok(newName);
            }
            
            // 실시간 저장
            saveOrganizationData();
        }
    };
    
    // 부서 수정
    window.editBuseo = function(buseo) {
        var newName = prompt(kac_frontend.messages.edit_prompt, buseo);
        if (newName && newName !== buseo) {
            if (organizationData[selectedSosok][newName]) {
                alert('이미 존재하는 부서입니다.');
                return;
            }
            
            organizationData[selectedSosok][newName] = organizationData[selectedSosok][buseo];
            delete organizationData[selectedSosok][buseo];
            
            if (selectedBuseo === buseo) {
                selectedBuseo = newName;
            }
            
            displayBuseoList();
            if (selectedBuseo === newName) {
                selectBuseo(newName);
            }
            
            // 실시간 저장
            saveOrganizationData();
        }
    };
    
    // 현장 수정
    window.editSite = function(index) {
        var oldSite = organizationData[selectedSosok][selectedBuseo][index];
        var newSite = prompt(kac_frontend.messages.edit_prompt, oldSite);
        
        if (newSite && newSite !== oldSite) {
            // 중복 확인
            if (organizationData[selectedSosok][selectedBuseo].includes(newSite)) {
                alert('이미 존재하는 현장입니다.');
                return;
            }
            
            // 사용자 업데이트 확인
            if (confirm(kac_frontend.messages.update_users_confirm)) {
                // 현장 이름 변경
                organizationData[selectedSosok][selectedBuseo][index] = newSite;
                displaySiteList();
                
                // 실시간 저장 및 사용자 업데이트
                saveOrganizationItem('edit', 'site', oldSite, newSite, selectedSosok, selectedBuseo);
            }
        }
    };
    
    window.addSosok = function() {
        var newSosok = document.getElementById('new-sosok').value.trim();
        if (!newSosok) return;
        
        if (organizationData[newSosok]) {
            alert('이미 존재하는 소속입니다.');
            return;
        }
        
        organizationData[newSosok] = {};
        document.getElementById('new-sosok').value = '';
        displaySosokList();
        
        // 실시간 저장
        saveOrganizationItem('add', 'sosok', '', newSosok);
    };
    
    window.addBuseo = function() {
        if (!selectedSosok) return;
        
        var newBuseo = document.getElementById('new-buseo').value.trim();
        if (!newBuseo) return;
        
        if (organizationData[selectedSosok][newBuseo]) {
            alert('이미 존재하는 부서입니다.');
            return;
        }
        
        organizationData[selectedSosok][newBuseo] = [];
        document.getElementById('new-buseo').value = '';
        displayBuseoList();
        
        // 실시간 저장
        saveOrganizationItem('add', 'buseo', '', newBuseo, selectedSosok);
    };
    
    window.addSite = function() {
        if (!selectedSosok || !selectedBuseo) return;
        
        var newSite = document.getElementById('new-site').value.trim();
        if (!newSite) return;
        
        if (organizationData[selectedSosok][selectedBuseo].includes(newSite)) {
            alert('이미 존재하는 현장입니다.');
            return;
        }
        
        organizationData[selectedSosok][selectedBuseo].push(newSite);
        document.getElementById('new-site').value = '';
        displaySiteList();
        
        // 실시간 저장
        saveOrganizationItem('add', 'site', '', newSite, selectedSosok, selectedBuseo);
    };
    
    window.removeSosok = function(sosok, event) {
        event.stopPropagation();
        if (sosok === '관리자') {
            alert('관리자 소속은 삭제할 수 없습니다.');
            return;
        }
        if (confirm('\'' + sosok + '\' 소속과 하위 부서/현장을 모두 삭제하시겠습니까?')) {
            delete organizationData[sosok];
            if (selectedSosok === sosok) {
                selectedSosok = null;
                selectedBuseo = null;
                document.getElementById('selected-sosok').textContent = '소속을 선택하세요';
                document.getElementById('selected-buseo').textContent = '부서를 선택하세요';
                document.getElementById('buseo-list').innerHTML = '';
                document.getElementById('site-list').innerHTML = '';
            }
            displaySosokList();
            
            // 실시간 저장
            saveOrganizationItem('delete', 'sosok', sosok, '');
        }
    };
    
    window.removeBuseo = function(buseo, event) {
        event.stopPropagation();
        if (!selectedSosok) return;
        
        if (selectedSosok === '관리자' && buseo === '관리자') {
            alert('관리자 부서는 삭제할 수 없습니다.');
            return;
        }
        
        if (confirm('\'' + buseo + '\' 부서와 하위 현장을 모두 삭제하시겠습니까?')) {
            delete organizationData[selectedSosok][buseo];
            if (selectedBuseo === buseo) {
                selectedBuseo = null;
                document.getElementById('selected-buseo').textContent = '부서를 선택하세요';
                document.getElementById('site-list').innerHTML = '';
            }
            displayBuseoList();
            
            // 실시간 저장
            saveOrganizationItem('delete', 'buseo', buseo, '', selectedSosok);
        }
    };
    
    window.removeSite = function(index, event) {
        event.stopPropagation();
        if (!selectedSosok || !selectedBuseo) return;
        
        var site = organizationData[selectedSosok][selectedBuseo][index];
        
        if (selectedSosok === '관리자' && selectedBuseo === '관리자' && site === '관리자') {
            alert('관리자 현장은 삭제할 수 없습니다.');
            return;
        }
        
        if (confirm('이 현장을 삭제하시겠습니까?')) {
            organizationData[selectedSosok][selectedBuseo].splice(index, 1);
            displaySiteList();
            
            // 실시간 저장
            saveOrganizationItem('delete', 'site', site, '', selectedSosok, selectedBuseo);
        }
    };
    
    // 전체 조직 데이터 저장 (수정 시 사용)
    window.saveOrganizationData = function() {
        showSaveStatus('saving');
        
        var data = {
            action: 'kac_frontend_save_organization',
            nonce: kac_frontend.nonce,
            data: JSON.stringify(organizationData)
        };
        
        $.post(kac_frontend.ajax_url, data, function(response) {
            if (response.success) {
                showSaveStatus('saved');
            } else {
                showNotice('error', response.data || kac_frontend.messages.save_error);
            }
        }).fail(function() {
            showNotice('error', kac_frontend.messages.save_error);
        });
    };
    
})(jQuery);