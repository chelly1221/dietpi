# Version Notice WordPress Plugin

버전 업데이트 공지사항을 관리하고 표시하는 워드프레스 플러그인입니다.

## 설치 방법

1. 플러그인 폴더 구조 생성:
```
/wp-content/plugins/version-notice/
├── version-notice.php (메인 플러그인 파일)
├── assets/
│   ├── frontend.css
│   ├── admin.css
│   └── admin.js
└── readme.md
```

2. 워드프레스 관리자 페이지에서 플러그인 활성화

## 사용 방법

### 숏코드 사용

페이지나 포스트에 다음 숏코드를 삽입하세요:

```
[version_notice]
```

### 관리자 메뉴

1. 워드프레스 관리자 메뉴 맨 아래에 "Version Notice" 메뉴가 생성됩니다
2. 이 메뉴에서 버전 공지사항을 추가, 수정, 삭제할 수 있습니다
3. "Settings" 서브메뉴에서 배경 이미지를 설정할 수 있습니다

### 배경 이미지 설정

1. Version Notice > Settings 메뉴로 이동
2. "Select Image" 버튼을 클릭하여 워드프레스 미디어 라이브러리에서 이미지 선택
3. 배경 이미지를 적용할 페이지 선택:
   - "Apply to all pages with version notice shortcode": 모든 버전 공지 페이지에 적용
   - 또는 특정 페이지만 선택 가능
4. "Save Settings" 클릭

### 공지사항 추가하기

1. Version Notice 메뉴 클릭
2. 다음 정보 입력:
   - **Year-Month**: 연도-월 선택 (예: 2025-06)
   - **Version Number**: 버전 번호 (예: v3.3.11)
   - **Date**: 날짜 (예: 27일)
   - **Title**: 업데이트 제목
   - **Content**: 업데이트 내용 (HTML 허용, `<li>` 태그 사용 권장)
   - **Sort Order**: 정렬 순서 (낮은 숫자가 먼저 표시)

3. "Save Notice" 버튼 클릭

### 공지사항 수정/삭제

- **수정**: 공지사항 목록에서 "Edit" 버튼 클릭
- **삭제**: 공지사항 목록에서 "Delete" 버튼 클릭

## 기능 특징

- 연월별 자동 그룹화
- 반응형 디자인
- AJAX 기반 관리자 인터페이스
- 데이터베이스 기반 저장
- 워드프레스 미디어 라이브러리를 통한 배경 이미지 관리
- 페이지별 배경 이미지 설정 가능
- 깔끔한 아코디언 스타일 UI

## 데이터베이스 구조

플러그인 활성화 시 다음 테이블이 생성됩니다:

```sql
wp_version_notices
- id: 고유 ID
- version_number: 버전 번호
- date: 날짜
- title: 제목
- content: 내용
- year_month: 연월 (YYYY-MM)
- sort_order: 정렬 순서
- created_at: 생성일시
```

추가 옵션:
- `version_notice_background_image`: 배경 이미지 URL
- `version_notice_background_pages`: 배경 이미지를 적용할 페이지 ID 목록

## 스타일 커스터마이징

CSS 파일을 수정하여 디자인을 변경할 수 있습니다:
- `assets/frontend.css`: 프론트엔드 스타일
- `assets/admin.css`: 관리자 페이지 스타일

주요 CSS 클래스:
- `.version-log-wrapper-kac`: 전체 래퍼
- `.version-title`: 메인 타이틀
- `.version-month`: 월별 헤더
- `.version-entry`: 각 공지사항 항목
- `.version-tag`: 버전 태그

## 주의사항

- 플러그인 비활성화 시 데이터베이스 테이블은 유지됩니다
- 완전히 제거하려면 데이터베이스에서 `wp_version_notices` 테이블을 수동으로 삭제해야 합니다
- 설정한 배경 이미지와 페이지 설정도 옵션으로 저장되므로, 완전 제거 시 `wp_options` 테이블에서 `version_notice_` 접두사가 붙은 옵션들을 삭제해야 합니다

## 라이선스

GPL v2 or later