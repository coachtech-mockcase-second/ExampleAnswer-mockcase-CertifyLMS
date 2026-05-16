# plan-management 要件定義

> **v3 新規 Feature**（2026-05-16）: プラン受講型 LMS の中核。`Plan` マスタ（admin が CRUD）+ `User` への Plan 紐づけ + `ExtendCourseAction`（プラン延長）+ `users:graduate-expired` Schedule Command（期限満了で `graduated` 自動遷移）+ `UserPlanLog` 履歴（INSERT only、event_type で遷移種別を区別）+ `PlanExpirationService`（期限切れ判定）を提供する。

## 概要

Certify LMS の **プラン受講モデルの中核** を担う Feature。価格情報は LMS 内に持たず（決済は LMS 外）、`Plan` は admin が CRUD する「`duration_days` + `default_meeting_quota` のセット」マスタとして機能する。受講生は admin 招待時に Plan を指定され、`User.plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` が初期化される。プラン期間満了で `User.status` が自動的に `graduated` へ遷移し、プラン機能ロックされる（修了証 PDF DL は永続）。

本 Feature は他 Feature の前提となるため、Phase 2 既存実装修正の **最初に実装する**（auth / user-management / certification-management / content-management の修正前に Plan モデルが必要）。

## ロールごとのストーリー

- **管理者（admin）**: Plan マスタを admin 画面で CRUD する。新規 Plan を作成（例: 「1 ヶ月プラン 4 回」「3 ヶ月プラン 12 回」）、編集（誤入力修正等）、削除（参照なしの場合のみ）。受講生招待時に Plan を select で指定する（[[user-management]] / [[auth]] と連携）。既存受講生の **プラン延長** ボタンで `plan_expires_at` + `max_meetings` を加算（[[user-management]] のユーザー詳細画面から起動）。Schedule Command による自動 `graduated` 遷移を運用ログで確認できる。
- **コーチ（coach）**: 本 Feature の直接操作はない。受講生のプラン情報は [[dashboard]] / [[user-management]] / [[settings-profile]] から間接参照される（読み取り用 Service 経由）。
- **受講生（student）**: 本 Feature の直接操作はない。自分のプラン情報（`plan.name` / `plan_expires_at` / `max_meetings` / 残面談回数）は [[dashboard]] のプラン情報パネルで閲覧。価格情報は LMS 内では表示しない（LMS 外の決済画面で確認）。

## 受け入れ基準（EARS形式）

### 機能要件 — A. Plan マスタ

- **REQ-plan-management-001**: The system shall ULID 主キー / `SoftDeletes` を備えた `plans` テーブルを提供し、`name`（VARCHAR(100), NOT NULL）/ `description`（TEXT, nullable）/ `duration_days`（unsigned smallint, NOT NULL, 1..3650）/ **`default_meeting_quota`（unsigned smallint, NOT NULL, 0..1000）**(N2 解消: design.md と統一) / `status` enum（`draft` / `published` / `archived`、default `draft`）/ `sort_order`（unsigned int, default 0）/ `created_by_user_id`（FK, NOT NULL）/ `updated_by_user_id`（FK, NOT NULL）/ `created_at` / `updated_at` / `deleted_at` を保持する。**`price` カラムは持たない**（決済は LMS 外で完結）。
- **REQ-plan-management-002**: The system shall `App\Enums\PlanStatus` enum（`Draft` / `Published` / `Archived`）を提供し、`label()` メソッドで日本語ラベル（`下書き` / `公開中` / `アーカイブ`）を返す。
- **REQ-plan-management-003**: The system shall `Plan` モデルに `belongsTo(User::class, 'created_by_user_id', 'createdBy')` / `belongsTo(User::class, 'updated_by_user_id', 'updatedBy')` / `hasMany(User::class, 'plan_id')` / `hasMany(UserPlanLog::class)` の 4 リレーションを公開する。

### 機能要件 — B. Plan CRUD（admin 専用）

- **REQ-plan-management-010**: When admin が `GET /admin/plans` にアクセスした際, the system shall Plan 一覧を `sort_order ASC, created_at DESC` でソートして表示する。フィルタ: `status`（`draft` / `published` / `archived`）。
- **REQ-plan-management-011**: When admin が `POST /admin/plans` で Plan を作成した際, the system shall `Plan\StoreRequest` で `name` / `description` / `duration_days` / `default_meeting_quota` / `sort_order` を検証し、`status=Draft` 固定 / `created_by_user_id = $admin->id` で INSERT する。
- **REQ-plan-management-012**: When admin が `PUT /admin/plans/{plan}` で Plan を編集した際, the system shall `Plan\UpdateRequest` で同セットを検証し、`updated_by_user_id = $admin->id` で UPDATE する。`status` は本エンドポイントで変更しない（公開状態遷移用エンドポイント REQ-020〜022 から）。
- **REQ-plan-management-013**: If admin が `published` または `archived` 状態の Plan に対して DELETE を要求した場合, then the system shall HTTP 409 Conflict で `PlanNotDeletableException` を返す（既存 User の `plan_id` 参照整合性を守るため、`draft` のみ削除可）。
- **REQ-plan-management-014**: When admin が `draft` 状態の Plan に対して DELETE を要求した際, the system shall (1) 当該 Plan を `User.plan_id` から参照する User が 1 人もいないことを確認、(2) SoftDelete を実施する。参照あれば `PlanInUseException`（HTTP 409）。
- **REQ-plan-management-015**: If `duration_days` が `0` 以下 / `3650` 超 / 非整数の場合, then the system shall FormRequest バリデーションエラー（422）。
- **REQ-plan-management-016**: If `default_meeting_quota` が負数 / `1000` 超 / 非整数の場合, then the system shall FormRequest バリデーションエラー（422）(N2 解消: design.md と統一)。
- **REQ-plan-management-020**: When admin が `POST /admin/plans/{plan}/publish` を呼んだ際, the system shall `Plan.status = Draft` であれば `Published` に遷移、それ以外は `PlanInvalidTransitionException`（HTTP 409）。
- **REQ-plan-management-021**: When admin が `POST /admin/plans/{plan}/archive` を呼んだ際, the system shall `Plan.status = Published` であれば `Archived` に遷移、それ以外は `PlanInvalidTransitionException`（HTTP 409）。
- **REQ-plan-management-022**: When admin が `POST /admin/plans/{plan}/unarchive` を呼んだ際, the system shall `Plan.status = Archived` であれば `Draft` に遷移、それ以外は `PlanInvalidTransitionException`。

### 機能要件 — C. User × Plan 紐づけ（招待時の Plan 指定 + 初期化）

- **REQ-plan-management-030**: The system shall [[auth]] が所有する `users` テーブルに以下カラムを追加する: `plan_id` ULID FK to `plans.id`（nullable、`invited` 状態では NULL 許容）/ `plan_started_at` datetime（nullable）/ `plan_expires_at` datetime（nullable）/ `max_meetings` unsigned smallint（default 0）。Migration は本 Feature が提供する（[[auth]] / [[user-management]] 実装時に合流）。
- **REQ-plan-management-031**: When [[auth]] の `IssueInvitationAction` が呼ばれる際, the system shall `$planId` 引数（必須）を受け取り、招待 User INSERT 時に `plan_id` をセットする。`plan_started_at` / `plan_expires_at` / `max_meetings` は NULL のまま（オンボーディング完了時に確定）。
- **REQ-plan-management-032**: When [[auth]] の `OnboardAction` が呼ばれる際（受講生がオンボーディング完了時）, the system shall 同一トランザクション内で (1) `User.plan_started_at = now()` / `plan_expires_at = now() + plan.duration_days days` / `max_meetings = plan.default_meeting_quota` を UPDATE、(2) `UserPlanLog` を `event_type = assigned` / `plan_started_at` / `plan_expires_at` / `meeting_quota_initial` で INSERT、(3) [[meeting-quota]] の `MeetingQuotaTransaction` を `type = granted_initial` / `amount = +plan.default_meeting_quota` で INSERT する。
- **REQ-plan-management-033**: If admin が `Published` でない（`draft` or `archived`）Plan で招待を発行しようとした場合, then the system shall `PlanNotPublishedException`（HTTP 422）を返す。

### 機能要件 — D. UserPlanLog（プラン履歴）

- **REQ-plan-management-040**: The system shall ULID 主キー（SoftDelete 非採用、履歴は不可逆）を備えた `user_plan_logs` テーブルを提供し、`user_id`（FK, NOT NULL）/ `plan_id`（FK, NOT NULL）/ `event_type` enum（`assigned` / `renewed` / `canceled` / `expired`）/ `plan_started_at` datetime / `plan_expires_at` datetime / `meeting_quota_initial` unsigned smallint（renewed 時は加算分のみ）/ `changed_by_user_id` ulid nullable（FK、システム自動の場合 NULL）/ `changed_reason` string nullable（max 200）/ `occurred_at` datetime / `created_at` / `updated_at` を保持する。
- **REQ-plan-management-041**: The system shall `App\Enums\UserPlanLogEventType` enum（`Assigned` / `Renewed` / `Canceled` / `Expired`）を提供する。
- **REQ-plan-management-042**: The system shall `UserPlanLog` モデルに `belongsTo(User)` / `belongsTo(Plan)` / `belongsTo(User::class, 'changed_by_user_id', 'changedBy')` の 3 リレーションを公開する。
- **REQ-plan-management-043**: The system shall `user_plan_logs.(user_id, occurred_at)` 複合 INDEX を提供する（ユーザー詳細画面での履歴一覧表示の高速化）。

### 機能要件 — E. プラン延長（ExtendCourseAction）

- **REQ-plan-management-050**: The system shall `App\UseCases\Plan\ExtendCourseAction::__invoke(User $user, Plan $plan, ?User $admin = null, ?string $reason = null): User` を提供する。
- **REQ-plan-management-051**: When `ExtendCourseAction` が呼ばれる際, the system shall 単一トランザクション内で (1) `$user->status === UserStatus::InProgress` を検証（`graduated` や `withdrawn` の場合は `UserNotInProgressException`（HTTP 409）を throw、再加入は新規招待で）、(2) `$plan->status === PlanStatus::Published` を検証（違反は `PlanNotPublishedException`）、(3) `User.plan_expires_at = $user->plan_expires_at + $plan->duration_days days` で UPDATE（既存期限が `now()` より過去でも `now() + duration_days` ではなく **既存 plan_expires_at + duration_days** で延長）、(4) `User.max_meetings = $user->max_meetings + $plan->default_meeting_quota` で UPDATE、(5) `UserPlanLog` を `event_type = renewed` で INSERT、(6) [[meeting-quota]] の `MeetingQuotaTransaction` を `type = granted_initial` / `amount = +$plan->default_meeting_quota` / **`granted_by_user_id = $admin?->id`**(B2 解消: nullable、admin 経由なら admin の ID、システム自動の場合は NULL) で INSERT する。
- **REQ-plan-management-052**: When [[user-management]] のユーザー詳細画面で「プラン延長」ボタンが押下された際, the system shall モーダルで対象 Plan を select で選択させ（複数 Plan を選択可、デフォルトは現契約と同じ Plan）、`reason` を任意入力、`POST /admin/users/{user}/extend-course` で `ExtendCourseAction` を起動する。
- **REQ-plan-management-053**: If 対象 User が `User.status != UserStatus::InProgress` の場合, then the system shall プラン延長ボタンを不活性化し、admin に「再加入の場合は新規招待が必要」と案内する。

### 機能要件 — F. Plan 期間満了による graduated 自動遷移

- **REQ-plan-management-060**: The system shall Schedule Command `users:graduate-expired` を提供し、毎日 00:30 に実行する（`app/Console/Kernel.php`）。
- **REQ-plan-management-061**: When `users:graduate-expired` が起動する際, the system shall `User::where('status', UserStatus::InProgress)->whereNotNull('plan_expires_at')->where('plan_expires_at', '<', now())->get()` を取得し、各 User に対して `GraduateUserAction::__invoke(User $user): void` を実行する。
- **REQ-plan-management-062**: When `GraduateUserAction` が走る際, the system shall 単一トランザクション内で (1) `User.status = UserStatus::Graduated` で UPDATE(**`User.deleted_at` は変更しない**(M11): graduated はログイン可 + プロフィール / 修了証 DL アクセス可なので soft delete しない、`withdrawn` 遷移時のみ soft delete + email リネーム)、(2) `UserPlanLog` を `event_type = expired` / `changed_by_user_id = NULL`（システム自動）/ `changed_reason = '期限満了による自動卒業'` で INSERT する。通知は **発火しない**（プラン期限間近通知は v3 撤回、卒業通知も MVP 外）。
- **REQ-plan-management-063**: The system shall `graduated` 遷移後の User がプラン機能にアクセスしようとした際、[[auth]] の `EnsureActiveLearning` Middleware で 403 を返す（本 Feature では Middleware を直接提供せず、auth が提供する Middleware を起点に判定する）。
- **REQ-plan-management-064**: When admin が `User.plan_expires_at` を手動で延長したい場合, the system shall `ExtendCourseAction` を使うのが標準動線。`UpdateUserAction` などで `plan_expires_at` を直接変更することは認めない（履歴 `UserPlanLog` を必ず残すため）。

### 機能要件 — G. PlanExpirationService（期限切れ判定）

- **REQ-plan-management-070**: The system shall `App\Services\PlanExpirationService` を提供し、以下のメソッドを公開する: `isExpired(User $user): bool`（`plan_expires_at < now()`）、`daysRemaining(User $user): int`（`max(0, ceil(($plan_expires_at - now()) / 1 day))`、`plan_expires_at IS NULL` の場合は `-1` を返して「未設定」を表現）。
- **REQ-plan-management-071**: The system shall `PlanExpirationService` をステートレス Service として実装し、`DB::transaction()` を内部に持たない。
- **REQ-plan-management-072**: When [[dashboard]] の受講生プラン情報パネルが描画される際, the system shall `PlanExpirationService::daysRemaining($auth)` を呼んで「プラン残日数」を取得する。

### 機能要件 — H. 認可（Policy + Middleware）

- **REQ-plan-management-080**: The system shall `/admin/plans/...` 群を `auth + role:admin` Middleware で保護する。
- **REQ-plan-management-081**: The system shall `PlanPolicy` を提供し、`viewAny` / `view` / `create` / `update` / `delete` / `publish` / `archive` のすべてを admin true、coach / student false で判定する。
- **REQ-plan-management-082**: The system shall `POST /admin/users/{user}/extend-course`（プラン延長動線）も `auth + role:admin` Middleware で保護する。`UserPolicy::extendCourse` で admin true のみ。

### 非機能要件

- **NFR-plan-management-001**: The system shall すべての状態変更を伴う Action（`Plan\StoreAction` / `Plan\UpdateAction` / `Plan\DestroyAction` / `Plan\PublishAction` / `Plan\ArchiveAction` / `ExtendCourseAction` / `GraduateUserAction`）を `DB::transaction()` で囲む。
- **NFR-plan-management-002**: The system shall Plan 一覧の N+1 を `withCount('users')` 等で避ける。
- **NFR-plan-management-003**: The system shall 以下 INDEX を提供: `plans.(status, sort_order)` 複合 / `plans.deleted_at` / `users.plan_id`（[[auth]] / [[user-management]] が users テーブルに index 設定） / `users.(status, plan_expires_at)` 複合（Schedule Command の高速化） / `user_plan_logs.(user_id, occurred_at)` 複合 / `user_plan_logs.plan_id`。
- **NFR-plan-management-004**: The system shall ドメイン例外を `app/Exceptions/Plan/` 配下に実装する（`PlanNotDeletableException` / `PlanInUseException` / `PlanInvalidTransitionException` / `PlanNotPublishedException` / `UserNotInProgressException`）。
- **NFR-plan-management-005**: The system shall `UserPlanLog` への記録を呼出元 Action が責務として担保する（Service ではなく Action が直接 INSERT、INSERT only）。
- **NFR-plan-management-006**: The system shall admin 画面を Wave 0b の共通 Blade コンポーネントに準拠して構築する。

## スコープ外

- **Plan の価格情報** — `Plan.price` カラム不採用、決済は LMS 外で完結
- **受講生が LMS 内で初回 Plan 購入** — admin 招待制（[[product.md]] 確定）
- **Plan の途中変更 / 乗換時の按分精算** — 撤回（プラン延長のみ提供）
- **1 User が複数 Plan を同時保有** — `User.plan_id` は 1 つ
- **Plan 期間間近通知**（受講生 / admin 宛）— MVP 外
- **graduated 遷移時の通知** — MVP 外（受講生は dashboard で残日数を確認）
- **Plan の自動再課金 / サブスクリプション** — Stripe ダッシュボード側で完結
- **プラン延長時に新 Plan の `default_meeting_quota` を **置換**** — 採用しない（加算のみ、未使用回数は失わない）

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[auth]] — `IssueInvitationAction` / `OnboardAction` から Plan を参照、User Migration に plan 関連カラム追加（本 Feature が Migration を提供）
  - [[user-management]] — ユーザー詳細画面に「プラン延長」ボタン + プラン情報表示
  - [[settings-profile]] — 受講生プロフィール画面でプラン情報を読み取り（ただし表示は dashboard 経由が主）
  - [[dashboard]] — プラン情報パネル + 残面談回数 + プラン残日数を表示
  - [[meeting-quota]] — Plan 起点で `max_meetings` 初期付与、`ExtendCourseAction` で加算
  - [[notification]] — Plan 期限間近通知は不採用、卒業通知も発火しない
- **依存先**（本 Feature が前提とする）:
  - [[auth]] — `User` モデル / `UserStatus` Enum（`InProgress` / `Graduated` 値の利用）
  - [[meeting-quota]] — `MeetingQuotaTransaction` を INSERT する契約（`type = granted_initial`）
