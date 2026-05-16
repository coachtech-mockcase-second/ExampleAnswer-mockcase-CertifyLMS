# settings-profile タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-settings-profile-NNN` / `NFR-settings-profile-NNN` を参照。
> 本 Feature は **新規モデルを追加しない**(`User` / `CoachAvailability` は他 Feature 所有)。
> **v3 改修反映**: 自己退会動線完全撤回 / EnsureActiveLearning 適用しない / プラン情報・通知設定タブ不採用。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Model & Enum

> 通知設定マトリクス基盤(`UserNotificationSetting` / `NotificationType` / `NotificationChannel`)は採用しないため本 Step タスクなし。新規モデル / Migration は不要。

## Step 2: Policy

- [ ] **`App\Policies\UserPolicy::updateSelf(User $auth, User $target): bool`** 新設(本人のみ true)
- [ ] **`UserPolicy::withdrawSelf` は新設しない**(v3 撤回、自己退会動線そのものがない)
- [ ] `AuthServiceProvider` 既存 UserPolicy 登録確認(本 Feature では追加 Policy なし、`CoachAvailabilityPolicy` は [[mentoring]] 所有を借用)

## Step 3: HTTP 層

### Controller(`app/Http/Controllers/SettingsProfile/`)

- [ ] `ProfileController`(`edit` / `update`)
- [ ] `AvatarController`(`store` / `destroy`、JSON or リダイレクト応答)
- [ ] `AvailabilityController`(`index` / `store` / `update` / `destroy`、coach のみ)

### 明示的に持たない Controller(v3 撤回)

- **`SelfWithdrawController`** — そもそも自己退会動線がない(`/settings/withdraw` ルート自体を持たない)

### FormRequest(`.claude/rules/backend-http.md` 準拠 — `app/Http/Requests/{Entity}/{Action}Request.php`)

- [ ] `app/Http/Requests/Profile/UpdateRequest.php`(`name: required string max:50` / `bio: nullable string max:1000` / `meeting_url: nullable string url max:500`、authorize: `Policy::updateSelf`)
- [ ] `app/Http/Requests/Avatar/StoreRequest.php`(`avatar: required file mimes:png,jpg,jpeg,webp max:2048`)
- [ ] `app/Http/Requests/Availability/StoreRequest.php`(`day_of_week: required integer between:1,7` / `start_time: required date_format:H:i` / `end_time: required date_format:H:i + after:start_time` / `is_active: boolean`、authorize: `CoachAvailabilityPolicy::create`)
- [ ] `app/Http/Requests/Availability/UpdateRequest.php`(同 rules、authorize: `Policy::update`)

### 明示的に持たない FormRequest(v3 撤回)

- **`SelfWithdrawRequest`**

### Route

- [ ] `routes/web.php`:
  - `auth` middleware group + `prefix('settings')` + `name('settings.')`
  - `/profile` GET (`profile.edit`) / PATCH (`profile.update`)
  - `/avatar` POST (`avatar.store`) / DELETE (`avatar.destroy`)
  - `role:coach` 内側 group: `/availability` GET / POST / PATCH / DELETE
  - **Fortify が `PUT /settings/password` を提供**(本 Feature でルート定義しない)
  - **`/settings/withdraw` ルートは追加しない**(v3 撤回)
  - **`EnsureActiveLearning` Middleware は適用しない**(product.md L482 と整合)

## Step 4: Action / Service / Exception

### Action(`.claude/rules/backend-usecases.md` 準拠 — `app/UseCases/{Entity}/{ControllerMethod}Action.php`)

- [ ] `app/UseCases/Profile/UpdateAction.php`(`ProfileController::update`、`role !== coach` で `meeting_url` drop、`DB::transaction`)
- [ ] `app/UseCases/Avatar/StoreAction.php`(`AvatarController::store`、新ファイル保存 → DB UPDATE → 旧ファイル best-effort 削除、保存失敗で `AvatarStorageFailedException` 500、UPDATE 失敗で新ファイル rollback)
- [ ] `app/UseCases/Avatar/DestroyAction.php`(`AvatarController::destroy`、Storage 削除 + `avatar_url=NULL` UPDATE、`DB::transaction`)
- [ ] `app/UseCases/Availability/IndexAction.php`(`AvailabilityController::index`、自身の `CoachAvailability` 一覧取得)
- [ ] `app/UseCases/Availability/StoreAction.php`(`AvailabilityController::store`、coach 本人で INSERT)
- [ ] `app/UseCases/Availability/UpdateAction.php`(`AvailabilityController::update`)
- [ ] `app/UseCases/Availability/DestroyAction.php`(`AvailabilityController::destroy`、SoftDelete)

### 明示的に持たない Action(v3 撤回)

- **`SelfWithdraw\SelfWithdrawAction`**

### Service

- 本 Feature では新設しない([[user-management]] の `UserStatusChangeService::record` 呼出も撤回、自己退会動線がないため)

### Fortify Action

- [ ] `App\Actions\Fortify\UpdateUserPassword`(`Fortify::updateUserPasswordsUsing` で登録、`current_password` 照合 + `password: min:8 confirmed` バリデーション + `Hash::make`)

### ドメイン例外(`app/Exceptions/SettingsProfile/`)

- [ ] `AvatarStorageFailedException`(HTTP 500、日本語メッセージ)

### 明示的に持たない例外(v3 撤回)

- **`AdminSelfWithdrawForbiddenException`** — 自己退会動線そのものがないため不要

## Step 5: Blade ビュー + JavaScript

### Blade(`resources/views/settings/`)

- [ ] `profile.blade.php`(`<x-tabs>` で **「プロフィール / パスワード」の 2 タブ**(v3、3 タブ → 2 タブに縮減))
- [ ] `_partials/tab-profile.blade.php`(name / bio + coach のみ meeting_url + アバターアップロード form)
- [ ] `_partials/tab-password.blade.php`(Fortify 標準の current_password / password / password_confirmation)
- [ ] `availability/index.blade.php`(coach 限定、CoachAvailability 一覧 + 作成 / 編集 / 削除モーダル)
- [ ] `availability/_partials/form.blade.php`

### 明示的に持たない Blade(v3 撤回)

- **`_partials/tab-withdraw.blade.php`** — 自己退会タブ全削除
- `_partials/tab-notifications.blade.php` — そもそも作らない([[notification]] が全通知 DB+Mail 固定送信)

### JavaScript(`resources/js/settings-profile/`)

- [ ] `avatar.js`(MIME / サイズのクライアント側検証)

## Step 6: テスト

### Feature(HTTP)

- [ ] `tests/Feature/Http/SettingsProfile/Profile/EditTest.php`(認証必須、自分の値表示)
- [ ] `tests/Feature/Http/SettingsProfile/Profile/UpdateTest.php`(name / bio 更新、coach の meeting_url 更新、student / admin の meeting_url drop)
- [ ] `tests/Feature/Http/SettingsProfile/Avatar/StoreTest.php`(MIME / サイズ検証、Storage 保存)
- [ ] `tests/Feature/Http/SettingsProfile/Avatar/DestroyTest.php`(Storage 削除 + DB UPDATE)
- [ ] `tests/Feature/Http/SettingsProfile/Password/UpdateTest.php`(Fortify 動作、current_password 不一致 422)
- [ ] `tests/Feature/Http/SettingsProfile/Availability/{Index,Store,Update,Destroy}Test.php`(coach 200 / admin・student 403)
- [ ] **`tests/Feature/Http/SettingsProfile/GraduatedAccessTest.php`(v3)** — `graduated` ユーザーが `/settings/profile` / `/settings/password` / `/settings/avatar` にアクセス可能(EnsureActiveLearning なし、product.md L482 と整合)

### 明示的に持たないテスト(v3 撤回)

- **`Withdraw/StoreTest.php`** — 自己退会動線そのものがないため
- `Withdraw/AdminSelfWithdrawForbiddenTest.php`
- `Withdraw/SelfWithdrawActionTest.php`

### Feature(UseCases)

- [ ] `Profile/UpdateActionTest.php`(role !== coach で meeting_url drop、coach で meeting_url UPDATE)
- [ ] `Avatar/StoreActionTest.php`(rollback シナリオ: Storage 失敗 / DB UPDATE 失敗)

### Unit(Policy)

- [ ] `Unit/Policies/UserPolicyTest.php`(`updateSelf` 本人 true / 他人 false)

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=SettingsProfile` 全件 pass
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザ動作確認シナリオ:
  - [ ] 全ロールで `/settings/profile` にアクセス → プロフィール / パスワードの **2 タブ表示**(退会タブなし、v3)
  - [ ] 受講生で name / bio 更新 → 成功 flash
  - [ ] 受講生で meeting_url 入力 → silently drop(更新されない)
  - [ ] コーチで meeting_url 編集 → 更新成功
  - [ ] アバターアップロード → Storage 保存 + 表示更新
  - [ ] アバター削除 → イニシャルアバター表示
  - [ ] パスワード変更(Fortify) → 成功 flash + 次ログインで新パスワード認証
  - [ ] コーチで `/settings/availability` → CoachAvailability CRUD 動作
  - [ ] admin / student で `/settings/availability` → 403
  - [ ] **`graduated` ユーザーで `/settings/profile` → 200 表示**(v3、EnsureActiveLearning なし)
- [ ] **v3 撤回確認**:
  - [ ] `/settings/withdraw` URL 直叩き → 404(ルート定義なし)
  - [ ] サイドバー「設定」リンクから退会タブが表示されない
- [ ] アクセシビリティ確認(Lighthouse Accessibility 90+)
- [ ] N+1 確認
