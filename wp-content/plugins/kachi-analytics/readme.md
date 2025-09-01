# Statistics Dashboard WordPress Plugin

Python 백엔드 API와 통신하여 문서 통계를 표시하는 워드프레스 플러그인입니다.

## 플러그인 정보

- **플러그인 이름**: Statistics Dashboard
- **제작자**: 3chan
- **웹사이트**: https://3chan.kr
- **버전**: 1.0.0
- **라이선스**: GPL v2 or later

## 주요 기능

- 📊 **실시간 통계 대시보드**: 문서, 섹션, 업로드 현황을 한눈에 확인
- 📈 **시각적 차트**: Chart.js를 활용한 다양한 통계 차트
- 🔒 **권한 기반 접근**: sosok/site 기반 데이터 필터링
- 🎨 **모던한 UI**: 다크/라이트 테마 지원
- ⚡ **성능 최적화**: 캐싱 및 자동 새로고침 기능
- 📱 **반응형 디자인**: 모바일 환경 완벽 지원

## 설치 방법

### 1. 플러그인 파일 구조

```
/wp-content/plugins/statistics-dashboard/
├── statistics-dashboard.php (메인 플러그인 파일)
├── assets/
│   ├── css/
│   │   └── statistics-dashboard.css
│   └── js/
│       └── statistics-dashboard.js
└── readme.md
```

### 2. 설치 단계

1. 위 파일들을 `/wp-content/plugins/statistics-dashboard/` 디렉토리에 업로드
2. 워드프레스 관리자 > 플러그인 메뉴에서 "Statistics Dashboard" 활성화
3. 설정 > Statistics Dashboard 메뉴에서 API 설정 구성

## 설정

### API 설정

1. **설정 > Statistics Dashboard** 메뉴로 이동
2. 다음 항목들을 설정:
   - **API Base URL**: Python API 서버 주소 (예: `http://localhost:8000`)
   - **API Timeout**: API 요청 타임아웃 (기본값: 30초)
   - **Enable Cache**: 캐싱 활성화 여부
   - **Cache Duration**: 캐시 유지 시간 (기본값: 300초)
   - **Default Sosok**: 기본 소속 값
   - **Default Site**: 기본 사이트 값

### Python API 요구사항

플러그인은 다음 엔드포인트를 가진 Python FastAPI 서버가 필요합니다:

- `GET /statistics/` - 전체 통계 데이터
- `GET /statistics/uploads-by-date/` - 날짜별 업로드 통계
- `GET /statistics/storage/` - 저장소 통계

## 사용 방법

### 기본 사용법

페이지나 포스트에 다음 숏코드를 삽입:

```
[statistics_dashboard]
```

### 고급 사용법

특정 소속/사이트로 필터링:

```
[statistics_dashboard sosok="관리자" site="관리자"]
```

라이트 테마 사용:

```
[statistics_dashboard theme="light"]
```

## 대시보드 구성

### 1. 개요 탭
- **통계 카드**: 전체 문서 수, 섹션 수, 평균 섹션, 소속 수
- **문서 유형 차트**: 파일 확장자별 분포 (도넛 차트)
- **인기 태그**: 가장 많이 사용된 태그 목록
- **최근 업로드**: 최근 업로드된 문서 목록

### 2. 업로드 현황 탭
- **타임라인 차트**: 선택한 기간 동안의 일별 업로드 추이
- **기간 선택**: 7일, 30일, 60일, 90일
- **업로드 통계**: 총 업로드 수, 일 평균

### 3. 저장소 탭
- **전체 저장 용량**: 총 용량, GB 단위, 파일 수
- **파일 정보**: 평균 파일 크기, 총 파일 수
- **파일 유형별 용량**: 확장자별 용량 분포
- *(관리자 권한 필요)*

### 4. 문서별 통계 탭
- **소속별 문서 분포**: 막대 차트
- **사이트별 문서 분포**: 수평 막대 차트 (상위 10개)

## 권한 관리

### 접근 레벨

1. **일반 사용자**: 자신의 소속/사이트 데이터만 조회
2. **부서 관리자**: `_전체` 접미사를 가진 사이트는 해당 소속 전체 데이터 조회
3. **관리자**: `sosok="관리자" site="관리자"`인 경우 모든 데이터 조회

### 제한 사항

- 저장소 통계는 관리자만 조회 가능
- 일반 사용자는 필터링된 데이터만 확인 가능

## 성능 최적화

### 캐싱
- 통계 데이터는 설정된 시간 동안 캐시됨
- 캐시는 데이터베이스에 저장되어 API 부하 감소

### 자동 새로고침
- 5분마다 자동으로 데이터 새로고침
- 수동 새로고침 버튼 제공

## 문제 해결

### API 연결 오류
1. API 서버가 실행 중인지 확인
2. API Base URL이 올바른지 확인
3. 방화벽/네트워크 설정 확인

### 데이터가 표시되지 않음
1. sosok/site 권한 설정 확인
2. 브라우저 콘솔에서 JavaScript 오류 확인
3. API 응답 형식이 올바른지 확인

### 차트가 표시되지 않음
1. Chart.js 라이브러리 로드 확인
2. 데이터가 있는지 확인
3. 브라우저 호환성 확인

## 개발자 정보

### 필터 및 액션 훅

```php
// 통계 데이터 필터링
add_filter('sd_statistics_data', 'your_filter_function', 10, 2);

// API 요청 전 액션
do_action('sd_before_api_request', $endpoint, $params);

// API 응답 후 액션
do_action('sd_after_api_response', $endpoint, $response);
```

### JavaScript 이벤트

```javascript
// 데이터 로드 완료
$(document).on('sd:data-loaded', function(e, data) {
    console.log('Statistics data loaded:', data);
});

// 섹션 변경
$(document).on('sd:section-changed', function(e, section) {
    console.log('Section changed to:', section);
});
```

## 라이선스

GPL v2 or later

## 지원

문제가 있거나 기능 요청이 있으시면 GitHub 이슈를 생성해주세요.