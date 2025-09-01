=== 시설 정의 관리자 ===
Contributors: 3chan
Donate link: https://3chan.kr
Tags: facility, definition, management
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

시설 정의를 관리하는 플러그인입니다.

== Description ==

이 플러그인은 사용자가 자신의 소속과 현장에 맞는 시설 정의를 관리할 수 있게 해줍니다.

= 주요 기능 =

* 시설 용어와 정의를 추가/삭제
* 사용자별 소속/현장에 따른 데이터 분리
* 쇼트코드를 통한 쉬운 통합
* AJAX 기반의 실시간 저장

== Installation ==

1. 플러그인 파일을 `/wp-content/plugins/facility-definition/` 디렉토리에 업로드합니다.
2. 워드프레스 관리자 페이지의 '플러그인' 메뉴에서 플러그인을 활성화합니다.
3. 페이지나 포스트에 `[facility_definition]` 쇼트코드를 추가합니다.

== Frequently Asked Questions ==

= 쇼트코드는 어떻게 사용하나요? =

페이지나 포스트 편집기에서 `[facility_definition]`를 입력하면 됩니다.

= ACF(Advanced Custom Fields)가 필요한가요? =

네, 이 플러그인은 ACF를 사용하여 데이터를 저장합니다. ACF가 설치되어 있어야 합니다.

== Screenshots ==

1. 시설 정의 관리 화면
2. 관리자 메뉴

== Changelog ==

= 1.0.0 =
* 첫 번째 릴리스

== Upgrade Notice ==

= 1.0.0 =
첫 번째 버전입니다.

== 파일 구조 ==

```
facility-definition/
├── facility-definition.php      # 메인 플러그인 파일
├── readme.txt                   # 이 파일
├── includes/                    # PHP 클래스 파일들
│   ├── class-facility-definition.php
│   ├── class-facility-ajax.php
│   ├── class-facility-shortcode.php
│   └── class-facility-admin.php
├── assets/                      # 정적 파일들
│   ├── css/
│   │   └── facility-definition.css
│   └── js/
│       └── facility-definition.js
└── languages/                   # 번역 파일 (선택사항)
```