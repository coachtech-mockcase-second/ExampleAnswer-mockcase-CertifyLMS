# settings-profile タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-settings-profile-NNN` / `NFR-settings-profile-NNN` を参照。
> 本 Feature は **新規モデルを追加しない**。`User` / `CoachAvailability` は他 Feature 所有のものを Read / Write。通知設定 UI は採用しない（[[notification]] が全通知 DB+Mail 固定送信、Phase 0 合意）。

## Step 1: Migration & Model & Enum

> 通知設定マトリクス基盤（`UserNotificationSetting` / `NotificationType` Enum / `NotificationChannel` Enum / Factory）は採用しないため、本 Step のタスクなし。新規モデル・Migration は不要。

## Step 2: Policy

- [ ] `UserPolicy` 既存（[[user-management]] 所有）に **メソッド追加**:
  - `updateSelf(User $auth, User $target): bool` — `$auth->id === $target->id`（REQ-settings-profile-081）
  - `withdrawSelf(User $auth, User $target): bool` — `$auth->id === $target->id && $auth->role !== UserRole::Admin`（REQ-settings-profile-081, REQ-settings-profile-005）
- [ ] `CoachAvailabilityPolicy`（[[mentoring]] 所有）の流用確認（本 Feature では新設しない）（REQ-settings-profile-082）

## Step 3: HTTP 層（Controller / FormRequest / Route）

### ルート登録

- [ ] `routes/web.php` に `/settings` プレフィックス group（`auth` middleware）を追加（REQ-settings-profile-001, REQ-settings-profile-002）
  - `GET /settings/profile` → `ProfileController::edit` (`settings.profile.edit`)
  - `PATCH /settings/profile` → `ProfileController::update` (`settings.profile.update`)
  - `POST /settings/avatar` → `AvatarController::store` (`settings.avatar.store`)
  - `DELETE /settings/avatar` → `AvatarController::destroy` (`settings.avatar.destroy`)
  - `PUT /settings/password` → `PasswordController::update` (`settings.password.update`)
  - `POST /settings/withdraw` → `SelfWithdrawController` (`settings.withdraw`)
  - `role:coach` group 内に `Route::resource('availability', AvailabilityController::class)->only(['index', 'store', 'update', 'destroy'])`（REQ-settings-profile-006, REQ-settings-profile-080）
- [ ] `app/Providers/FortifyServiceProvider` で `Fortify::routes(false)` 化または本 Feature ルートが Fortify 標準 `/user/password` を上書きする設定（REQ-settings-profile-030）

### Controller

- [ ] `ProfileController` — `edit` / `update`（薄く保つ、Action を DI）（REQ-settings-profile-010, REQ-settings-profile-011, REQ-settings-profile-014）
- [ ] `AvatarController` — `store` / `destroy`（REQ-settings-profile-021, REQ-settings-profile-023）
- [ ] `PasswordController` — `update`（REQ-settings-profile-031, REQ-settings-profile-034）
- [ ] `SelfWithdrawController` — `__invoke`（single-action、`Auth::logout` + session 破棄を Controller 層で実施）（REQ-settings-profile-050）
- [ ] `AvailabilityController` — `index` / `store` / `update` / `destroy`（REQ-settings-profile-060, REQ-settings-profile-061, REQ-settings-profile-062, REQ-settings-profile-063）

### FormRequest

- [ ] `app/Http/Requests/Profile/UpdateRequest`（`name` required min:1 max:50 / `bio` nullable max:1000 / `meeting_url` nullable max:500 url、`authorize` で `updateSelf`）（REQ-settings-profile-011, REQ-settings-profile-012）
- [ ] `app/Http/Requests/Avatar/StoreRequest`（`avatar` required file mimes:png,jpg,jpeg,webp max:2048）（REQ-settings-profile-020）
- [ ] `app/Http/Requests/Password/UpdateRequest`（`current_password` required current_password / `password` required min:8 confirmed）（REQ-settings-profile-031, REQ-settings-profile-032, REQ-settings-profile-033）
- [ ] `app/Http/Requests/User/SelfWithdrawRequest`（`reason` nullable max:200、`authorize` で `withdrawSelf`）（REQ-settings-profile-050, REQ-settings-profile-051）
- [ ] `app/Http/Requests/Availability/StoreRequest`（`day_of_week` required int between:1,7 / `start_time` H:i / `end_time` H:i after:start_time / `is_active` boolean）（REQ-settings-profile-061, REQ-settings-profile-064）
- [ ] `app/Http/Requests/Availability/UpdateRequest`（StoreRequest 同等ルール、`authorize` のみ `update` Policy 呼出）（REQ-settings-profile-062）

### 日本語バリデーションメッセージ

- [ ] `lang/ja/validation.php` の `attributes` / `messages` に本 Feature 用フィールド名を追加（current_password / password / name / bio / avatar / day_of_week / start_time / end_time 等）（NFR-settings-profile-006）

## Step 4: Action / Service / Exception

### Action（UseCase）

- [ ] `app/UseCases/Profile/EditAction`（ViewModel 構築: user + coach の availabilities）（REQ-settings-profile-010）
- [ ] `app/UseCases/Profile/UpdateAction`（DB::transaction + meeting_url の coach 限定処理 + `unset($validated['meeting_url'])` for non-coach）（REQ-settings-profile-011, REQ-settings-profile-012, REQ-settings-profile-013, NFR-settings-profile-001）
- [ ] `app/UseCases/Avatar/StoreAction`（(1) Storage::disk('public')->putFileAs 新ファイル保存 → (2) DB::transaction で `users.avatar_url` UPDATE（失敗時は新ファイル削除でロールバック）→ (3) 旧ファイルを best-effort 削除、の3段階。新ファイル保存失敗時は `AvatarStorageFailedException`）（REQ-settings-profile-021, REQ-settings-profile-022, NFR-settings-profile-004）
- [ ] `app/UseCases/Avatar/DestroyAction`（DB::transaction + Storage 削除 + `users.avatar_url = NULL`、ファイル不在は冪等）（REQ-settings-profile-023）
- [ ] `app/UseCases/Password/UpdateAction`（DB::transaction + `Hash::make` で UPDATE）（REQ-settings-profile-031）
- [ ] `app/UseCases/User/SelfWithdrawAction`（status 競合 + admin 拒否チェック → DB::transaction + `User::withdraw()` + `UserStatusChangeService::record($user, UserStatus::Withdrawn, $user, $reason ?? '自己退会')`、`UserStatusChangeService` は [[user-management]] 所有）（REQ-settings-profile-050, REQ-settings-profile-051, REQ-settings-profile-052, REQ-settings-profile-053）
- [ ] `app/UseCases/Availability/IndexAction`（自分の `CoachAvailability` を day_of_week / start_time 昇順で取得）（REQ-settings-profile-060）
- [ ] `app/UseCases/Availability/StoreAction`（DB::transaction + `CoachAvailability::create(['coach_id' => auth ID, ...])`）（REQ-settings-profile-061）
- [ ] `app/UseCases/Availability/UpdateAction`（DB::transaction + UPDATE）（REQ-settings-profile-062）
- [ ] `app/UseCases/Availability/DestroyAction`（DB::transaction + SoftDelete）（REQ-settings-profile-063）

### ドメイン例外

- [ ] `app/Exceptions/SettingsProfile/AdminSelfWithdrawForbiddenException`（`AccessDeniedHttpException` 継承、HTTP 403、日本語メッセージ）（REQ-settings-profile-051, NFR-settings-profile-002）
- [ ] `app/Exceptions/SettingsProfile/AvatarStorageFailedException`（`HttpException(500)` 継承、日本語メッセージ）（REQ-settings-profile-022, NFR-settings-profile-002）

## Step 5: Blade ビュー

### タブハブとパーシャル

- [ ] `resources/views/settings/profile.blade.php`（`@extends('layouts.app')` + `<x-tabs>` でロール別タブ配列を構築 + `?tab=` パラメータで該当パーシャル include）（REQ-settings-profile-001, REQ-settings-profile-003, NFR-settings-profile-003）
- [ ] `resources/views/settings/_partials/tab-profile.blade.php`（プロフィールフォーム + アバター アップロード / 削除ボタン + coach 限定 `meeting_url` フィールド + 学習時間目標への link 誘導 [[learning]]、すべてのフォームに `@csrf` 必須）（REQ-settings-profile-004, REQ-settings-profile-010, REQ-settings-profile-011, REQ-settings-profile-012, REQ-settings-profile-021, REQ-settings-profile-023, NFR-settings-profile-008）
- [ ] `resources/views/settings/_partials/tab-password.blade.php`（パスワード変更フォーム、`current_password` / `password` / `password_confirmation` の3フィールド）（REQ-settings-profile-031）
- [ ] `resources/views/settings/_partials/tab-withdraw.blade.php`（`<x-modal>` で確認 + `<x-form.textarea name="reason">` 任意、`@if(role !== Admin)` 全体ラップ）（REQ-settings-profile-050）

### 面談可能時間枠ページ

- [ ] `resources/views/settings/availability/index.blade.php`（曜日 × 時間範囲のテーブル + 新規追加フォーム + 既存行のインライン編集 / 削除ボタン）（REQ-settings-profile-060, REQ-settings-profile-061, REQ-settings-profile-062, REQ-settings-profile-063）

### サイドバー連携

- [ ] サイドバー（admin / coach / student 各 `_partials/sidebar-*.blade.php`）の「設定 [cog]」`<x-nav.item route="settings.profile.edit" />` 動作確認（`Route::has` が真でハイライト判定が動くこと）（`frontend-ui-foundation.md` 参照）

## Step 6: テスト

### Policy テスト

- [ ] `tests/Unit/Policies/UserPolicyTest.php` に **追加メソッド**:
  - `test_user_can_updateSelf_own_profile`（admin / coach / student すべて true）
  - `test_user_cannot_updateSelf_other_users_profile`
  - `test_coach_can_withdrawSelf`
  - `test_student_can_withdrawSelf`
  - `test_admin_cannot_withdrawSelf`
  - `test_user_cannot_withdrawSelf_other_user`

### Controller / Action Feature テスト

`structure.md` 規約「`tests/Feature/Http/{Entity}/` に Controller 単位で配置」に従い、entity 別ディレクトリ + Controller method 別ファイルで分割する。

- [ ] `tests/Feature/Http/Profile/EditTest.php`
  - `test_authenticated_user_can_view_profile_page`
  - `test_unauthenticated_user_is_redirected_to_login`
  - `test_admin_sees_2_tabs_without_withdraw`
  - `test_coach_sees_3_tabs_including_withdraw`
  - `test_student_sees_3_tabs_including_withdraw`
  - `test_coach_sees_meeting_url_field`
  - `test_student_does_not_see_meeting_url_field`
- [ ] `tests/Feature/Http/Profile/UpdateTest.php`
  - `test_user_can_update_own_name_and_bio`
  - `test_coach_can_update_meeting_url`
  - `test_student_meeting_url_is_silently_dropped`
  - `test_email_cannot_be_updated_via_profile_form`
  - `test_validation_fails_when_name_is_empty`
  - `test_no_user_status_log_is_recorded_on_profile_update`
- [ ] `tests/Feature/Http/Avatar/StoreTest.php`
  - `test_user_can_upload_png_avatar`
  - `test_user_can_upload_jpg_avatar`
  - `test_user_can_upload_webp_avatar`
  - `test_upload_fails_when_mime_is_invalid`（gif / pdf / txt）
  - `test_upload_fails_when_size_exceeds_2mb`
  - `test_old_avatar_file_is_deleted_after_successful_replacement`
  - `test_users_avatar_url_is_updated_with_public_url`
- [ ] `tests/Feature/Http/Avatar/DestroyTest.php`
  - `test_user_can_delete_own_avatar`
  - `test_avatar_url_is_set_to_null_after_delete`
  - `test_destroy_is_idempotent_when_no_avatar`
- [ ] `tests/Feature/Http/Password/UpdateTest.php`
  - `test_user_can_change_password_with_correct_current_password`
  - `test_password_change_fails_when_current_password_is_wrong`
  - `test_password_change_fails_when_new_password_is_less_than_8_chars`
  - `test_password_change_fails_when_confirmation_does_not_match`
- [ ] `tests/Feature/Http/User/SelfWithdrawTest.php`（entity = User、`SelfWithdrawController::__invoke` に対応）
  - `test_student_can_self_withdraw`
  - `test_coach_can_self_withdraw`
  - `test_admin_cannot_self_withdraw_via_policy`（403）
  - `test_email_is_renamed_after_withdraw`
  - `test_user_status_log_is_recorded_with_actor_self`
  - `test_user_is_logged_out_after_withdraw`
  - `test_enrollments_are_not_deleted_after_withdraw`
- [ ] `tests/Feature/Http/Availability/IndexTest.php`
  - `test_coach_can_view_own_availability_list`
  - `test_student_cannot_access_availability_index`（403）
  - `test_admin_cannot_access_availability_index`（403）
- [ ] `tests/Feature/Http/Availability/StoreTest.php`
  - `test_coach_can_create_availability_slot`
  - `test_validation_fails_when_end_time_is_before_start_time`
  - `test_validation_fails_when_day_of_week_is_out_of_range`
  - `test_overlapping_slots_are_allowed`（REQ-settings-profile-065 確認）
- [ ] `tests/Feature/Http/Availability/UpdateTest.php`
  - `test_coach_can_update_own_slot`
  - `test_coach_cannot_update_other_coachs_slot`（403、CoachAvailabilityPolicy）
- [ ] `tests/Feature/Http/Availability/DestroyTest.php`
  - `test_coach_can_delete_own_slot_soft`
  - `test_coach_cannot_delete_other_coachs_slot`

### Action / Unit テスト

- [ ] `tests/Feature/UseCases/User/SelfWithdrawActionTest.php`
  - `test_self_withdraw_updates_user_and_records_log_in_single_transaction`
  - `test_admin_self_withdraw_throws_exception`
  - `test_already_withdrawn_user_throws_exception`
- [ ] `tests/Feature/UseCases/Avatar/StoreActionTest.php`
  - `test_new_file_save_failure_throws_AvatarStorageFailedException`（`Storage::shouldReceive('putFileAs')->andThrow` でモック / DB と旧ファイル未変更を assert）
  - `test_db_update_failure_rolls_back_new_file`（新ファイル保存後 DB UPDATE 失敗時に新ファイルが削除されることを assert）
  - `test_old_file_is_removed_after_successful_db_update`（best-effort 削除の成功時挙動）
  - `test_old_file_deletion_failure_does_not_break_user_flow`（best-effort 削除が失敗してもユーザーフロー継続）

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Settings` が全 PASS（NFR-settings-profile-007 + 上記テスト）
- [ ] `sail artisan migrate:fresh --seed` で migration が正常実行されること（本 Feature では新規 migration なし、依存 Feature のみ）
- [ ] `sail artisan storage:link` が完了し、`public/storage/avatars/` が writable であること（NFR-settings-profile-004）
- [ ] `sail bin pint --dirty` で整形（NFR は `tech.md` 既定の Pint 規約に従う）
- [ ] ブラウザ動作確認（admin / coach / student の 3 ロールで通し検証）:
  1. ログイン後、サイドバー「設定」から `/settings/profile` に遷移できる
  2. プロフィールタブで氏名 / 自己紹介を編集して保存 → flash success 表示 + 値が反映
  3. アバター画像（PNG/JPG/WebP）をアップロード → `<x-avatar>` に新画像が表示 / 削除ボタンで NULL に戻る
  4. パスワードタブで現在のパスワード + 新パスワード（8文字以上）で変更 → 再ログイン可能
  5. （coach のみ）プロフィール画面に固定面談 URL フィールドが表示・保存できる
  6. （coach のみ）`/settings/availability` で枠を追加・編集・削除できる
  7. （coach 以外）`/settings/availability` にアクセスすると 403 ページ表示
  8. （coach / student のみ）退会タブから理由を入力して退会 → `/login` にリダイレクト + flash + 再ログイン不可
  9. （admin）退会タブが非表示 + `/settings/withdraw` 直 POST で 403
