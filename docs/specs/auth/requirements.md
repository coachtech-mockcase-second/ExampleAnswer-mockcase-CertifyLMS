# auth 要件定義

> **v3 改修反映**（2026-05-16）:
> - **`UserStatus` enum 4 値化**: `Invited` / `InProgress`（v3 で `Active` から rename）/ **`Graduated`**（v3 新規）/ `Withdrawn`
> - **`users` テーブルに 5 カラム追加宣言**: `plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` は [[plan-management]] Migration で追加（本 Feature では宣言のみ）、**`meeting_url` は本 Feature の Migration で追加**（D4 決定、coach オンボーディング時必須）
> - **`IssueInvitationAction` シグネチャに `Plan $plan` 引数追加**（招待時に Plan 必須、Plan 起点でプラン期間 + 面談回数を初期化）
> - **`OnboardAction` で `status = InProgress`**（旧 `active` から rename）+ **coach の `meeting_url` 必須化**
> - **`EnsureActiveLearning` Middleware 新規定義**（`graduated` ユーザーがプラン機能にアクセスしようとした際にロック、プロフィール / 修了証 DL は許可）
> - `Active` 表記の全リファレンスを `InProgress` に置換

## 概要

Certify LMS の認証基盤 Feature。**招待制サインアップ**（自己サインアップなし）を中核とし、招待URL発行 → 招待メール送信 → 署名付きトークン検証 → オンボーディング（初回パスワード設定 + プロフィール入力 + **coach は `meeting_url` 必須**）→ Fortify ログイン / ログアウト / パスワードリセットまでを担う。

3 ロール（`admin` / `coach` / `student`）の `User` モデルと `Invitation` モデルを所有し、ロール存在確認用の `EnsureUserRole` Middleware + **プラン機能ロック用の `EnsureActiveLearning` Middleware**（v3 新規）を提供する。リソース固有認可は各 Feature の Policy 側に委譲する（本 Feature 内では `Invitation` 自体への認可のみ扱う）。

ユーザー管理画面（一覧 / 詳細 / 編集 / 退会）と `UserStatusLog` モデル / `UserStatusChangeService` の所有は [[user-management]] Feature の責務で、本 Feature はそこから呼び出される **認証フロー素材**（Invitation 発行・受領・User 認証）を提供する。**本 Feature の `User.status` を変更する全 Action は [[user-management]] の `UserStatusChangeService::record` を経由して `UserStatusLog` を記録する**（呼び出し側の責務）。

**Plan 起点の招待フロー（v3）**: admin は招待発行時に Plan を必ず指定し、`IssueInvitationAction` が `User.plan_id` / `plan_started_at` / `plan_expires_at` / `max_meetings` を Plan 値から複写する（[[plan-management]] の Plan マスタを利用）。

## ロールごとのストーリー

- **受講生（student）**: 管理者から招待メールを受け取り、招待URL（有効期限 7 日）から初回パスワード設定 + プロフィール入力でアカウントを有効化する（`status = in_progress`、v3）。以降はメール+パスワードでログイン / ログアウトし、パスワードを忘れた場合はパスワードリセットメールから再設定できる。**Plan 期間満了で `status = graduated` に自動遷移**し、プラン機能（学習・演習・面談・chat 等）はロックされるが、プロフィール / 修了証 PDF DL は引き続き可能（`EnsureActiveLearning` Middleware の挙動、v3）。
- **コーチ（coach）**: 受講生と同じ招待 → オンボーディング → ログインのフローを通る。**オンボーディング時に `meeting_url`（固定面談 URL）が必須入力**（v3、空文字 NG）。担当資格の割当は [[user-management]] / [[certification-management]] 側で行われる前提。
- **管理者（admin）**: 既存 admin が他ユーザーへの招待発行（email + role + **Plan** 指定、v3）を起点とする。本 Feature では `Invitation` の発行 / 検証 / 期限切れマーク / 取消の **基盤 Action** を提供し、admin UI（一覧 / 再招待）は [[user-management]] 側で本 Feature の Action を利用する。初期 admin は seeder で投入。

## 受け入れ基準（EARS形式）

### 機能要件 — User モデルとロール

- **REQ-auth-001**: The system shall ULID 主キー / `email` UNIQUE / `password` **nullable**（invited 状態では未設定）/ `role` enum（`admin` / `coach` / `student`）/ **`status` enum 4 値**（`invited` / **`in_progress`**（v3 で `active` から rename）/ **`graduated`**（v3 新規）/ `withdrawn`）/ `name` **nullable**（invited 状態では未設定）/ `bio` nullable / `avatar_url` nullable / `profile_setup_completed` boolean default false / `email_verified_at` nullable timestamp / `last_login_at` nullable timestamp / **`plan_id` ULID nullable**（[[plan-management]] 所有 Migration で追加、本 Feature では Model リレーション宣言のみ）/ **`plan_started_at` datetime nullable**（同上）/ **`plan_expires_at` datetime nullable**（同上）/ **`max_meetings` unsigned smallint default 0**（同上）/ **`meeting_url` string nullable**（**本 Feature の Migration で追加**、coach のみ必須、D4 決定）/ `remember_token` / timestamps / soft deletes を備えた単一の `users` テーブルを提供する。
- **REQ-auth-002**: The system shall `UserRole` PHP enum（`Admin` / `Coach` / `Student`）を公開し、`label()` メソッドで日本語表示ラベル（`管理者` / `コーチ` / `受講生`）を返す。
- **REQ-auth-003**: The system shall **`UserStatus` PHP enum 4 値**（`Invited` / **`InProgress`**（v3）/ **`Graduated`**（v3 新規）/ `Withdrawn`）を公開し、`label()` メソッドで日本語表示ラベル（`招待中` / `受講中` / `修了` / `退会済`）を返す。状態遷移は `product.md` の state diagram 通り: `[*] → invited`（招待発行）/ `invited → in_progress`（オンボーディング完了、v3）/ `invited → withdrawn`（招待期限切れ・取り消しの cascade）/ **`in_progress → graduated`**（v3 新規、Plan 期間満了で `users:graduate-expired` Schedule Command が自動遷移、[[plan-management]] 所有）/ `in_progress → withdrawn`（admin による退会、v3 で自己退会動線は撤回）。
- **REQ-auth-004**: When `users` テーブルへ行が挿入される際, the system shall `email` UNIQUE 制約を満たす。soft delete された行は **email を `{ulid}@deleted.invalid` 形式へリネーム** することで実 email の再利用（再招待）を可能にする。
- **REQ-auth-005**: The system shall `users` テーブルで `password` カラムを nullable とし、status `invited` の User では NULL、`in_progress` 以降では NOT NULL を運用上保証する（DB レベル制約ではなく Action / Policy で担保）。

### 機能要件 — Invitation モデルと招待URL発行（v3 で Plan 引数追加）

- **REQ-auth-010**: The system shall ULID 主キー / `user_id`（`users.id` への外部キー、対象 User）/ `email` / `role` enum / `invited_by_user_id`（`users.id` への外部キー、発行 admin）/ `expires_at` timestamp / `accepted_at` nullable timestamp / `revoked_at` nullable timestamp / `status` enum（`pending` / `accepted` / `expired` / `revoked`）/ timestamps / soft deletes を備えた `invitations` テーブルを提供する。1 User × N Invitations の関係（再招待・履歴のため）。
- **REQ-auth-011**: When 管理者が招待を発行する際（初回 / `$force=false`）, the system shall **`IssueInvitationAction::__invoke(string $email, UserRole $role, Plan $plan, User $invitedBy, bool $force = false)`**（v3 で `Plan $plan` 引数追加）を呼び、単一トランザクション内で (1) 同 email の `in_progress` / `graduated` User 不在を検査、(2) `users` 行を `status = invited` / `email` / `role` / `password = NULL` / `name = NULL` / **`plan_id = $plan->id`** / **`plan_started_at = NULL`**（オンボーディング時に確定）/ **`plan_expires_at = NULL`**（同上）/ **`max_meetings = $plan->default_meeting_quota`** で INSERT、(3) `invitations` 行を `user_id = users.id` / `status = pending` / `expires_at = now() + 7日`（`config('auth.invitation_expire_days', 7)` で env 設定可）で INSERT、(4) [[user-management]] の `UserStatusChangeService::record($user, UserStatus::Invited, $invitedBy, '新規招待')` を呼ぶ、(5) [[plan-management]] の `UserPlanLog` を `event_type = assigned` で INSERT（呼出は [[plan-management]] の Service へ委譲）、(6) `InvitationMail` を dispatch する。
- **REQ-auth-012**: If すでに `in_progress` または `graduated` の `User` が同じ email で存在する場合, then the system shall ドメイン例外 `EmailAlreadyRegisteredException`（HTTP 409 Conflict）で招待発行を拒否する。
- **REQ-auth-013**: If すでに同じ email で `invited` 状態の User と未期限切れの `pending` Invitation が存在する場合, then the system shall `$force = false` なら `PendingInvitationAlreadyExistsException`（HTTP 409）で拒否し、`$force = true` なら単一トランザクション内で (1) 旧 pending Invitation を `revoked` 化、(2) **User は invited のまま継続**（withdrawn にしない、user_id を再利用）、(3) 新 Invitation を **同じ user_id** に紐付けて INSERT、(4) 新 InvitationMail を dispatch する。
- **REQ-auth-014**: When `Invitation` が作成された際, the system shall `InvitationMail`（Markdown Mailable）を招待先 email へ送信し、本文に `GET /onboarding/{invitation}` 宛の **署名付きオンボーディング URL** を含める。
- **REQ-auth-015**: The system shall オンボーディング URL を `URL::signedRoute('onboarding.show', ['invitation' => $invitation, 'expires' => $invitation->expires_at->timestamp])` で生成し、改ざんがあった場合は署名検証で拒否する。

### 機能要件 — 招待トークン検証とオンボーディング（v3 で coach の meeting_url 必須化）

- **REQ-auth-020**: When ユーザーが有効な署名付き URL で `GET /onboarding/{invitation}` にアクセスした際, the system shall (1) URL 署名 (2) `Invitation.status === pending` (3) `Invitation.expires_at > now()` (4) `Invitation.user.status === invited` を検証し、いずれかが不正なら `auth/invitation-invalid` ビューを日本語エラーメッセージ付きで描画する。
- **REQ-auth-021**: When REQ-auth-020 の検証が成功した際, the system shall オンボーディングフォーム（`auth/onboarding`）を表示し、`Invitation.email` と `Invitation.role` を読み取り専用で表示する。入力フィールドは `name`（必須）/ `bio`（任意）/ `password`（必須・8 文字以上）/ `password_confirmation`（必須・`password` と一致）+ **`meeting_url`**（v3、role=coach の場合のみ表示、必須・URL 形式・最大 500 文字）。
- **REQ-auth-022**: When `POST /onboarding/{invitation}` が有効な入力で送信された際, the system shall **`OnboardAction::__invoke(Invitation $invitation, array $validated)`** を呼び、単一トランザクション内で以下を行う: (1) **既存 invited User を UPDATE** — **`status = in_progress`**（v3、旧 `active`）/ `password = Hash::make(input.password)` / `name = input.name` / `bio = input.bio` / `email_verified_at = now()` / `profile_setup_completed = true` / **`plan_started_at = now()`** / **`plan_expires_at = now()->addDays($user->plan->duration_days)`**（v3、Plan 期間反映） / role=coach の場合 **`meeting_url = input.meeting_url`**（v3、必須）、(2) `Invitation.status = accepted` / `accepted_at = now()` を UPDATE、(3) [[user-management]] の `UserStatusChangeService::record($user, UserStatus::InProgress, $user, 'オンボーディング完了')` を呼ぶ、(4) [[meeting-quota]] の `MeetingQuotaTransaction` を `type = granted_initial` / `amount = $user->plan->default_meeting_quota` で INSERT、(5) `Auth::login($invitation->user)` で自動ログイン、(6) `/dashboard` へリダイレクト。
- **REQ-auth-023**: If `POST /onboarding/{invitation}` が (a) `pending` 以外の Invitation、(b) `expires_at <= now()` の Invitation、(c) `Invitation.user.status !== invited` の Invitation に対して呼ばれた場合, then the system shall `InvalidInvitationTokenException`（HTTP 410 Gone）で拒否する。
- **REQ-auth-024**: When オンボーディングフォームの `password` が 8 文字未満で送信された際, the system shall FormRequest バリデーションエラー（日本語メッセージ）で拒否する。
- **REQ-auth-025**: When role=coach のオンボーディングで `meeting_url` が空文字 / 不正な URL 形式で送信された場合, then the system shall FormRequest バリデーションエラー（日本語メッセージ「ミーティング URL を正しい形式で入力してください。」）で拒否する（v3 新規）。

### 機能要件 — ログイン / ログアウト / パスワードリセット（Fortify）

- **REQ-auth-030**: The system shall Laravel Fortify を `views` 有効で使用し、`Fortify::loginView()` / `Fortify::requestPasswordResetLinkView()` / `Fortify::resetPasswordView()` で Blade テンプレート `auth/login` / `auth/forgot-password` / `auth/reset-password` を登録する。
- **REQ-auth-031**: When ユーザーが正しい email + password かつ **`User.status IN (in_progress, graduated)`**（v3、graduated もログインは許可、ただしプラン機能は `EnsureActiveLearning` でロック）でログインフォームを送信した際, the system shall 認証セッションを開始し、`User.last_login_at = now()` を更新し、`/dashboard` へリダイレクトする。
- **REQ-auth-032**: If `User.status IN (invited, withdrawn)` の credentials でログインフォームが送信された場合, then the system shall 「認証情報が正しくありません」という共通エラーで拒否する（ステータスを呼び出し側に漏洩しない）。
- **REQ-auth-033**: When ユーザーが「ログアウト」（POST `/logout`）を実行した際, the system shall `Auth::logout()` + `session()->invalidate()` + `session()->regenerateToken()` でセッションを破棄し、`/login` へリダイレクトする。
- **REQ-auth-034**: When 既存の `in_progress` / `graduated` ユーザーの email でパスワード忘れフォームが送信された際, the system shall Laravel 標準 `ResetPassword` Notification を送信する。
- **REQ-auth-035**: The system shall アカウント列挙攻撃を防ぐため、email の存在有無に関わらず「パスワードリセットメールを送信しました」という同一メッセージを返す。
- **REQ-auth-036**: When 有効なトークン + 8 文字以上のパスワード（確認と一致）でパスワードリセットフォームが送信された際, the system shall `Hash::make` でパスワードを更新し、トークンを失効させ、`/login` へリダイレクトする。

### 機能要件 — ロール制御 Middleware + EnsureActiveLearning Middleware（v3 新規）

- **REQ-auth-040**: The system shall `role` という route middleware alias を `EnsureUserRole` クラスにマッピングし、1 個以上のロール名（例: `role:admin` / `role:admin,coach`）を受け取って `auth()->user()->role` が許可リストに含まれなければ HTTP 403 で abort する。
- **REQ-auth-041**: The `EnsureUserRole` middleware shall ルートグループ内で `auth` middleware の **後段** に適用される。
- **REQ-auth-042**: The system shall リソースごとの認可（例: 「コーチは担当資格のみ閲覧可」）を `EnsureUserRole` に持ち込まず、各 Feature の Policy 側で実装する。
- **REQ-auth-043**(v3 新規): The system shall **`EnsureActiveLearning` Middleware**（class `App\Http\Middleware\EnsureActiveLearning`）を提供し、`auth()->user()->status !== UserStatus::InProgress` の場合 HTTP 403 で abort する。`graduated` ユーザーがプラン機能（learning / quiz-answering / mock-exam / mentoring / chat / qa-board / ai-chat 等）にアクセスしようとした際にロックする責務を持つ。
- **REQ-auth-044**(v3 新規): The `EnsureActiveLearning` Middleware shall **以下のルートでは適用しない**: (a) `/settings/profile` / `/settings/password` / `/settings/avatar`（プロフィール管理は graduated でも許可、product.md L482 と整合）、(b) `/certificates/{certificate}/download`（修了証 PDF DL は graduated でも永続可能）、(c) `/notifications` / `/notifications/dropdown` / `/notifications/markAsRead`（通知一覧は graduated でも閲覧可）。
- **REQ-auth-045**(v3 新規): The system shall `EnsureActiveLearning` が 403 を返す際、graduated ユーザーには「プラン期間が満了しました。プラン機能はご利用いただけません。プロフィール / 修了証は引き続きアクセス可能です。」の日本語メッセージを表示する。

### 機能要件 — 招待の期限切れ自動マーク（Schedule Command）と cascade

- **REQ-auth-050**: The system shall Artisan コマンド `invitations:expire` を提供し、`status === pending AND expires_at <= now()` の Invitation 行を **単一トランザクション内で** (1) `Invitation.status = expired`、(2) 紐付く `User.status = invited` の User を `withdrawn` に遷移 + soft delete + email を `{ulid}@deleted.invalid` 形式へリネーム、(3) [[user-management]] の `UserStatusChangeService::record($user, UserStatus::Withdrawn, null, '招待期限切れ')` を呼ぶ、と更新する。
- **REQ-auth-051**: The system shall `app/Console/Kernel.php` で `invitations:expire` を毎日 00:30 に実行するようスケジュールする。
- **REQ-auth-052**: The system shall `RevokeInvitationAction` を提供し、管理者が `pending` Invitation を手動で取消する。シグネチャは `__invoke(Invitation $invitation, ?User $admin = null, bool $cascadeWithdrawUser = true)`。

### 機能要件 — 退会（cascade 整合性、v3 で自己退会動線撤回）

- **REQ-auth-070**: When User が退会する際（`in_progress` または `graduated` → `withdrawn`、別 Feature の責務だが本 Feature が User モデルの不変条件として保証）, the system shall 単一トランザクション内で (1) `User.status = withdrawn`、(2) `deleted_at = now()`、(3) `email` を `{ulid}@deleted.invalid` 形式へリネーム、(4) 呼出元 Feature の責任で [[user-management]] の `UserStatusChangeService::record` を呼ぶ、を行う。**v3 で自己退会動線は撤回**（[[settings-profile]] / [[user-management]] 参照、admin 経由のみ）。
- **REQ-auth-071**: The system shall withdrawn User に紐付く `pending` Invitation が存在する場合、Invitation も `revoked` に同期する整合性を保つ。

### 機能要件 — 認可（Policy）

- **REQ-auth-060**: The system shall `InvitationPolicy::create` を `User.role === Admin` の場合のみ `true` を返すよう実装する。
- **REQ-auth-061**: The system shall `InvitationPolicy::viewAny` および `InvitationPolicy::revoke` を `User.role === Admin` の場合のみ `true` を返すよう実装する。
- **REQ-auth-062**: The system shall `GET /onboarding/{invitation}` および `POST /onboarding/{invitation}` を未認証アクセス可能とする（署名付き URL が認可の担い手）。

### 非機能要件

- **NFR-auth-001**: The system shall すべてのパスワードハッシュに Laravel 標準の `bcrypt` ドライバーを使用する。
- **NFR-auth-002**: The system shall ログインフォームを Fortify 標準のスロットルで保護する（デフォルト IP+email あたり 5 回 / 分）。
- **NFR-auth-003**: The system shall すべての認証系 Blade ビューを `layouts.guest` 上で描画する。
- **NFR-auth-004**: The system shall すべての日本語エラーメッセージを `lang/ja/auth.php` で定義する。
- **NFR-auth-005**: The system shall 状態変更を伴うすべての Action（`OnboardAction` / `IssueInvitationAction` / `RevokeInvitationAction` / `ExpireInvitationsAction`）を `DB::transaction()` で囲む。

## スコープ外

- ユーザー一覧 / 詳細 / 編集画面（[[user-management]]）
- `UserStatusLog` の蓄積（[[user-management]]）
- ロール変更（admin → coach 等、[[user-management]]）
- 自己プロフィール編集 / アバター画像アップロード（[[settings-profile]]）
- **自己退会動線**（v3 で撤回、[[user-management]] / [[settings-profile]] 参照）
- 自己サインアップ / SNS連携 / SSO
- 2FA / IP制限 / ログイン履歴の詳細管理
- API 認証基盤
- **Plan マスタ CRUD / `User.plan_*` カラムの Migration**（[[plan-management]] 所有、本 Feature では Model リレーション宣言のみ）
- **`users.status = graduated` への自動遷移ロジック**（[[plan-management]] の `users:graduate-expired` Schedule Command が所有）

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[user-management]]: `IssueInvitationAction` / `RevokeInvitationAction` を admin UI から呼ぶ（v3 で `Plan $plan` 引数を渡す）
  - [[settings-profile]]: 自己プロフィール編集で `User` モデルを参照
  - 全 Feature: `auth` middleware / `role:*` middleware / **`EnsureActiveLearning` middleware**（v3 新規）でルートを保護
- **依存先**（本 Feature が前提とする）:
  - [[plan-management]]: `Plan` Model（招待時の Plan 指定）/ `UserPlanLog` Migration / `users.plan_*` カラム Migration
  - [[meeting-quota]]: `MeetingQuotaTransaction` モデル（オンボーディング時の初期付与）
