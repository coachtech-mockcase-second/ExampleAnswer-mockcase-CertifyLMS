# user-management タスクリスト

> 1 タスク = 1 コミット粒度。
> 関連要件 ID は `requirements.md` の `REQ-user-management-NNN` / `NFR-user-management-NNN` を参照。
> 本 Feature は [[auth]] の `User` / `Invitation` モデル + `IssueInvitationAction(Plan $plan, ...)`（v3 で Plan 引数必須）/ `RevokeInvitationAction` が **先行実装済み** であることを前提とする。
> **v3 改修反映**: 招待モーダル plan_id / プラン情報パネル / プラン延長 UI / 面談回数手動付与 UI / プロフィール編集・ロール変更撤回 / status filter graduated / `UserStatus::Active` → `InProgress` 統一。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Model

### Migration

- [ ] `database/migrations/{date}_create_user_status_logs_table.php`(ULID PK + `user_id` FK + **`event_type` enum**(2026-05-16 追加、`UserStatusEventType` cast、現時点では `status_change` 1 値) + `from_status` enum **4 値**(v3) + `to_status` enum **4 値**(v3) + `changed_by_user_id` FK nullable + `changed_reason` text nullable + `changed_at` datetime + `(user_id, changed_at)` 複合 INDEX + **`(event_type, changed_at)` 複合 INDEX**(将来 event_type 拡張時の高速化)、softDelete なし)(REQ-user-management-070)

### Model

- [ ] `App\Models\UserStatusLog`(`HasUlids` + fillable(`event_type` 含む) + `$casts['event_type'=>UserStatusEventType, 'from_status'=>UserStatus, 'to_status'=>UserStatus, 'changed_at'=>'datetime']` + `belongsTo(User, user_id)` + `belongsTo(User, changed_by_user_id, changedBy)` (withTrashed 含む) + `scopeOfEventType(UserStatusEventType)` 新規)(REQ-user-management-070)
- [ ] **Enum: `App\Enums\UserStatusEventType`**(2026-05-16 新設、`StatusChange = 'status_change'` の 1 値、`label()` で「ステータス変更」を返す、将来拡張余地あり)(REQ-user-management-070b)
- [ ] Factory: `UserStatusLogFactory`(`from(UserStatus)` / `to(UserStatus)` / `byAdmin(User)` / `bySystem()` state、v3 で 4 値対応。**`event_type` のデフォルトは `UserStatusEventType::StatusChange`**)

## Step 2: Policy

- [ ] `App\Policies\UserPolicy::view`(admin true)
- [ ] **`UserPolicy::withdraw`(admin true)** — last admin 検査は Action 側
- [ ] **`UserPolicy::extendCourse`**(v3 新規、admin true)
- [ ] **`UserPolicy::grantMeetingQuota`**(v3 新規、admin true)
- [ ] **`UserPolicy::update` / `UserPolicy::updateRole` は提供しない**(v3 撤回)
- [ ] `AuthServiceProvider::$policies` 登録

## Step 3: HTTP 層

### Controller

- [ ] `App\Http\Controllers\Admin\UserController`(`index(IndexRequest)` / `show($user)` / `withdraw($user)` / **`extendCourse($user, ExtendCourseRequest)`**(v3 新規) / **`grantMeetingQuota($user, GrantMeetingQuotaRequest)`**(v3 新規))
- [ ] `App\Http\Controllers\Admin\InvitationController`(`store(StoreInvitationRequest)` / `reissue($user)` / `revoke($invitation)`)
- [ ] **`update` / `updateRole` メソッドは提供しない**(v3 撤回)

### FormRequest

- [ ] `App\Http\Requests\Admin\User\IndexRequest`(`role: nullable Rule::enum(UserRole)` / **`status: nullable Rule::enum(UserStatus)`**(v3 で 4 値) / `keyword: nullable string max:100` / `page: nullable integer`)
- [ ] **`App\Http\Requests\Admin\Invitation\StoreRequest`(v3 更新)** — `email: required email max:255 unique:users,email,NULL,id,deleted_at,NULL` / `role: required Rule::enum(UserRole)` / **`plan_id: required ulid exists:plans,id`**(v3 必須)
- [ ] **`App\Http\Requests\Admin\User\ExtendCourseRequest`(v3 新規)** — `plan_id: required ulid exists:plans,id`
- [ ] **`App\Http\Requests\Admin\User\GrantMeetingQuotaRequest`(v3 新規)** — `amount: required integer min:1 max:100` / `reason: nullable string max:200`
- [ ] **削除(v3 撤回)**: `UpdateRequest` / `UpdateRoleRequest`

### Route

- [ ] `routes/web.php`:
  - `auth + role:admin` group + `prefix('admin')`:
    - `Route::resource('users', UserController::class)->only(['index', 'show'])`(**`update` / `destroy` は撤回**、v3)
    - `Route::post('users/{user}/withdraw', ...)`
    - **`Route::post('users/{user}/extend-course', ...)`(v3 新規)**
    - **`Route::post('users/{user}/grant-meeting-quota', ...)`(v3 新規)**
    - `Route::post('invitations', ...)` / `Route::post('users/{user}/invitations/reissue', ...)` / `Route::post('invitations/{invitation}/revoke', ...)`

## Step 4: Action / Service / Exception

### Action(`.claude/rules/backend-usecases.md`「Feature 間連携のラッパー Action」規約準拠)

`app/UseCases/User/`(`Admin\UserController` 対応):

- [ ] `IndexAction.php`(`UserController::index`、`IndexRequest` フィルタ + paginate)
- [ ] `ShowAction.php`(`UserController::show`、詳細取得 + 関連 Eager Load)
- [ ] **`WithdrawAction.php`** — `__invoke(User $user, ?User $admin = null)`、last admin 検査 + `User::withdraw()` ヘルパ呼出 + `UserStatusChangeService::record($user, Withdrawn, $admin, '管理者による退会')`、`DB::transaction`
- [ ] **`ExtendCourseAction.php`(v3 新規、ラッパー)** — `UserController::extendCourse` 対応、内部で [[plan-management]] の `Plan\ExtendCourseAction($user, $plan, $admin, $reason)` を DI 呼出
- [ ] **`GrantMeetingQuotaAction.php`(v3 新規、ラッパー)** — `UserController::grantMeetingQuota` 対応、内部で [[meeting-quota]] の `AdminGrantQuotaAction($user, $amount, $admin, $reason)` を DI 呼出
- [ ] **削除(v3 撤回)**: `UpdateAction`(プロフィール編集) / `UpdateRoleAction`(ロール変更)

`app/UseCases/Invitation/`(`Admin\InvitationController` 対応、すべて [[auth]] の Action を内部 DI するラッパー):

- [ ] **`StoreAction.php`** — `InvitationController::store` 対応、内部で [[auth]] の `IssueInvitationAction($email, $role, $plan, $admin)` を呼ぶ
- [ ] **`ReissueAction.php`** — `InvitationController::reissue` 対応、内部で [[auth]] の `IssueInvitationAction` を `force: true` で呼ぶ
- [ ] **`RevokeAction.php`** — `InvitationController::revoke` 対応、内部で [[auth]] の `RevokeInvitationAction($invitation)` を呼ぶ

### Service

- [ ] **`App\Services\UserStatusChangeService`** — `record(User $user, UserStatus $newStatus, ?User $changedBy, ?string $reason): UserStatusLog`(`from_status` は呼出時の `$user->status` を取得、`to_status = $newStatus`、**`event_type = UserStatusEventType::StatusChange` を内部で自動挿入**(2026-05-16、`UserPlanLog` とフォーマット統一)、`$user->status = $newStatus` UPDATE は呼出側責務、本 Service は **`UserStatusLog` の INSERT のみ**)、INSERT only、`DB::transaction` 非保有(呼出側で囲む)

### ドメイン例外(`app/Exceptions/UserManagement/`)

- [ ] `LastAdminWithdrawException`(HTTP 409、「最後の管理者は退会できません。」)
- [ ] `UserAlreadyWithdrawnException`(HTTP 409)

## Step 5: Blade ビュー

- [ ] `views/admin/users/index.blade.php`(ユーザー一覧 + フィルタ + 「+ 新規招待」)
- [ ] `views/admin/users/show.blade.php`(詳細 + プラン情報パネル + アクションボタン)
- [ ] `views/admin/users/_modals/invitation.blade.php`(email + role + **plan_id select**(v3、`Plan::published()->ordered()->get()` から))
- [ ] **`views/admin/users/_modals/extend-course.blade.php`(v3 新規)** — plan_id select(同様の選択肢)
- [ ] **`views/admin/users/_modals/grant-meeting-quota.blade.php`(v3 新規)** — amount(number 入力) + reason(textarea)
- [ ] `views/admin/users/_modals/withdraw-confirm.blade.php`
- [ ] **`views/admin/users/_partials/plan-info-panel.blade.php`(v3 新規)** — Plan 名 / plan_started_at / plan_expires_at / プラン残日数 / max_meetings / 残面談回数(`MeetingQuotaService::remaining` 経由)
- [ ] `views/admin/users/_partials/status-history.blade.php`(`UserStatusLog` 一覧)
- [ ] `views/admin/users/_partials/invitation-history.blade.php`(`Invitation` 一覧)

### 明示的に持たない Blade(v3 撤回)

- **`_modals/profile-edit.blade.php`** — admin による他者プロフィール編集動線なし
- **`_modals/role-change.blade.php`** — admin による他者ロール変更動線なし

## Step 6: テスト

### Feature(HTTP)

- [ ] `tests/Feature/Http/Admin/User/IndexTest.php`(role / status / keyword フィルタ動作、**status=graduated フィルタ**(v3))
- [ ] `tests/Feature/Http/Admin/User/ShowTest.php`(プロフィール + プラン情報 + 履歴表示、**プラン情報パネル表示**(v3))
- [ ] `tests/Feature/Http/Admin/User/WithdrawTest.php`(last admin で 409 / 正常時 status=withdrawn 遷移 + UserStatusLog 記録)
- [ ] **`tests/Feature/Http/Admin/User/ExtendCourseTest.php`(v3 新規)** — plan_expires_at + max_meetings 加算 / UserPlanLog renewed 記録 / MeetingQuotaTransaction granted_initial 記録 / 不正 plan_id で 422
- [ ] **`tests/Feature/Http/Admin/User/GrantMeetingQuotaTest.php`(v3 新規)** — MeetingQuotaTransaction admin_grant 記録 / granted_by_user_id=admin / amount=0 / amount=101 で 422
- [ ] **`tests/Feature/Http/Admin/Invitation/StoreTest.php`(v3 更新)** — plan_id 必須(欠落で 422) / 不正 plan_id で 422 / 正常時 User INSERT(plan_id / max_meetings 反映) + UserPlanLog assigned 記録 + InvitationMail dispatch
- [ ] `tests/Feature/Http/Admin/Invitation/ReissueTest.php`(既存 plan_id 再利用、`force=true`)
- [ ] `tests/Feature/Http/Admin/Invitation/RevokeTest.php`

### 明示的に持たないテスト(v3 撤回)

- **`Admin/User/UpdateTest.php`** — プロフィール編集動線なし
- **`Admin/User/UpdateRoleTest.php`** — ロール変更動線なし

### Feature(UseCases / Services)

- [ ] `tests/Feature/UseCases/UserManagement/WithdrawActionTest.php`
- [ ] `tests/Feature/Services/UserStatusChangeServiceTest.php`(`record` + UserStatusLog INSERT、**4 値網羅**(Invited / InProgress / Graduated / Withdrawn、v3)、**`event_type = StatusChange` が自動挿入されること**(2026-05-16)、UserStatusEventType Enum のラベル取得)

### Unit(Policy)

- [ ] `tests/Unit/Policies/UserPolicyTest.php`(view / withdraw / **extendCourse**(v3) / **grantMeetingQuota**(v3) の admin 真偽値網羅、`update` / `updateRole` テストなし)

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=UserManagement` 全件 pass
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認シナリオ:
  - [ ] admin で /admin/users → 一覧表示 + **status=graduated フィルタ**(v3) 動作
  - [ ] admin で 「+ 新規招待」 → モーダルに **plan_id select** 表示(v3) → 発行 → 招待メール送信
  - [ ] admin で /admin/users/{user} 詳細表示 → **プラン情報パネル**(v3) + 受講中資格 + 履歴表示
  - [ ] **「プラン延長」ボタン押下**(v3) → モーダル → Plan 選択 → plan_expires_at / max_meetings 加算反映
  - [ ] **「面談回数手動付与」ボタン押下**(v3) → モーダル → amount 入力 → MeetingQuotaTransaction admin_grant 記録
  - [ ] 「強制退会」ボタン押下 → 確認モーダル → status=withdrawn + soft delete + email リネーム
  - [ ] last admin 退会試行で 409
- [ ] **v3 撤回確認**:
  - [ ] /admin/users/{user}/edit URL 直叩き → 404 / 405(ルート定義なし)
  - [ ] /admin/users/{user}/role URL 直叩き → 404 / 405
