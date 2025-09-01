=== User Approval System ===
Contributors: 3chan
Tags: user, approval, membership, registration, pending
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

사용자 계정 승인 시스템을 제공하는 플러그인입니다.

== Description ==

User Approval System은 워드프레스 사이트에서 새로운 사용자 가입을 승인자가 검토하고 승인할 수 있도록 하는 플러그인입니다.

주요 기능:
* 승인 대기(pending) 사용자 역할 자동 생성
* 승인자(approver) 역할로 사용자 승인 권한 관리
* 사번/소속/현장 정보로 사용자 필터링
* 직관적인 승인 인터페이스
* 숏코드를 통한 쉬운 설치

== Installation ==

1. 플러그인 파일을 `/wp-content/plugins/user-approval-system` 디렉토리에 업로드합니다.
2. 워드프레스 관리자 페이지의 '플러그인' 메뉴에서 플러그인을 활성화합니다.
3. 원하는 페이지에 `[user_approval_system]` 숏코드를 추가합니다.
4. 승인자 권한이 있는 사용자만 해당 페이지에 접근할 수 있습니다.

== Frequently Asked Questions ==

= 승인자 권한은 어떻게 부여하나요? =

사용자 편집 페이지에서 역할을 'approver'로 변경하거나, 관리자 권한이 있는 사용자도 승인 기능을 사용할 수 있습니다.

= 승인 대기 사용자는 어떻게 생성되나요? =

새로운 사용자가 가입할 때 'pending' 역할로 자동 할당되도록 설정하거나, 관리자가 수동으로 역할을 변경할 수 있습니다.

= ACF(Advanced Custom Fields)가 필요한가요? =

소속과 현장 정보를 표시하려면 ACF 플러그인이 필요합니다. ACF가 없어도 기본 기능은 작동합니다.

== Screenshots ==

1. 승인 대기 사용자 목록
2. 필터링 기능
3. 관리자 설정 페이지

== Changelog ==

= 1.0.0 =
* 최초 릴리스
* 사용자 승인 시스템 구현
* 필터링 기능 추가
* 숏코드 지원

== Upgrade Notice ==

= 1.0.0 =
최초 릴리스입니다.