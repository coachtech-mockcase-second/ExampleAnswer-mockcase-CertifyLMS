# user-management 要件定義

## 概要

Certify LMS の管理者向けユーザー管理 Feature。**admin が他ユーザーのライフサイクル全体を運用する画面**を提供する。
具体的には: ユーザー一覧（フィルタ・検索 + 招待動線）→ 詳細画面（プロフィール / 受講中資格概要 / ステータス変更履歴 / 招待履歴）→ プロフィール編集 / ロール変更 / 強制退会、および 詳細からの再招待・取消フロー。

[[auth]] が提供する `User` / `Invitation` モデルと `IssueInvitationAction` / `RevokeInvitationAction` の上に **admin 向け UI と HTTP 層** を構築する。
さらに、ユーザー受講状態の遷移履歴を追跡する `UserStatusLog` モデルと、**全 Feature 共通のステータス変更記録エントリポイント** である `UserStatusChangeService` を本 Feature で新設する（[[auth]] / [[settings-profile]] のステータス変更系 Action からも呼び出される）。

## ロールごとのストーリー

- **管理者（admin）**: ダッシュボードから「ユーザー管理」へ遷移し、ユーザー一覧でロール / ステータス / キーワード検索でフィルタしてユーザーを把握する。一覧から「+招待」ボタンで新規招待を発行、行クリックで詳細へ遷移し、プロフィール編集・ロール変更・強制退会・再招待・取消の各操作を行う。各ユーザーの受講状態変化は履歴として時系列で閲覧できる。
- **コーチ（coach）/ 受講生（student）**: 本 Feature の URL（`/admin/users/*` および `/admin/invitations/*`）にアクセスすると HTTP 403。自分自身のプロフィール編集 / 自己退会は [[settings-profile]] で行う。

## 受け入れ基準（EARS形式）

### 機能要件 — ユーザー一覧

- **REQ-user-management-001**: The system shall `GET /admin/users` で User 一覧をテーブル形式（氏名 / メール / ロール badge / ステータス badge / 作成日 / 最終ログイン日時）で表示する。並び順は (1) status の優先度（`active` を上、`invited` を中、`withdrawn` を下）→ (2) 同 status 内では `created_at` 降順。
- **REQ-user-management-002**: When admin が一覧画面で検索キーワードを入力した際, the system shall `users.name` または `users.email` の部分一致（LIKE）でフィルタリングする。空文字列の場合はフィルタしない。
- **REQ-user-management-003**: When admin がロールフィルタを選択した際, the system shall `users.role` が指定値（`admin` / `coach` / `student`）と一致する User のみを表示する。空の場合は全ロール表示。
- **REQ-user-management-004**: When admin がステータスフィルタを選択した際, the system shall `users.status` が指定値（`invited` / `active` / `withdrawn`）と一致する User のみを表示する。空の場合は `withdrawn` を **デフォルト除外** し、`active` + `invited` のみ表示する。
- **REQ-user-management-005**: The system shall 一覧を 20 件 / ページでサーバサイドページネーションし、検索・フィルタ条件をクエリストリング（`?keyword=...&role=...&status=...&page=...`）で保持する。
- **REQ-user-management-006**: When admin が一覧の行をクリックした際, the system shall `GET /admin/users/{user}` で詳細ページへ遷移する。
- **REQ-user-management-007**: The system shall 一覧画面に「+招待」ボタンを配置し、クリックで招待フォーム（モーダル）を開く。
- **REQ-user-management-008**: The system shall `withdrawn` の User を表示する際、`status` フィルタで明示的に `withdrawn` を選択された場合に限り `users` モデルの `withTrashed()` スコープを適用し、soft delete 済みレコードを含めて取得する。

### 機能要件 — ユーザー招待・再招待・取消

- **REQ-user-management-010**: The system shall 招待フォームで `email`（必須 / メール形式）と `role`（`coach` または `student` のみ、`admin` は選択肢に含めない）を入力するフォームを表示する。
- **REQ-user-management-011**: When admin が招待フォームから `POST /admin/invitations` を送信した際, the system shall [[auth]] の `IssueInvitationAction($email, $role, $admin, force: false)` を呼ぶ。Action 内で User INSERT + Invitation INSERT + InvitationMail dispatch + `UserStatusChangeService::record($user, UserStatus::Invited, $admin, '新規招待')` が **同一トランザクション内** で実行される。
- **REQ-user-management-012**: When admin がユーザー詳細画面で「再招待」ボタンを押下した際, the system shall [[auth]] の `IssueInvitationAction($user->email, $user->role, $admin, force: true)` を呼ぶ。同 user_id の旧 pending Invitation は revoke され、User は invited のまま、新 Invitation が同 user_id に紐付いて発行される。`UserStatusLog` は **新規挿入しない**（status 変化なし）。
- **REQ-user-management-013**: When admin がユーザー詳細画面で「招待を取消」ボタンを押下した際, the system shall [[auth]] の `RevokeInvitationAction($invitation, $admin, cascadeWithdrawUser: true)` を呼ぶ。Invitation は revoked、User は invited → withdrawn + soft delete + email リネーム、`UserStatusChangeService::record($user, UserStatus::Withdrawn, $admin, '招待取消')` も同一トランザクション内で記録される。
- **REQ-user-management-014**: If 対象 Invitation が `pending` 以外（既に `accepted` / `expired` / `revoked`）の場合, then the system shall [[auth]] の `InvitationNotPendingException`（HTTP 409）でそのまま伝播する。
- **REQ-user-management-015**: The system shall 再招待・取消の確認ステップ（モーダル）を表示し、誤操作を防止する。

### 機能要件 — ユーザー詳細

- **REQ-user-management-020**: The system shall `GET /admin/users/{user}` で対象 User のプロフィール（`name` / `email` / `role` badge / `status` badge / `bio` / `avatar_url` / `created_at` / `last_login_at`）を表示する。`withdrawn` の User も `withTrashed` で取得し、リネーム済 email を表示する。
- **REQ-user-management-021**: The system shall 同画面に対象 User の `Enrollment` 概要（資格名 / `status` / `current_term` / `exam_date`）を最大 10 件まで `created_at` 降順で一覧表示し、詳細リンクで [[enrollment]] / [[dashboard]] へ遷移可能とする。受講中資格が無い場合は「受講中の資格はありません」と表示する。
- **REQ-user-management-022**: The system shall 同画面に対象 User の `UserStatusLog` 履歴を `changed_at` 降順で全件表示する（`status` badge + `changed_at` + 変更者名（NULL なら「システム」） + `changed_reason`）。
- **REQ-user-management-023**: The system shall 同画面に対象 User の `Invitation` 履歴を `created_at` 降順で表示する（`status` badge + `expires_at` + `accepted_at` / `revoked_at` の補助情報）。
- **REQ-user-management-024**: If 指定 ID の User が存在しない場合, then the system shall HTTP 404 を返す。`withdrawn` の User は `withTrashed` で取得し詳細表示する（admin にとっては「過去ユーザーの監査」のため）。

### 機能要件 — プロフィール編集（admin → 他者）

- **REQ-user-management-030**: When admin が `PATCH /admin/users/{user}` をプロフィール編集フォームから送信した際, the system shall `name`（必須 / 1-50 文字）/ `email`（必須 / メール形式 / 自分以外で UNIQUE）/ `bio`（任意 / 最大 1000 文字）/ `avatar_url`（任意 / URL 形式 / 最大 500 文字）の検証後、`users` 行を更新する。
- **REQ-user-management-031**: If 同 `email` を持つ別 User（active / invited）が存在する場合, then the system shall FormRequest バリデーションエラー（日本語メッセージ）で拒否する。soft delete 済みの User（email は `{ulid}@deleted.invalid` 形式にリネーム済み）は重複対象外。
- **REQ-user-management-032**: When プロフィール編集が成功した際, the system shall 詳細ページにリダイレクトし、Flash メッセージで成功を通知する。`UserStatusLog` への記録は行わない（status 変化なし）。
- **REQ-user-management-033**: If 対象 User が `withdrawn` の場合, then the system shall `UserAlreadyWithdrawnException`（HTTP 409）でプロフィール編集を拒否する。

### 機能要件 — ロール変更

- **REQ-user-management-040**: When admin が `PATCH /admin/users/{user}/role` をロール変更フォームから送信した際, the system shall `role`（`admin` / `coach` / `student` のいずれか必須）の検証後、`users.role` を更新する。
- **REQ-user-management-041**: If admin が **自分自身** のロールを変更しようとした場合, then the system shall `SelfRoleChangeForbiddenException`（HTTP 403）で拒否する。
- **REQ-user-management-042**: If 対象 User が `withdrawn` の場合, then the system shall `UserAlreadyWithdrawnException`（HTTP 409）でロール変更を拒否する。
- **REQ-user-management-043**: The system shall ロール変更時に `UserStatusLog` への記録を行わない（本 Feature ではロール変更履歴を保持しない、`UserStatusLog` は status 専用）。

### 機能要件 — admin による強制退会

- **REQ-user-management-050**: When admin が `POST /admin/users/{user}/withdraw` を退会確認モーダルから送信した際, the system shall 単一トランザクション内で (1) `users.email` を `{ulid}@deleted.invalid` 形式へリネーム、(2) `users.status = withdrawn` を更新、(3) `users.deleted_at = now()`（soft delete）、(4) `UserStatusChangeService::record($user, UserStatus::Withdrawn, $admin, $reason)` を実行する。
- **REQ-user-management-051**: If admin が **自分自身** を退会させようとした場合, then the system shall `SelfWithdrawForbiddenException`（HTTP 403）で拒否する。
- **REQ-user-management-052**: If 対象 User が既に `withdrawn` の場合, then the system shall `UserAlreadyWithdrawnException`（HTTP 409）で拒否する。
- **REQ-user-management-053**: If 対象 User が `invited` 状態の場合, then the system shall `WithdrawAction` ではなく **招待取消ルート**（`DELETE /admin/invitations/{invitation}`）を案内し、HTTP 422 で拒否する。`WithdrawAction` は **active User のみ** を対象とする。
- **REQ-user-management-054**: The system shall 退会理由（`reason`、任意 / 最大 200 文字）を `UserStatusLog.changed_reason` に保存する。

### 機能要件 — UserStatusLog（履歴モデル）

- **REQ-user-management-060**: The system shall ULID 主キー / `user_id`（`users.id` FK、`cascadeOnDelete` なし — 履歴は User の物理削除で消えない設計だが、Certify は soft delete のみで物理削除を行わないため実害なし）/ `changed_by_user_id`（`users.id` FK、nullable、NULL は **システム自動変更** を意味する）/ `status`（`UserStatus` Enum string cast）/ `changed_at` datetime / `changed_reason` string nullable（最大 200 文字）/ timestamps を備えた `user_status_logs` テーブルを提供する。**soft delete は採用しない**（履歴は不可逆）。
- **REQ-user-management-061**: The system shall `user_status_logs.user_id`、`user_status_logs.changed_by_user_id`、`user_status_logs.changed_at` に各々 INDEX を付与する。
- **REQ-user-management-062**: The system shall `UserStatusLog` モデルに `belongsTo(User::class, 'user_id')`（変更対象）と `belongsTo(User::class, 'changed_by_user_id', 'changedBy')`（変更者）の 2 リレーションを定義し、`changedBy` リレーションは `withTrashed()` を含めて soft delete 済 admin も解決可能にする。

### 機能要件 — UserStatusChangeService（共通エントリポイント）

- **REQ-user-management-070**: The system shall `record(User $user, UserStatus $newStatus, ?User $changedBy, ?string $reason = null): UserStatusLog` を提供する。
- **REQ-user-management-071**: The system shall ステータス変化を伴う全 Action（[[auth]] の `IssueInvitationAction` / `OnboardAction` / `ExpireInvitationsAction` / `RevokeInvitationAction`、本 Feature の `WithdrawAction`、[[settings-profile]] の `SelfWithdrawAction`）が本 Service を経由して `UserStatusLog` を記録する設計とする。**`User.status` の UPDATE と `UserStatusLog` の INSERT は同一トランザクション内** で行うことを呼び出し側 Action が保証する。
- **REQ-user-management-072**: The system shall `record` の引数 `$newStatus` を呼び出し側に **更新後の値を渡す責任** を持たせる。`$user->status` を Service 内で書き換えない（呼び出し側 Action が `User::update(['status' => ...])` した後で Service を呼ぶ）。
- **REQ-user-management-073**: When `$changedBy === null` で記録された場合, the `changed_by_user_id` shall NULL となり、ビュー / Resource 上の actor 名は **「システム」** と表示される。Schedule Command（[[auth]] の `invitations:expire` 等）からの呼出を想定。

### 機能要件 — 認可（Policy + Middleware）

- **REQ-user-management-080**: The system shall すべての `/admin/users/*` および `/admin/invitations/*` ルートを `auth` + `role:admin` Middleware で保護する。
- **REQ-user-management-081**: The system shall `UserPolicy` で `viewAny` / `view` / `update` / `updateRole` / `withdraw` を実装し、admin のみ true を返す。`update` / `updateRole` / `withdraw` は自分自身対象でも true を返し、**自己操作禁止は Action 内のドメイン例外で表現** する（「権限の有無」と「業務制約」を分離するため）。
- **REQ-user-management-082**: The system shall `InvitationPolicy::create` / `revoke`（[[auth]] 既存）を引き続き admin のみ true で利用する。
- **REQ-user-management-083**: If coach / student が `/admin/users/*` にアクセスした場合, then the system shall `EnsureUserRole` Middleware で HTTP 403 を返す。

### 非機能要件

- **NFR-user-management-001**: The system shall 状態変更を伴うすべての Action（`UpdateAction` / `UpdateRoleAction` / `WithdrawAction`、および本 Feature 経由で呼ばれる [[auth]] / [[settings-profile]] の Action）を `DB::transaction()` で囲む。
- **NFR-user-management-002**: The system shall 一覧の検索・フィルタ・ページネーションを **SQL レベル** で実行する。Eloquent クエリの `where` / `orderBy` / `paginate` を使い、全件取得後のコレクション操作は禁止。
- **NFR-user-management-003**: The system shall `users.name` と `users.email` の部分一致検索を LIKE で実装する。FULLTEXT 化は本 Feature ではスコープ外。
- **NFR-user-management-004**: The system shall ドメイン例外を `app/Exceptions/UserManagement/` 配下に独立クラスとして配置する（`SelfRoleChangeForbiddenException` / `SelfWithdrawForbiddenException` / `UserAlreadyWithdrawnException`）。
- **NFR-user-management-005**: The system shall `UserStatusChangeService` を **single-responsibility（記録のみ）** とし、User の状態を書き換えない / Notification を送らない。状態変化と通知は呼び出し側 Action の責務とする。
- **NFR-user-management-006**: The system shall 全 admin 操作画面を `layouts.app` 上で描画し、Wave 0b の Design System コンポーネント（`<x-button>` / `<x-form.*>` / `<x-modal>` / `<x-alert>` / `<x-card>` / `<x-badge>`）を再利用する。

## スコープ外

- **自己プロフィール編集 / 自己退会**（[[settings-profile]]）— 本 Feature は admin → 他者のみ
- **物理削除（force delete）** — soft delete + email リネームのみ採用（[[auth]] / `tech.md` 「SoftDeletes 採用」方針）
- **ロール変更履歴の独立テーブル化** — `UserStatusLog` は status 専用、ロール変更履歴は教育PJスコープ外
- **`withdrawn → active` 復活フロー** — state diagram の終端、再招待は別 Invitation として新規発行（同 user_id を維持する [[auth]] `IssueInvitationAction(force=true)` も `invited` 状態の User のみ対象）
- **バルク操作（一括招待 / 一括退会 / 一括ロール変更）** — 教育PJスコープ外
- **CSV エクスポート / インポート** — `product.md` スコープ外
- **招待履歴の全 User 横断ビュー** — 個別 User 詳細でのみ閲覧、運用拡張範囲
- **メール変更時の確認フロー**（旧 email 通知 + 新 email 確認リンク）— admin が直接変更（self-service は [[settings-profile]] スコープ）
- **ロール変更時の旧ロール固有データのクリーンアップ**（例: coach → student で担当資格を解除）— 関連 Feature（[[certification-management]]）が後続対応

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[auth]]: `IssueInvitationAction` / `OnboardAction` / `ExpireInvitationsAction` / `RevokeInvitationAction` が本 Feature の `UserStatusChangeService::record` を呼ぶ
  - [[settings-profile]]: 自己退会 Action が本 Feature の `UserStatusChangeService::record` を呼ぶ
  - [[dashboard]]: admin ダッシュボードから本 Feature の一覧 / 詳細へ遷移
- **依存先**（本 Feature が前提とする）:
  - [[auth]]: `User` / `Invitation` モデル、`UserStatus` / `UserRole` / `InvitationStatus` Enum、`IssueInvitationAction` / `RevokeInvitationAction` Action、`EnsureUserRole` Middleware
  - [[enrollment]]: ユーザー詳細画面で `Enrollment` 概要を表示するため `Enrollment` モデルを Read-only で参照
