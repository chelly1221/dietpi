# KAC Login System

커스텀 로그인 시스템과 회원가입 기능을 제공하는 워드프레스 플러그인입니다.

## 설치 방법

1. `kac-login-system` 폴더를 `/wp-content/plugins/` 디렉토리에 업로드합니다.
2. 워드프레스 관리자 대시보드에서 플러그인을 활성화합니다.

## 폴더 구조

```
kac-login-system/
├── kac-login-system.php          # 메인 플러그인 파일
├── includes/
│   ├── class-kac-login.php       # 메인 클래스
│   ├── class-kac-login-shortcode.php  # 쇼트코드 처리
│   ├── class-kac-login-handler.php    # 로그인 처리
│   ├── class-kac-login-admin.php      # 관리자 설정
│   └── class-kac-registration.php     # 회원가입 처리
├── assets/
│   ├── css/
│   │   ├── login-style.css       # 로그인 스타일시트
│   │   └── registration-style.css # 회원가입 스타일시트
│   ├── js/
│   │   └── login-script.js       # JavaScript
│   └── images/
│       ├── kac.webp              # 기본 로고
│       └── airport2.png          # 배경 이미지
├── templates/
│   └── blank-template.php        # 빈 페이지 템플릿
└── readme.md                     # 플러그인 정보

```

## 사용 방법

### 1. 로그인 페이지

#### 기본 사용법
```
[kac_login]
```

#### 옵션 사용
```
[kac_login logo="이미지URL" redirect="리다이렉트URL"]
```

### 2. 회원가입 페이지

#### 기본 사용법
```
[kac_register]
```

#### 옵션 사용
```
[kac_register logo="이미지URL" redirect="승인대기페이지URL"]
```

### 3. 회원가입 완료 페이지 (승인 대기)

#### 기본 사용법
```
[kac_register_complete]
```

#### 옵션 사용
```
[kac_register_complete background="배경이미지URL" image="표시이미지URL"]
```

### 관리자 설정

워드프레스 관리자 대시보드 좌측 메뉴 하단의 "로그인" 메뉴에서 다음 항목을 설정할 수 있습니다:

- **로고 이미지**: 로그인/회원가입 폼에 표시될 기본 로고
- **배경 이미지**: 전체 페이지 모드에서 사용될 배경 이미지
- **로그인 후 이동 페이지**: 로그인 성공 후 기본 리다이렉트 URL
- **이용약관 URL**: 회원가입 시 표시될 이용약관 페이지 URL
- **개인정보처리방침 URL**: 회원가입 시 표시될 개인정보처리방침 페이지 URL

### 전체 페이지 모드

기존 테마 레이아웃 없이 로그인/회원가입 페이지만 표시하려면 URL에 `?full_page=1` 파라미터를 추가하세요:

```
https://your-site.com/login-page/?full_page=1
```

## 주요 기능

### 로그인 기능
- 커스텀 로그인 폼
- 비밀번호 표시/숨김 토글
- 로그인 유지 옵션
- 로그인 실패 시 에러 메시지
- 반응형 디자인
- 보안 강화 (Nonce 검증)

### 회원가입 기능
- 사번 기반 회원가입
- 소속/부서/현장 선택 (계층적 드롭다운)
- 비밀번호 강도 검증 (9자 이상, 영문/숫자/특수문자 포함)
- 비밀번호 확인 기능
- 승인 대기 시스템
- 이메일 자동 생성 (사번@noemail.local)

### 보안 기능
- 승인 대기 사용자 로그인 제한
- 구독자/승인 대기 사용자 관리자 접근 차단
- 구독자 관리자 바 숨김

## 페이지 설정 예시

### 1. 로그인 페이지 생성
- 페이지 제목: 로그인
- 본문: `[kac_login]`
- 고유주소: /login

### 2. 회원가입 페이지 생성 (ID: 241)
- 페이지 제목: 회원가입
- 본문: `[kac_register]`
- 고유주소: /register

### 3. 승인 대기 페이지 생성 (ID: 264)
- 페이지 제목: 가입완료
- 본문: `[kac_register_complete]`
- 고유주소: /register-complete

## 사용자 역할

- **pending (승인 대기)**: 회원가입은 완료했지만 관리자 승인을 기다리는 사용자
- **subscriber (구독자)**: 승인된 일반 사용자

## 스타일 커스터마이징

### 주요 CSS 클래스
- `.custom-login-wrapper`: 로그인 폼 컨테이너
- `#kac-register-form`: 회원가입 폼
- `.approval-message-box`: 승인 대기 메시지 박스
- `.password-wrap`: 비밀번호 필드 래퍼
- `.contact-box`: 담당자 정보 박스

## 필요 파일

플러그인 정상 작동을 위해 다음 이미지 파일을 추가하거나 관리자 설정에서 지정해야 합니다:
- `/assets/images/kac.webp` - 기본 로고 이미지
- `/wp-content/uploads/2025/05/공항.png` - 회원가입 완료 페이지 배경
- 승인 대기 페이지 이미지

## 주의사항

- 회원가입 시 사번이 사용자명(username)으로 사용됩니다
- 이메일은 자동으로 `사번@noemail.local` 형식으로 생성됩니다
- ACF(Advanced Custom Fields) 플러그인이 설치되어 있으면 소속/현장 정보가 ACF 필드로 저장됩니다
- ACF가 없는 경우 user meta로 저장됩니다

## 버전 히스토리

### 1.1.0
- 회원가입 기능 추가
- 승인 대기 시스템 구현
- 소속/부서/현장 선택 기능

### 1.0.0
- 초기 릴리스
- 커스텀 로그인 폼 구현
- 쇼트코드 기능
- 반응형 디자인

## 제작자

- 제작자: 3chan
- 웹사이트: https://3chan.kr

## 라이선스

GPL v2 or later