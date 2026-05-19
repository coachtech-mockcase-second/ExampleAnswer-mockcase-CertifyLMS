# settings-profile 要件定義

> **v3 改修反映**(2026-05-16):
> - **自己退会動線を完全撤回**(`/settings/withdraw` route / `SelfWithdrawController` / `SelfWithdrawAction` / `SelfWithdrawRequest` / `AdminSelfWithdrawForbiddenException` / `withdrawSelf` Policy / `tab-withdraw.blade.php` 全削除)
> - 退会はすべて [[user-management]] の admin 経由オペレーションに集約(product.md L497「以下は提供しない: ①自己退会動線」)
> - **EnsureActiveLearning Middleware は適用しない**(product.md L482「プロフィール / 修了証 DL は許可」と整合、`graduated` ユーザーも自分のプロフィール / パスワード / アバター変更は可能)
> - プラン情報表示・追加面談購入 CTA は引き続き [[dashboard]] に集約(本 Feature では持たない)
> - 通知設定 UI は引き続き不採用([[notification]] が全通知 DB+Mail 固定送信)
>
> **v3.7 改修反映**(2026-05-18):
> - **コーチ向けの「面談設定」タブを `/settings/profile` 配下に新設**し、Google カレンダー連携と面談可能時間枠(週次カレンダー UI)をプロフィール画面内で集約。サイドバーの「面談可能時間枠」/「Googleカレンダー連携」個別リンクを撤回(coach は設定画面のタブ切替で完結)
> - 面談可能時間枠の編集 UI を **テーブル一覧から週次カレンダー(日 〜 土 × 時刻グリッド)に刷新**(受講生の面談予約画面と類似の体感)
> - `/settings/availability` (GET) は廃止し、`/settings/profile?tab=meeting` への 302 redirect に変更(POST / PATCH / DELETE は本 Feature のフォーム POST 先として継続利用)
> - 旧 `settings.google-calendar.index` 画面は撤回し、route も廃止(`redirect` / `callback` / `destroy` は OAuth フローと解除動線用に残す)

## 概要

Certify LMS の全ロール(admin / coach / student)が **自分自身のアカウント情報を管理する画面**。`/settings` 配下に、プロフィール表示・編集 / パスワード変更 / アバター画像変更 を集約し、`/settings/availability` で coach のみ面談可能時間枠(`CoachAvailability`)を CRUD する。固定面談 URL(`users.meeting_url`)はプロフィール画面内で coach のみ編集可能。

[[user-management]] が「**admin が他者を管理する画面**」を所有するのに対し、本 Feature は「**本人が自分自身をいじる画面**」を所有する責務分離。**v3 で自己退会動線は完全撤回**(退会は admin に依頼するオペレーション、LMS 内に動線なし)。

**通知設定 UI は提供しない**([[notification]] が全通知 Database + Mail 両方を固定送信する設計、種別 × channel ごとの ON/OFF は不採用)。`UserNotificationSetting` モデル / `NotificationType` Enum / `NotificationChannel` Enum も新設しない。`CoachAvailability` モデル / `CoachAvailabilityPolicy` は [[mentoring]] が所有し、本 Feature はその上に **編集 UI(Controller / FormRequest / Blade)** を構築する。

## ロールごとのストーリー

- **受講生(student)**: サイドバー「設定」から `/settings/profile` に遷移し、タブで「プロフィール」「パスワード」を切り替える。自分の氏名 / 自己紹介 / アイコン画像を更新する。パスワードは現在のパスワード照合の上で 8 文字以上に変更する。**退会したい場合は管理者に依頼する**(LMS 内に自己退会動線なし、v3)。`graduated` 状態の受講生も同様にプロフィール / パスワードを変更可能(プラン機能はロックされるが本 Feature はロックしない)。
- **コーチ(coach)**: 上記に加え、プロフィール画面で **固定面談 URL** を編集できる。`/settings/availability` で面談可能時間枠(曜日 × 開始 / 終了時刻 × 有効フラグ)を CRUD する。
- **管理者(admin)**: プロフィール / パスワードタブのみ利用可能。`/settings/availability` は 403(コーチ限定)。

## 受け入れ基準(EARS形式)

### 機能要件 — ルーティング・タブ構造

- **REQ-settings-profile-001**: The system shall `/settings/profile` を **唯一のサブページ** とする `/settings` 配下に、ロール共通画面として **画面内タブ切替** を提供する。タブは `?tab=profile`(全ロール) / `?tab=password`(全ロール) / `?tab=meeting`(coach 限定)の 3 種類。**`/settings/withdraw` は提供しない**(v3 撤回)。サイドバーには「設定」アイテム 1 つだけを置き、`settings.availability.index` / `settings.google-calendar.index` の個別リンクは撤回(v3.7)。
- **REQ-settings-profile-002**: The system shall すべての `/settings/*` ルートを `auth` middleware で保護し、未認証アクセスはログイン画面へリダイレクトする。**`EnsureActiveLearning` Middleware は適用しない**(product.md L482「プロフィール / 修了証 DL は許可」と整合、`graduated` ユーザーも自身のプロフィール / パスワード / アバター変更は可能)。
- **REQ-settings-profile-003**: While ユーザーが `/settings/profile` を閲覧している間, the system shall admin / student に対して「プロフィール / パスワード」の **2 タブ**、coach に対しては「プロフィール / パスワード / 面談設定」の **3 タブ** を表示する(v3.7、コーチ専用の面談設定タブを追加)。**「退会」タブは提供しない**(v3 撤回)。
- **REQ-settings-profile-004**: While coach がログインしている間, the system shall `/settings/profile` のプロフィール編集フォームに **固定面談 URL(`users.meeting_url`)** 入力欄を表示する。admin / student に対しては表示しない(フォーム送信側でも `role !== coach` の場合は当該フィールドを無視する)。
- **REQ-settings-profile-005**: If coach 以外(student / admin)が `/settings/profile?tab=meeting` のタブ切替リンクを操作したり、`/settings/availability` 系エンドポイント(`store` / `update` / `destroy`)を直接呼び出した場合, then the system shall タブ表示自体を行わず、エンドポイントは HTTP 403 で拒否する。`GET /settings/availability` は `/settings/profile?tab=meeting` への 302 redirect とする(v3.7)。

### 機能要件 — プロフィール表示・編集

- **REQ-settings-profile-010**: The system shall `GET /settings/profile` で、認証ユーザー本人の **氏名 / メール(読み取り専用、ロックアイコン付き) / 自己紹介 / アイコン画像 / ロール badge / アカウント状態 badge** を表示する。
- **REQ-settings-profile-011**: When ユーザーが `PATCH /settings/profile` をプロフィール編集フォームから送信した際, the system shall `name`(必須 / 1-50 文字)/ `bio`(任意 / 最大 1000 文字)の検証後、`users` 行の `name` / `bio` を UPDATE する。**`email` は受け取らず UPDATE しない**(編集動線は admin 経由のみ、[[user-management]] が所有)。
- **REQ-settings-profile-012**: When プロフィール編集の入力にロール `coach` のユーザーから `meeting_url` が含まれていた際, the system shall `meeting_url`(任意 / 最大 500 文字 / URL 形式)を検証して `users.meeting_url` を UPDATE する。`meeting_url` が空文字列の場合は NULL を保存する。**ただし、coach のオンボーディング時に `meeting_url` が必須化されたため(v3、[[auth]] `OnboardAction`)、本 Feature での空文字保存は admin の運用ガイダンス(再オンボーディング案内等)に従って稀に発生するケースとして許容する**。
- **REQ-settings-profile-013**: If ロール `coach` 以外のユーザーから `meeting_url` フィールドが送信された場合, then the system shall そのフィールドを **無視して破棄** する(silently drop)。
- **REQ-settings-profile-014**: When プロフィール編集が成功した際, the system shall `/settings/profile?tab=profile` にリダイレクトし、`session('success')` に「プロフィールを更新しました。」を入れて `<x-flash>` 経由で表示する。`UserStatusLog` への記録は **行わない**(status 変化なし)。

### 機能要件 — アバター画像アップロード

- **REQ-settings-profile-020**: When ユーザーが `POST /settings/avatar` でアバター画像をアップロードした際, the system shall `avatar` ファイル(必須 / MIME `image/png` / `image/jpeg` / `image/webp` / 最大 2048 KB)の **サーバ側 MIME 検証** を実施し、不正な場合は FormRequest バリデーションエラー(日本語メッセージ)で拒否する。
- **REQ-settings-profile-021**: When アバターアップロードのサーバ側検証が成功した際, the system shall 以下の順序で処理する: (1) 新ファイルを Laravel Storage public driver の `avatars/{ulid}.{ext}` パスへ保存、(2) 単一トランザクション内で `users.avatar_url` を `Storage::url("avatars/{ulid}.{ext}")` で生成した publicUrl に UPDATE、(3) UPDATE 成功後に旧 `users.avatar_url` が指していた Storage ファイルを **best-effort** で削除する。
- **REQ-settings-profile-022**: If 新ファイル保存(REQ-021 のステップ 1)で Storage 書込失敗が発生した場合, then the system shall `AvatarStorageFailedException`(HTTP 500、日本語メッセージ「画像のアップロードに失敗しました。時間をおいて再度お試しください。」)を throw し、DB の `users.avatar_url` と旧ファイルは未変更のまま保つ。If DB UPDATE(REQ-021 のステップ 2)が失敗した場合, then the system shall 直前に保存した新ファイルを削除して例外を伝播し、`users.avatar_url` と旧ファイルは未変更のまま保つ。
- **REQ-settings-profile-023**: When ユーザーが `DELETE /settings/avatar` でアバターを削除した際, the system shall 単一トランザクション内で (1) `users.avatar_url` の指す Storage ファイルを削除、(2) `users.avatar_url` を NULL に UPDATE する。`<x-avatar>` コンポーネントは NULL 時にイニシャルアバターを描画する。

### 機能要件 — パスワード変更(Fortify 統合)

- **REQ-settings-profile-030**: The system shall Laravel Fortify の `Fortify::updateUserPasswordsUsing(UpdateUserPassword::class)` を有効化し、`PUT /settings/password` を Fortify 標準の Password Update ルートとして登録する。
- **REQ-settings-profile-031**: When ユーザーが `PUT /settings/password` を送信した際, the system shall `current_password`(必須 / 現在のパスワードと bcrypt 一致)/ `password`(必須 / 8 文字以上 / `confirmed` ルール)/ `password_confirmation`(`password` と一致)を Fortify 標準ルールで検証し、合格した場合のみ `Hash::make($password)` で `users.password` を UPDATE する。
- **REQ-settings-profile-032**: If `current_password` が現在のパスワードと一致しない場合, then the system shall FormRequest バリデーションエラー(日本語メッセージ「現在のパスワードが正しくありません。」)で拒否し、`users.password` を UPDATE しない。
- **REQ-settings-profile-033**: If `password` が 8 文字未満の場合, then the system shall FormRequest バリデーションエラー(日本語メッセージ)で拒否する。
- **REQ-settings-profile-034**: When パスワード変更が成功した際, the system shall `/settings/profile?tab=password` にリダイレクトし、`session('status')` に Fortify 標準の `password-updated` ステータスメッセージを入れて `<x-flash>` 経由で表示する。

### 機能要件 — 面談可能時間枠(coach のみ、CoachAvailability 編集 UI)

- **REQ-settings-profile-060**: The system shall `/settings/profile?tab=meeting` の **面談設定タブ内に週次カレンダー(日 〜 土 × 時刻グリッド) UI** を描画する(v3.7、テーブル一覧から刷新)。既存の `CoachAvailability` 行は曜日・時間帯に応じた色付きブロックとしてグリッド上に重ね、空セルクリックで新規作成モーダル、ブロッククリックで編集 / 削除モーダルを開く。受講生の面談予約画面と同じ「カレンダー上で選ぶ」体感を提供する。
- **REQ-settings-profile-061**: When coach が `POST /settings/availability` で枠新規作成フォームを送信した際, the system shall `day_of_week`(必須 / `1`〜`7` の int、ISO-8601 月=1〜日=7)/ `start_time`(必須 / `HH:MM` 形式、`00:00`〜`23:00`)/ `end_time`(必須 / `HH:MM` 形式、`start_time` より後)/ `is_active`(boolean、デフォルト true)を検証後、`coach_availabilities` テーブルに `coach_id = auth()->id()` で INSERT する。
- **REQ-settings-profile-062**: When coach が `PATCH /settings/availability/{availability}` で枠を編集した際, the system shall `CoachAvailabilityPolicy::update`([[mentoring]] 所有)で本人所有確認の上、`day_of_week` / `start_time` / `end_time` / `is_active` を UPDATE する。
- **REQ-settings-profile-063**: When coach が `DELETE /settings/availability/{availability}` で枠を削除した際, the system shall `CoachAvailabilityPolicy::delete`([[mentoring]] 所有)で本人所有確認の上、SoftDelete する。
- **REQ-settings-profile-064**: If `end_time <= start_time` の枠が送信された場合, then the system shall FormRequest バリデーションエラー(日本語メッセージ「終了時刻は開始時刻より後を指定してください。」)で拒否する。
- **REQ-settings-profile-065**: The system shall 同一 coach × 同一曜日で時刻範囲が重複する複数枠の登録を **許容する**(コーチ運用上、午前枠と午後枠など分割管理に有用)。

### 機能要件 — 認可(Policy)

- **REQ-settings-profile-080**: The system shall ロール存在確認用の middleware を本 Feature では新設せず、`/settings/availability` のみ `role:coach` middleware で保護する。`/settings/profile` / `/settings/password` は `auth` のみで保護する。
- **REQ-settings-profile-081**: The system shall `UserPolicy::updateSelf` を本 Feature で **新設** し、`updateSelf(User $auth, User $target)` は `$auth->id === $target->id` の場合のみ true を返す。**`withdrawSelf` は提供しない**(v3 撤回)。[[user-management]] が所有する `UserPolicy::update` / `withdraw` とは別メソッドとして共存させる(admin 経由 vs 本人経由を Policy 層で分ける)。
- **REQ-settings-profile-082**: The system shall `CoachAvailabilityPolicy`([[mentoring]] 所有)の `viewAny` / `create` / `update` / `delete` を `/settings/availability/*` ルートでも利用する。本 Feature では新設しない。

### 非機能要件

- **NFR-settings-profile-001**: The system shall 状態変更を伴うすべての Action(`Profile\UpdateAction` / `Avatar\StoreAction` / `Avatar\DestroyAction` / `Availability\StoreAction` / `Availability\UpdateAction` / `Availability\DestroyAction`)を `DB::transaction()` で囲む。**`SelfWithdrawAction` は提供しない**(v3 撤回)。
- **NFR-settings-profile-002**: The system shall ドメイン例外を `app/Exceptions/SettingsProfile/` 配下に配置する: `AvatarStorageFailedException`(500)のみ。**`AdminSelfWithdrawForbiddenException` は提供しない**(v3 撤回、自己退会動線そのものがない)。
- **NFR-settings-profile-003**: The system shall すべての画面を `layouts.app` 上で描画し、Wave 0b の Design System コンポーネントを再利用する。
- **NFR-settings-profile-004**: The system shall アバター画像を `Storage::disk('public')` 配下に保存し、`public/storage` シンボリックリンク経由で `/storage/avatars/{ulid}.{ext}` として配信する。
- **NFR-settings-profile-006**: The system shall すべての日本語エラーメッセージを `lang/ja/validation.php` / `lang/ja/auth.php` およびドメイン例外コンストラクタで定義する。
- **NFR-settings-profile-007**: The system shall アバター画像のクライアント側検証(MIME / サイズ)は素の JS(`resources/js/settings-profile/avatar.js`)で実装し、サーバ側 MIME 検証と二重化する。
- **NFR-settings-profile-008**: The system shall すべてのフォーム送信を CSRF トークンで保護する。

### 機能要件 — Google Calendar 連携 UI (v3.6 追加、coach のみ)

> v3.6 追加 (2026-05-18): mentoring spec の REQ-mentoring-100〜114 と連動。コーチのプロフィール画面に「Googleカレンダー連携」セクションを追加し、連携 / 状態表示 / 解除を 1 画面で完結させる。OAuth フロー本体は [[mentoring]] が所有し、本 Feature は導線のみ提供する。
>
> v3.7 (2026-05-18) で、本セクションは **面談設定タブ(`?tab=meeting`)内に移動** し、面談可能時間枠カレンダーとセットで描画する。プロフィールタブ(`?tab=profile`)には表示しない。

- **REQ-settings-profile-090**: When coach が `GET /settings/profile?tab=meeting` を表示した際, the system shall タブ上部に「Googleカレンダー連携」セクションを描画し、(a) 未連携時は「Googleカレンダーと連携する」ボタンを表示 (`settings.google-calendar.redirect` ルートへ遷移)、(b) 連携済時は「連携中: {gmail アドレス or `primary`}」+ 「連携を解除」ボタンを表示する。admin / student は本タブ自体を非表示とする。
- **REQ-settings-profile-091**: When coach が「Googleカレンダーと連携する」ボタンを押下した際, the system shall [[mentoring]] の `FetchAuthUrlAction` を経由して Google OAuth 認可 URL へ 302 redirect する。`state` には現プロフィール画面 URL (`/settings/profile?tab=meeting`) を redirect 戻り先として埋め込む。
- **REQ-settings-profile-092**: When coach が「連携を解除」ボタンを押下した際, the system shall [[mentoring]] の `DestroyAction` を呼び出して Google 側トークンを revoke + DB 行を soft delete し、`/settings/profile?tab=meeting` へ redirect + Flash「Googleカレンダー連携を解除しました。」を表示する。
- **REQ-settings-profile-093**: The system shall 本セクションの UI を `views/settings/_partials/tab-meeting.blade.php` 内に埋め込み、`role === coach && CoachGoogleCredential` 有無で分岐する条件分岐を集約する。受講生 / 管理者の Blade 描画には影響しない。

## スコープ外

- **自己退会動線**(v3 で完全撤回) — 退会は admin に依頼するオペレーション、LMS 内に動線なし。`/settings/withdraw` / `SelfWithdrawController` / `SelfWithdrawAction` / `SelfWithdrawRequest` / `AdminSelfWithdrawForbiddenException` / `withdrawSelf` Policy / `tab-withdraw.blade.php` を一切作らない
- **admin 経由の他者プロフィール編集 / ロール変更 / 強制退会** — [[user-management]] が所有
- **メールアドレスの変更動線** — admin 経由のみ
- **自己ロール変更** — admin 経由のみ
- **2FA / IP 制限 / ログイン履歴の詳細管理** — `product.md` スコープ外
- **API トークン(Sanctum PAT)の発行 / 失効 UI** — 採用しない
- **学習時間目標(`LearningHourTarget`)の編集 UI** — [[learning]] が `/learning/enrollments/{enrollment}/hour-target` で所有
- **`CoachAvailability` モデル / Migration / Policy 定義** — [[mentoring]] が所有、本 Feature は **編集 UI のみ**
- **通知設定 UI(種別 × channel ON/OFF)** — 採用しない([[notification]] が全通知 DB+Mail 固定送信)
- **プラン情報表示 / 追加面談購入 CTA**(v3) — [[dashboard]] の「プラン情報パネル」に集約、本 Feature には持ち込まない
- **アバター画像の自動リサイズ / 圧縮** — 採用しない、2MB 上限のみで運用
- **Google OAuth のトークン保存 / API 呼び出し / event 作成・削除** — [[mentoring]] が所有 (`coach_google_credentials` テーブル + `App\Services\Google\*` + `App\UseCases\CoachGoogleCredential\*`)。本 Feature は連携 / 解除ボタンの UI 導線のみ提供

## 関連 Feature

- **依存元**(本 Feature を利用する):
  - [[mentoring]]: 本 Feature の `/settings/availability` 画面が `CoachAvailability` を CRUD して coach の空き枠を整備
  - [[dashboard]]: サイドバー「設定」アイテムから `settings.profile.edit` ルートへ遷移
- **依存先**(本 Feature が前提とする):
  - [[auth]]: `User` モデル、`UserRole` / `UserStatus` Enum、`auth` middleware、Fortify Password Update 機構
  - [[mentoring]]: `CoachAvailability` Model / Migration、`CoachAvailabilityPolicy`、`role:coach` middleware
  - [[learning]]: `/learning/enrollments/{enrollment}/hour-target` URL(link 誘導先)
