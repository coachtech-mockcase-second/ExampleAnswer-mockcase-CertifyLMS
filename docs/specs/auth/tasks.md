# auth タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-auth-NNN` / `NFR-auth-NNN` を参照。

## Step 1: Migration & Model

- [ ] `database/migrations/{date}_create_users_table.php` — ULID 主キー / `email` UNIQUE / `password` **nullable** / `role` / `status` / `name` **nullable** / `bio` / `avatar_url` / `profile_setup_completed` / `email_verified_at` / `last_login_at` / `remember_token` / timestamps / softDeletes（REQ-auth-001, 004, 005）
- [ ] `app/Enums/UserRole.php` — backed enum `Admin` / `Coach` / `Student` + `label()`（REQ-auth-002）
- [ ] `app/Enums/UserStatus.php` — backed enum `Invited` / `Active` / `Withdrawn` + `label()`（REQ-auth-003）
- [ ] `app/Models/User.php` — `HasUlids` + `SoftDeletes` + `Notifiable`、`$fillable` / `$casts`（role / status を Enum cast）、`hasMany(Invitation::class, 'user_id')`、`sendPasswordResetNotification()` の日本語化オーバーライド、`withdraw()` ヘルパ（status=withdrawn + soft delete + email リネームを 1 メソッドに集約、REQ-auth-070）
- [ ] `database/factories/UserFactory.php` — `admin()` / `coach()` / `student()` / `invited()` / `withdrawn()` state 提供（invited は password/name を NULL に）
- [ ] `database/seeders/UserSeeder.php` — 初期 admin 1名を投入
- [ ] `database/migrations/{date}_create_invitations_table.php` — ULID 主キー / `user_id` FK（cascade なし、Action 側で明示削除）/ `email` / `role` / `invited_by_user_id` FK（restrict）/ `expires_at` / `accepted_at` / `revoked_at` / `status` / timestamps / softDeletes + INDEX（`user_id`, `expires_at+status`）（REQ-auth-010）
- [ ] `app/Enums/InvitationStatus.php` — backed enum `Pending` / `Accepted` / `Expired` / `Revoked` + `label()`
- [ ] `app/Models/Invitation.php` — `HasUlids` + `SoftDeletes`、`belongsTo(User, 'user_id')` + `belongsTo(User, 'invited_by_user_id', 'invitedBy')`、`scopePending()` / `scopeExpired()`、`isUsable()` ヘルパ（pending かつ未期限切れ判定）
- [ ] `database/factories/InvitationFactory.php` — `pending()` / `accepted()` / `expired()` / `revoked()` state、`forUser()` で対象 User を紐付け

## Step 2: Policy

- [ ] `app/Policies/InvitationPolicy.php` — `viewAny` / `create` / `revoke`（REQ-auth-060, 061）
- [ ] `AuthServiceProvider::$policies` で `Invitation::class => InvitationPolicy::class` を登録（or 自動検出を確認）

## Step 3: HTTP 層

- [ ] `app/Http/Controllers/Auth/OnboardingController.php` — `show` / `store` 2 メソッド（REQ-auth-020, 021, 022）
- [ ] `app/Http/Requests/Auth/OnboardingRequest.php` — name / bio / password (min:8, confirmed)（REQ-auth-024）。`authorize()` は true（署名検証は Controller 側）
- [ ] `app/Http/Middleware/EnsureUserRole.php` — `role:admin` / `role:admin,coach` 等を受け付け（REQ-auth-040, 041）
- [ ] `app/Http/Kernel.php` の `$middlewareAliases` に `'role' => EnsureUserRole::class` を追加
- [ ] `routes/web.php` — Fortify ルート（自動）+ `Route::get('/onboarding/{invitation}', ...)->name('onboarding.show')->middleware('signed')` / `Route::post('/onboarding/{invitation}', ...)->name('onboarding.store')->middleware('signed')`（REQ-auth-062 — `auth` middleware は付けない）

## Step 4: Action / Service / Exception

- [ ] `app/Exceptions/Auth/EmailAlreadyRegisteredException.php` — ConflictHttpException 継承（409）
- [ ] `app/Exceptions/Auth/PendingInvitationAlreadyExistsException.php` — ConflictHttpException 継承（409）
- [ ] `app/Exceptions/Auth/InvalidInvitationTokenException.php` — HttpException 継承（410）
- [ ] `app/Exceptions/Auth/InvitationNotPendingException.php` — ConflictHttpException 継承（409）
- [ ] `app/Services/InvitationTokenService.php` — `generateUrl(Invitation): string` / `verify(Request, Invitation): bool`（REQ-auth-015, 020）
- [ ] `app/UseCases/Auth/IssueInvitationAction.php` — シグネチャ `__invoke(string $email, UserRole $role, User $invitedBy, bool $force = false): Invitation`。重複検査 → User INSERT or 既存 invited User 再利用 → 旧 pending revoke（force=true 時、cascade なし）→ Invitation INSERT → InvitationMail dispatch を `DB::transaction()` で包む（REQ-auth-011〜014, NFR-auth-005）
- [ ] `app/UseCases/Auth/OnboardAction.php` — シグネチャ `__invoke(Invitation $invitation, array $validated): User`。Invitation + User 整合性検証 → **User UPDATE**（status=active 等）+ Invitation UPDATE（accepted）+ `Auth::login()` を `DB::transaction()` で包む（REQ-auth-022, 023）
- [ ] `app/UseCases/Auth/RevokeInvitationAction.php` — シグネチャ `__invoke(Invitation $invitation, bool $cascadeWithdrawUser = true): void`。Invitation revoke、cascade なら `User::withdraw()` 呼び出し（REQ-auth-052）
- [ ] `app/UseCases/Auth/ExpireInvitationsAction.php` — シグネチャ `__invoke(): int`。期限切れ pending を一括 expired にし、紐付く invited User を cascade withdraw（REQ-auth-050）
- [ ] `app/Mail/InvitationMail.php` — Markdown Mailable + 件名「Certify LMS への招待」（REQ-auth-014）
- [ ] `resources/views/emails/invitation.blade.php` — 招待者名 / ロール / 有効期限 / 「アカウントを作成」ボタン
- [ ] `app/Providers/FortifyServiceProvider.php` — view binding + `authenticateUsing()` で `status === active` チェック + `rateLimit('login')`（REQ-auth-030, 032, NFR-auth-002）
- [ ] `app/Listeners/UpdateLastLoginAt.php` — `Illuminate\Auth\Events\Login` を購読し `last_login_at = now()` を更新（REQ-auth-031）
- [ ] `app/Providers/EventServiceProvider.php` で `Login::class => [UpdateLastLoginAt::class]` を登録

## Step 5: Schedule Command

- [ ] `app/Console/Commands/Auth/ExpireInvitationsCommand.php` — signature `invitations:expire`、`ExpireInvitationsAction` を呼ぶ薄いラッパー（REQ-auth-050）
- [ ] `app/Console/Kernel.php::schedule()` に `->command('invitations:expire')->dailyAt('00:30')` 追加（REQ-auth-051）

## Step 6: Blade ビュー

- [ ] `resources/views/auth/login.blade.php` — Fortify 連携、Tailwind ガードレイアウト（NFR-auth-003）
- [ ] `resources/views/auth/forgot-password.blade.php` — email のみ、共通成功メッセージ（REQ-auth-035）
- [ ] `resources/views/auth/reset-password.blade.php` — password + confirmation + token + email hidden
- [ ] `resources/views/auth/onboarding.blade.php` — name / bio / password / confirmation、Invitation の email + role を表示
- [ ] `resources/views/auth/invitation-invalid.blade.php` — 「招待リンクが無効または期限切れです」+ 管理者連絡案内
- [ ] `lang/ja/auth.php` — Fortify / カスタム例外のメッセージ集約（NFR-auth-004）

## Step 7: テスト

### Feature テスト

- [ ] `tests/Feature/Auth/OnboardingTest.php`
  - `test_show_renders_form_with_valid_signed_url`（REQ-auth-020, 021）
  - `test_show_renders_invalid_view_for_tampered_signature`
  - `test_show_renders_invalid_view_for_expired_invitation`
  - `test_show_renders_invalid_view_for_accepted_invitation`
  - `test_show_renders_invalid_view_when_user_status_not_invited`（REQ-auth-020 (4)）
  - `test_store_updates_existing_invited_user_to_active`（REQ-auth-022、UPDATE 方式）
  - `test_store_marks_invitation_accepted_and_auto_logs_in`
  - `test_store_does_not_create_new_user_row`（User 数が変化しないこと）
  - `test_store_rejects_short_password`（REQ-auth-024）
  - `test_store_throws_invalid_invitation_for_expired_invitation`（REQ-auth-023）
- [ ] `tests/Feature/Auth/LoginTest.php`
  - `test_active_user_can_login_and_last_login_at_updated`（REQ-auth-031）
  - `test_invited_status_user_cannot_login`（REQ-auth-032）
  - `test_withdrawn_status_user_cannot_login`（REQ-auth-032）
  - `test_invalid_password_returns_same_error_as_inactive_status`
- [ ] `tests/Feature/Auth/LogoutTest.php`
  - `test_logout_invalidates_session_and_redirects`（REQ-auth-033）
- [ ] `tests/Feature/Auth/PasswordResetTest.php`
  - `test_forgot_password_returns_same_message_for_existing_and_non_existing_email`（REQ-auth-035）
  - `test_reset_password_updates_hash_and_redirects`（REQ-auth-036）
- [ ] `tests/Feature/UseCases/Auth/IssueInvitationActionTest.php`
  - `test_creates_user_with_invited_status_and_invitation_with_7_days_expiry`（REQ-auth-011）
  - `test_user_has_nullable_password_and_name_when_invited`（REQ-auth-001, 005）
  - `test_throws_email_already_registered_for_active_user`（REQ-auth-012）
  - `test_throws_pending_already_exists_when_force_is_false`（REQ-auth-013）
  - `test_re_invite_with_force_revokes_old_pending_and_keeps_user_invited`（REQ-auth-013, cascade なし）
  - `test_dispatches_invitation_mail`（REQ-auth-014）
- [ ] `tests/Feature/UseCases/Auth/RevokeInvitationActionTest.php`
  - `test_revokes_pending_invitation_and_cascade_withdraws_user`（REQ-auth-052, cascade=true デフォルト）
  - `test_revoke_with_cascade_false_keeps_user_invited`（force re-invite で使う internal モード）
  - `test_throws_invitation_not_pending_for_accepted_invitation`
  - `test_cascade_withdraw_renames_email_and_soft_deletes`（REQ-auth-070）
- [ ] `tests/Feature/UseCases/Auth/ExpireInvitationsActionTest.php`
  - `test_marks_expired_and_cascade_withdraws_users`（REQ-auth-050, product.md state diagram 整合）
  - `test_does_not_touch_active_or_accepted_users`

### Unit テスト

- [ ] `tests/Unit/Services/InvitationTokenServiceTest.php`
  - `test_generate_url_includes_expires_query`（REQ-auth-015）
  - `test_verify_returns_true_for_valid_request`
  - `test_verify_returns_false_for_tampered_signature`
- [ ] `tests/Unit/UseCases/Auth/ExpireInvitationsActionTest.php`
  - `test_marks_only_pending_past_expiry_as_expired`（REQ-auth-050）
  - `test_does_not_touch_accepted_or_revoked_invitations`
- [ ] `tests/Unit/Policies/InvitationPolicyTest.php`
  - `test_admin_can_create_viewAny_revoke`
  - `test_coach_and_student_cannot_create_viewAny_revoke`（REQ-auth-060, 061）
- [ ] `tests/Unit/Middleware/EnsureUserRoleTest.php`
  - `test_passes_when_role_matches`
  - `test_aborts_403_when_role_mismatches`（REQ-auth-040）

## Step 8: 動作確認 & 整形

- [ ] `sail artisan test --filter=Auth` 通過
- [ ] `sail artisan test --filter=Invitation` 通過
- [ ] `sail bin pint --dirty` で整形
- [ ] ブラウザで通しシナリオを確認:
  1. 初期 admin で `/login` ログイン → `/dashboard` リダイレクト
  2. admin 操作（user-management 側、ここでは仮に `sail artisan tinker` から `IssueInvitationAction` を実行）で招待発行
  3. Mailpit（http://localhost:8025）で InvitationMail を受信、署名付き URL をクリック
  4. オンボーディングフォーム表示 → name / password 入力 → 送信 → 自動ログインで `/dashboard`
  5. ログアウト → `/login` → 同じ email + 新 password でログイン成功
  6. パスワードリセット要求 → Mailpit で受信 → 新パスワード設定 → ログイン
  7. 期限切れ Invitation でアクセス → `auth/invitation-invalid` 表示 + 該当 User が cascade で withdrawn になっていることを phpMyAdmin（http://localhost:8080）で確認
- [ ] Schedule Command の動作確認: `sail artisan invitations:expire` 手動実行 → 期限切れ pending が expired になり、紐付く invited User が withdrawn + email リネームされることを phpMyAdmin で確認
- [ ] CSRF / セッション regenerate / Hash::make が想定通り動いているか PR 動作確認動画に収録
