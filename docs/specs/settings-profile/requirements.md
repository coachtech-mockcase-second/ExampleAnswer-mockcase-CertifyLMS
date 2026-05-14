# settings-profile 要件定義

## 概要

Certify LMS の全ロール（admin / coach / student）が **自分自身のアカウント情報を管理する画面**。`/settings` 配下に、プロフィール表示・編集 / パスワード変更 / 自己退会 を集約し、`/settings/availability` で coach のみ面談可能時間枠（`CoachAvailability`）を CRUD する。固定面談 URL（`users.meeting_url`）はプロフィール画面内で coach のみ編集可能。

[[user-management]] が「**admin が他者を管理する画面**」を所有するのに対し、本 Feature は「**本人が自分自身をいじる画面**」を所有する責務分離（`product.md` Feature 一覧表 16 行目 + `frontend-ui-foundation.md`「ロール共通画面の責務分担」準拠）。

**通知設定 UI は提供しない**（[[notification]] が全通知 Database + Mail 両方を固定送信する設計、種別 × channel ごとの ON/OFF は不採用、Phase 0 の議論で受講生メリットと実装複雑度のバランス点として確定）。`UserNotificationSetting` モデル / `NotificationType` Enum / `NotificationChannel` Enum も新設しない。状態遷移（`active → withdrawn`）を伴う自己退会 Action は [[user-management]] の `UserStatusChangeService::record` を経由してログを記録する（actor は本人）。`CoachAvailability` モデル / `CoachAvailabilityPolicy` は [[mentoring]] が所有し、本 Feature はその上に **編集 UI（Controller / FormRequest / Blade）** を構築する。

## ロールごとのストーリー

- **受講生（student）**: サイドバー「設定」から `/settings/profile` に遷移し、タブで「プロフィール」「パスワード」「退会」を切り替える。自分の氏名 / 自己紹介 / アイコン画像を更新する。パスワードは現在のパスワード照合の上で 8 文字以上に変更する。試験諦め等で退会したい場合は退会タブから理由を任意入力して自己退会する（即時ログアウト）。
- **コーチ（coach）**: 上記に加え、プロフィール画面で **固定面談 URL** を編集できる。`/settings/availability` で面談可能時間枠（曜日 × 開始 / 終了時刻 × 有効フラグ）を CRUD する。
- **管理者（admin）**: プロフィール / パスワードタブは利用可能。**自己退会タブと自己退会 Action は admin に対しては存在しない / 拒否される**（最後の admin 消失リスク防止 + [[user-management]] `SelfWithdrawForbiddenException` と整合）。`/settings/availability` は 403。

## 受け入れ基準（EARS形式）

### 機能要件 — ルーティング・タブ構造

- **REQ-settings-profile-001**: The system shall `/settings/profile` を **既定ページ** とする `/settings` 配下に、ロール共通画面として `/settings/profile` / `/settings/password` / `/settings/withdraw` の 3 サブページと、coach 限定の `/settings/availability` を提供する。ナビゲーションは「設定」サイドバーアイテム → `settings.profile.edit` 起点で、画面内タブで `?tab=profile` / `?tab=password` / `?tab=withdraw` 切替を可能とする。`/settings/availability` のみ別ページ（タブ内ではない）。
- **REQ-settings-profile-002**: The system shall すべての `/settings/*` ルートを `auth` middleware で保護し、未認証アクセスはログイン画面へリダイレクトする。
- **REQ-settings-profile-003**: While ユーザーが `/settings/profile` を閲覧している間, the system shall そのユーザーのロールに応じてタブ可視性を制御する: admin は「プロフィール / パスワード」の 2 タブ、coach / student は「プロフィール / パスワード / 退会」の 3 タブを表示する。
- **REQ-settings-profile-004**: While coach がログインしている間, the system shall `/settings/profile` のプロフィール編集フォームに **固定面談 URL（`users.meeting_url`）** 入力欄を表示する。admin / student に対しては表示しない（フォーム送信側でも `role !== coach` の場合は当該フィールドを無視する）。
- **REQ-settings-profile-005**: If admin が `/settings/withdraw` または `/settings/availability` に直接アクセスした場合, then the system shall HTTP 403（`AccessDeniedHttpException`、`errors/403.blade.php` 描画）で拒否する。
- **REQ-settings-profile-006**: If coach 以外（student / admin）が `/settings/availability` に直接アクセスした場合, then the system shall HTTP 403 で拒否する。

### 機能要件 — プロフィール表示・編集

- **REQ-settings-profile-010**: The system shall `GET /settings/profile` で、認証ユーザー本人の **氏名 / メール（読み取り専用、ロックアイコン付き）/ 自己紹介 / アイコン画像 / ロール badge / アカウント状態 badge** を表示する。
- **REQ-settings-profile-011**: When ユーザーが `PATCH /settings/profile` をプロフィール編集フォームから送信した際, the system shall `name`（必須 / 1-50 文字）/ `bio`（任意 / 最大 1000 文字）の検証後、`users` 行の `name` / `bio` を UPDATE する。**`email` は受け取らず UPDATE しない**（編集動線は admin 経由のみ、[[user-management]] が所有）。
- **REQ-settings-profile-012**: When プロフィール編集の入力にロール `coach` のユーザーから `meeting_url` が含まれていた際, the system shall `meeting_url`（任意 / 最大 500 文字 / URL 形式）を検証して `users.meeting_url` を UPDATE する。`meeting_url` が空文字列の場合は NULL を保存する。
- **REQ-settings-profile-013**: If ロール `coach` 以外のユーザーから `meeting_url` フィールドが送信された場合, then the system shall そのフィールドを **無視して破棄** する（バリデーションエラーにせず、サーバ側で silently drop）。
- **REQ-settings-profile-014**: When プロフィール編集が成功した際, the system shall `/settings/profile?tab=profile` にリダイレクトし、`session('success')` に「プロフィールを更新しました。」を入れて `<x-flash>` 経由で表示する。`UserStatusLog` への記録は **行わない**（status 変化なし）。

### 機能要件 — アバター画像アップロード

- **REQ-settings-profile-020**: When ユーザーが `POST /settings/avatar` でアバター画像をアップロードした際, the system shall `avatar` ファイル（必須 / MIME `image/png` / `image/jpeg` / `image/webp` / 最大 2048 KB）の **サーバ側 MIME 検証** を実施し、不正な場合は FormRequest バリデーションエラー（日本語メッセージ）で拒否する。
- **REQ-settings-profile-021**: When アバターアップロードのサーバ側検証が成功した際, the system shall 以下の順序で処理する: (1) 新ファイルを Laravel Storage public driver の `avatars/{ulid}.{ext}` パスへ保存（`{ulid}` は新規生成の ULID で衝突なし、`{ext}` は MIME に対応する `png` / `jpg` / `webp`）、(2) 単一トランザクション内で `users.avatar_url` を `Storage::url("avatars/{ulid}.{ext}")` で生成した publicUrl に UPDATE、(3) UPDATE 成功後に旧 `users.avatar_url` が指していた Storage ファイルを **best-effort** で削除する（失敗してもユーザーフロー継続、孤児ファイルは運用上許容）。
- **REQ-settings-profile-022**: If 新ファイル保存（REQ-021 のステップ 1）で Storage 書込失敗が発生した場合, then the system shall `AvatarStorageFailedException`（HTTP 500、日本語メッセージ「画像のアップロードに失敗しました。時間をおいて再度お試しください。」）を throw し、DB の `users.avatar_url` と旧ファイルは未変更のまま保つ。If DB UPDATE（REQ-021 のステップ 2）が失敗した場合, then the system shall 直前に保存した新ファイルを削除して例外を伝播し、`users.avatar_url` と旧ファイルは未変更のまま保つ。
- **REQ-settings-profile-023**: When ユーザーが `DELETE /settings/avatar` でアバターを削除した際, the system shall 単一トランザクション内で (1) `users.avatar_url` の指す Storage ファイルを削除、(2) `users.avatar_url` を NULL に UPDATE する。`<x-avatar>` コンポーネントは NULL 時にイニシャルアバターを描画する。

### 機能要件 — パスワード変更（Fortify 統合）

- **REQ-settings-profile-030**: The system shall Laravel Fortify の `Fortify::updateUserPasswordsUsing(UpdateUserPassword::class)` を有効化し、`PUT /settings/password` を Fortify 標準の Password Update ルートとして登録する。
- **REQ-settings-profile-031**: When ユーザーが `PUT /settings/password` を送信した際, the system shall `current_password`（必須 / 現在のパスワードと bcrypt 一致）/ `password`（必須 / 8 文字以上 / `confirmed` ルール）/ `password_confirmation`（`password` と一致）を Fortify 標準ルールで検証し、合格した場合のみ `Hash::make($password)` で `users.password` を UPDATE する。
- **REQ-settings-profile-032**: If `current_password` が現在のパスワードと一致しない場合, then the system shall FormRequest バリデーションエラー（日本語メッセージ「現在のパスワードが正しくありません。」）で拒否し、`users.password` を UPDATE しない。
- **REQ-settings-profile-033**: If `password` が 8 文字未満の場合, then the system shall FormRequest バリデーションエラー（日本語メッセージ）で拒否する。
- **REQ-settings-profile-034**: When パスワード変更が成功した際, the system shall `/settings/profile?tab=password` にリダイレクトし、`session('status')` に Fortify 標準の `password-updated` ステータスメッセージを入れて `<x-flash>` 経由で表示する。

### 機能要件 — 自己退会（SelfWithdrawAction）

- **REQ-settings-profile-050**: When coach / student のユーザーが `POST /settings/withdraw` を退会確認フォームから送信した際, the system shall `reason`（任意 / 最大 200 文字）を受け取り、単一トランザクション内で (1) [[auth]] の `User::withdraw()` ヘルパ呼出（`users.email` を `{ulid}@deleted.invalid` 形式へリネーム + `users.status = withdrawn` + `users.deleted_at = now()`）、(2) [[user-management]] の `UserStatusChangeService::record($user, UserStatus::Withdrawn, $user, $reason ?? '自己退会')` を呼ぶ（`$changedBy = $user` 本人）、(3) `Auth::logout()` + `session()->invalidate()` + `session()->regenerateToken()` でセッションを破棄、(4) `/login` へリダイレクト + `session('status')` に「退会が完了しました。」を入れる。
- **REQ-settings-profile-051**: If ロール `admin` のユーザーが `POST /settings/withdraw` を呼んだ場合, then the system shall ドメイン例外 `AdminSelfWithdrawForbiddenException`（HTTP 403、日本語メッセージ「管理者は自己退会できません。」）で拒否する。
- **REQ-settings-profile-052**: If 既に `withdrawn` 状態の User が（何らかの理由で）`POST /settings/withdraw` を呼んだ場合, then the system shall [[user-management]] の `UserAlreadyWithdrawnException`（HTTP 409）でそのまま伝播する。
- **REQ-settings-profile-053**: When 自己退会が成功した際, the system shall 対象 User に紐付く `Enrollment` レコードを **削除しない**（[[enrollment]] スコープ外指定通り、履歴として保持。`enrollments.user_id` への soft delete cascade は行わない）。

### 機能要件 — 面談可能時間枠（coach のみ、CoachAvailability 編集 UI）

- **REQ-settings-profile-060**: The system shall `GET /settings/availability` で、認証 coach 本人の `CoachAvailability` 行を曜日（月〜日）× 時間範囲のテーブル形式で一覧表示する。各行は「曜日 / 開始時刻 / 終了時刻 / 有効フラグ / 編集 / 削除」を持つ。
- **REQ-settings-profile-061**: When coach が `POST /settings/availability` で枠新規作成フォームを送信した際, the system shall `day_of_week`（必須 / `1`〜`7` の int、ISO-8601 月=1〜日=7）/ `start_time`（必須 / `HH:MM` 形式、`00:00`〜`23:00`）/ `end_time`（必須 / `HH:MM` 形式、`start_time` より後）/ `is_active`（boolean、デフォルト true）を検証後、`coach_availabilities` テーブルに `coach_id = auth()->id()` で INSERT する。
- **REQ-settings-profile-062**: When coach が `PATCH /settings/availability/{availability}` で枠を編集した際, the system shall `CoachAvailabilityPolicy::update`（[[mentoring]] 所有）で本人所有確認の上、`day_of_week` / `start_time` / `end_time` / `is_active` を UPDATE する。
- **REQ-settings-profile-063**: When coach が `DELETE /settings/availability/{availability}` で枠を削除した際, the system shall `CoachAvailabilityPolicy::delete`（[[mentoring]] 所有）で本人所有確認の上、SoftDelete する。
- **REQ-settings-profile-064**: If `end_time <= start_time` の枠が送信された場合, then the system shall FormRequest バリデーションエラー（日本語メッセージ「終了時刻は開始時刻より後を指定してください。」）で拒否する。
- **REQ-settings-profile-065**: The system shall 同一 coach × 同一曜日で時刻範囲が重複する複数枠の登録を **許容する**（コーチ運用上、午前枠と午後枠など分割管理に有用）。[[mentoring]] 側で空き枠展開時に和集合として扱う前提（`backend-mentoring spec` の `REQ-mentoring-026` 参照）。

### 機能要件 — 認可（Policy）

- **REQ-settings-profile-080**: The system shall ロール存在確認用の middleware を本 Feature では新設せず、`/settings/availability` のみ `role:coach` middleware で保護する。`/settings/profile` / `/settings/password` / `/settings/withdraw` は `auth` のみで保護し、ロール別タブ可視・admin 自己退会拒否は Controller / Action 内の Policy ＆ ドメイン例外で表現する。
- **REQ-settings-profile-081**: The system shall `UserPolicy::updateSelf` / `UserPolicy::withdrawSelf` を本 Feature で **新設** し、`updateSelf(User $auth, User $target)` は `$auth->id === $target->id` の場合のみ true、`withdrawSelf` は `$auth->id === $target->id && $auth->role !== UserRole::Admin` の場合のみ true を返す（admin 自己退会の二重防衛、業務制約は `AdminSelfWithdrawForbiddenException` で表現済みだが Policy 側にもガードを置く）。[[user-management]] が所有する `UserPolicy::update` / `withdraw` とは別メソッドとして共存させる（admin 経由 vs 本人経由を Policy 層で分ける）。
- **REQ-settings-profile-082**: The system shall `CoachAvailabilityPolicy`（[[mentoring]] 所有）の `viewAny` / `create` / `update` / `delete` を `/settings/availability/*` ルートでも利用する。本 Feature では新設しない。

### 非機能要件

- **NFR-settings-profile-001**: The system shall 状態変更を伴うすべての Action（`UpdateProfileAction` / `UpdateAvatarAction` / `DestroyAvatarAction` / `SelfWithdrawAction` / `StoreAvailabilityAction` / `UpdateAvailabilityAction` / `DestroyAvailabilityAction`）を `DB::transaction()` で囲む。
- **NFR-settings-profile-002**: The system shall ドメイン例外を `app/Exceptions/SettingsProfile/` 配下に配置する（`backend-exceptions.md` 準拠）: `AdminSelfWithdrawForbiddenException`（403）/ `AvatarStorageFailedException`（500）。`UserAlreadyWithdrawnException`（409）は [[user-management]] 所有のものをそのまま伝播する。
- **NFR-settings-profile-003**: The system shall すべての画面を `layouts.app` 上で描画し、Wave 0b の Design System コンポーネント（`<x-button>` / `<x-form.*>` / `<x-modal>` / `<x-alert>` / `<x-card>` / `<x-tabs>` / `<x-flash>`）を再利用する（`frontend-blade.md` 共通コンポーネント API 準拠）。
- **NFR-settings-profile-004**: The system shall アバター画像を `Storage::disk('public')` 配下に保存し、`public/storage` シンボリックリンク（`sail artisan storage:link`）経由で `/storage/avatars/{ulid}.{ext}` として配信する。**chat 添付や修了証 PDF が使う private driver は採用しない**（プロフィール画像は公開前提、`tech.md`「ファイル保存」方針準拠）。
- **NFR-settings-profile-006**: The system shall すべての日本語エラーメッセージを `lang/ja/validation.php` / `lang/ja/auth.php` およびドメイン例外コンストラクタで定義し、ビュー / FormRequest 内のマジック文字列は禁止する。
- **NFR-settings-profile-007**: The system shall アバター画像のクライアント側検証（MIME / サイズ）は素の JS（`resources/js/settings-profile/avatar.js`）で実装し、サーバ側 MIME 検証と二重化する。`frontend-javascript.md` 規約準拠。
- **NFR-settings-profile-008**: The system shall すべてのフォーム送信を CSRF トークン（`@csrf` + Laravel 標準 `VerifyCsrfToken` middleware）で保護する。`frontend-blade.md`「必須事項」準拠。

## スコープ外

- **admin 経由の他者プロフィール編集 / ロール変更 / 強制退会** — [[user-management]] が所有（本 Feature は admin → 自分のみ）
- **メールアドレスの変更動線** — admin 経由のみ（[[user-management]]）。本 Feature では表示のみ
- **自己ロール変更** — admin 経由のみ（[[user-management]]）
- **admin の自己退会** — `AdminSelfWithdrawForbiddenException`（403）で拒否、admin → admin での退会フローも本 Feature 外（[[user-management]] が他 admin 経由の `WithdrawAction` を持つ）
- **2FA / IP 制限 / ログイン履歴の詳細管理** — `product.md` スコープ外
- **API トークン（Sanctum PAT）の発行 / 失効 UI** — **採用しない**。[[analytics-export]] は `.env` の共通 API キー方式（個人トークン不要）、[[quiz-answering]] の Advance SPA は Sanctum SPA 認証（Cookie ベース、Personal Access Token 不要）を採用するため、Personal Access Token の管理画面 / Action / Migration は LMS 全体で **不要**
- **学習時間目標（`LearningHourTarget`）の編集 UI** — [[learning]] が `/learning/enrollments/{enrollment}/hour-target` で所有、`/settings/profile` からは link 誘導のみ
- **`CoachAvailability` モデル / Migration / Policy 定義** — [[mentoring]] が所有、本 Feature は **編集 UI（Controller / FormRequest / Blade）のみ**
- **通知設定 UI（種別 × channel ON/OFF）** — **採用しない**。[[notification]] が全通知 Database + Mail 両方を固定送信する設計（product.md「## スコープ外」記載、Phase 0 議論で確定）。`UserNotificationSetting` モデル / `NotificationType` Enum / `NotificationChannel` Enum / `NotificationSettingController` / `tab-notifications.blade.php` は本 Feature では一切作らない
- **退会時の `Enrollment` 削除 / cascade** — 採用しない（履歴として残す、[[enrollment]] が責務担保）
- **`withdrawn → active` 復活フロー** — state diagram の終端、再招待は別 Invitation として新規発行（[[auth]] / [[user-management]]）
- **アバター画像の自動リサイズ / 圧縮** — Canvas API 等は採用しない（教育PJスコープ）。2MB 上限のみで運用

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[mentoring]]: 本 Feature の `/settings/availability` 画面が `CoachAvailability` を CRUD して coach の空き枠を整備（mentoring 側の予約フローの前提条件）
  - [[dashboard]]: サイドバー「設定 [cog]」アイテムから `settings.profile.edit` ルートへ遷移
  - [[notification]]: 通知設定 UI は不要（[[notification]] が全通知 DB+Mail 固定送信、`UserNotificationSetting` 等は本 Feature で新設しない）
- **依存先**（本 Feature が前提とする）:
  - [[auth]]: `User` モデル、`UserRole` / `UserStatus` Enum、`User::withdraw()` ヘルパ、`auth` middleware、Fortify Password Update 機構
  - [[user-management]]: `UserStatusChangeService::record` を自己退会フローから呼出
  - [[mentoring]]: `CoachAvailability` Model / Migration、`CoachAvailabilityPolicy`、`role:coach` middleware（[[auth]] 所有を借用）
  - [[learning]]: `/learning/enrollments/{enrollment}/hour-target` URL（link 誘導先）
