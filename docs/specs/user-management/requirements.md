# user-management 要件定義

> **v3 改修反映**（2026-05-16）:
> - **招待モーダルに `plan_id` フィールド追加**（[[plan-management]] の `Plan` マスタから選択、v3 で `IssueInvitationAction` が `Plan $plan` 必須）
> - **ユーザー詳細にプラン情報パネル / プラン延長ボタン / 面談回数手動付与 UI を追加**（[[plan-management]] / [[meeting-quota]] と連携）
> - **status フィルタに `graduated` 追加**（v3 で `UserStatus` enum 4 値化）
> - **`UpdateAction`（プロフィール編集）/ `UpdateRoleAction`（ロール変更）を撤回**（admin が他者のプロフィール / ロールを変更する動線を撤回、編集動線なし）
> - **`SelfWithdrawAction` 呼出は撤回**（[[settings-profile]] で自己退会動線撤回、本 Feature の `WithdrawAction` のみで admin 経由退会）
> - **`UserStatus::Active` 参照を `UserStatus::InProgress` に統一**

## 概要

Certify LMS の管理者向けユーザー管理 Feature。**admin が他ユーザーのライフサイクル全体を運用する画面**を提供する。
具体的には: ユーザー一覧（フィルタ・検索 + 招待動線）→ 詳細画面（プロフィール / 受講中資格概要 / **プラン情報パネル**（v3）/ ステータス変更履歴 / 招待履歴 / **面談回数残数**（v3））→ **強制退会 / プラン延長 / 面談回数手動付与**、および詳細からの再招待・取消フロー。

[[auth]] が提供する `User` / `Invitation` モデルと **`IssueInvitationAction(Plan $plan, ...)`**（v3 で Plan 引数必須）/ `RevokeInvitationAction` の上に **admin 向け UI と HTTP 層** を構築する。
さらに、ユーザー受講状態の遷移履歴を追跡する `UserStatusLog` モデルと、**全 Feature 共通のステータス変更記録エントリポイント** である `UserStatusChangeService` を本 Feature で新設する（[[auth]] のステータス変更系 Action から呼び出される、v3 で settings-profile からの呼出は撤回）。

**v3 で「プロフィール編集 / ロール変更」UI を撤回**: admin が他者のプロフィール / ロールを変更する動線を本 Feature では提供しない（メールアドレス変更は invitation を新規発行する形でのみ可能、ロール変更は撤回）。

## ロールごとのストーリー

- **管理者（admin）**: ユーザー一覧で `role` / `status`（v3 で `graduated` 追加）/ keyword フィルタを使い、招待発行（**Plan 必須選択**、v3）/ 再招待 / 強制退会 / **プラン延長** / **面談回数手動付与** を行う。詳細画面でプロフィール / 受講状態履歴 / プラン情報 / 面談回数残数を閲覧する。プロフィール編集・ロール変更 UI は提供しない（v3 で撤回）。

## 受け入れ基準（EARS形式）

### 機能要件 — ユーザー一覧

- **REQ-user-management-001**: The system shall `GET /admin/users` で `User::where('role', '!=', null)->orderBy('created_at', 'desc')->paginate(20)` をテーブル表示する。
- **REQ-user-management-002**: The system shall フィルタ提供: `role`（admin / coach / student）/ **`status`（`invited` / `in_progress`（v3） / `graduated`（v3 新規） / `withdrawn`）** / `keyword`（name / email 部分一致）。
- **REQ-user-management-003**: The system shall 各ユーザー行に `name` / `email` / `role` バッジ / `status` バッジ（4 値、v3）/ 最終ログイン日時 / **Plan 名（v3 新規）** / **プラン残日数（v3 新規）** / 「詳細」リンクを表示する。
- **REQ-user-management-004**: The system shall ユーザー一覧上部に「+ 新規招待」ボタンを配置し、招待モーダルを開く。

### 機能要件 — 招待発行（v3 で Plan 必須化）

- **REQ-user-management-010**: The system shall 招待モーダルに `email`（必須 / email 形式）/ `role`（必須 / `admin` / `coach` / `student` から選択）/ **`plan_id`（v3 新規、必須、[[plan-management]] の `Plan::published()` から `<x-form.select>` で選択）** の入力フィールドを提供する。
- **REQ-user-management-011**: When admin が招待フォームから `POST /admin/invitations` を送信した際, the system shall [[auth]] の **`IssueInvitationAction($email, $role, $plan, $admin, force: false)`**（v3 で `Plan $plan` 引数追加）を呼ぶ。Action 内で User INSERT + Invitation INSERT + InvitationMail dispatch + `UserStatusChangeService::record($user, UserStatus::Invited, $admin, '新規招待')` + **`UserPlanLog`（v3、event_type=assigned）** が **同一トランザクション内** で実行される。
- **REQ-user-management-012**: When admin がユーザー詳細画面で「再招待」ボタンを押下した際, the system shall [[auth]] の `IssueInvitationAction($user->email, $user->role, $user->plan, $admin, force: true)` を呼ぶ（**既存 plan_id を再利用**、v3）。同 user_id の旧 pending Invitation は revoke され、User は invited のまま、新 Invitation が同 user_id に紐付いて発行される。
- **REQ-user-management-013**: When 招待発行が失敗した際（既存 active User と email 衝突など）, the system shall [[auth]] のドメイン例外をそのまま伝播し、フラッシュメッセージで日本語エラーを表示する。

### 機能要件 — ユーザー詳細

- **REQ-user-management-020**: The system shall `GET /admin/users/{user}` でユーザー詳細を表示し、以下のセクションを提供する: (a) プロフィール（name / email / role / status バッジ / avatar / bio / 招待日 / 最終ログイン）、(b) **プラン情報パネル（v3 新規）**（Plan 名 / `plan_started_at` / `plan_expires_at` / プラン残日数 / **`max_meetings`** / **残面談回数**（`MeetingQuotaService::remaining` 経由））、(c) 受講中資格一覧（Enrollment 経由）、(d) **ステータス変更履歴 + プラン履歴 + 面談回数履歴**（v3 で `UserStatusLog` + `UserPlanLog` + `MeetingQuotaTransaction` を統合表示）、(e) 招待履歴一覧（pending / accepted / expired / revoked）。
- **REQ-user-management-021**: The system shall 詳細画面に以下のアクションボタンを提供する: 「再招待」（pending Invitation あり時のみ）/ 「招待取消」（pending Invitation あり時のみ）/ 「強制退会」（status=in_progress / graduated 時のみ）/ **「プラン延長」**（v3 新規、status=in_progress / graduated 時、modal で Plan 選択）/ **「面談回数手動付与」**（v3 新規、status=in_progress 時、modal で `amount` 入力）。
- **REQ-user-management-022**: **削除（v3 撤回）**: 「プロフィール編集」「ロール変更」ボタンは提供しない。プロフィール編集は本人が [[settings-profile]] で行う、ロール変更は撤回（メールアドレス変更 / ロール変更が必要な場合は退会 + 再招待）。

### 機能要件 — 強制退会（admin 経由）

- **REQ-user-management-040**: When admin が「強制退会」ボタンを押下した際, the system shall `POST /admin/users/{user}/withdraw` を呼び、`WithdrawAction` で単一トランザクション内に (1) [[auth]] の `User::withdraw()` ヘルパ呼出（status=withdrawn + soft delete + email リネーム）、(2) `UserStatusChangeService::record($user, UserStatus::Withdrawn, $admin, '管理者による退会')` を実行する。
- **REQ-user-management-041**: If 退会対象が admin ロールで、かつ削除後に残る admin が 0 人になる場合, then the system shall `LastAdminWithdrawException`（HTTP 409、日本語メッセージ「最後の管理者は退会できません。」）で拒否する。

### 機能要件 — プラン延長（v3 新規）

- **REQ-user-management-050**: When admin が「プラン延長」ボタンを押下した際, the system shall モーダルに `Plan::published()` から選択する `plan_id` フィールドを表示する。
- **REQ-user-management-051**: When admin がプラン延長フォームを送信した際, the system shall `POST /admin/users/{user}/extend-course` を呼び、[[plan-management]] の **`ExtendCourseAction($user, $plan, $admin)`** を実行する（plan_expires_at += plan.duration_days + max_meetings += plan.default_meeting_quota + UserPlanLog renewed 記録 + MeetingQuotaTransaction granted_initial 起票、全工程 1 トランザクション）。

### 機能要件 — 面談回数手動付与（v3 新規）

- **REQ-user-management-060**: When admin が「面談回数手動付与」ボタンを押下した際, the system shall モーダルに `amount`（unsigned int、1..100）/ `reason`（任意、最大 200 文字）入力フィールドを表示する。
- **REQ-user-management-061**: When admin が面談回数手動付与フォームを送信した際, the system shall `POST /admin/users/{user}/grant-meeting-quota` を呼び、[[meeting-quota]] の **`AdminGrantQuotaAction($user, $amount, $admin, $reason)`** を実行する（MeetingQuotaTransaction admin_grant 起票 + granted_by 記録、`DB::transaction`）。

### 機能要件 — UserStatusLog と UserStatusChangeService

- **REQ-user-management-070**: The system shall `UserStatusLog` テーブルを提供する: ULID 主キー / `user_id` ULID FK / **`event_type` `UserStatusEventType` enum**（2026-05-16 追加、`UserPlanLog.event_type` とフォーマット統一、現時点では `status_change` の 1 値固定で将来拡張可）/ `from_status` `UserStatus` enum（**4 値**、v3）/ `to_status` `UserStatus` enum（**4 値**、v3）/ `changed_by_user_id` ULID FK nullable（システム自動変更時 NULL）/ `changed_reason` text nullable / `changed_at` datetime / timestamps。`(user_id, changed_at)` 複合 INDEX、`(event_type, changed_at)` 複合 INDEX（将来の event_type 拡張時のクエリ高速化）。
- **REQ-user-management-070b**: The system shall `App\Enums\UserStatusEventType` enum を提供する（`StatusChange = 'status_change'` の 1 値、`label()` で日本語ラベル「ステータス変更」を返す）。
- **REQ-user-management-071**: The system shall ステータス変化を伴う全 Action（[[auth]] の `IssueInvitationAction` / `OnboardAction` / `ExpireInvitationsAction` / `RevokeInvitationAction`、本 Feature の `WithdrawAction`、[[plan-management]] の `GraduateExpiredUsersAction`（v3 新規））が本 Service を経由して `UserStatusLog` を記録する設計とする。**`User.status` の UPDATE と `UserStatusLog` の INSERT は同一トランザクション内** で行うことを呼び出し側 Action が保証する。
- **REQ-user-management-072**: The system shall `UserStatusChangeService::record(User $user, UserStatus $newStatus, ?User $changedBy, ?string $reason): UserStatusLog` シグネチャを提供する。本 Service は内部で `event_type = UserStatusEventType::StatusChange` を自動挿入する（呼出側は event_type を意識しない設計、`UserPlanLog` とフォーマット統一）。将来 `event_type` を拡張する場合は本メソッドの引数追加か新規 method `recordEvent(User, UserStatusEventType, ...)` を追加して対応する。

### 機能要件 — 認可

- **REQ-user-management-080**: The system shall `/admin/users/*` ルートに `auth + role:admin` Middleware を適用する。
- **REQ-user-management-081**: The system shall `UserPolicy::view / withdraw / extendCourse / grantMeetingQuota`（v3 新規 2 つ追加）を admin のみ true で実装する。**`update`（プロフィール編集）/ `updateRole`（ロール変更）は本 Feature で提供しない**（v3 撤回）。

### 非機能要件

- **NFR-user-management-001**: The system shall 状態変更を伴うすべての Action（`WithdrawAction`、および本 Feature 経由で呼ばれる [[auth]] / [[plan-management]] / [[meeting-quota]] の Action）を `DB::transaction()` で囲む。**`UpdateAction` / `UpdateRoleAction` は提供しない**（v3 撤回）。
- **NFR-user-management-002**: The system shall N+1 を避けるため `with(['plan', 'enrollments.certification', 'invitations'])` Eager Loading を使用する。
- **NFR-user-management-003**: The system shall ドメイン例外を `app/Exceptions/UserManagement/` 配下に配置する: `LastAdminWithdrawException`（409）/ `UserAlreadyWithdrawnException`（409）。

## スコープ外

- **プロフィール編集（admin → 他者）**（v3 で撤回） — 本人が [[settings-profile]] で行う
- **ロール変更（admin → 他者）**（v3 で撤回） — メールアドレス / ロール変更が必要な場合は退会 + 再招待
- 自己プロフィール編集 / 自己退会（[[settings-profile]]）— v3 で自己退会動線も撤回
- 招待 / Invitation モデル / `IssueInvitationAction` の実装本体（[[auth]]）
- `Plan` モデル / `ExtendCourseAction` の実装本体（[[plan-management]]）
- `MeetingQuotaTransaction` / `AdminGrantQuotaAction` の実装本体（[[meeting-quota]]）
- 2FA / IP制限 / ログイン履歴の詳細管理
- `withdrawn → in_progress` 復活フロー — state diagram の終端、再招待は別 Invitation として新規発行

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[auth]]: `IssueInvitationAction` / `OnboardAction` / `ExpireInvitationsAction` / `RevokeInvitationAction` が本 Feature の `UserStatusChangeService::record` を呼ぶ
- **依存先**（本 Feature が前提とする）:
  - [[auth]]: `User` / `Invitation` モデル、`UserStatus`（v3 で 4 値化） / `UserRole` / `InvitationStatus` Enum、`IssueInvitationAction(Plan $plan, ...)` / `RevokeInvitationAction` Action、`EnsureUserRole` Middleware
  - **[[plan-management]]**: `Plan` Model（招待モーダル / プラン延長モーダル）/ `ExtendCourseAction` / `UserPlanLog`（v3 新規）
  - **[[meeting-quota]]**: `MeetingQuotaService::remaining`（プラン情報パネル表示）/ `AdminGrantQuotaAction`（面談回数手動付与、v3 新規）
