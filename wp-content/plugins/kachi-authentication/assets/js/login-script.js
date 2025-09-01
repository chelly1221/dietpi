/**
 * KAC Login System JavaScript
 */

document.addEventListener("DOMContentLoaded", function () {
    // 비밀번호 표시/숨김 토글 - 모든 비밀번호 필드에 적용
    const toggleBtns = document.querySelectorAll(".wp-hide-pw");
    
    toggleBtns.forEach(function(toggleBtn) {
        const passwordField = toggleBtn.closest('.wp-pwd').querySelector('input[type="password"], input[type="text"]');
        
        if (passwordField) {
            toggleBtn.addEventListener("click", function () {
                const icon = this.querySelector(".dashicons");
                
                if (passwordField.type === "password") {
                    passwordField.type = "text";
                    this.setAttribute("data-toggle", "1");
                    icon.classList.remove("dashicons-visibility");
                    icon.classList.add("dashicons-hidden");
                } else {
                    passwordField.type = "password";
                    this.setAttribute("data-toggle", "0");
                    icon.classList.remove("dashicons-hidden");
                    icon.classList.add("dashicons-visibility");
                }
            });
        }
    });

    // 회원가입 메시지 변경 (필요한 경우)
    const messages = document.querySelectorAll("p, .message, .notice, .login .message");
    messages.forEach(msg => {
        if (msg.textContent.includes("가입 확인용 이메일이 발송될 것입니다")) {
            msg.textContent = "(담당자 : 13615 서상현)";
        }
    });

    // 회원가입 폼 비밀번호 확인
    const registerForm = document.querySelector('#register form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('user_pass');
            const passwordConfirm = document.getElementById('user_pass_confirm');
            
            if (password && passwordConfirm) {
                if (password.value !== passwordConfirm.value) {
                    e.preventDefault();
                    alert('비밀번호가 일치하지 않습니다.');
                    passwordConfirm.focus();
                    return false;
                }
                
                // 비밀번호 강도 체크 (선택사항)
                if (password.value.length < 6) {
                    e.preventDefault();
                    alert('비밀번호는 최소 6자 이상이어야 합니다.');
                    password.focus();
                    return false;
                }
            }
        });
    }

    // 입력 필드 포커스 효과
    const inputs = document.querySelectorAll('.full-input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
});