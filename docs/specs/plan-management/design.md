# plan-management 設計

> **v3 Blocker 解消**（Phase D、2026-05-16）:
> - D-1: `GrantInitialQuotaAction::__invoke(User $user, int $amount, ?User $admin = null, ?string $reason = null): MeetingQuotaTransaction` でシグネチャ統一(meeting-quota と共通)
> - D-2: **Step 1(本 Feature)で UserStatus enum 拡張 + active → in_progress 移行 Migration を同梱**(auth Step 2 が前提とする状態を本 Feature が確立する)
> - D-4: Controller / FormRequest / Policy / Route の API 契約を明示

## アーキテクチャ概要

```
Admin Browser
    ↓
[Web Layer]
PlanController (admin/plans/*)
ExtendCourseController (admin/users/{user}/extend-course)
    ↓
[FormRequest 層]
Plan\StoreRequest / Plan\UpdateRequest / ExtendCourseRequest
    ↓
[Policy 層]
PlanPolicy / UserPolicy::extendCourse
    ↓
[UseCase 層]
Plan\StoreAction / Plan\UpdateAction / Plan\DestroyAction
Plan\PublishAction / Plan\ArchiveAction / Plan\UnarchiveAction
ExtendCourseAction / GraduateUserAction
    ↓
[Service 層]
PlanExpirationService / UserPlanLogService
    ↓
[Model 層]
Plan / UserPlanLog / User(拡張)
    ↓
[Schedule Command]
users:graduate-expired (日次 00:30)
```

**本 Feature の責務範囲(D-2 で明示)**:
1. `Plan` マスタ CRUD
2. `users` テーブルに `plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` 追加 Migration
3. **`UserStatus` enum 拡張**(v3、本 Feature が同梱): `Invited` / **`Active` → `InProgress` rename + `Graduated` 追加** + データ移行 Migration
4. `UserPlanLog` 履歴管理
5. `ExtendCourseAction`(プラン延長)
6. `GraduateUserAction` + `users:graduate-expired` Schedule Command(自動卒業)

## ERD

```
plans
├ id ULID PK
├ name varchar(100) NOT NULL
├ description text NULL
├ duration_days unsigned smallint NOT NULL (1..3650)
├ default_meeting_quota unsigned smallint NOT NULL (0..1000)
├ status enum('draft','published','archived') NOT NULL DEFAULT 'draft'
├ sort_order unsigned int NOT NULL DEFAULT 0
├ created_by_user_id ULID FK NOT NULL → users.id (restrict)
├ updated_by_user_id ULID FK NOT NULL → users.id (restrict)
├ created_at / updated_at / deleted_at
INDEX (status, sort_order), (deleted_at)
※ price カラムなし(LMS 内で価格を持たない、決済 LMS 外)

users (本 Feature の Migration で追加)
├ plan_id ULID FK NULL → plans.id (restrict)
├ plan_started_at datetime NULL
├ plan_expires_at datetime NULL
├ max_meetings unsigned smallint NOT NULL DEFAULT 0
INDEX (plan_id), (status, plan_expires_at)

user_plan_logs (履歴、INSERT only、SoftDelete 不採用)
├ id ULID PK
├ user_id ULID FK NOT NULL → users.id (restrict)
├ plan_id ULID FK NOT NULL → plans.id (restrict)
├ event_type enum('assigned','renewed','canceled','expired') NOT NULL
├ plan_started_at datetime NOT NULL
├ plan_expires_at datetime NOT NULL
├ meeting_quota_initial unsigned smallint NOT NULL
├ changed_by_user_id ULID FK NULL → users.id (NULL = システム自動)
├ changed_reason varchar(200) NULL
├ occurred_at datetime NOT NULL
├ created_at / updated_at
INDEX (user_id, occurred_at), (plan_id)
```

### 監査ログ設計判断: UserStatusLog と UserPlanLog の関係(2026-05-16)

本 Feature の `UserPlanLog` と [[user-management]] 所有の `UserStatusLog` は、**いずれも User に関する append-only 監査ログ**で、**`event_type` ベースの同一フォーマット**で設計されている。

| | `UserStatusLog`([[user-management]] 所有) | `UserPlanLog`(本 Feature 所有) |
|---|---|---|
| **対象** | User の `status` 遷移 | User の Plan 期間 / 面談付与回数の遷移 |
| **event_type** | `status_change`(現時点 1 値、将来拡張可) | `assigned` / `renewed` / `canceled` / `expired` |
| **スナップショット列** | `from_status` / `to_status`(遷移内容) | `plan_started_at` / `plan_expires_at` / `meeting_quota_initial`(イベント発生時のプラン情報) |
| **実行者列** | `changed_by_user_id` nullable | `changed_by_user_id` nullable |
| **理由列** | `changed_reason` text nullable | `changed_reason` varchar(200) nullable |
| **時刻列** | `changed_at` | `occurred_at` |
| **書込責務** | `UserStatusChangeService::record` (user-management 所有) | `UserPlanLogService::record` (本 Feature 所有) |

**Why 別テーブルで持つか**(統合テーブル化を不採用とした理由):

- **読み取り頻度の違い**: `UserStatusLog` は admin の「ユーザー詳細 → ステータス履歴」UI と Schedule Command(`users:graduate-expired`)の対象抽出で頻繁に参照される。`UserPlanLog` は「プラン延長履歴 / 卒業履歴」を見るときのみで、頻度が低い。クエリパスを分離することで `UserStatusLog` の INDEX を `(user_id, changed_at)` に最適化できる
- **INDEX 設計の違い**: `UserStatusLog` は `(user_id, changed_at)` + `(event_type, changed_at)` が主、`UserPlanLog` は `(user_id, occurred_at)` + `(plan_id)` が主。`plan_id` の参照クエリ(「Plan A で延長したユーザー一覧」等)は Plan 監査固有
- **概念の独立性**: 「ステータス(状態)の遷移」と「プラン期間(期間 + 面談付与回数の組み合わせ)の遷移」は別ドメイン概念。プラン延長してもステータスは `in_progress` のまま不変(`UserPlanLog` のみ INSERT、`UserStatusLog` は INSERT されない)。逆に手動退会ではステータスのみ変化(`UserStatusLog` のみ INSERT)
- **統合テーブルの困難**: 1 テーブルに統合すると `from_status` / `to_status` が NULL の Plan 遷移行と、`plan_started_at` 等が NULL の Status 遷移行が混在する。`UNION` クエリでも分かりにくく、Pro 生レベルとして「監査ログは概念単位で分離する」を学べる教材として、別テーブル方式が筋

**両者の同期点**(`GraduateUserAction` の例):
- 期限満了による自動卒業時は **両方の Service に同一トランザクション内で record**: `UserStatusChangeService::record($user, Graduated, null, '期限満了による自動卒業')` + `UserPlanLogService::record($user, $plan, Expired, null, '期限満了')`
- これにより監査の整合性を保ちつつ、概念分離も維持

**UserStatusLog 側の event_type 追加**(2026-05-16): 当初 `UserStatusLog` には `event_type` カラムがなく `from_status` / `to_status` のみで遷移を表していたが、`UserPlanLog` の `event_type` 設計とフォーマットを揃えるため `event_type`(現時点 `status_change` の 1 値)カラムを `user-management/design.md` で追加決定。両者は「append-only 監査ログ + event_type で分類 + 状態スナップショット + 実行者 + 理由 + 発生時刻」の同一形式で揃った。詳細は [[user-management]] の design.md「Enum: UserStatusEventType」セクション参照。

**UserStatus enum 拡張(D-2)**:
- Migration step 1: `users.status` の enum 値を `('invited', 'active', 'withdrawn')` → `('invited', 'active', 'in_progress', 'graduated', 'withdrawn')` に拡張(両立期、一時的に 5 値)
- Migration step 2: `UPDATE users SET status='in_progress' WHERE status='active'`(データ移行)
- Migration step 3: enum から `'active'` を削除(`('invited', 'in_progress', 'graduated', 'withdrawn')` の 4 値最終形)

## 主要 Action 設計

### ExtendCourseAction(D-1 でシグネチャ統一)

```php
class ExtendCourseAction
{
    public function __construct(
        private GrantInitialQuotaAction $grantQuota,  // D-1: meeting-quota の Action を DI
        private UserPlanLogService $planLog,
    ) {}

    public function __invoke(User $user, Plan $plan, ?User $admin = null, ?string $reason = null): User
    {
        return DB::transaction(function () use ($user, $plan, $admin, $reason) {
            // ガード
            if ($user->status !== UserStatus::InProgress) {
                throw new UserNotInProgressException();
            }
            if ($plan->status !== PlanStatus::Published) {
                throw new PlanNotPublishedException();
            }

            // 期限延長 + 回数加算
            $user->plan_expires_at = ($user->plan_expires_at ?? now())->copy()->addDays($plan->duration_days);
            $user->max_meetings += $plan->default_meeting_quota;
            $user->save();

            // 履歴記録
            $this->planLog->record($user, $plan, UserPlanLogEventType::Renewed, $admin, $reason);

            // D-1: 統一シグネチャで meeting-quota Action を呼ぶ
            // GrantInitialQuotaAction(User $user, int $amount, ?User $admin = null, ?string $reason = null): MeetingQuotaTransaction
            ($this->grantQuota)($user, $plan->default_meeting_quota, $admin, $reason ?? 'プラン延長');

            return $user->fresh();
        });
    }
}
```

### GraduateUserAction(Schedule Command 起動)

```php
class GraduateUserAction
{
    public function __construct(
        private UserStatusChangeService $statusService,  // user-management 所有
        private UserPlanLogService $planLog,
    ) {}

    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->update(['status' => UserStatus::Graduated]);

            // user-management の UserStatusLog に記録
            $this->statusService->record($user, UserStatus::Graduated, null, '期限満了による自動卒業');

            // user_plan_logs に記録
            $this->planLog->record($user, $user->plan, UserPlanLogEventType::Expired, null, '期限満了');
        });
    }
}
```

### Service: UserPlanLogService(履歴記録の一元化)

```php
class UserPlanLogService
{
    public function record(
        User $user,
        Plan $plan,
        UserPlanLogEventType $eventType,
        ?User $changedBy = null,
        ?string $reason = null,
    ): UserPlanLog {
        return UserPlanLog::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'event_type' => $eventType,
            'plan_started_at' => $user->plan_started_at ?? now(),
            'plan_expires_at' => $user->plan_expires_at ?? now(),
            'meeting_quota_initial' => $plan->default_meeting_quota,
            'changed_by_user_id' => $changedBy?->id,
            'changed_reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
```

## Controller(D-4 で API 契約明示)

`app/Http/Controllers/Admin/`(`auth + role:admin` middleware):

| Controller | Method | Route | Action |
|---|---|---|---|
| `PlanController` | `index` | `GET /admin/plans` | `Plan\IndexAction` |
| `PlanController` | `create` | `GET /admin/plans/create` | (Blade のみ) |
| `PlanController` | `store` | `POST /admin/plans` | `Plan\StoreAction` |
| `PlanController` | `show` | `GET /admin/plans/{plan}` | `Plan\ShowAction` |
| `PlanController` | `edit` | `GET /admin/plans/{plan}/edit` | (Blade のみ) |
| `PlanController` | `update` | `PUT /admin/plans/{plan}` | `Plan\UpdateAction` |
| `PlanController` | `destroy` | `DELETE /admin/plans/{plan}` | `Plan\DestroyAction` |
| `PlanStatusController` | `publish` | `POST /admin/plans/{plan}/publish` | `Plan\PublishAction` |
| `PlanStatusController` | `archive` | `POST /admin/plans/{plan}/archive` | `Plan\ArchiveAction` |
| `PlanStatusController` | `unarchive` | `POST /admin/plans/{plan}/unarchive` | `Plan\UnarchiveAction` |
| `Admin\UserController` | `extendCourse` | `POST /admin/users/{user}/extend-course` | [[user-management]] の `User\ExtendCourseAction`(ラッパー) → 内部で `Plan\ExtendCourseAction` を DI 呼出 |

> 本 Feature の `Plan\ExtendCourseAction` は [[user-management]] が所有する `Admin\UserController::extendCourse` から呼ばれる。本 Feature では独立した `ExtendCourseController` を持たず、user-management の Controller に責務を集約(レビュー D-4 推奨パターン)。
> **`.claude/rules/backend-usecases.md`「Feature 間連携のラッパー Action」規約に従い、Controller は他 Feature の Action を直接 DI せず、必ず呼出元 Feature 配下のラッパー Action を経由する**(=user-management の `User\ExtendCourseAction` が本 Feature の `Plan\ExtendCourseAction` を呼ぶ)。

## FormRequest(D-4 で明示)

`app/Http/Requests/Plan/`:

- **`Plan\StoreRequest`**:
  ```php
  public function rules(): array
  {
      return [
          'name' => ['required', 'string', 'max:100'],
          'description' => ['nullable', 'string', 'max:2000'],
          'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
          'default_meeting_quota' => ['required', 'integer', 'min:0', 'max:1000'],
          'sort_order' => ['nullable', 'integer', 'min:0'],
      ];
  }
  public function authorize(): bool { return $this->user()->can('create', Plan::class); }
  ```
- **`Plan\UpdateRequest`**: 同 rules、`status` は変更不可(別エンドポイント)
- **`ExtendCourseRequest`**(`app/Http/Requests/Admin/User/`、user-management 所有だが本 Feature 内で参照):
  ```php
  public function rules(): array { return ['plan_id' => ['required', 'ulid', 'exists:plans,id']]; }
  public function authorize(): bool { return $this->user()->can('extendCourse', $this->route('user')); }
  ```

## Policy(D-4 で真偽値マトリクス明示)

`PlanPolicy`:

| メソッド | admin | coach | student |
|---|---|---|---|
| `viewAny` | true | false | false |
| `view` | true | false | false |
| `create` | true | false | false |
| `update` | true | false | false |
| `delete` | true | false | false(かつ status=draft、参照中でない) |
| `publish` | true | false | false |
| `archive` / `unarchive` | true | false | false |

`UserPolicy::extendCourse(User $auth, User $target)`: admin のみ true。

## Schedule Command

```php
class GraduateExpiredUsersCommand extends Command
{
    protected $signature = 'users:graduate-expired';

    public function handle(GraduateUserAction $action): int
    {
        $users = User::query()
            ->where('status', UserStatus::InProgress)
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now())
            ->get();

        foreach ($users as $user) {
            $action($user);
        }

        $this->info("{$users->count()} users graduated.");
        return Command::SUCCESS;
    }
}

// app/Console/Kernel.php::schedule()
$schedule->command('users:graduate-expired')->dailyAt('00:45')->withoutOverlapping(5);
// M10 衝突回避: auth の invitations:expire(00:30) と時刻ずらし、両 Command に withoutOverlapping(5) 付与
```

## PlanExpirationService

```php
class PlanExpirationService
{
    public function isExpired(User $user): bool
    {
        return $user->plan_expires_at !== null && $user->plan_expires_at->isPast();
    }

    public function daysRemaining(User $user): int
    {
        if ($user->plan_expires_at === null) return -1;
        return max(0, ceil(now()->diffInDays($user->plan_expires_at, false)));
    }
}
```

## エラーハンドリング

`app/Exceptions/Plan/` 配下に以下を配置:
- `PlanNotDeletableException`(HTTP 409): published/archived の DELETE、または User.plan_id 参照中
- `PlanInvalidTransitionException`(HTTP 409): status 遷移違反
- `PlanNotPublishedException`(HTTP 422): published でない Plan で招待・延長
- `UserNotInProgressException`(HTTP 409): graduated / withdrawn ユーザーの延長

## Blade テンプレ

- `views/admin/plans/index.blade.php`: Plan 一覧 + status フィルタ + 新規作成ボタン
- `views/admin/plans/create.blade.php` / `edit.blade.php`: フォーム(`name` / `description` / `duration_days` / `default_meeting_quota` / `sort_order`)
- `views/admin/plans/show.blade.php`: 詳細 + 紐づく User 一覧

## Test 戦略

- `tests/Feature/Http/Admin/PlanControllerTest.php`: CRUD + 状態遷移 + 認可漏れ
- `tests/Feature/UseCases/Plan/ExtendCourseActionTest.php`: 期限加算 + 回数加算 + UserPlanLog renewed 記録 + **`GrantInitialQuotaAction` 統一シグネチャ呼出検証**(D-1)
- `tests/Feature/Commands/GraduateExpiredUsersCommandTest.php`: 期限切れユーザーの自動 graduated 遷移
- `tests/Unit/Services/PlanExpirationServiceTest.php`: 期限切れ判定 / 残日数算出
- **`tests/Feature/Migrations/UserStatusEnumExtensionTest.php`**(D-2 新規): Migration 実行後に `UserStatus` enum が 4 値(invited / in_progress / graduated / withdrawn)になっていること、`active` ステータスを持っていた既存 User は `in_progress` に移行されていること
