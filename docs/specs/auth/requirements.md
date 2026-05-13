# auth 要件定義

## 概要

Certify LMS の認証基盤 Feature。**招待制サインアップ**（自己サインアップなし）を中核とし、招待URL発行 → 招待メール送信 → 署名付きトークン検証 → オンボーディング（初回パスワード設定 + プロフィール入力）→ Fortify ログイン / ログアウト / パスワードリセットまでを担う。

3ロール（`admin` / `coach` / `student`）の `User` モデルと `Invitation` モデルを所有し、ロール存在確認用の `EnsureUserRole` Middleware を提供する。リソース固有認可は各 Feature の Policy 側に委譲する（本 Feature 内では `Invitation` 自体への認可のみ扱う）。

ユーザー管理画面（一覧 / 詳細 / 編集 / 退会）と `UserStatusLog` の蓄積は [[user-management]] Feature の責務で、本 Feature はそこから呼び出される **認証フロー素材**（Invitation 発行・受領・User 認証）を提供する。

## ロールごとのストーリー

- **受講生（student）**: 管理者から招待メールを受け取り、招待URL（有効期限 7日）から初回パスワード設定 + プロフィール入力でアカウントを有効化する。以降はメール+パスワードでログイン / ログアウトし、パスワードを忘れた場合はパスワードリセットメールから再設定できる。
- **コーチ（coach）**: 受講生と同じ招待 → オンボーディング → ログインのフローを通る。担当資格の割当は [[user-management]] / [[certification-management]] 側で行われる前提。
- **管理者（admin）**: 既存 admin が他ユーザーへの招待発行（email + role 指定）を起点とする。本 Feature では `Invitation` の発行 / 検証 / 期限切れマーク / 取消の **基盤 Action** を提供し、admin UI（一覧 / 再招待）は [[user-management]] 側で本 Feature の Action を利用する。初期 admin は seeder で投入。

## 受け入れ基準（EARS形式）

### 機能要件 — User モデルとロール

- **REQ-auth-001**: The system shall ULID 主キー / `email` UNIQUE / `password` **nullable**（invited 状態では未設定）/ `role` enum（`admin` / `coach` / `student`）/ `status` enum（`invited` / `active` / `withdrawn`）/ `name` **nullable**（invited 状態では未設定）/ `bio` nullable / `avatar_url` nullable / `profile_setup_completed` boolean default false / `email_verified_at` nullable timestamp / `last_login_at` nullable timestamp / `remember_token` / timestamps / soft deletes を備えた単一の `users` テーブルを提供する。
- **REQ-auth-002**: The system shall `UserRole` PHP enum（`Admin` / `Coach` / `Student`）を公開し、`label()` メソッドで日本語表示ラベル（`管理者` / `コーチ` / `受講生`）を返す。
- **REQ-auth-003**: The system shall `UserStatus` PHP enum（`Invited` / `Active` / `Withdrawn`）を公開し、`label()` メソッドで日本語表示ラベル（`招待中` / `アクティブ` / `退会済`）を返す。状態遷移は `product.md` の state diagram 通り: `[*] → invited`（招待発行）/ `invited → active`（オンボーディング完了）/ `invited → withdrawn`（招待期限切れ・取り消しの cascade）/ `active → withdrawn`（自己退会）。
- **REQ-auth-004**: When `users` テーブルへ行が挿入される際, the system shall `email` UNIQUE 制約を満たす。soft delete された行は **email を `{ulid}@deleted.invalid` 形式へリネーム** することで実 email の再利用（再招待）を可能にする。リネームは soft delete を行う Action の責務（`OnWithdrawUserAction` 等）。
- **REQ-auth-005**: The system shall `users` テーブルで `password` カラムを nullable とし、status `invited` の User では NULL、`active` 以降では NOT NULL を運用上保証する（DB レベル制約ではなく Action / Policy で担保）。

### 機能要件 — Invitation モデルと招待URL発行

- **REQ-auth-010**: The system shall ULID 主キー / `user_id`（`users.id` への外部キー、対象 User）/ `email` / `role` enum / `invited_by_user_id`（`users.id` への外部キー、発行 admin）/ `expires_at` timestamp / `accepted_at` nullable timestamp / `revoked_at` nullable timestamp / `status` enum（`pending` / `accepted` / `expired` / `revoked`）/ timestamps / soft deletes を備えた `invitations` テーブルを提供する。1 User × N Invitations の関係（再招待・履歴のため）。
- **REQ-auth-011**: When 管理者が招待を発行する際（初回 / `$force=false`）, the system shall 単一トランザクション内で (1) 同 email の active User 不在を検査、(2) `users` 行を `status = invited` / `email` / `role` / `password = NULL` / `name = NULL` で INSERT、(3) `invitations` 行を `user_id = users.id` / `status = pending` / `expires_at = now() + 7日`（`config('auth.invitation_expire_days', 7)` で env 設定可）で INSERT、(4) `InvitationMail` を dispatch する。
- **REQ-auth-012**: If すでに `active` の `User` が同じ email で存在する場合, then the system shall ドメイン例外 `EmailAlreadyRegisteredException`（HTTP 409 Conflict）で招待発行を拒否する。
- **REQ-auth-013**: If すでに同じ email で `invited` 状態の User と未期限切れの `pending` Invitation が存在する場合, then the system shall `$force = false` なら `PendingInvitationAlreadyExistsException`（HTTP 409）で拒否し、`$force = true` なら単一トランザクション内で (1) 旧 pending Invitation を `revoked` 化、(2) **User は invited のまま継続**（withdrawn にしない、user_id を再利用）、(3) 新 Invitation を **同じ user_id** に紐付けて INSERT、(4) 新 InvitationMail を dispatch する。
- **REQ-auth-014**: When `Invitation` が作成された際, the system shall `InvitationMail`（Markdown Mailable）を招待先 email へ送信し、本文に `GET /onboarding/{invitation}` 宛の **署名付きオンボーディング URL**（`expires` クエリは `expires_at` と一致）を含める。
- **REQ-auth-015**: The system shall オンボーディング URL を `URL::signedRoute('onboarding.show', ['invitation' => $invitation, 'expires' => $invitation->expires_at->timestamp])` で生成し、パス / クエリの改ざんがあった場合は署名検証で拒否する。

### 機能要件 — 招待トークン検証とオンボーディング

- **REQ-auth-020**: When ユーザーが有効な署名付き URL で `GET /onboarding/{invitation}` にアクセスした際, the system shall (1) URL 署名 (2) `Invitation.status === pending` (3) `Invitation.expires_at > now()` (4) `Invitation.user.status === invited` を検証し、いずれかが不正なら 403/404 を投げずに `auth/invitation-invalid` ビューを日本語エラーメッセージ付きで描画する。
- **REQ-auth-021**: When REQ-auth-020 の検証が成功した際, the system shall オンボーディングフォーム（`auth/onboarding`）を表示し、`Invitation.email` と `Invitation.role` を読み取り専用で表示する。入力フィールドは `name`（必須）/ `bio`（任意）/ `password`（必須・8文字以上）/ `password_confirmation`（必須・`password` と一致）。
- **REQ-auth-022**: When `POST /onboarding/{invitation}` が有効な入力で送信された際, the system shall 単一トランザクション内で以下を行う: (1) **既存 invited User を UPDATE** — `status = active` / `password = Hash::make(input.password)` / `name = input.name` / `bio = input.bio` / `email_verified_at = now()` / `profile_setup_completed = true`、(2) `Invitation.status = accepted` / `accepted_at = now()` を UPDATE、(3) `Auth::login($invitation->user)` で自動ログイン、(4) `/dashboard` へリダイレクト。**User 行の新規 INSERT は行わない**（IssueInvitationAction で既に INSERT 済み）。
- **REQ-auth-023**: If `POST /onboarding/{invitation}` が (a) `pending` 以外の Invitation、(b) `expires_at <= now()` の Invitation、(c) `Invitation.user.status !== invited` の Invitation に対して呼ばれた場合, then the system shall `InvalidInvitationTokenException`（HTTP 410 Gone、日本語メッセージ）で拒否する。
- **REQ-auth-024**: When オンボーディングフォームの `password` が 8文字未満で送信された際, the system shall FormRequest バリデーションエラー（日本語メッセージ）で拒否する。

### 機能要件 — ログイン / ログアウト / パスワードリセット（Fortify）

- **REQ-auth-030**: The system shall Laravel Fortify を `views` 有効で使用し、`Fortify::loginView()` / `Fortify::requestPasswordResetLinkView()` / `Fortify::resetPasswordView()` で Blade テンプレート `auth/login` / `auth/forgot-password` / `auth/reset-password` を登録する。
- **REQ-auth-031**: When ユーザーが正しい email + password かつ `User.status === active` でログインフォームを送信した際, the system shall 認証セッションを開始し、`User.last_login_at = now()` を更新し、`/dashboard` へリダイレクトする。
- **REQ-auth-032**: If `User.status !== active`（`invited` または `withdrawn`）の credentials でログインフォームが送信された場合, then the system shall 「認証情報が正しくありません」という共通エラーで拒否する（ステータスを呼び出し側に漏洩しない）。
- **REQ-auth-033**: When ユーザーが「ログアウト」（POST `/logout`）を実行した際, the system shall `Auth::logout()` + `session()->invalidate()` + `session()->regenerateToken()` でセッションを破棄し、`/login` へリダイレクトする。
- **REQ-auth-034**: When 既存の `active` ユーザーの email でパスワード忘れフォームが送信された際, the system shall Laravel 標準 `ResetPassword` Notification を送信し、本文に署名付きリセット URL を含める。
- **REQ-auth-035**: The system shall アカウント列挙攻撃を防ぐため、email の存在有無に関わらず「パスワードリセットメールを送信しました」という同一メッセージを返す。
- **REQ-auth-036**: When 有効なトークン + 8文字以上のパスワード（確認と一致）でパスワードリセットフォームが送信された際, the system shall `Hash::make` でパスワードを更新し、トークンを失効させ、`/login` へリダイレクトする（成功メッセージ表示）。

### 機能要件 — ロール制御 Middleware

- **REQ-auth-040**: The system shall `role` という route middleware alias を `EnsureUserRole` クラスにマッピングし、1個以上のロール名（例: `role:admin` / `role:admin,coach`）を受け取って `auth()->user()->role` が許可リストに含まれなければ HTTP 403 で abort する。
- **REQ-auth-041**: The `EnsureUserRole` middleware shall ルートグループ内で `auth` middleware の **後段** に適用される（`auth()` が null を返す場合は `auth` 側で先にリダイレクト済み）。
- **REQ-auth-042**: The system shall リソースごとの認可（例: 「コーチは担当資格のみ閲覧可」）を `EnsureUserRole` に持ち込まず、各 Feature の Policy 側で実装する。

### 機能要件 — 招待の期限切れ自動マーク（Schedule Command）と cascade

- **REQ-auth-050**: The system shall Artisan コマンド `invitations:expire` を提供し、`status === pending AND expires_at <= now()` の Invitation 行を **単一トランザクション内で** (1) `Invitation.status = expired`、(2) 紐付く `User.status = invited` の User を `withdrawn` に遷移 + soft delete + email を `{ulid}@deleted.invalid` 形式へリネーム、と更新する（`product.md` の state diagram `invited → withdrawn: 招待期限切れ` に対応）。
- **REQ-auth-051**: The system shall `app/Console/Kernel.php` で `invitations:expire` を毎日 00:30 に実行するようスケジュールする（`->command('invitations:expire')->dailyAt('00:30')`）。
- **REQ-auth-052**: The system shall `RevokeInvitationAction` を提供し、管理者が `pending` Invitation を手動で取消する。シグネチャは `__invoke(Invitation $invitation, bool $cascadeWithdrawUser = true)`:
  - `$cascadeWithdrawUser = true`（admin の完全取消、デフォルト）: 単一トランザクション内で (1) `Invitation.status = revoked` / `revoked_at = now()`、(2) 紐付く User を `invited → withdrawn` + soft delete + email リネーム（`product.md` の `invited → withdrawn: 取り消し` に対応）
  - `$cascadeWithdrawUser = false`（`IssueInvitationAction` の `$force=true` で内部利用）: Invitation のみ revoke、User は invited のまま継続

### 機能要件 — 退会（cascade 整合性）

- **REQ-auth-070**: When User が能動退会する際（active → withdrawn、別 Feature の責務だが本 Feature が User モデルの不変条件として保証）, the system shall 単一トランザクション内で (1) `User.status = withdrawn`、(2) `deleted_at = now()`（soft delete）、(3) `email` を `{ulid}@deleted.invalid` 形式へリネーム、を行う。
- **REQ-auth-071**: The system shall withdrawn User に紐付く `pending` Invitation が存在する場合（理論上は invited→withdrawn cascade で発生しないが防衛的）、Invitation も `revoked` に同期する整合性を保つ（DB レベル制約ではなく Action / Service で担保）。

### 機能要件 — 認可（Policy）

- **REQ-auth-060**: The system shall `InvitationPolicy::create` を `User.role === Admin` の場合のみ `true` を返すよう実装する。
- **REQ-auth-061**: The system shall `InvitationPolicy::viewAny` および `InvitationPolicy::revoke` を `User.role === Admin` の場合のみ `true` を返すよう実装する。
- **REQ-auth-062**: The system shall `GET /onboarding/{invitation}` および `POST /onboarding/{invitation}` を未認証アクセス可能とする（署名付き URL が認可の担い手、`auth` middleware は適用しない）。

### 非機能要件

- **NFR-auth-001**: The system shall すべてのパスワードハッシュに Laravel 標準の `bcrypt` ドライバーを使用する（`config('hashing.driver') === 'bcrypt'`）。
- **NFR-auth-002**: The system shall ログインフォームをブルートフォース攻撃から守るため、Fortify 標準のスロットル（`Fortify::rateLimit('login')`、デフォルト IP+email あたり 5回/分）を有効にする。
- **NFR-auth-003**: The system shall すべての認証系 Blade ビューを Tailwind ベースの `layouts.guest` 上で描画し、ダッシュボードのナビ等は表示しない。
- **NFR-auth-004**: The system shall すべての日本語エラーメッセージを `lang/ja/auth.php` およびドメイン例外のコンストラクタで定義し、ビュー内のマジック文字列は禁止する。
- **NFR-auth-005**: The system shall 状態変更を伴うすべての Action（`OnboardAction` / `IssueInvitationAction` / `RevokeInvitationAction` / `ExpireInvitationsAction`）を `DB::transaction()` で囲む。

## スコープ外

- ユーザー一覧 / 詳細 / 編集画面（[[user-management]]）
- `UserStatusLog` の蓄積と admin による手動 `invited → withdrawn` 遷移（[[user-management]]）
- ロール変更（admin → coach 等、[[user-management]]）
- 自己プロフィール編集 / 自己退会動線 / アバター画像アップロード（[[settings-profile]]）
- 自己サインアップ / SNS連携 / SSO（product.md スコープ外）
- 2FA / IP制限 / ログイン履歴の詳細管理（product.md スコープ外）
- Sanctum トークンの発行 / 失効（[[public-api]]）
- API Token のスコープ管理（[[public-api]]）

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[user-management]]: `IssueInvitationAction` / `RevokeInvitationAction` を admin UI から呼ぶ
  - [[settings-profile]]: 自己プロフィール編集 / 自己退会で `User` モデルを参照
  - 全 Feature: `auth` middleware と `role:*` middleware でルートを保護
- **依存先**（本 Feature が前提とする）: なし（基盤 Feature）
