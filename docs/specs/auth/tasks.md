# auth タスクリスト

> 1 タスク = 1 コミット粒度。
> 関連要件 ID は `requirements.md` の `REQ-auth-NNN` / `NFR-auth-NNN` を参照。
> **v3 改修反映**: `UserStatus` 4 値化 / users に meeting_url + Plan 関連カラム / IssueInvitationAction に Plan $plan / OnboardAction で status=InProgress + coach meeting_url 必須 / **`EnsureActiveLearning` Middleware 新規**。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Enum & Model

### Migration

- [ ] `database/migrations/{date}_create_users_table.php`(ULID + email UNIQUE + password nullable + role enum + **`status` enum 4 値**(`invited` / `in_progress` / `graduated` / `withdrawn`、v3) + name nullable + bio + avatar_url + profile_setup_completed + email_verified_at + last_login_at + remember_token + timestamps + softDeletes)(REQ-auth-001)
- [ ] **`database/migrations/{date}_add_meeting_url_to_users_table.php`(v3 新規、D4 決定)** — `users.meeting_url string nullable max 500` 追加
- [ ] `database/migrations/{date}_create_invitations_table.php`(ULID + FK + email + role + invited_by + expires_at + accepted_at + revoked_at + status enum + timestamps + softDeletes)(REQ-auth-010)
- [ ] **`users.plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` は [[plan-management]] の Migration で追加**(本 Feature では Model リレーション宣言のみ)

### Enum

- [ ] `App\Enums\UserRole`(`Admin` / `Coach` / `Student` + `label()`)
- [ ] **`App\Enums\UserStatus`(v3 で 4 値化)** — `Invited` / **`InProgress`**(v3 で `Active` から rename) / **`Graduated`**(v3 新規) / `Withdrawn` + `label()` 日本語(`招待中` / `受講中` / `修了` / `退会済`)
- [ ] `App\Enums\InvitationStatus`(`Pending` / `Accepted` / `Expired` / `Revoked` + `label()`)

### Model

- [ ] `App\Models\User`(`HasUlids` + `HasFactory` + `SoftDeletes` + `Notifiable` + fillable + `$casts['role'=>UserRole, 'status'=>UserStatus, 'last_login_at'=>'datetime', 'plan_started_at'=>'datetime', 'plan_expires_at'=>'datetime']` + **`belongsTo(Plan)`(v3)** + `hasMany(Invitation)` + `scopeActive`(v3 で `whereIn('status', [InProgress, Graduated])`))(REQ-auth-001)
- [ ] `App\Models\Invitation`(fillable + casts + `belongsTo(User)` + `belongsTo(User, invited_by_user_id, invitedBy)`)
- [ ] Factory: `UserFactory`(`invited()` / **`inProgress()`(v3 で `active()` から rename)** / **`graduated()`(v3 新規)** / `withdrawn()` / `coach()` / `student()` / `admin()` / `withPlan(Plan)` state)
- [ ] Factory: `InvitationFactory`(`pending()` / `accepted()` / `expired()` / `revoked()` state)

## Step 2: Policy

- [ ] `App\Policies\InvitationPolicy`(`create` / `viewAny` / `revoke`、admin のみ true)
- [ ] `AuthServiceProvider::$policies` に登録

## Step 3: HTTP 層

### Controller

- [ ] `App\Http\Controllers\Auth\OnboardingController`(`show($invitation)` / `store($invitation, OnboardingRequest)`、Fortify は別途)
- [ ] Fortify Action `App\Actions\Fortify\CreateNewUser`(Fortify が必要とするが本 Feature では使わない、null を返す or `throw new \LogicException()`)
- [ ] Fortify Action `App\Actions\Fortify\UpdateUserPassword`(現在のパスワード照合 + min:8 + Hash::make 更新)
- [ ] **Fortify Action `App\Actions\Fortify\AuthenticateUserUsing`(v3 更新)** — `User.status IN (InProgress, Graduated)` でログイン許可、`invited` / `withdrawn` は拒否

### FormRequest

- [ ] **`App\Http\Requests\Auth\OnboardingRequest`(v3 更新)** — `name: required string max:50` / `bio: nullable string max:1000` / `password: required string min:8 confirmed` / **`meeting_url: required_if:role,coach string url max:500`**(v3、coach のみ必須)

### Middleware

- [ ] `App\Http\Middleware\EnsureUserRole`(既存)
- [ ] **`App\Http\Middleware\EnsureActiveLearning`(v3 新規)** — `auth()->user()->status !== UserStatus::InProgress` で 403 + 日本語メッセージ
- [ ] `Kernel.php::$middlewareAliases` に `'role' => EnsureUserRole::class` + **`'active-learning' => EnsureActiveLearning::class`(v3)** 追加
- [ ] **適用しないルート(v3)** — `/settings/profile` / `/settings/password` / `/settings/avatar` / `/certificates/{certificate}/download` / `/notifications` 系には EnsureActiveLearning を **適用しない**(各 Feature 側で対応)

### Route

- [ ] `routes/web.php` に追加:
  - 未認証 + 署名付き: `/onboarding/{invitation}` GET / POST (`signed` middleware)
  - Fortify が `/login` / `/logout` / `/forgot-password` / `/reset-password` / `/email/verification-notification` を自動登録

## Step 4: Action / Service / Exception

### Action(`app/UseCases/Auth/`)

- [ ] **`IssueInvitationAction`(v3 更新)** — シグネチャ `__invoke(string $email, UserRole $role, Plan $plan, User $invitedBy, bool $force = false)`、`in_progress` / `graduated` 不在検査 + 同 email pending Invitation 検査 + `User` INSERT(plan_id / max_meetings 反映) + `Invitation` INSERT + UserStatusChangeService + UserPlanLog(assigned) + InvitationMail dispatch、`DB::transaction`
- [ ] **`OnboardAction`(v3 更新)** — `status = InProgress`(v3) UPDATE + Plan 期間反映 + coach の meeting_url 必須 + UserStatusChangeService + **MeetingQuotaTransaction(granted_initial)** 起票 + Auth::login、`DB::transaction`
- [ ] `RevokeInvitationAction`(変更なし、`UserStatusChangeService::record($user, Withdrawn, ...)` 経由)
- [ ] `ExpireInvitationsAction`(変更なし、Schedule Command エントリポイント)

### Service

- [ ] 本 Feature では新設しない([[user-management]] の `UserStatusChangeService` / [[plan-management]] の `UserPlanLogService` / [[meeting-quota]] の `MeetingQuotaService` を消費)

### ドメイン例外(`app/Exceptions/Auth/`)

- [ ] `EmailAlreadyRegisteredException`(HTTP 409)
- [ ] `PendingInvitationAlreadyExistsException`(HTTP 409)
- [ ] `InvalidInvitationTokenException`(HTTP 410)

### Schedule Command

- [ ] `App\Console\Commands\Auth\ExpireInvitationsCommand`(`invitations:expire`、`ExpireInvitationsAction` を呼ぶ薄いラッパー)
- [ ] `app/Console/Kernel::schedule()` に `->command('invitations:expire')->dailyAt('00:30')->withoutOverlapping(5)` 追加(**M10**: plan-management の `users:graduate-expired` (00:45) と時刻ずらし、両 Command に `withoutOverlapping(5)` 付与で多重起動防止)

## Step 5: Blade ビュー

- [ ] `views/auth/login.blade.php`(Fortify 標準)
- [ ] `views/auth/forgot-password.blade.php`
- [ ] `views/auth/reset-password.blade.php`
- [ ] `views/auth/onboarding.blade.php`(name / bio / password + **role=coach の場合のみ meeting_url 表示**(v3))
- [ ] `views/auth/invitation-invalid.blade.php`(無効リンク表示)
- [ ] `views/emails/invitation.blade.php`(招待メール、署名付きオンボーディング URL 含む)

## Step 6: テスト

### Feature(HTTP)

- [ ] `tests/Feature/Auth/LoginTest.php`(InProgress + Graduated でログイン可、Invited + Withdrawn で 共通エラー)(v3)
- [ ] `tests/Feature/Auth/LogoutTest.php`
- [ ] `tests/Feature/Auth/ForgotPasswordTest.php` / `ResetPasswordTest.php`
- [ ] **`tests/Feature/Auth/OnboardingTest.php`(v3 更新)** — show 表示 / 不正トークンで `auth/invitation-invalid` / 有効入力で `status=in_progress` 遷移 / **role=coach で `meeting_url` 必須 422**(空文字) / 有効 URL でオンボーディング成功 / Plan 期間反映確認 / `MeetingQuotaTransaction granted_initial` INSERT 確認
- [ ] `tests/Feature/Middleware/EnsureUserRoleTest.php`
- [ ] **`tests/Feature/Middleware/EnsureActiveLearningTest.php`(v3 新規)** — InProgress で 200 / Graduated で 403 + 日本語メッセージ / Withdrawn で 403 / 適用除外ルート(`/settings/profile` 等)で graduated も 200

### Feature(UseCases)

- [ ] `tests/Feature/UseCases/Auth/IssueInvitationActionTest.php`(Plan 引数必須 / `in_progress`・`graduated` 不在検査 / UserPlanLog assigned 記録)
- [ ] **`tests/Feature/UseCases/Auth/OnboardActionTest.php`(v3 更新)** — status=InProgress 遷移 / coach の meeting_url 必須(空文字で例外) / plan_started_at + plan_expires_at セット / MeetingQuotaTransaction granted_initial 記録

### Unit

- [ ] **`tests/Unit/Enums/UserStatusTest.php`(v3 更新)** — `label()` 日本語表記 + 4 値網羅(Invited / InProgress / Graduated / Withdrawn)

## Step 7: Factory + Seeder

- [ ] `database/factories/UserFactory.php` — 状態網羅 state:
  - role state: `admin()` / `coach()` / `student()`
  - status state: `invited()` / `inProgress()` / `graduated()` / `withdrawn()`
  - 関連 state: `withPlan(Plan $plan, ?Carbon $startedAt = null)` / `unverified()`
- [ ] **`database/seeders/UserSeeder.php`** — `structure.md` Seeder 規約「① ユーザー基盤」分類、**固定アカウント + 状態網羅 demo** の 2 層構造:
  - **固定アカウント**(全 `password='password'`、PR スクショ / 動作確認で安定参照):
    - `admin@certify-lms.test` (admin、in_progress)
    - `coach@certify-lms.test` (coach、in_progress、bio 入り)
    - `coach2@certify-lms.test` (coach、in_progress、別ドメイン担当)
    - `student@certify-lms.test` (student、in_progress、Plan 紐づけは PlanSeeder で実施)
  - **状態網羅 demo データ**(Factory + state + count、admin / coach 視点での一覧フィルタ確認用):
    - `student × invited × 2` (Plan 未確定、name / password NULL)
    - `student × in_progress × 8` (Plan / 進捗位置の多様化は PlanSeeder で実施)
    - `student × graduated × 3` (`plan_expires_at` は過去 1〜90 日内のランダム)
    - `student × withdrawn × 2` (`deleted_at` 設定、email リネーム)
- [ ] `DatabaseSeeder::run()` の `$this->call([...])` 先頭に `UserSeeder::class` を登録(後続 Seeder の前提)

## Step 8: 動作確認 & 整形

- [ ] `sail artisan migrate:fresh --seed` 通過(`UserStatus` 4 値マイグレーション正常)
- [ ] `sail artisan test --filter=Auth` 全件 pass
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認シナリオ:
  - [ ] admin で招待発行(plan 指定)→ メール送信 → 招待リンク表示 → オンボーディング → status=in_progress → /dashboard
  - [ ] coach のオンボーディングで meeting_url 未入力 → 422
  - [ ] coach のオンボーディングで meeting_url 入力 → 成功 + users.meeting_url 保存確認
  - [ ] オンボーディング後の Plan 期間 + max_meetings 確認(plan-management 連携)
  - [ ] `EnsureActiveLearning` Middleware 動作確認(`graduated` ユーザーで /learning → 403 / /settings/profile → 200 / /certificates/{id}/download → 200)
  - [ ] 招待期限切れの Schedule Command 動作(`invitations:expire`)
