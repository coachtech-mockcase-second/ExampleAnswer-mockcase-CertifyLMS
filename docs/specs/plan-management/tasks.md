# plan-management 実装タスク

> **v3 Blocker 解消(Phase D / F)**:
> - **D-2: Step 1 で UserStatus enum 拡張 + active → in_progress 移行 Migration を同梱**(本 Feature 最重要)
> - D-1: `GrantInitialQuotaAction` 統一シグネチャで meeting-quota を呼ぶ
> - D-4: Controller / FormRequest / Policy / Route の API 契約を design.md で明示
> - **M1(Phase F): 実装着手時に各タスクへ REQ ID 注記**(`REQ-plan-management-NNN` を行末に `(REQ-NNN)` 形式で追加。requirements.md と design.md の関連要件マッピングで全 REQ ID を確認できる)

## Step 1: Migration(D-2 で UserStatus enum 拡張同梱)

- [x] `database/migrations/{timestamp}_create_plans_table.php`(ULID + SoftDeletes + name + description + duration_days + default_meeting_quota + status enum + sort_order + created_by + updated_by + INDEX (status, sort_order))
- [x] **`database/migrations/{timestamp}_extend_user_status_enum.php`(D-2 新規)** — `users.status` enum 拡張: `('invited', 'active', 'withdrawn')` → `('invited', 'active', 'in_progress', 'graduated', 'withdrawn')` の **5 値暫定**(両立期、データ移行のため)
- [x] **`database/migrations/{timestamp}_migrate_user_status_active_to_in_progress.php`(D-2 新規)** — `UPDATE users SET status='in_progress' WHERE status='active'`(全 active を in_progress に移行)
- [x] **`database/migrations/{timestamp}_finalize_user_status_enum.php`(D-2 新規)** — enum から `'active'` を削除し `('invited', 'in_progress', 'graduated', 'withdrawn')` の **4 値最終形** に確定
- [x] `database/migrations/{timestamp}_add_plan_columns_to_users_table.php`(`plan_id` ULID FK nullable / `plan_started_at` datetime nullable / `plan_expires_at` datetime nullable / `max_meetings` unsigned smallint default 0 + INDEX (plan_id) / (status, plan_expires_at))
- [x] `database/migrations/{timestamp}_create_user_plan_logs_table.php`(ULID + `user_id` / `plan_id` FK restrict + event_type enum + plan_started_at + plan_expires_at + meeting_quota_initial + changed_by_user_id nullable + changed_reason + occurred_at + INDEX (user_id, occurred_at))

## Step 2: Enum / Model

- [x] `app/Enums/PlanStatus.php`(`Draft` / `Published` / `Archived` + `label()`)
- [x] `app/Enums/UserPlanLogEventType.php`(`Assigned` / `Renewed` / `Canceled` / `Expired` + `label()`)
- [x] **`app/Enums/UserStatus.php` を更新(D-2)** — 旧 `Invited` / `Active` / `Withdrawn` → **`Invited` / `InProgress`**(v3 で `Active` から rename) / **`Graduated`**(v3 新規) / `Withdrawn`(本 Feature が責任を持つ enum 拡張、[[auth]] が利用)
- [x] `app/Models/Plan.php`(`HasUlids` + `HasFactory` + `SoftDeletes` + fillable + `$casts['status'=>PlanStatus, 'duration_days'=>'integer', 'default_meeting_quota'=>'integer', 'sort_order'=>'integer']` + `belongsTo(User, created_by_user_id, createdBy)` + `belongsTo(User, updated_by_user_id, updatedBy)` + `hasMany(User)` + `hasMany(UserPlanLog)` + `scopePublished` / `scopeOrdered`)
- [x] `app/Models/UserPlanLog.php`(`HasUlids` + `HasFactory`、SoftDelete 不採用 + fillable + `$casts['event_type'=>UserPlanLogEventType, 'plan_started_at'=>'datetime', 'plan_expires_at'=>'datetime', 'occurred_at'=>'datetime']` + `belongsTo(User)` + `belongsTo(Plan)` + `belongsTo(User, changed_by_user_id, changedBy)`)
- [x] `app/Models/User.php` 拡張(`plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` の fillable + cast + `belongsTo(Plan)`、`hasMany(UserPlanLog)`)

## Step 3: Policy

- [x] `app/Policies/PlanPolicy.php`(`viewAny` / `view` / `create` / `update` / `delete` / `publish` / `archive` / `unarchive`、admin true のみ、`delete` は draft + 参照中でない条件)
- [x] **`app/Policies/UserPolicy.php` 拡張** — `extendCourse(User $auth, User $target)` メソッド追加(admin のみ true)、[[user-management]] と協調

## Step 4: ドメイン例外

- [x] `app/Exceptions/Plan/PlanNotDeletableException.php`(HTTP 409、published / archived / 参照中)
- [x] `app/Exceptions/Plan/PlanInvalidTransitionException.php`(HTTP 409、status 遷移違反)
- [x] `app/Exceptions/Plan/PlanNotPublishedException.php`(HTTP 422、招待・延長で published 以外)
- [x] `app/Exceptions/Plan/UserNotInProgressException.php`(HTTP 409、graduated / withdrawn ユーザーの延長)

> **`PlanInUseException` は `PlanNotDeletableException` に統合**(d-4 で重複排除)

## Step 5: FormRequest

- [x] `app/Http/Requests/Plan/StoreRequest.php`(`name` / `description` / `duration_days: 1..3650` / `default_meeting_quota: 0..1000` / `sort_order`、authorize: `PlanPolicy::create`)
- [x] `app/Http/Requests/Plan/UpdateRequest.php`(同 rules、authorize: `PlanPolicy::update`)
- [x] `app/Http/Requests/Plan/IndexRequest.php`(`status` / `keyword` 任意フィルタ、authorize: `PlanPolicy::viewAny`)
- [x] **`app/Http/Requests/Admin/User/ExtendCourseRequest.php`** — [[user-management]] 側で定義、本 Feature では参照のみ(`plan_id: required ulid exists:plans,id`、authorize: `UserPolicy::extendCourse`)

## Step 6: UseCase / Action

- [x] `app/UseCases/Plan/IndexAction.php` / `ShowAction.php` / `StoreAction.php` / `UpdateAction.php` / `DestroyAction.php`(409 ガード)
- [x] `app/UseCases/Plan/PublishAction.php` / `ArchiveAction.php` / `UnarchiveAction.php`
- [x] **`app/UseCases/Plan/ExtendCourseAction.php`(D-1)** — `__invoke(User $user, Plan $plan, ?User $admin = null, ?string $reason = null): User`、`UserNotInProgressException` + `PlanNotPublishedException` ガード + 期限延長 + 回数加算 + `UserPlanLogService::record(Renewed)` + **`GrantInitialQuotaAction($user, $plan->default_meeting_quota, $admin, $reason)`**(D-1、統一シグネチャで meeting-quota の Action を呼ぶ)、`DB::transaction`
- [x] **`app/UseCases/Plan/GraduateUserAction.php`** — `UserStatusChangeService::record($user, Graduated, null, ...)` + `UserPlanLogService::record(Expired)`、`DB::transaction`

## Step 7: Service

- [x] `app/Services/PlanExpirationService.php`(`isExpired(User): bool` / `daysRemaining(User): int`)
- [x] **`app/Services/UserPlanLogService.php`** — `record(User, Plan, UserPlanLogEventType, ?User $changedBy = null, ?string $reason = null): UserPlanLog`(INSERT only、各 Feature の Action から呼ばれる)

## Step 8: Controller

- [x] `app/Http/Controllers/Admin/PlanController.php`(`index` / `show` / `create` / `store` / `edit` / `update` / `destroy`)
- [x] `app/Http/Controllers/Admin/PlanStatusController.php`(`publish` / `archive` / `unarchive`)
- [x] **`Admin\UserController::extendCourse` は [[user-management]] 所有**(本 Feature の `ExtendCourseAction` を DI で呼ぶ)

## Step 9: Schedule Command

- [x] `app/Console/Commands/GraduateExpiredUsersCommand.php`(`users:graduate-expired` signature、`User::where('status', InProgress)->whereNotNull('plan_expires_at')->where('plan_expires_at', '<', now())->each(fn($u) => $action($u))`)
- [x] `app/Console/Kernel.php` に登録(`->dailyAt('00:45')->withoutOverlapping(5)`、M10 衝突回避: [[auth]] の `invitations:expire`(00:30) と時刻ずらし + 両 Command に `withoutOverlapping(5)` 付与で多重起動防止)

## Step 10: Route

- [x] `routes/web.php` に追加(`auth + role:admin` group + `prefix('admin')`):
  - `Route::resource('plans', PlanController::class)`
  - `Route::post('plans/{plan}/publish'|'archive'|'unarchive', PlanStatusController::class, ...)`
- [x] **`Admin\UserController::extendCourse` のルート登録は [[user-management]] 側**

## Step 11: Blade

- [x] `resources/views/admin/plans/index.blade.php`(一覧 + status フィルタ + 「+ 新規 Plan」ボタン)
- [x] `resources/views/admin/plans/create.blade.php` / `edit.blade.php`(フォーム)
- [x] `resources/views/admin/plans/show.blade.php`(詳細 + 紐づく User 一覧)
- [x] **`resources/views/admin/users/_modals/extend-course.blade.php`** — [[user-management]] 所有(本 Feature では設計のみ参照)
- [x] サイドバーに「Plan 管理」メニュー追加(admin のみ表示)

## Step 12: Resource(必要なら)

- [x] `app/Http/Resources/PlanResource.php`(招待モーダル / プラン延長モーダルで select に使用)

## Step 13: Test

### Migration(D-2 新規)

- [x] **`tests/Feature/Migrations/UserStatusEnumExtensionTest.php`(D-2)** — Migration 実行後に `UserStatus` enum が 4 値になっていること / `active` ステータスを持っていた既存 User が `in_progress` に移行されていること / Rollback で active に戻ることを確認

### Feature(HTTP)

- [x] `tests/Feature/Http/Admin/PlanControllerTest.php`
  - index 一覧 + status フィルタ
  - store 新規作成 + バリデーション
  - update 編集
  - destroy 削除(draft + 参照ゼロのみ削除可、published / archived / 参照中で 409)
  - publish / archive / unarchive 状態遷移 + 認可漏れ(admin 以外 403)

### Feature(UseCases)

- [x] `tests/Feature/UseCases/Plan/ExtendCourseActionTest.php`
  - 期限加算 + 回数加算 + UserPlanLog renewed 記録
  - **`GrantInitialQuotaAction` が統一シグネチャ(User, int, ?User, ?string)で呼ばれる**(D-1 検証、`Action::shouldReceive` or mock で確認)
  - graduated ユーザーで 409
  - published でない Plan で 422

### Feature(Schedule)

- [x] `tests/Feature/Commands/GraduateExpiredUsersCommandTest.php`
  - 期限切れ in_progress ユーザーが graduated 遷移
  - UserStatusLog 記録 + UserPlanLog expired 記録
  - in_progress 以外のユーザーは対象外

### Unit(Service)

- [x] `tests/Unit/Services/PlanExpirationServiceTest.php`(期限切れ判定 / 残日数算出 / plan_expires_at NULL で -1 を返す)
- [x] `tests/Unit/Services/UserPlanLogServiceTest.php`(record で UserPlanLog INSERT、event_type 4 値網羅)

### Unit(Enum、D-2 新規)

- [x] **`tests/Unit/Enums/UserStatusTest.php`(D-2 で更新)** — `Invited` / `InProgress` / `Graduated` / `Withdrawn` の 4 値網羅、`label()` 日本語ラベル確認

## Step 14: ファクトリ + シーダー

- [x] `database/factories/PlanFactory.php`(status 網羅 state: `published()` / `draft()` / `archived()` + `withDurationDays(int)`)
- [x] `database/factories/UserPlanLogFactory.php`(event_type 4 値網羅 state: `assigned()` / `renewed()` / `canceled()` / `expired()`)
- [x] **`database/factories/UserFactory.php` 更新(D-2)** — `withPlan(Plan)` / `inProgress()` / **`graduated()`(v3 新規)** state 追加
- [x] **`database/seeders/PlanSeeder.php`** — `structure.md` Seeder 規約「② マスタ系」分類、**status 網羅 + 受講生紐づけ進捗多様化** の 2 段構成:
  - **Plan マスタ status 網羅**(admin の一覧フィルタ確認用): published × 3 (1 ヶ月 / 3 ヶ月 / 6 ヶ月) + draft × 1 + archived × 1
  - **受講生 × Plan 紐づけ進捗網羅**(`UserSeeder` の in_progress × 8 + graduated × 3 に対し):
    - `student@certify-lms.test` (固定): 1 ヶ月プラン、開始 2 日前
    - in_progress × 8: 3 プランに分散 + 開始直後 / 中盤 / 期限直前(残り 5 日 / 10 日 / 30 日)を散らす
    - graduated × 3: published Plan に紐づけ、`plan_started_at` を過去日逆算
- [x] `DatabaseSeeder::run()` に `PlanSeeder::class` を `UserSeeder` の **後** に登録
