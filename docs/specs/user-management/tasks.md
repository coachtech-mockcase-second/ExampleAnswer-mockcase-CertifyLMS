# user-management タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-user-management-NNN` / `NFR-user-management-NNN` を参照。
> 本 Feature は [[auth]] の `User` / `Invitation` モデル + `IssueInvitationAction` / `RevokeInvitationAction` が **先行実装済み** であることを前提とする。

## Step 1: Migration & Model

- [x] `database/migrations/{date}_create_user_status_logs_table.php` — ULID 主キー / `user_id` FK to users / `changed_by_user_id` FK to users nullable / `status` string / `changed_at` datetime / `changed_reason` string nullable max 200 / timestamps + INDEX (`user_id`, `changed_by_user_id`, `changed_at`)。softDelete は無し（REQ-user-management-060, 061）
- [x] `app/Models/UserStatusLog.php` — `HasUlids`、`$fillable` / `$casts`（`status` を `UserStatus` Enum cast、`changed_at` を datetime）、`belongsTo(User::class, 'user_id')` + `belongsTo(User::class, 'changed_by_user_id', 'changedBy')` の 2 リレーション、`changedBy` は `withTrashed()` を含めて解決可能にするアクセサもしくはリレーション定義（REQ-user-management-062）
- [x] `database/factories/UserStatusLogFactory.php` — `forUser(User $user)` / `bySystem()` / `byAdmin(User $admin)` の state を提供
- [x] `app/Models/User.php` への追記（[[auth]] 既存ファイルへの拡張）— `hasMany(UserStatusLog::class, 'user_id')` を `statusLogs()` として、`hasMany(UserStatusLog::class, 'changed_by_user_id')` を `statusChanges()` として追加（REQ-user-management-062）

## Step 2: Service

- [x] `app/Services/UserStatusChangeService.php` — `record(User $user, UserStatus $newStatus, ?User $changedBy, ?string $reason = null): UserStatusLog`（REQ-user-management-070, 072, 073、NFR-user-management-005）
- [x] [[auth]] Action 側への組込み確認（auth 実装時の追従、本 Feature では未実装でも spec として明示）—  `IssueInvitationAction` / `OnboardAction` / `ExpireInvitationsAction` / `RevokeInvitationAction` の各々で `UserStatusChangeService` を constructor injection し、status 更新後に `record()` を呼ぶ。本 Feature の実装時点で [[auth]] が先行実装済みの場合は **このタスクで auth 側へ追記する**（REQ-user-management-071）— **[[auth]] Feature 実装の Step 4 で対応**

## Step 3: Policy

- [ ] `app/Policies/UserPolicy.php` — `viewAny` / `view` / `update` / `updateRole` / `withdraw` の 5 メソッド、すべて `$auth->role === UserRole::Admin` 判定（REQ-user-management-081）
- [ ] `app/Providers/AuthServiceProvider.php` の `$policies` 配列に `User::class => UserPolicy::class` を登録（または Laravel 自動検出に任せる）

## Step 4: HTTP 層（Controller / FormRequest / Route）

- [ ] `app/Http/Controllers/UserController.php` — `index` / `show` / `update` / `updateRole` / `withdraw` の 5 メソッド、Controller 内ビジネスロジック 0 行（REQ-user-management-006, 020, 030, 040, 050）
- [ ] `app/Http/Controllers/InvitationController.php` — `store` / `resend` / `destroy` の 3 メソッド、[[auth]] の `IssueInvitationAction` / `RevokeInvitationAction` を DI（REQ-user-management-011, 012, 013）
- [ ] `app/Http/Requests/User/IndexRequest.php` — `keyword: nullable string max:100` / `role: nullable in:admin,coach,student` / `status: nullable in:invited,active,withdrawn` / `page: nullable integer min:1`、`authorize` で `viewAny User::class`（REQ-user-management-002, 003, 004）
- [ ] `app/Http/Requests/User/UpdateRequest.php` — `name: required string max:50` / `email: required email max:255 unique:users,email,{user}` / `bio: nullable string max:1000` / `avatar_url: nullable url max:500`、`authorize` で `update User`（REQ-user-management-030, 031）
- [ ] `app/Http/Requests/User/UpdateRoleRequest.php` — `role: required in:admin,coach,student`、`authorize` で `updateRole User`（REQ-user-management-040）
- [ ] `app/Http/Requests/User/WithdrawRequest.php` — `reason: required string max:200`、`authorize` で `withdraw User`（REQ-user-management-054、必須化で誤操作防止 + 監査）
- [ ] `app/Http/Requests/Invitation/StoreRequest.php` — `email: required email max:255` / `role: required in:coach,student`、`authorize` で `create Invitation`（REQ-user-management-010）
- [ ] `app/Http/Requests/Invitation/ResendRequest.php` — body なし、`authorize` で `create Invitation`
- [ ] `routes/web.php` に `Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(...)` で 8 ルートを追加（`admin.users.{index, show, update, updateRole, withdraw}` + `admin.invitations.{store, resend, destroy}`）。`admin.users.show` は `->withTrashed()` 付与（REQ-user-management-006, 024, 080）

## Step 5: Action / Exception

- [ ] `app/Exceptions/UserManagement/SelfRoleChangeForbiddenException.php` — `AccessDeniedHttpException` 継承（HTTP 403）、メッセージ「自分自身のロールは変更できません。」（NFR-user-management-004）
- [ ] `app/Exceptions/UserManagement/SelfWithdrawForbiddenException.php` — `AccessDeniedHttpException` 継承（HTTP 403）、メッセージ「自分自身を退会させることはできません。退会は設定画面から行ってください。」（NFR-user-management-004）
- [ ] `app/Exceptions/UserManagement/UserAlreadyWithdrawnException.php` — `ConflictHttpException` 継承（HTTP 409）、メッセージ「対象ユーザーは既に退会済みです。」（NFR-user-management-004）
- [ ] `app/UseCases/User/IndexAction.php` — `keyword` / `role` / `status` の検索・フィルタ → `FIELD(status, 'active', 'invited', 'withdrawn')` priority sort + `created_at` desc → `paginate(20)`、`status === Withdrawn` 明示選択時のみ `withTrashed()` 適用（REQ-user-management-001, 002, 003, 004, 005, 008、NFR-user-management-002, 003）
- [ ] `app/UseCases/User/ShowAction.php` — `User` に `enrollments.certification` / `statusLogs.changedBy`（`withTrashed`）/ `invitations` を eager load、各リレーションを時系列 sort（REQ-user-management-020, 021, 022, 023）
- [ ] `app/UseCases/User/UpdateAction.php` — `withdrawn` ガード + `DB::transaction` 内で User UPDATE（REQ-user-management-030, 033、NFR-user-management-001）
- [ ] `app/UseCases/User/UpdateRoleAction.php` — `$user->is($admin)` / `withdrawn` ガード + `DB::transaction` 内で role UPDATE（REQ-user-management-040, 041, 042, 043、NFR-user-management-001）
- [ ] `app/UseCases/User/WithdrawAction.php` — `$user->is($admin)` / `withdrawn` / `invited` ガード + `DB::transaction` 内で [[auth]] の `User::withdraw()` ヘルパ呼出（email リネーム + status UPDATE + soft delete）+ `UserStatusChangeService::record($user, UserStatus::Withdrawn, $admin, $reason)` 呼出（REQ-user-management-050, 051, 052, 053, 054、NFR-user-management-001）
- [ ] `app/UseCases/Invitation/StoreAction.php` — シグネチャ `__invoke(string $email, UserRole $role, User $admin): Invitation`。内部で [[auth]] `IssueInvitationAction($email, $role, $admin, force: false)` を呼ぶラッパー（REQ-user-management-011）
- [ ] `app/UseCases/Invitation/ResendAction.php` — シグネチャ `__invoke(User $user, User $admin): Invitation`。内部で [[auth]] `IssueInvitationAction($user->email, $user->role, $admin, force: true)` を呼ぶラッパー（REQ-user-management-012）
- [ ] `app/UseCases/Invitation/DestroyAction.php` — シグネチャ `__invoke(Invitation $invitation, User $admin): void`。内部で [[auth]] `RevokeInvitationAction($invitation, $admin, cascadeWithdrawUser: true)` を呼ぶラッパー（REQ-user-management-013, 014）

## Step 6: Blade ビュー

- [ ] `resources/views/admin/users/index.blade.php` — テーブル + 検索フォーム + ロール / ステータスフィルタ + ページネーション + 「+招待」ボタン（モーダル展開）（REQ-user-management-001, 002, 003, 004, 005, 007）
- [ ] `resources/views/admin/users/show.blade.php` — プロフィールカード + 操作ボタン群 + Enrollment 概要 + UserStatusLog タイムライン + Invitation 履歴（REQ-user-management-020, 021, 022, 023）
- [ ] `resources/views/admin/users/_partials/profile-card.blade.php` — プロフィール表示（編集モーダル起点ボタン含む）
- [ ] `resources/views/admin/users/_partials/enrollments-section.blade.php` — 受講中資格カード（最大 10 件、enrollment 詳細リンク）
- [ ] `resources/views/admin/users/_partials/status-log-timeline.blade.php` — 時系列タイムライン（actor 名 `$log->changedBy?->name ?? 'システム'`、status badge、changed_reason 表示）（REQ-user-management-073）
- [ ] `resources/views/admin/users/_partials/invitation-history.blade.php` — 招待履歴テーブル（status badge + 期限 + accepted/revoked 補助情報）
- [ ] `resources/views/admin/users/_modals/invite-user-form.blade.php` — 招待モーダル（email + role 入力、`POST /admin/invitations`）（REQ-user-management-010, 011）
- [ ] `resources/views/admin/users/_modals/edit-profile-form.blade.php` — プロフィール編集モーダル（`PATCH /admin/users/{user}`）
- [ ] `resources/views/admin/users/_modals/change-role-form.blade.php` — ロール変更モーダル（`PATCH /admin/users/{user}/role`）
- [ ] `resources/views/admin/users/_modals/withdraw-confirm.blade.php` — 退会確認モーダル（reason 入力、`POST /admin/users/{user}/withdraw`）（REQ-user-management-054）
- [ ] `resources/views/admin/users/_modals/cancel-invitation-confirm.blade.php` — 招待取消確認モーダル（`DELETE /admin/invitations/{invitation}`）（REQ-user-management-015）

## Step 7: テスト

### Feature テスト

- [ ] `tests/Feature/Http/Admin/User/IndexTest.php`
  - `test_admin_can_view_user_list`（REQ-user-management-001）
  - `test_coach_and_student_cannot_access_admin_users_index`（REQ-user-management-083）
  - `test_keyword_search_filters_by_name_and_email`（REQ-user-management-002）
  - `test_role_filter_returns_only_matching_role`（REQ-user-management-003）
  - `test_status_filter_returns_only_matching_status`（REQ-user-management-004）
  - `test_status_filter_excludes_withdrawn_by_default`（REQ-user-management-004）
  - `test_status_filter_includes_withdrawn_when_explicitly_selected`（REQ-user-management-008）
  - `test_paginates_20_per_page`（REQ-user-management-005）
  - `test_orders_by_status_priority_then_created_at_desc`（REQ-user-management-001）
- [ ] `tests/Feature/Http/Admin/User/ShowTest.php`
  - `test_admin_can_view_active_user_detail`（REQ-user-management-020）
  - `test_admin_can_view_withdrawn_user_detail_with_renamed_email`（REQ-user-management-024）
  - `test_displays_enrollments_status_logs_and_invitations`（REQ-user-management-021, 022, 023）
  - `test_returns_404_for_non_existing_user`（REQ-user-management-024）
- [ ] `tests/Feature/Http/Admin/User/UpdateTest.php`
  - `test_admin_can_update_user_profile`（REQ-user-management-030）
  - `test_email_uniqueness_excludes_target_user`（REQ-user-management-031）
  - `test_rejects_duplicate_email`（REQ-user-management-031）
  - `test_rejects_update_when_user_is_withdrawn`（REQ-user-management-033）
  - `test_does_not_insert_user_status_log`（REQ-user-management-032）
- [ ] `tests/Feature/Http/Admin/User/UpdateRoleTest.php`
  - `test_admin_can_change_role`（REQ-user-management-040）
  - `test_admin_cannot_change_own_role`（REQ-user-management-041）
  - `test_rejects_role_change_when_user_is_withdrawn`（REQ-user-management-042）
  - `test_does_not_insert_user_status_log`（REQ-user-management-043）
- [ ] `tests/Feature/Http/Admin/User/WithdrawTest.php`
  - `test_admin_can_withdraw_active_user`（REQ-user-management-050）
  - `test_renames_email_and_soft_deletes`（REQ-user-management-050）
  - `test_inserts_user_status_log_with_admin_as_changer`（REQ-user-management-050, 054）
  - `test_admin_cannot_withdraw_themselves`（REQ-user-management-051）
  - `test_cannot_withdraw_already_withdrawn_user`（REQ-user-management-052）
  - `test_cannot_withdraw_invited_user`（REQ-user-management-053）
- [ ] `tests/Feature/Http/Admin/Invitation/StoreTest.php`
  - `test_admin_can_issue_invitation_for_coach_role`（REQ-user-management-010, 011）
  - `test_admin_can_issue_invitation_for_student_role`（REQ-user-management-010, 011）
  - `test_cannot_issue_invitation_for_admin_role`（REQ-user-management-010）
  - `test_dispatches_invitation_mail_and_inserts_status_log`（REQ-user-management-011）
- [ ] `tests/Feature/Http/Admin/Invitation/ResendTest.php`
  - `test_admin_can_resend_invitation_with_force_true`（REQ-user-management-012）
  - `test_old_pending_is_revoked_and_user_stays_invited`（REQ-user-management-012）
  - `test_does_not_insert_new_status_log`（REQ-user-management-012）
- [ ] `tests/Feature/Http/Admin/Invitation/DestroyTest.php`
  - `test_admin_can_cancel_pending_invitation`（REQ-user-management-013）
  - `test_user_is_cascade_withdrawn_with_renamed_email`（REQ-user-management-013）
  - `test_inserts_user_status_log_with_admin_as_changer`（REQ-user-management-013）
  - `test_throws_invitation_not_pending_for_accepted_invitation`（REQ-user-management-014）

### Unit テスト

- [ ] `tests/Unit/Services/UserStatusChangeServiceTest.php`
  - `test_record_inserts_user_status_log_with_changed_by_user_id`（REQ-user-management-070）
  - `test_record_with_null_changer_inserts_null_changed_by_user_id`（REQ-user-management-073）
  - `test_record_does_not_update_user_status`（REQ-user-management-072）
  - `test_record_stores_changed_reason`（REQ-user-management-070）
- [ ] `tests/Unit/UseCases/User/WithdrawActionTest.php`
  - `test_throws_self_withdraw_forbidden_for_self_user`（REQ-user-management-051）
  - `test_throws_user_already_withdrawn_for_withdrawn_user`（REQ-user-management-052）
  - `test_throws_http_422_for_invited_user`（REQ-user-management-053）
  - `test_transaction_rolls_back_on_status_log_failure`（NFR-user-management-001）
- [ ] `tests/Unit/Policies/UserPolicyTest.php`
  - `test_admin_can_perform_all_user_management_operations`（REQ-user-management-081）
  - `test_coach_and_student_cannot_perform_user_management_operations`（REQ-user-management-081）
- [ ] `tests/Unit/Models/UserStatusLogTest.php`
  - `test_user_relation_returns_target_user`（REQ-user-management-062）
  - `test_changed_by_relation_returns_admin_user_even_when_soft_deleted`（REQ-user-management-062）
  - `test_status_is_cast_to_enum`（REQ-user-management-060）

## Step 8: 動作確認 & 整形

- [ ] `sail artisan test --filter=UserManagement` 通過
- [ ] `sail artisan test --filter=Admin` 通過
- [ ] `sail bin pint --dirty` で整形
- [ ] ブラウザでの通しシナリオ確認:
  1. admin で `/login` → ダッシュボード → 「ユーザー管理」リンクで `/admin/users` 一覧表示
  2. 検索 / ロール / ステータスフィルタ + ページネーション動作確認
  3. 「+招待」ボタン → モーダルで `coach` / `student` ロールで招待発行 → Mailpit（http://localhost:8025）で InvitationMail 受信確認
  4. 一覧 → 行クリック → 詳細表示（プロフィール / 受講中資格 / UserStatusLog タイムライン / Invitation 履歴）
  5. 「プロフィール編集」モーダル → 名前・bio・avatar を更新 → 成功 Flash
  6. 「ロール変更」モーダル → coach → admin に変更 → 成功 Flash + UserStatusLog に記録されない（status 変化なし）ことを phpMyAdmin で確認
  7. 自分自身のロール変更を試行 → HTTP 403 + 適切なエラーメッセージ
  8. 「再招待」ボタン → 再招待モーダル → 旧 pending Invitation が revoked、新 pending が同 user_id に作成されることを phpMyAdmin で確認
  9. 「招待を取消」ボタン → 確認モーダル → User が cascade で withdrawn + email リネーム、UserStatusLog に `actor=admin / reason="招待取消"` で記録されることを phpMyAdmin で確認
  10. `active` User に対して「退会処理」ボタン → 確認モーダル + reason 入力 → soft delete + email リネーム + UserStatusLog 記録を phpMyAdmin で確認
  11. ステータスフィルタで `withdrawn` 選択 → soft delete 済 User が表示されること、また email リネーム済の値が表示されることを確認
  12. coach / student アカウントで `/admin/users` にアクセス → HTTP 403
- [ ] [[auth]] の `invitations:expire` Command を `sail artisan invitations:expire` で手動実行 → 期限切れ Invitation が `expired` になり、対応 User が `withdrawn` + email リネーム、UserStatusLog に `changed_by_user_id = NULL` で記録されること、また詳細画面で actor 名が「システム」と表示されることを確認（REQ-user-management-073）
- [ ] 動的機能（モーダル開閉 / 確認フロー）は PR 動作確認で動画必須（`tech.md` PR 規約参照）
