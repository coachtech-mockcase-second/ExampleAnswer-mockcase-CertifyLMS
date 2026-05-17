# user-management タスクリスト

> 1 タスク = 1 コミット粒度。
> 関連要件 ID は `requirements.md` の `REQ-user-management-NNN` / `NFR-user-management-NNN` を参照。
> 本 Feature は [[auth]] の `User` / `Invitation` モデル + `IssueInvitationAction(Plan $plan, ...)`（v3 で Plan 引数必須）/ `RevokeInvitationAction` が **先行実装済み** であることを前提とする。
> **v3 改修反映**: 招待モーダル plan_id / プラン情報パネル / プラン延長 UI / 面談回数手動付与 UI / プロフィール編集・ロール変更撤回 / status filter graduated / `UserStatus::Active` → `InProgress` 統一。
> **2026-05-17 設計修正**: `UserStatusLog.event_type` カラムごと撤回(from/to で遷移を表現できるため冗長、`UserPlanLog.event_type` のみ 4 値で必要)。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Model

### Migration

- [x] `database/migrations/{date}_create_user_status_logs_table.php`(ULID PK + `user_id` FK + `from_status` enum **4 値**(v3) + `to_status` enum **4 値**(v3) + `changed_by_user_id` FK nullable + `changed_reason` text nullable + `changed_at` datetime + `(user_id, changed_at)` 複合 INDEX、softDelete なし。**イベント分類用の event_type カラムは持たない**: from/to で遷移を表現するため冗長カラムを排除、2026-05-17 設計修正)(REQ-user-management-070)

### Model

- [x] `App\Models\UserStatusLog`(`HasUlids` + fillable(`from_status` / `to_status` 含む) + `$casts['from_status'=>UserStatus, 'to_status'=>UserStatus, 'changed_at'=>'datetime']` + `belongsTo(User, user_id)` + `belongsTo(User, changed_by_user_id, changedBy)` (withTrashed 含む) + `scopeForUser` / `scopeRecent`)(REQ-user-management-070)
- [x] Factory: `UserStatusLogFactory`(`from(UserStatus)` / `to(UserStatus)` / `byAdmin(User)` / `bySystem()` state、v3 で 4 値対応)

## Step 2: Policy

- [x] `App\Policies\UserPolicy::view`(admin true)
- [x] **`UserPolicy::withdraw`(admin true)** — last admin 検査は Action 側
- [x] **`UserPolicy::extendCourse`**(v3 新規、admin true)
- [x] **`UserPolicy::grantMeetingQuota`**(v3 新規、admin true)
- [x] **`UserPolicy::update` / `UserPolicy::updateRole` は提供しない**(v3 撤回)
- [x] `AuthServiceProvider::$policies` 登録

## Step 3: HTTP 層

### Controller

- [x] `App\Http\Controllers\UserController`(`index(IndexRequest)` / `show($user)` / `withdraw($user)` / **`extendCourse($user, ExtendCourseRequest)`**(v3 新規) / **`grantMeetingQuota($user, GrantMeetingQuotaRequest)`**(v3 新規))
- [x] `App\Http\Controllers\InvitationController`(`store(StoreRequest)` / `resend($user)` / `destroy($invitation)`)
- [x] **`update` / `updateRole` メソッドは提供しない**(v3 撤回)

### FormRequest

- [x] `App\Http\Requests\User\IndexRequest`(`role: nullable Rule::enum(UserRole)` / **`status: nullable Rule::enum(UserStatus)`**(v3 で 4 値) / `keyword: nullable string max:100` / `page: nullable integer`)
- [x] **`App\Http\Requests\Invitation\StoreRequest`(v3 更新)** — `email: required email max:255` / `role: required in:coach,student` / **`plan_id: required_if:role,student exists:plans,id`**(v3 必須)
- [x] **`App\Http\Requests\User\ExtendCourseRequest`(v3 新規)** — `plan_id: required ulid exists:plans,id`
- [x] **`App\Http\Requests\User\GrantMeetingQuotaRequest`(v3 新規)** — `amount: required integer min:1 max:100` / `reason: nullable string max:200`
- [x] **削除(v3 撤回)**: `UpdateRequest` / `UpdateRoleRequest`

### Route

- [x] `routes/web.php`:
  - `auth + role:admin` group + `prefix('admin')`:
    - `Route::get/post users` + `Route::get users/{user}`(**`update` / `destroy` は撤回**、v3)
    - `Route::post('users/{user}/withdraw', ...)`
    - **`Route::post('users/{user}/extend-course', ...)`(v3 新規)**
    - **`Route::post('users/{user}/grant-meeting-quota', ...)`(v3 新規)**
    - `Route::post('invitations', ...)` / `Route::post('users/{user}/resend-invitation', ...)` / `Route::delete('invitations/{invitation}', ...)`

## Step 4: Action / Service / Exception

### Action(`.claude/rules/backend-usecases.md`「Feature 間連携のラッパー Action」規約準拠)

`app/UseCases/User/`(`UserController` 対応):

- [x] `IndexAction.php`(`UserController::index`、`IndexRequest` フィルタ + paginate + plan Eager Load)
- [x] `ShowAction.php`(`UserController::show`、詳細取得 + 関連 Eager Load)
- [x] **`WithdrawAction.php`** — `__invoke(User $user, ?User $admin = null)`、last admin 検査 + `UserStatusChangeService::record` + `User::withdraw()` ヘルパ呼出 + `'管理者による退会'` 固定、`DB::transaction`
- [x] **`ExtendCourseAction.php`(v3 新規、ラッパー)** — `UserController::extendCourse` 対応、内部で `\App\UseCases\Plan\ExtendCourseAction($user, $plan, $admin, $reason)` を DI 呼出
- [x] **`GrantMeetingQuotaAction.php`(v3 新規、ラッパー)** — `UserController::grantMeetingQuota` 対応、内部で `\App\UseCases\MeetingQuota\AdminGrantQuotaAction($user, $amount, $admin, $reason)` を DI 呼出
- [x] **削除(v3 撤回)**: `UpdateAction`(プロフィール編集) / `UpdateRoleAction`(ロール変更)

`app/UseCases/Invitation/`(`InvitationController` 対応、すべて [[auth]] の Action を内部 DI するラッパー):

- [x] **`StoreAction.php`** — `InvitationController::store` 対応、内部で `\App\UseCases\Auth\IssueInvitationAction($email, $role, $plan, $admin)` を呼ぶ
- [x] **`ResendAction.php`** — `InvitationController::resend` 対応、内部で `\App\UseCases\Auth\IssueInvitationAction` を `force: true` で呼ぶ
- [x] **`DestroyAction.php`** — `InvitationController::destroy` 対応、内部で `\App\UseCases\Auth\RevokeInvitationAction($invitation)` を呼ぶ

### Service

- [x] **`App\Services\UserStatusChangeService`** — `record(User $user, UserStatus $newStatus, ?User $changedBy, ?string $reason): UserStatusLog`(`from_status` は呼出時の `$user->status` を取得、`to_status = $newStatus`、`$user->status = $newStatus` UPDATE は呼出側責務、本 Service は **`UserStatusLog` の INSERT のみ**)、INSERT only、`DB::transaction` 非保有(呼出側で囲む)

### ドメイン例外(`app/Exceptions/UserManagement/`)

- [x] `LastAdminWithdrawException`(HTTP 409、「最後の管理者は退会できません。」)
- [x] `UserAlreadyWithdrawnException`(HTTP 409)

## Step 5: Blade ビュー

- [x] `views/admin/users/index.blade.php`(ユーザー一覧 + フィルタ + 「+ 新規招待」+ プラン名・残日数列)
- [x] `views/admin/users/show.blade.php`(詳細 + プラン情報パネル + アクションボタン)
- [x] `views/admin/users/_modals/invite-user-form.blade.php`(email + role + **plan_id select**(v3、`Plan::published()->ordered()->get()` から))
- [x] **`views/admin/users/_modals/extend-course.blade.php`(v3 新規)** — plan_id select(同様の選択肢)
- [x] **`views/admin/users/_modals/grant-meeting-quota.blade.php`(v3 新規)** — amount(number 入力) + reason(textarea)
- [x] `views/admin/users/_modals/withdraw-confirm.blade.php`
- [x] **`views/admin/users/_partials/plan-info-panel.blade.php`(v3 新規)** — Plan 名 / plan_started_at / plan_expires_at / プラン残日数 / max_meetings / 残面談回数(`MeetingQuotaService::remaining` 経由)
- [x] `views/admin/users/_partials/status-log-timeline.blade.php`(`UserStatusLog` 一覧、from→to 表示)
- [x] `views/admin/users/_partials/invitation-history.blade.php`(`Invitation` 一覧)

### 明示的に持たない Blade(v3 撤回)

- **`_modals/profile-edit.blade.php`** — admin による他者プロフィール編集動線なし
- **`_modals/role-change.blade.php`** — admin による他者ロール変更動線なし

## Step 6: テスト

### Feature(HTTP)

- [x] `tests/Feature/Http/User/IndexTest.php`(role / status / keyword フィルタ動作、**status=graduated フィルタ**(v3))
- [x] `tests/Feature/Http/User/ShowTest.php`(プロフィール + プラン情報 + 履歴表示、**プラン情報パネル表示**(v3))
- [x] `tests/Feature/Http/User/WithdrawTest.php`(last admin で 409 / 正常時 status=withdrawn 遷移 + UserStatusLog 記録)
- [x] **`tests/Feature/Http/User/ExtendCourseTest.php`(v3 新規)** — plan_expires_at + max_meetings 加算 / UserPlanLog renewed 記録 / MeetingQuotaTransaction granted_initial 記録 / 不正 plan_id で 422
- [x] **`tests/Feature/Http/User/GrantMeetingQuotaTest.php`(v3 新規)** — MeetingQuotaTransaction admin_grant 記録 / granted_by_user_id=admin / amount=0 / amount=101 で 422
- [x] **`tests/Feature/Http/Invitation/StoreTest.php`(v3 更新)** — plan_id 必須(欠落で 422) / 不正 plan_id で 422 / 正常時 User INSERT(plan_id / max_meetings 反映) + UserPlanLog assigned 記録 + InvitationMail dispatch
- [x] `tests/Feature/Http/Invitation/ResendTest.php`(既存 plan_id 再利用、`force=true`)
- [x] `tests/Feature/Http/Invitation/DestroyTest.php`

### 明示的に持たないテスト(v3 撤回)

- **`Http/User/UpdateTest.php`** — プロフィール編集動線なし
- **`Http/User/UpdateRoleTest.php`** — ロール変更動線なし

### Feature(UseCases / Services)

- [x] `tests/Unit/UseCases/User/WithdrawActionTest.php`
- [x] `tests/Unit/Services/UserStatusChangeServiceTest.php`(`record` + UserStatusLog INSERT、**4 値網羅**(Invited / InProgress / Graduated / Withdrawn、v3))

### Unit(Policy)

- [x] `tests/Unit/Policies/UserPolicyTest.php`(view / withdraw / **extendCourse**(v3) / **grantMeetingQuota**(v3) の admin 真偽値網羅、`update` / `updateRole` テストなし)

## Step 7: Factory + Seeder

- [x] `database/factories/InvitationFactory.php`(status 網羅 state: `pending()` / `accepted()` / `expired()` / `revoked()`)
- [x] `database/factories/UserStatusLogFactory.php`(各 from/to 組合せ state)
- [x] **Seeder 不要**: 本 Feature は [[auth]] の `UserSeeder` が投入した admin / coach / student / 各 status の demo students に対する CRUD・履歴表示が責務のため、専用 Seeder は提供しない(`structure.md` Seeder 規約「⑤ 自己リソース系」分類)。一覧フィルタ・status バッジ・プラン情報パネル等の動作確認は `UserSeeder` の状態網羅 demo データで担保される

## Step 8: 動作確認 & 整形

- [x] `sail artisan test --filter=UserManagement` 全件 pass
- [x] `sail bin pint --dirty` 整形
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
