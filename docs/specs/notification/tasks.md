# notification タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> **v3 改修反映**: `CompletionApprovedNotification` 発火元変更 / `MeetingRequested/Approved/Rejected` 撤回 / `MeetingReserved` 新規(コーチ宛のみ) / chat 双方向通知 / `StagnationReminder` 撤回 / `PlanExpireSoon` 撤回。
> 関連要件 ID は `requirements.md` の `REQ-notification-NNN` / `NFR-notification-NNN` を参照。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Enum & Model

- [ ] migration: `change_notifications_id_to_ulid`(Laravel 標準 `notifications` テーブルの `id` を ULID 型に変更、`(notifiable_type, notifiable_id, read_at)` 複合 INDEX + `created_at` 単体 INDEX 追加)(REQ-notification-001, REQ-notification-002)
- [ ] migration: `create_admin_announcements_table`(ULID 主キー / `created_by_user_id` ulid FK / `title` string 200 / `body` text / `target_type` string / `target_certification_id` ulid nullable FK / `target_user_id` ulid nullable FK / `dispatched_count` unsignedInteger default 0 / `dispatched_at` datetime nullable / SoftDeletes / `(target_type, dispatched_at)` 複合 INDEX)(REQ-notification-010)
- [ ] Enum: `App\Enums\AdminAnnouncementTargetType`(`AllStudents` / `Certification` / `User`、`label()`)(REQ-notification-011)
- [ ] Enum: `App\Enums\MeetingReminderWindow`(`Eve` / `OneHourBefore`、`label()`)(REQ-notification-073)
- [ ] Model: `App\Models\AdminAnnouncement`(`HasUlids` + `HasFactory` + `SoftDeletes`、`belongsTo(User, 'created_by_user_id', 'createdBy')` / `belongsTo(Certification, 'target_certification_id', 'targetCertification')` / `belongsTo(User, 'target_user_id', 'targetUser')`)(REQ-notification-010)
- [ ] Factory: `AdminAnnouncementFactory`(`allStudents()` / `forCertification` / `forUser` / `dispatched()` state)

## Step 2: Notification 共通基盤

- [ ] abstract class: `App\Notifications\BaseNotification`(`Illuminate\Notifications\Notification` 継承 + `implements ShouldQueue`、`__construct` で `$this->id = (string) Str::ulid()` + `via($notifiable)` で `['database', 'mail']` 返却、Broadcasting 有効時 `'broadcast'` 追加)(REQ-notification-020, REQ-notification-021)

## Step 3: Notification クラス(v3 で 8 種類に縮減)

各クラスは `extends BaseNotification` + `use Queueable`、`toDatabase` / `toMail`(`MailMessage`、件名 `【Certify LMS】...`) / `broadcastOn` / `broadcastWith` 実装。

- [ ] `App\Notifications\ChatMessageReceivedNotification`(コンストラクタ `ChatMessage $message, bool $mailEnabled = true`、**v3 で `$mailEnabled` 追加**(コーチ間は false))(REQ-notification-030)
- [ ] `App\Notifications\QaReplyReceivedNotification`(REQ-notification-040)
- [ ] `App\Notifications\MockExamGradedNotification`(REQ-notification-050)
- [ ] **`App\Notifications\CompletionApprovedNotification`(v3 で発火元変更)** — コンストラクタ `Enrollment $enrollment, Certificate $certificate`、Mail 本文に `route('certificates.download', $certificate)` 含む(REQ-notification-060)
- [ ] **`App\Notifications\MeetingReservedNotification`(v3 新規、コーチ宛のみ)** — コンストラクタ `Meeting $meeting`、Mail 本文に `scheduled_at` + 受講生名 + `topic` + `meeting_url_snapshot`(REQ-notification-070)
- [ ] `App\Notifications\MeetingCanceledNotification`(コンストラクタ `Meeting $meeting, User $actor`、actor で文面分岐)(REQ-notification-071)
- [ ] `App\Notifications\MeetingReminderNotification`(コンストラクタ `Meeting $meeting, MeetingReminderWindow $window`)(REQ-notification-072)
- [ ] `App\Notifications\AdminAnnouncementNotification`(コンストラクタ `AdminAnnouncement $announcement`)(REQ-notification-085)

### 明示的に持たない Notification クラス(v3 撤回)

- `MeetingRequestedNotification` / `MeetingApprovedNotification` / `MeetingRejectedNotification`(mentoring 自動割当によりフロー撤回)
- `PlanExpireSoonNotification`(MVP 外)
- `StagnationReminderNotification`(滞留検知 v3 撤回)

## Step 4: Policy & FormRequest

- [ ] `App\Policies\NotificationPolicy`(`view` / `update`、自分宛のみ true)(REQ-notification-111)
- [ ] `App\Policies\AdminAnnouncementPolicy`(`viewAny` / `view` / `create`、admin のみ)(REQ-notification-113)
- [ ] `AuthServiceProvider` 登録
- [ ] `App\Http\Requests\Notification\IndexRequest`(`tab: in:all,unread` / `page`)
- [ ] `App\Http\Requests\Admin\AdminAnnouncement\StoreRequest`(`title` / `body` / `target_type` / `target_certification_id required_if` / `target_user_id required_if`)

## Step 5: HTTP 層

- [ ] `App\Http\Controllers\NotificationController`(`index` / `dropdown` / `markAsRead` / `markAllAsRead`)
- [ ] `App\Http\Controllers\Admin\AdminAnnouncementController`(`index` / `create` / `store` / `show`)
- [ ] `routes/web.php`:
  - `auth` group: `notifications.index` / `notifications.dropdown` / `notifications.markAsRead` / `notifications.markAllAsRead`
  - `auth + role:admin` group: `Route::resource('announcements')->only(['index', 'create', 'store', 'show'])`
- [ ] `routes/channels.php`: `Broadcast::channel('notifications.{userId}', fn (User $user, $userId) => (string) $user->id === $userId)`

## Step 6: Action(UseCase)

### 通知発火ラッパー Action 群(v3 で 8 種類)

- [ ] **`NotifyChatMessageReceivedAction`(v3 で双方向化)** — sender role で相手方解決、受講生→全コーチ DB+Mail / コーチ→受講生 DB+Mail / コーチ→他コーチ **Database のみ**、担当コーチ未割当 skip、`graduated/withdrawn` skip(REQ-notification-030〜033)
- [ ] `NotifyQaReplyReceivedAction`(自己回答 skip)(REQ-notification-040)
- [ ] `NotifyMockExamGradedAction`(受験者通知)(REQ-notification-050)
- [ ] **`NotifyCompletionApprovedAction`(v3 で発火元変更)** — 受講生通知、シグネチャ `__invoke(Enrollment, Certificate)`、**[[enrollment]] の `ReceiveCertificateAction` から呼ばれる**(旧 `ApproveCompletionAction` ではない)(REQ-notification-060)
- [ ] **`NotifyMeetingReservedAction`(v3 新規)** — コーチ宛のみ dispatch、受講生宛は発火しない(REQ-notification-070)
- [ ] `NotifyMeetingCanceledAction`(actor で相手方解決)(REQ-notification-071)
- [ ] `NotifyMeetingReminderAction`(`(meeting_id, window)` 重複排除 + 受講生 + コーチ両方通知)(REQ-notification-072, NFR-notification-007)
- [ ] `NotifyAdminAnnouncementAction`(受信者 status 検査 + 通知)(REQ-notification-082)

### 明示的に持たないラッパー Action(v3 撤回)

- `NotifyMeetingRequestedAction` / `NotifyMeetingApprovedAction` / `NotifyMeetingRejectedAction`(mentoring 申請承認フロー撤回)
- `NotifyStagnationReminderAction` / `NotifyPlanExpireSoonAction`

### 通知一覧操作 Action 群

- [ ] `Notification\IndexAction`(tab フィルタ + paginate)
- [ ] `Notification\MarkAsReadAction`
- [ ] `Notification\MarkAllAsReadAction`
- [ ] `Notification\DropdownAction`(最近 5 件 + 未読件数)

### 管理者お知らせ Action 群

- [ ] `Admin\AdminAnnouncement\IndexAction`
- [ ] `Admin\AdminAnnouncement\StoreAction`(target 整合性検査 + Announcement INSERT + 対象 User 解決 + 各 User へ NotifyAdminAnnouncementAction 呼出 + `dispatched_count` / `dispatched_at` UPDATE、1 トランザクション)
- [ ] `Admin\AdminAnnouncement\ShowAction`

## Step 7: ドメイン例外

- [ ] `app/Exceptions/Notification/AdminAnnouncementInvalidTargetException`(HTTP 422)
- [ ] `app/Exceptions/Notification/AdminAnnouncementTargetNotFoundException`(HTTP 404)

## Step 8: View Composer & Blade

- [ ] `app/View/Composers/NotificationBadgeComposer`(未読件数 + 99+ 表示、未認証時 0)
- [ ] `AppServiceProvider::boot()` に Composer 登録
- [ ] `views/notifications/index.blade.php`(`<x-tabs>` で 全件 / 未読切替、行クリックで markAsRead + 遷移、「全件既読」ボタン)
- [ ] `views/notifications/_partials/notification-row.blade.php`(種別アイコン + タイトル + プレビュー + 経過時間 + 未読バッジ)
- [ ] `views/notifications/_partials/dropdown.blade.php`(最近 5 件 + 「すべての通知」リンク)
- [ ] `views/admin/announcements/index.blade.php` / `create.blade.php` / `show.blade.php`
- [ ] `views/admin/announcements/_partials/target-fields.blade.php`(target_type ラジオで表示切替、素の JS)
- [ ] `views/layouts/_partials/topbar.blade.php` への通知ベル追記
- [ ] `views/layouts/_partials/sidebar-{admin,coach,student}.blade.php` の「通知」`<x-nav.item>` に `:badge="$notificationBadge['count']"` 追記
- [ ] `resources/js/admin/announcement-form.js`(target_type ラジオ change で表示切替)

## Step 9: Schedule Command

- [ ] `App\Console\Commands\Notification\SendMeetingRemindersCommand`(signature: `notifications:send-meeting-reminders {--window=eve}`、`eve` なら翌日 meeting / `one_hour_before` なら +55..65min 範囲、各 meeting に対し `NotifyMeetingReminderAction` 呼出)(REQ-notification-072, NFR-notification-007)
- [ ] `app/Console/Kernel.php::schedule()`:
  - `->command('notifications:send-meeting-reminders --window=eve')->dailyAt('18:00')`
  - `->command('notifications:send-meeting-reminders --window=one_hour_before')->everyFiveMinutes()`

### 明示的に持たない Command(v3 撤回)

- `SendStagnationRemindersCommand`(滞留検知 v3 撤回)
- `SendPlanExpireSoonNotificationsCommand`(MVP 外)

## Step 10: 各 Feature の起点呼出(協調修正)

### v3 で **追加 / 変更** する通知発火

- [ ] [[chat]] `App\UseCases\Chat\StoreMessageAction` の `DB::afterCommit` 内に `app(NotifyChatMessageReceivedAction::class)($message)` を組み込む(sender role 判定は Notify*Action 側で実施、双方向通知)
- [ ] [[qa-board]] `App\UseCases\QaReply\StoreAction` に `app(NotifyQaReplyReceivedAction::class)($reply)` 組込
- [ ] [[mock-exam]] `App\UseCases\MockExamSession\SubmitAction` の `DB::afterCommit` に `app(NotifyMockExamGradedAction::class)($session)` 組込
- [ ] **[[enrollment]] `App\UseCases\Enrollment\ReceiveCertificateAction`(v3 新規)** に `NotifyCompletionApprovedAction` を DI、`DB::afterCommit` 内で `($this->notify)($enrollment, $certificate)` を呼出
- [ ] **[[mentoring]] `App\UseCases\Meeting\ReserveAction`(v3 新規)** に `NotifyMeetingReservedAction` を DI、`DB::afterCommit` 内で `($this->notify)($meeting)` を呼出(**コーチ宛のみ**)
- [ ] [[mentoring]] `App\UseCases\Meeting\CancelAction` 内に `app(NotifyMeetingCanceledAction::class)($meeting, $actor)` 組込

### v3 で **削除** する通知発火

- [ ] [[enrollment]] の旧 `RequestCompletionAction` / `Admin\Enrollment\ApproveCompletionAction` ベースの通知発火を削除(代わりに `ReceiveCertificateAction` 起点)
- [ ] [[mentoring]] の旧 `Meeting\StoreAction`(申請) / `ApproveAction` / `RejectAction` ベースの通知発火を削除(v3 で自動割当)
- [ ] mentoring の独自 Reminder Command を削除(本 Feature の `SendMeetingRemindersCommand` に統合)

### [[settings-profile]] 協調修正(変更なし、引き続き通知設定 UI なし)

- [ ] [[settings-profile]] spec / 実装に **通知設定タブを実装しない**(全通知 Database + Mail 固定送信、ユーザー設定 UI なし)

## Step 11: Broadcasting（Pusher リアルタイム）

- [ ] `.env.example` に `BROADCAST_DRIVER=pusher` / `PUSHER_APP_*`(Wave 0b で整備済前提)
- [ ] 全 8 個の Notification クラスに `broadcastOn(): PrivateChannel("notifications.{$notifiable->id}")` + `broadcastWith()` 実装
- [ ] `resources/js/notification/realtime.js`(Echo subscribe + バッジ +1 + ドロップダウン先頭追加)
- [ ] `resources/js/app.js` から `realtime.js` import + `<meta name="auth-user-id" content="{{ auth()->id() }}">` を `layouts/app.blade.php` に追加

## Step 12: テスト

### Notification クラス(`tests/Unit/Notifications/`)

- [ ] `BaseNotificationTest`(via 構築 + Broadcasting 有効時 / ULID id)
- [ ] 各 8 Notification の toDatabase / toMail / broadcastOn / broadcastWith 単体テスト

### ラッパー Action(`tests/Feature/UseCases/Notification/`)

- [ ] **`NotifyChatMessageReceivedActionTest`(v3)** — 受講生→コーチ全員 DB+Mail / コーチ→受講生 DB+Mail / コーチ→他コーチ DB only / コーチ未割当 skip / withdrawn skip / graduated skip
- [ ] `NotifyQaReplyReceivedActionTest`(自己回答 skip)
- [ ] `NotifyMockExamGradedActionTest`(受験者通知)
- [ ] **`NotifyCompletionApprovedActionTest`(v3 発火元変更)** — `ReceiveCertificateAction` 経由で発火 / Mail 内 DL URL 含有 / withdrawn / graduated skip
- [ ] **`NotifyMeetingReservedActionTest`(v3 新規)** — コーチ宛 dispatch のみ、受講生宛は発火しない / scheduled_at + 受講生名 + topic + meeting_url_snapshot 含有
- [ ] `NotifyMeetingCanceledActionTest`(student キャンセル → coach 通知 / coach キャンセル → student 通知)
- [ ] `NotifyMeetingReminderActionTest`(eve / one_hour_before / 重複排除)
- [ ] `NotifyAdminAnnouncementActionTest`(受信者 status 検査)

### 明示的に持たないテスト(v3 撤回)

- `NotifyMeetingRequestedActionTest` / `NotifyMeetingApprovedActionTest` / `NotifyMeetingRejectedActionTest`
- `NotifyStagnationReminderActionTest`

### 通知一覧 / 管理者お知らせ HTTP(`tests/Feature/Http/Notification/`、`Admin/AdminAnnouncement/`)

- [ ] `Notification/{Index,MarkAsRead,MarkAllAsRead,Dropdown}Test.php`
- [ ] `Admin/AdminAnnouncement/{Index,Store,Show}Test.php`(target_type 別配信件数 / 不整合 422 / target Not Found)

### Schedule Command(`tests/Feature/Console/Notification/`)

- [ ] `SendMeetingRemindersCommandTest`(eve 範囲 / one_hour_before 範囲 / 重複起動 skip)

### Policy / View Composer

- [ ] `NotificationPolicyTest` / `AdminAnnouncementPolicyTest`
- [ ] `NotificationBadgeComposerTest`(未認証 0 / 99 超 → "99+")

## Step 12.5: Factory + Seeder

- [ ] **Seeder 不要**: 本 Feature の `DatabaseNotification` 行は他 Feature(`enrollment`, `mentoring`, `chat`, `qa-board`, `mock-exam`)の Action 実行時に副作用として INSERT されるため、専用 Seeder は提供しない(`structure.md` Seeder 規約「④ 集計・読み取り専用系」分類)
- [ ] ただし TopBar ベル / 通知一覧画面の動作確認には **既読・未読・各種通知タイプの混在** が必要。これは他 Feature の Seeder(`ChatSeeder` / `MentoringSeeder` / `EnrollmentSeeder` 等)が Action 経由で通知を発火することで自動的に揃う想定

## Step 13: 動作確認 & 整形

- [ ] `sail bin pint --dirty` 整形
- [ ] `sail artisan test --filter=Notification` 全件 pass
- [ ] `sail artisan migrate:fresh --seed` + ブラウザシナリオ:
  - [ ] 受講生で chat 送信 → コーチ全員に DB + Mail 通知(Mailpit 確認)
  - [ ] コーチで chat 返信 → 受講生に DB + Mail、他コーチに **DB only** (Mailpit に他コーチ向けメールが届かないこと)
  - [ ] 受講生で **「修了証を受け取る」**(v3 自己発火) → 自分自身に DB + Mail(PDF DL URL 含む)、admin 宛通知 **0 件**
  - [ ] 受講生で面談予約 → **コーチ宛のみ**に DB + Mail、受講生宛は **0 件**(予約 UI で即時確認のため)
  - [ ] 受講生で面談キャンセル → コーチに DB + Mail
  - [ ] コーチで面談キャンセル → 受講生に DB + Mail
  - [ ] mock-exam 採点完了 → 受験者に DB + Mail
  - [ ] admin で全 InProgress 受講生宛にお知らせ配信 → 各受講生に DB + Mail
  - [ ] 通知行クリック → 既読化 + 関連画面遷移
  - [ ] 「全件既読」一括既読化
  - [ ] サイドバー「通知」バッジ件数連動更新
- [ ] Schedule Command 手動実行:
  - [ ] `sail artisan notifications:send-meeting-reminders --window=eve` → 翌日 Reserved 面談に対し受講生 + コーチ両方に「面談リマインド(前日)」追加
  - [ ] 2 回目実行で重複しない
  - [ ] `--window=one_hour_before` → +55..65min 範囲 Meeting に対し両方に「面談リマインド(1h 前)」
- [ ] Pusher リアルタイム配信 動作確認(`BROADCAST_DRIVER=pusher`):
  - [ ] Tab A で受講生、Tab B でコーチが chat 送信 → Tab A の TopBar ベルバッジ +1 + ドロップダウン先頭に行追加(リロード不要)
