=== 3chan PDF Manager - AI Document System ===
Contributors: threechan-dev-team
Donate link: https://3chan.kr/donate
Tags: pdf-manager, document-ai, pdf-split, hwpx-support, document-management
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 2.5.2
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI 기반 PDF/DOCX/PPTX/HWPX 문서 업로드 및 관리 시스템. 여백 설정, 페이지 분할, 태그 관리 기능 제공.

== Description ==

**3chan PDF Manager - AI Document System**은 한국형 문서 관리에 최적화된 WordPress 플러그인입니다.

= 주요 특징 =

* **다양한 문서 형식 지원**: PDF, DOCX, PPTX, HWPX (한글 문서)
* **AI 기반 문서 처리**: 자동 페이지 분할 및 내용 추출
* **PDF 여백 설정**: 머리말/꼬리말 영역 자동 제거
* **태그 기반 분류**: 효율적인 문서 관리
* **실시간 검색**: 파일명 및 태그로 빠른 검색
* **페이지별 미리보기**: 문서 내용을 페이지 단위로 확인
* **다중 파일 업로드**: 여러 파일 동시 처리
* **사용자별 관리**: 소속/현장별 문서 분류

= 한국 기업/기관에 최적화 =

* 한글(HWPX) 문서 완벽 지원
* 한국어 태그 및 검색 최적화
* 소속/부서별 문서 관리 기능
* 직관적인 한국어 UI

= 기술적 특징 =

* WordPress REST API 통합
* AJAX 기반 비동기 처리
* PDF.js를 이용한 클라이언트 사이드 렌더링
* 반응형 디자인
* 모듈화된 JavaScript 구조

== Installation ==

1. 플러그인 폴더를 `/wp-content/plugins/` 디렉토리에 업로드
2. WordPress 관리자 페이지에서 '플러그인' 메뉴로 이동
3. '3chan PDF Manager - AI Document System' 플러그인 활성화
4. 설정 > PDF Manager에서 API 서버 주소 설정
5. 페이지나 포스트에 `[3chan_pdf_manager]` 숏코드 추가

= 시스템 요구사항 =

* WordPress 5.0 이상
* PHP 7.2 이상
* MySQL 5.6 이상
* 최소 256MB PHP 메모리
* FastAPI 백엔드 서버 (별도 제공)

== Frequently Asked Questions ==

= 이 플러그인은 다른 PDF 관리 플러그인과 어떻게 다른가요? =

3chan PDF Manager는 한국형 문서(HWPX) 지원, AI 기반 페이지 분할, 소속/부서별 관리 등 한국 기업 환경에 특화된 기능을 제공합니다.

= API 서버는 별도로 필요한가요? =

네, 문서 처리를 위한 FastAPI 백엔드 서버가 필요합니다. 기본값은 http://1.3chan.kr 입니다.

= 어떤 파일 형식을 지원하나요? =

PDF, DOCX, PPTX, HWPX 파일을 지원합니다. DOC, PPT, HWP 등 구형 포맷은 먼저 변환이 필요합니다.

= 파일 크기 제한이 있나요? =

기본 설정은 50MB이며, 설정에서 변경 가능합니다. 서버의 PHP 업로드 제한도 확인하세요.

= 사용자별 문서 관리가 가능한가요? =

네, WordPress 사용자별로 업로드한 문서를 별도 관리하며, 소속/부서별 분류도 지원합니다.

== Screenshots ==

1. 문서 업로드 화면 - 드래그 앤 드롭 지원
2. PDF 여백 설정 - 머리말/꼬리말 영역 지정
3. 문서 목록 - 태그별 필터링 및 검색
4. 페이지 뷰어 - 문서 내용 미리보기
5. 관리자 대시보드 - 통계 및 설정

== Changelog ==

= 2.5.2 =
* JavaScript 모듈화 리팩토링
* 특수 문자 처리 개선 (▶ 기호 등)
* 리스트 및 테이블 포맷 지원 강화
* 성능 최적화

= 2.5.1 =
* 다중 PDF 파일 여백 설정 기능 추가
* 업로드 프로세스 개선
* UI/UX 개선

= 2.5.0 =
* 초기 공개 버전
* PDF/DOCX/PPTX/HWPX 지원
* AI 기반 페이지 분할
* 태그 관리 시스템

== Upgrade Notice ==

= 2.5.2 =
JavaScript 구조가 개선되었습니다. 캐시를 클리어하시기 바랍니다.

= 2.5.0 =
초기 설치 시 API 서버 설정이 필요합니다.

== Additional Info ==

= 개발자 정보 =
* 개발팀: 3chan Development Team
* 홈페이지: https://3chan.kr
* 지원: support@3chan.kr
* 깃허브: https://github.com/3chan/pdf-manager (비공개)

= 라이선스 =
이 플러그인은 GPL v2 또는 이후 버전으로 라이선스가 부여됩니다.

= 외부 서비스 =
이 플러그인은 문서 처리를 위해 외부 API 서버와 통신합니다:
* 기본 API: http://1.3chan.kr
* 개인정보처리방침: https://3chan.kr/privacy

= 기여 =
버그 리포트, 기능 제안은 support@3chan.kr로 보내주세요.

== Credits ==

* PDF.js - Mozilla Foundation
* jQuery - jQuery Foundation
* WordPress Plugin Boilerplate