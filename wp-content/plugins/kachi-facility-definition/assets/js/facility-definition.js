jQuery(document).ready(function($) {
    const form = document.getElementById("facilityForm");
    const list = document.getElementById("facilityList");
    const loadingIndicator = document.getElementById("loadingIndicator");
    
    // 초기 데이터 로드
    loadDefinitions();
    
    // 폼 제출 이벤트
    form.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        const key = document.getElementById("facilityKey").value.trim();
        const value1 = document.getElementById("facilityValue1").value.trim();
        const value2 = document.getElementById("facilityValue2").value.trim();
        
        if (!key || !value1) {
            showNotification("정의 항목을 모두 입력해주세요.", "error");
            return;
        }
        
        const newValues = [value1];
        if (value2) newValues.push(value2);
        
        let defs = groupToArray(window.facilityDefinitionsFromPHP);
        const existing = defs.find(def => def.key === key);
        
        if (existing) {
            const existingValues = existing.value.split(',').map(v => v.trim());
            newValues.forEach(v => {
                if (!existingValues.includes(v)) existingValues.push(v);
            });
            existing.value = existingValues.join(', ');
        } else {
            defs.push({ key, value: newValues.join(', ') });
        }
        
        await saveDefinitions(defs);
    });
    
    // 삭제 함수
    window.deleteDefinition = async function(key) {
        if (!confirm(`"${key}" 항목을 삭제하시겠습니까?`)) {
            return;
        }
        
        const defs = groupToArray(window.facilityDefinitionsFromPHP)
            .filter(def => def.key !== key);
        
        await saveDefinitions(defs);
    };
    
    // 편집 시작 함수
    window.editDefinition = function(key) {
        const li = document.querySelector(`li[data-key="${key}"]`);
        if (!li) return;
        
        const values = window.facilityDefinitionsFromPHP[key];
        const valueStr = values.join(', ');
        
        li.classList.add('editing');
        
        // 편집 인터페이스 표시
        const editDiv = li.querySelector('.facility-item-edit');
        const keyInput = editDiv.querySelector('.edit-key');
        const valueInput = editDiv.querySelector('.edit-value');
        
        keyInput.value = key;
        valueInput.value = valueStr;
        valueInput.focus();
        
        // 입력 필드 끝으로 커서 이동
        valueInput.setSelectionRange(valueInput.value.length, valueInput.value.length);
    };
    
    // 편집 저장 함수
    window.saveEdit = async function(oldKey) {
        const li = document.querySelector(`li[data-key="${oldKey}"]`);
        if (!li) return;
        
        const editDiv = li.querySelector('.facility-item-edit');
        const newKey = editDiv.querySelector('.edit-key').value.trim();
        const newValue = editDiv.querySelector('.edit-value').value.trim();
        
        if (!newKey || !newValue) {
            showNotification("모든 필드를 입력해주세요.", "error");
            return;
        }
        
        let defs = groupToArray(window.facilityDefinitionsFromPHP);
        
        // 기존 항목 제거
        defs = defs.filter(def => def.key !== oldKey);
        
        // 새 항목 추가 또는 병합
        const existing = defs.find(def => def.key === newKey);
        if (existing && newKey !== oldKey) {
            // 다른 키와 중복되는 경우 값 병합
            const existingValues = existing.value.split(',').map(v => v.trim());
            const newValues = newValue.split(',').map(v => v.trim());
            newValues.forEach(v => {
                if (!existingValues.includes(v)) existingValues.push(v);
            });
            existing.value = existingValues.join(', ');
        } else {
            // 새 항목 추가
            defs.push({ key: newKey, value: newValue });
        }
        
        await saveDefinitions(defs);
    };
    
    // 편집 취소 함수
    window.cancelEdit = function(key) {
        const li = document.querySelector(`li[data-key="${key}"]`);
        if (li) {
            li.classList.remove('editing');
        }
    };
    
    // 데이터 로드 함수
    function loadDefinitions() {
        showLoading(true);
        
        $.ajax({
            url: facility_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'load_facility_definitions',
                nonce: facility_ajax.nonce
            },
            success: function(response) {
                window.facilityDefinitionsFromPHP = groupByKey(response.definitions || []);
                renderFacilityList(window.facilityDefinitionsFromPHP);
                showLoading(false);
            },
            error: function() {
                showNotification('데이터 로드 실패', 'error');
                showLoading(false);
            }
        });
    }
    
    // 데이터 저장 함수
    async function saveDefinitions(defs) {
        showLoading(true);
        
        return new Promise((resolve, reject) => {
            $.ajax({
                url: facility_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_facility_definitions',
                    nonce: facility_ajax.nonce,
                    facility_definitions_json: JSON.stringify(defs)
                },
                success: function(response) {
                    showLoading(false);
                    
                    if (response.success) {
                        // 성공 애니메이션
                        showNotification('✅ 저장되었습니다!', 'success');
                        
                        // 성공 후 재로드
                        loadDefinitions();
                        
                        // 폼 초기화 애니메이션
                        const inputs = form.querySelectorAll('input');
                        inputs.forEach((input, index) => {
                            setTimeout(() => {
                                input.value = "";
                                input.style.transform = "scale(0.95)";
                                setTimeout(() => {
                                    input.style.transform = "scale(1)";
                                }, 150);
                            }, index * 50);
                        });
                        
                        // 첫 번째 입력 필드에 포커스
                        setTimeout(() => {
                            document.getElementById("facilityKey").focus();
                        }, 200);
                        
                        resolve(response);
                    } else {
                        showNotification(response.data || '저장 실패', 'error');
                        reject(response);
                    }
                },
                error: function() {
                    showLoading(false);
                    showNotification('❌ 저장 실패', 'error');
                    reject();
                }
            });
        });
    }
    
    // 리스트 렌더링 함수
    function renderFacilityList(defMap) {
        list.innerHTML = "";
        const entries = Object.entries(defMap);
        
        if (entries.length === 0) {
            list.innerHTML = '<li style="text-align: center; color: #95a5a6; padding: 40px;">등록된 시설 정의가 없습니다.</li>';
            return;
        }
        
        entries.forEach(([key, values], index) => {
            const li = document.createElement("li");
            li.setAttribute('data-key', key);
            li.style.animationDelay = `${index * 50}ms`;
            li.innerHTML = `
                <div class="facility-item-content">
                    <div class="facility-item-text">
                        <strong>${key}</strong> → ${values.join(', ')}
                    </div>
                    <div class="facility-item-actions">
                        <button class="action-btn edit-btn" onclick="editDefinition('${key}')" title="편집">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button class="action-btn delete-btn" onclick="deleteDefinition('${key}')" title="삭제">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="facility-item-edit">
                    <div class="facility-edit-inputs">
                        <input type="text" class="edit-key" placeholder="용어" />
                        <input type="text" class="edit-value" placeholder="정의" />
                    </div>
                    <div class="facility-edit-buttons">
                        <button class="edit-cancel-btn" onclick="cancelEdit('${key}')">취소</button>
                        <button class="edit-save-btn" onclick="saveEdit('${key}')">저장</button>
                    </div>
                </div>
            `;
            list.appendChild(li);
        });
    }
    
    // 로딩 표시 함수
    function showLoading(show) {
        if (show) {
            loadingIndicator.style.display = 'block';
            setTimeout(() => {
                loadingIndicator.style.opacity = '1';
            }, 10);
        } else {
            loadingIndicator.style.opacity = '0';
            setTimeout(() => {
                loadingIndicator.style.display = 'none';
            }, 300);
        }
    }
    
    // 알림 표시 함수
    function showNotification(message, type = 'info') {
        // 기존 알림 제거
        $('.facility-notification').remove();
        
        const notification = $('<div class="facility-notification"></div>');
        notification.addClass(`notification-${type}`);
        notification.html(message);
        
        // 아이콘 추가
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        notification.prepend(`<span class="notification-icon">${icons[type]}</span> `);
        
        $('body').append(notification);
        
        // 애니메이션
        setTimeout(() => {
            notification.addClass('show');
        }, 10);
        
        // 자동 숨김
        setTimeout(() => {
            notification.removeClass('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    // 데이터 그룹핑 함수
    function groupByKey(defs) {
        const map = {};
        defs.forEach(def => {
            const valList = def.value.split(',').map(v => v.trim());
            if (!map[def.key]) map[def.key] = [];
            valList.forEach(v => {
                if (!map[def.key].includes(v)) {
                    map[def.key].push(v);
                }
            });
        });
        return map;
    }
    
    // 그룹을 배열로 변환
    function groupToArray(defMap) {
        const arr = [];
        for (const key in defMap) {
            arr.push({ key, value: defMap[key].join(', ') });
        }
        return arr;
    }
    
    // Enter 키로 다음 필드로 이동
    $('#facilityKey, #facilityValue1').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const nextInput = $(this).parent().next().find('input');
            if (nextInput.length) {
                nextInput.focus();
            } else {
                form.dispatchEvent(new Event('submit'));
            }
        }
    });
    
    // 편집 모드에서 Enter 키 처리
    $(document).on('keypress', '.facility-item-edit input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const key = $(this).closest('li').attr('data-key');
            saveEdit(key);
        }
    });
    
    // ESC 키로 편집 취소
    $(document).on('keydown', '.facility-item-edit input', function(e) {
        if (e.which === 27) {
            e.preventDefault();
            const key = $(this).closest('li').attr('data-key');
            cancelEdit(key);
        }
    });
});

// 알림 스타일 추가
const notificationStyles = `
<style>
.facility-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    border-radius: 12px;
    background: white;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    font-size: 15px;
    font-weight: 500;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 10px;
    max-width: 400px;
}

.facility-notification.show {
    opacity: 1;
    transform: translateX(0);
}

.notification-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border: 1px solid #c3e6cb;
}

.notification-error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.notification-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
    color: #856404;
    border: 1px solid #ffeeba;
}

.notification-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.notification-icon {
    font-size: 18px;
}

@media (max-width: 640px) {
    .facility-notification {
        right: 10px;
        left: 10px;
        max-width: none;
    }
}
</style>
`;

jQuery('head').append(notificationStyles);