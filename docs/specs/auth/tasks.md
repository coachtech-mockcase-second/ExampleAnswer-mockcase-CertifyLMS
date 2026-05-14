# auth タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-auth-NNN` / `NFR-auth-NNN` を参照。
> **前提**: [[user-management]] の `UserStatusLog` モデル + `UserStatusChangeService` が先行実装されていることが望ましい。auth 実装時点で未実装なら、本 Feature の Action 実装と並行して user-management Step 1-2 を進める必要がある（依存先 Feature の Step 1-2 を流用）。

## Step 1: Migration & Model

- [x] `database/migrations/{date}_create_users_table.php` — ULID 主キー / `email` UNIQUE / `password` **nullable** / `role` / `status` / `name` **nullable** / `bio` / `avatar_url` / `profile_setup_completed` / `email_verified_at` / `last_login_at` / `remember_token` / timestamps / softDeletes（REQ-auth-001, 004, 005）
- [x] `app/Enums/UserRole.php` — backed enum `Admin` / `Coach` / `Student` + `label()`（REQ-auth-002）
- [x] `app/Enums/UserStatus.php` — backed enum `Invited` / `Active` / `Withdrawn` + `label()`（REQ-auth-003）
- [x] `app/Models/User.php` — `HasUlids` + `SoftDeletes` + `Notifiable`、`$fillable` / `$casts`（role / status を Enum cast）、`hasMany(Invitation::class, 'user_id')`、`sendPasswordResetNotification()` の日本語化オーバーライド（`App\Notifications\Auth\ResetPasswordNotification`）、`withdraw()` ヘルパ（status=withdrawn + soft delete + email リネームを 1 メソッドに集約、REQ-auth-070）
- [x] `database/factories/UserFactory.php` — `admin()` / `coach()` / `student()` / `invited()` / `withdrawn()` state 提供（invited は password/name を NULL に）
- [x] `database/seeders/UserSeeder.php` — 初期 admin 1名を投入（`admin@certify-lms.test` / password=`password`、`DatabaseSeeder` に登録）
- [x] `database/migrations/{date}_create_invitations_table.php` — ULID 主キー / `user_id` FK（restrict、cascade なし、Action 側で明示削除）/ `email` / `role` / `invited_by_user_id` FK（restrict）/ `expires_at` / `accepted_at` / `revoked_at` / `status` / timestamps / softDeletes + INDEX（`user_id`, `status+expires_at`, `invited_by_user_id`）（REQ-auth-010）
- [x] `app/Enums/InvitationStatus.php` — backed enum `Pending` / `Accepted` / `Expired` / `Revoked` + `label()`
- [x] `app/Models/Invitation.php` — `HasUlids` + `SoftDeletes`、`belongsTo(User, 'user_id')` + `belongsTo(User, 'invited_by_user_id', 'invitedBy')`、`scopePending()` / `scopeExpired()`、`isUsable()` ヘルパ（pending かつ未期限切れ判定）
- [x] `database/factories/InvitationFactory.php` — `pending()` / `accepted()` / `expired()` / `revoked()` state、`forUser()` で対象 User を紐付け

## Step 2: Policy

- [x] `app/Policies/InvitationPolicy.php` — `viewAny` / `create` / `revoke`（REQ-auth-060, 061）
- [x] `AuthServiceProvider::$policies` で `Invitation::class => InvitationPolicy::class` を登録（or 自動検出を確認）

## Step 3: HTTP 層

- [x] `app/Http/Controllers/Auth/OnboardingController.php` — `show` / `store` 2 メソッド（REQ-auth-020, 021, 022）
- [x] `app/Http/Requests/Auth/OnboardingRequest.php` — name / bio / password (min:8, confirmed)（REQ-auth-024）。`authorize()` は true（署名検証は Controller 側）
- [x] `app/Http/Middleware/EnsureUserRole.php` — `role:admin` / `role:admin,coach` 等を受け付け（REQ-auth-040, 041）
- [x] `app/Http/Kernel.php` の `$middlewareAliases` に `'role' => EnsureUserRole::class` を追加
- [x] `routes/web.php` — Fortify ルート（自動）+ `Route::get('/onboarding/{invitation}', ...)->name('onboarding.show')->middleware('signed')` / `Route::post('/onboarding/{invitation}', ...)->name('onboarding.store')->middleware('signed')`（REQ-auth-062 — `auth` middleware は付けない）

## Step 4: Action / Service / Exception

- [x] `app/Exceptions/Auth/EmailAlreadyRegisteredException.php` — ConflictHttpException 継承（409）
- [x] `app/Exceptions/Auth/PendingInvitationAlreadyExistsException.php` — ConflictHttpException 継承（409）
- [x] `app/Exceptions/Auth/InvalidInvitationTokenException.php` — HttpException 継承（410）
- [x] `app/Exceptions/Auth/InvitationNotPendingException.php` — ConflictHttpException 継承（409）
- [x] `app/Services/InvitationTokenService.php` — `generateUrl(Invitation): string` / `verify(Request, Invitation): bool`（REQ-auth-015, 020）
- [x] `app/UseCases/Auth/IssueInvitationAction.php` — シグネチャ `__invoke(string $email, UserRole $role, User $invitedBy, bool $force = false): Invitation`。`UserStatusChangeService`（[[user-management]]）を constructor injection。重複検査 → User INSERT or 既存 invited User 再利用 → 旧 pending revoke（force=true 時、cascade なし、UserStatusLog 記録なし）→ 新規 User INSERT 時のみ `UserStatusChangeService::record($user, UserStatus::Invited, $invitedBy, '新規招待')` → Invitation INSERT → InvitationMail dispatch を `DB::transaction()` で包む（REQ-auth-011〜014, NFR-auth-005）
- [x] `app/UseCases/Auth/OnboardAction.php` — シグネチャ `__invoke(Invitation $invitation, array $validated): User`。`UserStatusChangeService`（[[user-management]]）を constructor injection。Invitation + User 整合性検証 → **User UPDATE**（status=active 等）+ Invitation UPDATE（accepted）+ `UserStatusChangeService::record($user, UserStatus::Active, $user, 'オンボーディング完了')` + `Auth::login()` を `DB::transaction()` で包む（REQ-auth-022, 023）
- [x] `app/UseCases/Auth/RevokeInvitationAction.php` — シグネチャ `__invoke(Invitation $invitation, ?User $admin = null, bool $cascadeWithdrawUser = true): void`。`UserStatusChangeService`（[[user-management]]）を constructor injection。Invitation revoke、cascade=true なら `User::withdraw()` 呼び出し + `UserStatusChangeService::record($user, UserStatus::Withdrawn, $admin, '招待取消')`。`DB::transaction()` で包む（REQ-auth-052）
- [x] `app/UseCases/Auth/ExpireInvitationsAction.php` — シグネチャ `__invoke(): int`。`UserStatusChangeService`（[[user-management]]）を constructor injection。期限切れ pending を一括 expired にし、紐付く invited User を cascade withdraw + 各 User に対して `UserStatusChangeService::record($user, UserStatus::Withdrawn, null, '招待期限切れ')`（actor=null でシステム自動記録）。`DB::transaction()` で包む（REQ-auth-050）
- [x] `app/Mail/InvitationMail.php` — Markdown Mailable + 件名「Certify LMS への招待」（REQ-auth-014）
- [x] `resources/views/emails/invitation.blade.php` — 招待者名 / ロール / 有効期限 / 「アカウントを作成」ボタン
- [x] `app/Providers/FortifyServiceProvider.php` — view binding + `authenticateUsing()` で `status === active` チェック + `rateLimit('login')`（REQ-auth-030, 032, NFR-auth-002）+ `config/fortify.php` を Registration / 2FA 無効化（招待制スコープ）+ `home => /dashboard`
- [x] `app/Listeners/UpdateLastLoginAt.php` — `Illuminate\Auth\Events\Login` を購読し `last_login_at = now()` を更新（REQ-auth-031）
- [x] `app/Providers/EventServiceProvider.php` で `Login::class => [UpdateLastLoginAt::class]` を登録

## Step 5: Schedule Command

- [x] `app/Console/Commands/Auth/ExpireInvitationsCommand.php` — signature `invitations:expire`、`ExpireInvitationsAction` を呼ぶ薄いラッパー（REQ-auth-050）
- [x] `app/Console/Kernel.php::schedule()` に `->command('invitations:expire')->dailyAt('00:30')` 追加（REQ-auth-051）

## Step 6: Blade ビュー

- [x] `resources/views/auth/login.blade.php` — Fortify 連携、Tailwind ガードレイアウト（NFR-auth-003）
- [x] `resources/views/auth/forgot-password.blade.php` — email のみ、共通成功メッセージ（REQ-auth-035）
- [x] `resources/views/auth/reset-password.blade.php` — password + confirmation + token + email hidden
- [x] `resources/views/auth/onboarding.blade.php` — name / bio / password / confirmation、Invitation の email + role を表示（POST URL は Controller が `URL::temporarySignedRoute` で生成して `$postUrl` で渡す）
- [x] `resources/views/auth/invitation-invalid.blade.php` — 「招待リンクが無効または期限切れです」+ 管理者連絡案内
- [x] `lang/ja/auth.php` — Fortify / カスタム例外のメッセージ集約（NFR-auth-004）+ `lang/ja/validation.php` + `config/app.php` の `'locale' => 'ja'`

## Step 7: テスト

### Feature テスト

- [x] `tests/Feature/Auth/OnboardingTest.php`
  - `test_show_renders_form_with_valid_signed_url`（REQ-auth-020, 021）
  - `test_show_renders_invalid_view_for_tampered_signature`
  - `test_show_renders_invalid_view_for_expired_invitation`
  - `test_show_renders_invalid_view_for_accepted_invitation`
  - `test_show_renders_invalid_view_when_user_status_not_invited`（REQ-auth-020 (4)）
  - `test_store_updates_existing_invited_user_to_active`（REQ-auth-022、UPDATE 方式）
  - `test_store_marks_invitation_accepted_and_auto_logs_in`
  - `test_store_does_not_create_new_user_row`（User 数が変化しないこと）
  - `test_store_rejects_short_password`（REQ-auth-024）
- [x] `tests/Feature/Auth/LoginTest.php`
  - `test_active_user_can_login_and_last_login_at_updated`（REQ-auth-031）
  - `test_invited_status_user_cannot_login`（REQ-auth-032）
  - `test_withdrawn_status_user_cannot_login`（REQ-auth-032）
  - `test_invalid_password_returns_same_error_as_inactive_status`
- [x] `tests/Feature/Auth/LogoutTest.php`
  - `test_logout_invalidates_session_and_redirects`（REQ-auth-033）
- [x] `tests/Feature/Auth/PasswordResetTest.php`
  - `test_forgot_password_returns_same_message_for_existing_and_non_existing_email`（REQ-auth-035）
  - `test_reset_password_updates_hash_and_redirects`（REQ-auth-036）
- [x] `tests/Feature/UseCases/Auth/IssueInvitationActionTest.php`
  - `test_creates_user_with_invited_status_and_invitation_with_7_days_expiry`（REQ-auth-011）
  - `test_user_has_nullable_password_and_name_when_invited`（REQ-auth-001, 005）
  - `test_throws_email_already_registered_for_active_user`（REQ-auth-012）
  - `test_throws_pending_already_exists_when_force_is_false`（REQ-auth-013）
  - `test_re_invite_with_force_revokes_old_pending_and_keeps_user_invited_without_status_log`（REQ-auth-013, cascade なし、UserStatusLog 新規挿入なし）
  - `test_dispatches_invitation_mail`（REQ-auth-014）
  - `test_inserts_user_status_log_with_invited_status_on_new_user_insert`（REQ-auth-011、actor=invitedBy）
- [x] `tests/Feature/UseCases/Auth/OnboardActionTest.php`
  - `test_inserts_user_status_log_with_active_status_on_onboarding`（REQ-auth-022、actor=本人）
  - `test_throws_invalid_invitation_for_expired_invitation`（REQ-auth-023）
- [x] `tests/Feature/UseCases/Auth/RevokeInvitationActionTest.php`
  - `test_revokes_pending_invitation_and_cascade_withdraws_user`（REQ-auth-052, cascade=true デフォルト）
  - `test_revoke_with_cascade_false_keeps_user_invited`（force re-invite で使う internal モード、UserStatusLog 新規挿入なし）
  - `test_throws_invitation_not_pending_for_accepted_invitation`
  - `test_cascade_withdraw_renames_email_and_soft_deletes`（REQ-auth-070）
  - `test_inserts_user_status_log_with_admin_actor_on_cascade`（REQ-auth-052、actor=$admin）
  - `test_inserts_user_status_log_with_null_actor_when_admin_is_null`（REQ-auth-052、actor=null でシステム自動相当）
- [x] `tests/Feature/UseCases/Auth/ExpireInvitationsActionTest.php`
  - `test_marks_expired_and_cascade_withdraws_users`（REQ-auth-050, product.md state diagram 整合）
  - `test_does_not_touch_active_or_accepted_users`
  - `test_inserts_user_status_log_with_null_actor_for_each_expired_user`（REQ-auth-050、Schedule Command 由来で actor=null）

### Unit テスト

- [x] `tests/Unit/Services/InvitationTokenServiceTest.php`
  - `test_generate_url_includes_expires_query`（REQ-auth-015）
  - `test_verify_returns_true_for_valid_request`
  - `test_verify_returns_false_for_tampered_signature`
- [x] `tests/Unit/UseCases/Auth/ExpireInvitationsActionTest.php`
  - `test_marks_only_pending_past_expiry_as_expired`（REQ-auth-050）
  - `test_does_not_touch_accepted_or_revoked_invitations`
- [x] `tests/Unit/Policies/InvitationPolicyTest.php`
  - `test_admin_can_create_viewAny_revoke`
  - `test_coach_and_student_cannot_create_viewAny_revoke`（REQ-auth-060, 061）
  - `test_admin_cannot_revoke_already_accepted_invitation`（REQ-auth-061、pending 以外は false）
- [x] `tests/Unit/Middleware/EnsureUserRoleTest.php`
  - `test_passes_when_role_matches`
  - `test_passes_when_one_of_multiple_roles_matches`
  - `test_aborts_403_when_role_mismatches`（REQ-auth-040）
  - `test_aborts_403_when_unauthenticated`

## Step 8: 動作確認 & 整形

- [x] `sail artisan test` 通過（46 tests, 127 assertions, 全 PASS）
- [x] `sail bin pint --dirty` で整形（passed）
- [x] CLI スモークテストで通しシナリオを確認:
  1. 初期 admin (`admin@certify-lms.test` / `password`) で `/login` ログイン → `/dashboard` 200、`last_login_at` 更新を確認
  2. `IssueInvitationAction` 実行で招待発行 → `users.status='invited'` / `user_status_logs.status='invited' / changed_by_user_id=$admin / reason='新規招待'` 挿入確認
  3. Mailpit (http://localhost:8025) で InvitationMail（subject「Certify LMS への招待」、to=招待先 email）受信確認
  4. 署名付きオンボーディング URL を `curl` → `auth.onboarding` ビューが email + ロール表示で 200 返却
  5. 署名改竄 URL → `auth.invitation-invalid` 200 返却（403 ではない、REQ-auth-020 整合）
  6. `invitations:expire` 手動実行 → 期限切れ pending が expired、紐付く invited User が withdrawn + email リネーム + `user_status_logs.status='withdrawn' / changed_by_user_id=NULL / reason='招待期限切れ'` 挿入確認
- [ ] ブラウザでのフル UI 通しテスト + 動画キャプチャは PR 提出時に実施（Chrome 自動化は本セッション時点で extension 未接続のため CLI で代替）
