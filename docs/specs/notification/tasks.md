# notification タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> **v3 改修反映**: `MeetingRequested/Approved/Rejected` 撤回 / `MeetingReserved` 新規(コーチ宛のみ) / chat 双方向通知 / `StagnationReminder` 撤回 / `PlanExpireSoon` 撤回。
> **2026-05-18 UX 改修**: 通知ベルクリック時の UI を ドロップダウン → 通知ポップオーバー(ベル横アンカー Popover、Stripe / Jira 風) に変更。`Notification\DropdownAction` → `PopoverAction`、`_partials/dropdown.blade.php` → `_partials/notification-popover.blade.php`、ルート `notifications.dropdown` → `notifications.popover`、テストは `DropdownTest` → `PopoverTest`、`realtime.js` はポップオーバー open 時 DOM prepend に拡張。
> 関連要件 ID は `requirements.md` の `REQ-notification-NNN` / `NFR-notification-NNN` を参照。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Enum & Model

- [x] migration: `change_notifications_id_to_ulid`(Laravel 標準 `notifications` テーブルの `id` を ULID 型に変更、`(notifiable_type, notifiable_id, read_at)` 複合 INDEX + `created_at` 単体 INDEX 追加)(REQ-notification-001, REQ-notification-002)
- [x] migration: `create_admin_announcements_table`(ULID 主キー / `created_by_user_id` ulid FK / `title` string 200 / `body` text / `target_type` string / `target_certification_id` ulid nullable FK / `target_user_id` ulid nullable FK / `dispatched_count` unsignedInteger default 0 / `dispatched_at` datetime nullable / `(target_type, dispatched_at)` 複合 INDEX)(REQ-notification-010)
- [x] Enum: `App\Enums\AnnouncementTargetType`(`AllStudents` / `Certification` / `User`、`label()`)(REQ-notification-011)
- [x] Enum: `App\Enums\MeetingReminderWindow`(`Eve` / `OneHourBefore`、`label()`)(REQ-notification-073)
- [x] Model: `App\Models\Announcement`(`HasUlids` + `HasFactory`、`belongsTo(User, 'created_by_user_id', 'createdBy')` / `belongsTo(Certification, 'target_certification_id', 'targetCertification')` / `belongsTo(User, 'target_user_id', 'targetUser')`)(REQ-notification-010)
- [x] Factory: `AnnouncementFactory`(`allStudents()` / `forCertification` / `forUser` / `dispatched()` state)

## Step 2: Notification 共通基盤

- [x] abstract class: `App\Notifications\BaseNotification`(`Illuminate\Notifications\Notification` 継承 + `implements ShouldQueue`、`__construct` で `$this->id = (string) Str::ulid()` + `via($notifiable)` で `['database', 'mail']` 返却、Broadcasting 有効時 `'broadcast'` 追加)(REQ-notification-020, REQ-notification-021)

## Step 3: Notification クラス(v3 で 8 種類に縮減)

各クラスは `extends BaseNotification` + `use Queueable`、`toDatabase` / `toMail`(`MailMessage`、件名 `【Certify LMS】...`) / `broadcastOn` / `broadcastWith` 実装。

- [x] `App\Notifications\Chat\ChatMessageReceivedNotification`(コンストラクタ `ChatMessage $message, bool $mailEnabled = true`、**v3 で `$mailEnabled` 追加**(コーチ間は false))(REQ-notification-030)
- [x] `App\Notifications\QaBoard\QaReplyReceivedNotification`(REQ-notification-040)
- [x] **`App\Notifications\Mentoring\MeetingReservedNotification`(v3 新規、コーチ宛のみ)** — コンストラクタ `Meeting $meeting`、Mail 本文に `scheduled_at` + 受講生名 + `topic` + `meeting_url_snapshot`(REQ-notification-070)
- [x] `App\Notifications\Mentoring\MeetingCanceledNotification`(コンストラクタ `Meeting $meeting, User $actor`、actor で文面分岐)(REQ-notification-071)
- [x] `App\Notifications\Mentoring\MeetingReminderNotification`(コンストラクタ `Meeting $meeting, MeetingReminderWindow $window`)(REQ-notification-072)
- [x] `App\Notifications\Announcement\AnnouncementNotification`(コンストラクタ `Announcement $announcement`)(REQ-notification-085)

### 明示的に持たない Notification クラス(v3 撤回)

- `CompletionApprovedNotification`(受講生の自己操作で完結、遷移先画面の PDF DL リンクで通知冗長)
- `MeetingRequestedNotification` / `MeetingApprovedNotification` / `MeetingRejectedNotification`(mentoring 自動割当によりフロー撤回)
- `PlanExpireSoonNotification`(MVP 外)
- `StagnationReminderNotification`(滞留検知 v3 撤回)

## Step 4: Policy & FormRequest

- [x] `App\Policies\NotificationPolicy`(`view` / `update`、自分宛のみ true)(REQ-notification-111)
- [x] `App\Policies\AnnouncementPolicy`(`viewAny` / `view` / `create`、admin のみ)(REQ-notification-113)
- [x] `AuthServiceProvider` 登録
- [x] `App\Http\Requests\Notification\IndexRequest`(`tab: in:all,unread` / `page`)
- [x] `App\Http\Requests\Announcement\StoreRequest`(`title` / `body` / `target_type` / `target_certification_id required_if` / `target_user_id required_if`)

## Step 5: HTTP 層

- [x] `App\Http\Controllers\NotificationController`(`index` / `show` / `popover` / `markAsRead` / `markAllAsRead`)
- [x] `App\Http\Controllers\AnnouncementController`(`index` / `create` / `store` / `show`)
- [x] `routes/web.php`:
  - `auth` group: `notifications.index` / `notifications.show` / `notifications.popover` / `notifications.markAsRead` / `notifications.markAllAsRead`
  - `auth + role:admin` group: `Route::resource('announcements')->only(['index', 'create', 'store', 'show'])`
- [x] `routes/channels.php`: `Broadcast::channel('notifications.{userId}', fn (User $user, $userId) => (string) $user->id === $userId)`

## Step 6: Action(UseCase)

### 通知発火ラッパー Action 群(v3 で 8 種類)

- [x] **`NotifyChatMessageReceivedAction`(v3 で双方向化)** — sender role で相手方解決、受講生→全コーチ DB+Mail / コーチ→受講生 DB+Mail / コーチ→他コーチ **Database のみ**、担当コーチ未割当 skip、`graduated/withdrawn` skip(REQ-notification-030〜033)
- [x] `NotifyQaReplyReceivedAction`(自己回答 skip)(REQ-notification-040)
- [x] **`NotifyMeetingReservedAction`(v3 新規)** — コーチ宛のみ dispatch、受講生宛は発火しない(REQ-notification-070)
- [x] `NotifyMeetingCanceledAction`(actor で相手方解決)(REQ-notification-071)
- [x] `NotifyMeetingReminderAction`(`(meeting_id, window)` 重複排除 + 受講生 + コーチ両方通知)(REQ-notification-072, NFR-notification-007)
- [x] `NotifyAnnouncementAction`(受信者 status 検査 + 通知)(REQ-notification-082)

### 明示的に持たないラッパー Action(v3 撤回)

- `NotifyCompletionApprovedAction`(受講生の自己操作で完結、遷移先画面の PDF DL リンクで通知冗長)
- `NotifyMeetingRequestedAction` / `NotifyMeetingApprovedAction` / `NotifyMeetingRejectedAction`(mentoring 申請承認フロー撤回)
- `NotifyStagnationReminderAction` / `NotifyPlanExpireSoonAction`

### 通知一覧操作 Action 群

- [x] `Notification\IndexAction`(tab フィルタ + paginate)
- [x] `Notification\MarkAsReadAction`
- [x] `Notification\MarkAllAsReadAction`
- [x] `Notification\PopoverAction`(最新 20 件 + tab フィルタ(全件 / 未読) + 未読件数、ポップオーバー内容取得用、`IndexAction` とロジック共有)

### 管理者お知らせ Action 群

- [x] `Announcement\IndexAction`
- [x] `Announcement\StoreAction`(target 整合性検査 + Announcement INSERT + 対象 User 解決 + 各 User へ NotifyAnnouncementAction 呼出 + `dispatched_count` / `dispatched_at` UPDATE、1 トランザクション)
- [x] `Announcement\ShowAction`

## Step 7: ドメイン例外

- [x] `app/Exceptions/Notification/AnnouncementInvalidTargetException`(HTTP 422)
- [x] `app/Exceptions/Notification/AnnouncementTargetNotFoundException`(HTTP 404)

## Step 8: View Composer & Blade

- [x] `app/View/Composers/NotificationBadgeComposer`(未読件数 + 99+ 表示、未認証時 0)
- [x] `AppServiceProvider::boot()` に Composer 登録
- [x] `views/notifications/index.blade.php`(`<x-tabs>` で 全件 / 未読切替、行クリックで markAsRead + 遷移、「全件既読」ボタン)
- [x] `views/notifications/show.blade.php`(通知詳細ページ。遷移先となる業務画面を持たない自己完結通知=お知らせ等の本文全文表示、開封時に既読化)
- [x] `views/notifications/_partials/notification-row.blade.php`(種別アイコン + タイトル + プレビュー + 経過時間 + 未読バッジ)
- [x] `views/notifications/_partials/notification-popover.blade.php`(**ベル横アンカー Popover**、**素の JS** で open/tab/items/loading 管理、`absolute right-0 mt-2 w-[400px] max-w-[calc(100vw-1rem)] max-h-[70vh] rounded-lg shadow-lg border bg-white`、Tailwind `transition opacity, translate-y duration-150` で `opacity-0 -translate-y-1` ↔ `opacity-100 translate-y-0` フェード + 微スライド、ESC / 外側クリック / フッターリンクで close、ヘッダ: 全件/未読タブ + 全件既読ボタン、ボディ: 最新 20 件(内部スクロール)、フッター: 「すべての通知を見る →」リンク)
- [x] `resources/js/notification/notification-popover.js`(**素の JS、frontend-javascript.md 規約に合わせて Alpine.js 不採用**: open/tab/items 管理 + タブ切替 `GET /notifications/popover?tab=...` fetch + 行クリック既読化 + 遷移前 close + `bumpBadge(delta)` export 関数を realtime.js から呼ぶ)
- [x] `views/admin/announcements/index.blade.php` / `create.blade.php` / `show.blade.php`
- [x] `views/admin/announcements/_partials/target-fields.blade.php`(target_type ラジオで表示切替、素の JS)
- [x] `views/layouts/_partials/topbar.blade.php` への通知ベル追記(ベルボタンを Popover トリガーとして配置、素の JS スコープ内に data-attribute で制御、未読バッジ重ね)
- [x] `views/layouts/app.blade.php` の TopBar 内へ `@include('notifications._partials.notification-popover')` を含める(ベル横アンカーを成立させるため topbar scope 内に配置)
- [x] `views/layouts/_partials/sidebar-{admin,coach,student}.blade.php` の「通知」`<x-nav.item>` に `:badge="$notificationBadge ?? 0"` 追記
- [x] `resources/js/admin/announcement-form.js`(target_type ラジオ change で表示切替)

## Step 9: Schedule Command

- [x] `App\Console\Commands\Notification\SendMeetingRemindersCommand`(signature: `notifications:send-meeting-reminders {--window=eve}`、`eve` なら翌日 meeting / `one_hour_before` なら +55..65min 範囲、各 meeting に対し `NotifyMeetingReminderAction` 呼出)(REQ-notification-072, NFR-notification-007)
- [x] `app/Console/Kernel.php::schedule()`:
  - `->command('notifications:send-meeting-reminders --window=eve')->dailyAt('18:00')`
  - `->command('notifications:send-meeting-reminders --window=one_hour_before')->everyFiveMinutes()`

### 明示的に持たない Command(v3 撤回)

- `SendStagnationRemindersCommand`(滞留検知 v3 撤回)
- `SendPlanExpireSoonNotificationsCommand`(MVP 外)

## Step 10: 各 Feature の起点呼出(協調修正)

### v3 で **追加 / 変更** する通知発火

- [x] [[chat]] `App\UseCases\Chat\StoreMessageAction` の `DB::afterCommit` 内に `NotifyChatMessageReceivedAction` を DI 経由で呼出 (sender role 判定は Notify*Action 側で実施、双方向通知)
- [x] [[qa-board]] `App\UseCases\QaReply\StoreAction` に `NotifyQaReplyReceivedAction` を DI 経由で組込
- [x] **[[mentoring]] `App\UseCases\Meeting\StoreAction`** に `NotifyMeetingReservedAction` を DI、`DB::afterCommit` 内で `($this->notify)($meeting)` を呼出(**コーチ宛のみ**)
- [x] [[mentoring]] `App\UseCases\Meeting\CancelAction` 内に `NotifyMeetingCanceledAction` を DI 経由で組込

### v3 で **削除** する通知発火

- [ ] [[enrollment]] の旧 `RequestCompletionAction` / `Admin\Enrollment\ApproveCompletionAction` ベースの通知発火を削除(代わりに `ReceiveCertificateAction` 起点)
- [ ] [[mentoring]] の旧 `Meeting\StoreAction`(申請) / `ApproveAction` / `RejectAction` ベースの通知発火を削除(v3 で自動割当)
- [ ] mentoring の独自 Reminder Command を削除(本 Feature の `SendMeetingRemindersCommand` に統合)

### [[settings-profile]] 協調修正(変更なし、引き続き通知設定 UI なし)

- [ ] [[settings-profile]] spec / 実装に **通知設定タブを実装しない**(全通知 Database + Mail 固定送信、ユーザー設定 UI なし)

## Step 11: Broadcasting（Pusher リアルタイム）

- [x] `.env.example` に `BROADCAST_DRIVER=log`(Wave 0b で整備済)/ `PUSHER_APP_*`(Wave 0b で整備済、`.env` 切替で `pusher` 有効化)
- [x] 全 7 個の Notification クラスに `broadcastOn(): PrivateChannel("notifications.{$notifiable->id}")` + `toBroadcast()` 実装(BaseNotification で broadcast チャネル追加判定、各クラスで実装)
- [x] `resources/js/notification/realtime.js`(Echo subscribe + TopBar バッジ +1、**通知ポップオーバーが open 状態であれば `notification-popover.js` の `bumpBadge` 経由で refresh**)
- [x] `resources/js/app.js` から `realtime.js` import + `<meta name="auth-user-id" content="{{ auth()->id() }}">` を `layouts/app.blade.php` に追加

## Step 12: テスト

### Notification クラス(`tests/Unit/Notifications/`)

- [-] `BaseNotificationTest` / 各 Notification の toDatabase/toMail 単体テストは、Feature 層 (ラッパー Action / HTTP) でカバー済のため省略 (重複テスト回避)

### ラッパー Action(`tests/Feature/UseCases/Notification/`)

- [x] **`NotifyChatMessageReceivedActionTest`(v3)** — 受講生→コーチ全員 DB+Mail / コーチ→受講生 DB+Mail / コーチ→他コーチ DB only / withdrawn recipient skip
- [x] `NotifyQaReplyReceivedActionTest`(既存、自己回答 skip / withdrawn skip)
- [x] **`NotifyMeetingReservedActionTest` 相当** — 既存 `tests/Feature/UseCases/Meeting/StoreActionTest.php` でカバー(Meeting StoreAction 経由)
- [x] `NotifyMeetingCanceledActionTest` 相当 — 既存 `tests/Feature/UseCases/Meeting/CancelActionTest.php` でカバー
- [x] `NotifyMeetingReminderActionTest`(eve / one_hour_before / 重複排除)
- [x] `NotifyAnnouncementActionTest`(in_progress 通知 / withdrawn skip / graduated skip)

### 明示的に持たないテスト(v3 撤回)

- `NotifyCompletionApprovedActionTest`
- `NotifyMeetingRequestedActionTest` / `NotifyMeetingApprovedActionTest` / `NotifyMeetingRejectedActionTest`
- `NotifyStagnationReminderActionTest`

### 通知一覧 / 管理者お知らせ HTTP(`tests/Feature/Http/Notification/`、`Admin/Announcement/`)

- [x] `Notification/{Index,MarkAsRead,MarkAllAsRead,Popover}Test.php`(`PopoverTest`: tab フィルタ別件数 / 自分宛のみ取得 / 認証)
- [x] `Admin/Announcement/{Index,Store,Show}Test.php`(target_type 別配信件数 / 不整合 422 / 非 admin 403)

### Schedule Command(`tests/Feature/Commands/Notification/`)

- [x] `SendMeetingRemindersCommandTest`(eve 範囲 / one_hour_before 範囲 / 重複起動 skip / 不正 window で INVALID)

### Policy / View Composer

- [x] `NotificationPolicyTest` / `AnnouncementPolicyTest`
- [x] `NotificationBadgeComposerTest`(未認証 0 / 未読件数集計)

## Step 12.5: Factory + Seeder

- [ ] **Seeder 不要**: 本 Feature の `DatabaseNotification` 行は他 Feature(`enrollment`, `mentoring`, `chat`, `qa-board`)の Action 実行時に副作用として INSERT されるため、専用 Seeder は提供しない(`structure.md` Seeder 規約「④ 集計・読み取り専用系」分類)
- [ ] ただし TopBar ベル / 通知一覧画面の動作確認には **既読・未読・各種通知タイプの混在** が必要。これは他 Feature の Seeder(`ChatSeeder` / `MentoringSeeder` / `EnrollmentSeeder` 等)が Action 経由で通知を発火することで自動的に揃う想定

## Step 13: 動作確認 & 整形

- [x] `sail bin pint --dirty` 整形
- [x] `sail artisan test` 全件 pass (1016 tests / 2252 assertions)
- [x] `sail artisan migrate:fresh --seed` + Playwright E2E:
  - [x] admin で `/admin/announcements/create` → 「メンテナンスのお知らせ E2E」配信 → 9 in_progress 受講生宛 DB INSERT + Show 画面で「9 件 / 2026/05/19 00:39」表示
  - [x] student で TopBar ベル「(1 件未読)」+ サイドバー「通知 1」バッジ表示 / `/notifications` 全件タブで announcement + 既存 chat 通知が時系列降順
  - [x] ベルクリックで通知ポップオーバー open + フェードイン + 全件/未読タブ切替で内容差し替え + 「全件既読」ボタンで badge → hidden + DB read_at 設定
  - [x] Schedule Command `notifications:send-meeting-reminders --window=eve` → 翌日 reserved 面談に対し student + coach 両方に DB 通知 INSERT
  - [x] coach で `/notifications` の reminder クリック → markAsRead + `/meetings/{id}` 遷移 + DB read_at 設定
  - [x] coach で `/admin/announcements` → 403 (Policy 拒否)
  - [x] 未認証で `/admin/announcements` → `/login` redirect
- [ ] `sail artisan migrate:fresh --seed` + ブラウザシナリオ:
  - [ ] 受講生で chat 送信 → コーチ全員に DB + Mail 通知(Mailpit 確認)
  - [ ] コーチで chat 返信 → 受講生に DB + Mail、他コーチに **DB only** (Mailpit に他コーチ向けメールが届かないこと)
  - [ ] 受講生で **「修了証を受け取る」**(v3 自己発火) → 通知は発火しない(画面遷移先で PDF DL リンクを提示するため、通知は冗長)
  - [ ] 受講生で面談予約 → **コーチ宛のみ**に DB + Mail、受講生宛は **0 件**(予約 UI で即時確認のため)
  - [ ] 受講生で面談キャンセル → コーチに DB + Mail
  - [ ] コーチで面談キャンセル → 受講生に DB + Mail
  - [ ] admin で全 InProgress 受講生宛にお知らせ配信 → 各受講生に DB + Mail
  - [ ] 通知行クリック → 既読化 + 関連画面遷移
  - [ ] 「全件既読」一括既読化
  - [ ] サイドバー「通知」バッジ件数連動更新
  - [ ] TopBar ベルクリックで通知ポップオーバーがベル右下にフェードイン + 微スライド、ESC / 外側クリック / フッターリンク遷移で close
  - [ ] ポップオーバー内タブ切替(全件 / 未読)で内容差し替え(`GET /notifications/popover?tab=...` fetch)
  - [ ] ポップオーバー内行クリックで既読化 + 該当画面遷移 + ポップオーバー自動 close
  - [ ] ポップオーバー内「全件既読」ボタンで全件既読化
  - [ ] ポップオーバー内フッター「すべての通知を見る →」リンクで `/notifications` フルページへ遷移
  - [ ] モバイル幅(< sm)でもポップオーバーが画面端からはみ出さず、`max-w-[calc(100vw-1rem)]` で収まる
- [ ] Schedule Command 手動実行:
  - [ ] `sail artisan notifications:send-meeting-reminders --window=eve` → 翌日 Reserved 面談に対し受講生 + コーチ両方に「面談リマインド(前日)」追加
  - [ ] 2 回目実行で重複しない
  - [ ] `--window=one_hour_before` → +55..65min 範囲 Meeting に対し両方に「面談リマインド(1h 前)」
- [ ] Pusher リアルタイム配信 動作確認(`BROADCAST_DRIVER=pusher`):
  - [ ] Tab A で受講生、Tab B でコーチが chat 送信 → Tab A の TopBar ベルバッジ +1、**通知ポップオーバー open 中なら先頭に行 prepend**(リロード不要)、閉じていればバッジのみ更新
