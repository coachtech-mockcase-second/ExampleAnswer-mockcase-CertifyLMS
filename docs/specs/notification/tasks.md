# notification タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-notification-NNN` / `NFR-notification-NNN` を参照。
> 開発環境は Laravel Sail（Docker）。すべてのコマンドは `sail` プレフィックスで実行する。

## Step 1: Migration & Enum & Model

- [ ] migration: `change_notifications_id_to_ulid`（Laravel 標準 `notifications` テーブルの `id` を ULID 型に変更、`(notifiable_type, notifiable_id, read_at)` 複合 INDEX 追加 + `created_at` 単体 INDEX 追加）（REQ-notification-001, REQ-notification-002）
- [ ] migration: `create_admin_announcements_table`（ULID 主キー / `created_by_user_id` ulid FK / `title` string 200 / `body` text / `target_type` string / `target_certification_id` ulid nullable FK / `target_user_id` ulid nullable FK / `dispatched_count` unsignedInteger default 0 / `dispatched_at` datetime nullable / SoftDeletes / `(target_type, dispatched_at)` 複合 INDEX + `dispatched_at` / `deleted_at` 単体 INDEX）（REQ-notification-010）
- [ ] Enum: `App\Enums\AdminAnnouncementTargetType`（case 名 ⇔ backed value: `AllStudents` = `'all_students'` / `Certification` = `'certification'` / `User` = `'user'`、`label()` 含む）（REQ-notification-011）
- [ ] Enum: `App\Enums\MeetingReminderWindow`（case 名 ⇔ backed value: `Eve` = `'eve'` / `OneHourBefore` = `'one_hour_before'`、`label()` 含む）（REQ-notification-075）
- [ ] Model: `App\Models\AdminAnnouncement`（`HasUlids` + `HasFactory` + `SoftDeletes` / fillable / casts: `target_type` Enum + `dispatched_at` datetime / `belongsTo(User, 'created_by_user_id', 'createdBy')` / `belongsTo(Certification, 'target_certification_id', 'targetCertification')` / `belongsTo(User, 'target_user_id', 'targetUser')` / `scopeOrderByDispatchedAt`）（REQ-notification-010）
- [ ] Factory: `AdminAnnouncementFactory`（`allStudents()` / `forCertification(Certification)` / `forUser(User)` / `dispatched()` state）

## Step 2: Notification 共通基盤

- [ ] abstract class: `App\Notifications\BaseNotification`（`Illuminate\Notifications\Notification` 継承 + `implements ShouldQueue`、`__construct` 内で `$this->id = (string) Str::ulid()` 設定 + `via($notifiable)` で `['database', 'mail']` を返却、`config('broadcasting.default') !== 'null'` なら `'broadcast'` 追加。子 Notification は `parent::__construct()` 必須。本固定動作で qa-board / mock-exam / mentoring 系の `via` 規定もすべて担保）（REQ-notification-020, REQ-notification-021, REQ-notification-002, REQ-notification-124, REQ-notification-042, REQ-notification-051, REQ-notification-077, REQ-notification-085）

## Step 3: Notification クラス（10 種類）

各クラスは `extends BaseNotification` + `use Queueable`、`toDatabase()`（共通キー + 種別固有キーを併存、REQ-notification-003, REQ-notification-004）/ `toMail()`（`MailMessage` で構成、`subject` は `【Certify LMS】...` 統一、独立 Mailable 不要、REQ-notification-025, NFR-notification-006）/ `broadcastOn()` / `broadcastWith()` を実装。

- [ ] `App\Notifications\ChatMessageReceivedNotification`（コンストラクタ `ChatMessage $message`、`data.notification_type='chat_message_received'`）（REQ-notification-030, REQ-notification-033）
- [ ] `App\Notifications\QaReplyReceivedNotification`（コンストラクタ `QaReply $reply`、`data.notification_type='qa_reply_received'`）（REQ-notification-040, REQ-notification-043）
- [ ] `App\Notifications\MockExamGradedNotification`（コンストラクタ `MockExamSession $session`、`data.notification_type='mock_exam_graded'`）（REQ-notification-050, REQ-notification-052, REQ-notification-053）
- [ ] `App\Notifications\CompletionApprovedNotification`（コンストラクタ `Enrollment $enrollment, Certificate $certificate`、Mail 本文に `route('certificates.download', $certificate)` 含める、`data.notification_type='completion_approved'`）（REQ-notification-060, REQ-notification-061, REQ-notification-062）
- [ ] `App\Notifications\MeetingRequestedNotification`（コンストラクタ `Meeting $meeting`、`data.notification_type='meeting_requested'`、コーチ宛）（REQ-notification-071, REQ-notification-078）
- [ ] `App\Notifications\MeetingApprovedNotification`（コンストラクタ `Meeting $meeting`、Mail 本文に `meeting_url_snapshot` + `scheduled_at`、`data.notification_type='meeting_approved'`）（REQ-notification-070, REQ-notification-078）
- [ ] `App\Notifications\MeetingRejectedNotification`（コンストラクタ `Meeting $meeting`、Mail 本文に `rejected_reason`、`data.notification_type='meeting_rejected'`）（REQ-notification-072, REQ-notification-078）
- [ ] `App\Notifications\MeetingCanceledNotification`（コンストラクタ `Meeting $meeting, User $actor`、`actor` で文面分岐、`data.notification_type='meeting_canceled'` + `data.canceled_by`）（REQ-notification-073, REQ-notification-078）
- [ ] `App\Notifications\MeetingReminderNotification`（コンストラクタ `Meeting $meeting, MeetingReminderWindow $window`、`data` に `meeting_id` + `window`、`data.notification_type='meeting_reminder'`）（REQ-notification-074, REQ-notification-078）
- [ ] `App\Notifications\AdminAnnouncementNotification`（コンストラクタ `AdminAnnouncement $announcement`、`data.notification_type='admin_announcement'`）（REQ-notification-085, REQ-notification-086）

## Step 4: Policy & FormRequest

- [ ] `App\Policies\NotificationPolicy`（`view` / `update`）（REQ-notification-111, REQ-notification-112）
- [ ] `App\Policies\AdminAnnouncementPolicy`（`viewAny` / `view` / `create`、`update` / `delete` 不採用）（REQ-notification-114）
- [ ] `app/Providers/AuthServiceProvider::$policies` に登録: `Illuminate\Notifications\DatabaseNotification::class => NotificationPolicy::class` + `App\Models\AdminAnnouncement::class => AdminAnnouncementPolicy::class`
- [ ] `App\Http\Requests\Notification\IndexRequest`（`tab: nullable in:all,unread` / `page: nullable integer min:1` / authorize: `$this->user() !== null`）（REQ-notification-090, REQ-notification-092）
- [ ] `App\Http\Requests\Admin\AdminAnnouncement\StoreRequest`（`title: required string max:200` / `body: required string max:5000` / `target_type: required in:all_students,certification,user` / `target_certification_id: required_if:target_type,certification ulid exists:certifications,id` / `target_user_id: required_if:target_type,user ulid exists:users,id` / authorize: `$this->user()->can('create', AdminAnnouncement::class)`）（REQ-notification-081, REQ-notification-082, REQ-notification-083, REQ-notification-084）

## Step 5: HTTP 層 — Controller & Route

- [ ] `App\Http\Controllers\NotificationController`（`index` / `dropdown` / `markAsRead` / `markAllAsRead` の 4 メソッド、薄い実装）（REQ-notification-090, REQ-notification-093, REQ-notification-094, REQ-notification-103）
- [ ] `App\Http\Controllers\Admin\AdminAnnouncementController`（`index` / `create` / `store` / `show` の 4 メソッド、`update` / `destroy` 不採用、`create` は Action 省略の薄い view 返却のみ）（REQ-notification-080, REQ-notification-087, REQ-notification-088, REQ-notification-089）
- [ ] `routes/web.php` に追記: `auth` middleware group 内に `notifications.index` / `notifications.dropdown` / `notifications.markAsRead` / `notifications.markAllAsRead`（REQ-notification-090, REQ-notification-093, REQ-notification-094, REQ-notification-110）
- [ ] `routes/web.php` に追記: `auth + role:admin` middleware group 内に `Route::resource('announcements')->only(['index', 'create', 'store', 'show'])`（REQ-notification-080, REQ-notification-089, REQ-notification-113）
- [ ] `routes/channels.php` に追記: `Broadcast::channel('notifications.{userId}', fn (User $user, string $userId) => (string) $user->id === $userId)`（REQ-notification-123）

## Step 6: Action（UseCase）

### 通知発火ラッパー Action 群（`app/UseCases/Notification/`、10 種類）

- [ ] `NotifyChatMessageReceivedAction`（sender role で相手方解決 → 双方向通知、コーチ未割当時は skip）（REQ-notification-022, REQ-notification-023, REQ-notification-024, REQ-notification-026, REQ-notification-030, REQ-notification-031, REQ-notification-032）
- [ ] `NotifyQaReplyReceivedAction`（自己回答ガード + スレッド投稿者通知）（REQ-notification-022, REQ-notification-040, REQ-notification-041）
- [ ] `NotifyMockExamGradedAction`（受験者通知）（REQ-notification-022, REQ-notification-050）
- [ ] `NotifyCompletionApprovedAction`（受講生通知 + Certificate 引数）（REQ-notification-022, REQ-notification-060, REQ-notification-061）
- [ ] `NotifyMeetingRequestedAction`（担当コーチ通知）（REQ-notification-022, REQ-notification-071）
- [ ] `NotifyMeetingApprovedAction`（受講生通知）（REQ-notification-022, REQ-notification-070）
- [ ] `NotifyMeetingRejectedAction`（受講生通知 + rejected_reason）（REQ-notification-022, REQ-notification-072）
- [ ] `NotifyMeetingCanceledAction`（actor で相手方解決 → 相手方通知）（REQ-notification-022, REQ-notification-073）
- [ ] `NotifyMeetingReminderAction`（`(meeting_id, window)` 重複排除検査 + 受講生 + コーチ両方通知）（REQ-notification-022, REQ-notification-074, REQ-notification-076, NFR-notification-007）
- [ ] `NotifyAdminAnnouncementAction`（受信者 status 検査 + 通知）（REQ-notification-022, REQ-notification-081, REQ-notification-085）

### 通知一覧操作 Action 群（`app/UseCases/Notification/`）

- [ ] `IndexAction`（tab フィルタ + paginate、`data` JSON 内に表示要素を全て含めるため eager load 不要 / N+1 回避）（REQ-notification-090, REQ-notification-091, REQ-notification-092, NFR-notification-002）
- [ ] `MarkAsReadAction`（`$notification->markAsRead()` + `fresh()` 返却）（REQ-notification-093）
- [ ] `MarkAllAsReadAction`（`$user->unreadNotifications->markAsRead()` + 影響件数返却）（REQ-notification-094）
- [ ] `DropdownAction`（最近 5 件 + 未読件数返却）（REQ-notification-103）

### 管理者お知らせ Action 群（`app/UseCases/Admin/AdminAnnouncement/`）

- [ ] `IndexAction`（`AdminAnnouncement` 一覧 + paginate）（REQ-notification-087）
- [ ] `StoreAction`（target 整合性検査 + Announcement INSERT + 対象 受講生 User 解決 + 各 User へ NotifyAdminAnnouncementAction 呼出 + dispatched_count/dispatched_at UPDATE、全工程 1 トランザクション）（REQ-notification-081, REQ-notification-082, REQ-notification-083, REQ-notification-084, NFR-notification-001）
- [ ] `ShowAction`（eager load して返却）（REQ-notification-088）

## Step 7: ドメイン例外

- [ ] `app/Exceptions/Notification/AdminAnnouncementInvalidTargetException`（HTTP 422、`UnprocessableEntityHttpException` 継承）（NFR-notification-003）
- [ ] `app/Exceptions/Notification/AdminAnnouncementTargetNotFoundException`（HTTP 404、`NotFoundHttpException` 継承）（NFR-notification-003）

## Step 8: View Composer & Blade

- [ ] `app/View/Composers/NotificationBadgeComposer`（未読件数 + 99+ 表示判定、未認証時は 0 を返す）（REQ-notification-100, REQ-notification-102, NFR-notification-005）
- [ ] `app/Providers/AppServiceProvider::boot()` に `View::composer(['layouts._partials.topbar', 'layouts._partials.sidebar-admin', 'layouts._partials.sidebar-coach', 'layouts._partials.sidebar-student'], NotificationBadgeComposer::class)` 登録（REQ-notification-100）
- [ ] `views/notifications/index.blade.php`（`<x-tabs>` で全件 / 未読切替、行クリックで markAsRead POST + 遷移、「全件既読」`<x-button>`）（REQ-notification-090, REQ-notification-093, REQ-notification-094, REQ-notification-095, NFR-notification-004）
- [ ] `views/notifications/_partials/notification-row.blade.php`（種別アイコン + タイトル + プレビュー + 経過時間 + 未読バッジ）（REQ-notification-095, NFR-notification-010）
- [ ] `views/notifications/_partials/dropdown.blade.php`（最近 5 件 + 「すべての通知を見る」リンク）（REQ-notification-103）
- [ ] `views/admin/announcements/index.blade.php`（配信履歴一覧 + 「+新規配信」ボタン + paginate）（REQ-notification-087）
- [ ] `views/admin/announcements/create.blade.php`（タイトル / 本文 / 対象種別ラジオ + 対象種別ごとの追加フィールド表示切替）（REQ-notification-080, REQ-notification-081）
- [ ] `views/admin/announcements/show.blade.php`（タイトル全文 / 本文全文 / 対象種別解決結果 / 配信件数 / 配信日時）（REQ-notification-088）
- [ ] `views/admin/announcements/_partials/target-fields.blade.php`（target_certification_id / target_user_id を素の JS で表示切替する部分ビュー）（REQ-notification-081）
- [ ] `views/layouts/_partials/topbar.blade.php` への通知ベル追記（`<button aria-label="通知 {未読件数} 件">` + `<x-badge>` + ドロップダウン include、[[frontend-ui-foundation]] 既存ファイルに追記）（REQ-notification-101, REQ-notification-102, REQ-notification-103, NFR-notification-010）
- [ ] `views/layouts/_partials/sidebar-admin.blade.php` / `sidebar-coach.blade.php` / `sidebar-student.blade.php` の「通知」`<x-nav.item>` に `:badge="$notificationBadge['count']"` 追記（REQ-notification-104）
- [ ] `resources/js/admin/announcement-form.js`（target_type ラジオ change で対象フィールド表示切替、素の JS）

## Step 9: Schedule Command

- [ ] `app/Console/Commands/Notification/SendMeetingRemindersCommand`（signature: `notifications:send-meeting-reminders {--window=eve}`、`--window=eve` なら `Meeting::where('status', Approved)->whereBetween('scheduled_at', [tomorrow 00:00, tomorrow 23:59])`、`--window=one_hour_before` なら `whereBetween('scheduled_at', [now+55min, now+65min])` 抽出 + `NotifyMeetingReminderAction($meeting, MeetingReminderWindow::from($window))` 呼出）（REQ-notification-074, REQ-notification-075, REQ-notification-076, NFR-notification-007）
- [ ] `app/Console/Kernel.php::schedule()` に追記:
  - `->command('notifications:send-meeting-reminders --window=eve')->dailyAt('18:00')`
  - `->command('notifications:send-meeting-reminders --window=one_hour_before')->everyFiveMinutes()`

> 学習途絶リマインド Command は **採用しない**（`StagnationDetectionService` は dashboard 側でのみ利用）。

## Step 10: 各 Feature の起点呼出（協調修正、各 Feature の PR で扱う）

> 本 Feature の実装と並行して、依存元 Feature の Action から `Notify*Action` 呼出を組み込む。各 Feature の spec / 実装をどう変えるかは協調 PR として明示する。

### 追加する通知発火（10 種類分の起点呼出）

- [ ] [[chat]] `App\UseCases\Chat\StoreMessageAction` の DB::transaction 内に `app(NotifyChatMessageReceivedAction::class)($message)` を組み込む（sender role 判定は Notify*Action 側で実施、Chat 側は無条件で呼ぶ、双方向通知）
- [ ] [[qa-board]] `App\UseCases\QaReply\StoreAction` 内に `app(NotifyQaReplyReceivedAction::class)($reply)` を組み込む（qa-board spec の Notification dispatch 部分を本方式に揃える協調修正）
- [ ] [[mock-exam]] `App\UseCases\MockExamSession\SubmitAction` の `DB::afterCommit` 内に `app(NotifyMockExamGradedAction::class)($result)` を組み込む（mock-exam spec の Event 経由匂わせコメントを Action 直呼出に置換）
- [ ] [[enrollment]] `App\UseCases\Admin\Enrollment\ApproveCompletionAction` のコンストラクタに `NotifyCompletionApprovedAction` を DI し、`DB::afterCommit` 内で `($this->notifyCompletion)($enrollment, $certificate)` を呼出
- [ ] [[mentoring]] `App\UseCases\Meeting\StoreAction` 内に `app(NotifyMeetingRequestedAction::class)($meeting)` を組み込む
- [ ] [[mentoring]] `App\UseCases\Meeting\ApproveAction` 内に `app(NotifyMeetingApprovedAction::class)($meeting)` を組み込む
- [ ] [[mentoring]] `App\UseCases\Meeting\RejectAction` 内に `app(NotifyMeetingRejectedAction::class)($meeting)` を組み込む
- [ ] [[mentoring]] `App\UseCases\Meeting\CancelAction` 内に `app(NotifyMeetingCanceledAction::class)($meeting, $actor)` を組み込む（actor で文面分岐）

### 削除する通知発火（不採用方針の徹底）

- [ ] [[enrollment]] `App\UseCases\Enrollment\RequestCompletionAction` に通知発火コードを **追加しない**（admin 宛通知不採用方針、admin は [[dashboard]] の「修了申請待ち一覧」で確認）（REQ-notification-063）
- [ ] [[mentoring]] の `RemindEveMeetingsCommand` / `RemindOneHourBeforeMeetingsCommand` 等の独自 Reminder Command を **削除**、本 Feature の `SendMeetingRemindersCommand` に統合（mentoring spec から該当 Command 記述を削除し、本 Feature を参照する形に修正）

### [[settings-profile]] 協調修正

- [ ] [[settings-profile]] spec / 実装から **通知設定タブ関連を全削除**:
  - `UserNotificationSetting` Model / Migration / Factory 削除
  - `NotificationType` Enum / `NotificationChannel` Enum 削除
  - `NotificationSettingController` / `NotificationSetting\UpdateAction` 削除
  - `tab-notifications.blade.php` 削除 + `settings/profile.blade.php` のタブ配列から `notifications` 削除
  - `Profile/EditAction::buildNotificationMatrix` 削除
  - 関連 REQ-settings-profile-040 〜 046 削除
  - 関連テスト削除

## Step 11: Advance Broadcasting（Pusher）

- [ ] `composer.json` に `pusher/pusher-php-server` 追加 + `sail composer install`
- [ ] `package.json` に `laravel-echo` + `pusher-js` 追加 + `sail npm install`
- [ ] `.env.example` に `BROADCAST_DRIVER=pusher` / `PUSHER_APP_KEY=` / `PUSHER_APP_SECRET=` / `PUSHER_APP_ID=` / `PUSHER_APP_CLUSTER=` 追記（NFR-notification-008）
- [ ] `.env` の `BROADCAST_DRIVER` を Basic では `null`、Advance では `pusher` に切替可能と明記
- [ ] `config/broadcasting.php` の `pusher` connection 設定確認（Wave 0b で公開済前提）
- [ ] 全 10 個の Notification クラスに `broadcastOn(): PrivateChannel` / `broadcastWith(): array` を実装（REQ-notification-120, REQ-notification-121）
- [ ] `resources/js/notification/realtime.js`（Echo subscribe + バッジ +1 / ドロップダウン先頭追加ロジック、素の JS）（REQ-notification-122）
- [ ] `resources/js/app.js` から `realtime.js` を import + `<meta name="auth-user-id" content="{{ auth()->id() }}">` を `views/layouts/app.blade.php` に追加
- [ ] `routes/channels.php` の認可確認（Step 5 で実施済）

## Step 12: テスト

### Notification クラス（`tests/Unit/Notifications/`）

> 各テストは `Notification::fake()` + `Notification::assertSentTo(...)` を基本パターンとして実装する（NFR-notification-009）。

- [ ] `BaseNotificationTest`（via が `['database', 'mail']` を返す + Advance 時 `'broadcast'` 含む、`__construct` で id が ULID 化）（REQ-notification-020, REQ-notification-021, REQ-notification-002）
- [ ] 各 10 Notification クラスに対する toDatabase / toMail / broadcastOn / broadcastWith 単体テスト（NFR-notification-009）

### ラッパー Action（`tests/Feature/UseCases/Notification/`）

- [ ] `NotifyChatMessageReceivedActionTest`（コーチ送信時は受講生通知 / 受講生送信時はコーチ通知 / コーチ未割当 skip / withdrawn skip）（REQ-notification-024, REQ-notification-026, REQ-notification-031, REQ-notification-032）
- [ ] `NotifyQaReplyReceivedActionTest`（自己回答 skip / 通常通知 dispatch）（REQ-notification-041）
- [ ] `NotifyMockExamGradedActionTest`（受験者通知 dispatch）（REQ-notification-050）
- [ ] `NotifyCompletionApprovedActionTest`（受講生通知 dispatch + Mail 内 URL 含有）（REQ-notification-060, REQ-notification-061）
- [ ] `NotifyMeetingRequestedActionTest`（コーチ通知 dispatch）（REQ-notification-071）
- [ ] `NotifyMeetingApprovedActionTest`（受講生通知 dispatch + Mail 内 meeting_url_snapshot 含有）（REQ-notification-070）
- [ ] `NotifyMeetingRejectedActionTest`（受講生通知 dispatch + Mail 内 rejected_reason 含有）（REQ-notification-072）
- [ ] `NotifyMeetingCanceledActionTest`（受講生キャンセル → コーチ通知 / コーチキャンセル → 受講生通知）（REQ-notification-073）
- [ ] `NotifyMeetingReminderActionTest`（(meeting_id, window) ペアで 2 回目 skip、受講生 + コーチ両方に dispatch）（REQ-notification-074, REQ-notification-076, NFR-notification-007）
- [ ] `NotifyAdminAnnouncementActionTest`（受信者 status active のみ dispatch）（REQ-notification-024, REQ-notification-081）

### 通知一覧操作（`tests/Feature/Http/Notification/`）

- [ ] `IndexTest`（受講生 / コーチ / admin それぞれ自分宛のみ取得 + tab=unread フィルタ）（REQ-notification-091, REQ-notification-092）
- [ ] `MarkAsReadTest`（自分の通知のみ既読化可 / 他人の通知は 403）（REQ-notification-093, REQ-notification-111, REQ-notification-112）
- [ ] `MarkAllAsReadTest`（自分の未読のみ既読化、他人の未読は影響なし）（REQ-notification-094）
- [ ] `DropdownTest`（最近 5 件 + 未読件数返却）（REQ-notification-103）

### 管理者お知らせ（`tests/Feature/Http/Admin/AdminAnnouncement/`）

- [ ] `IndexTest`（admin のみアクセス可 / coach / student は 403）（REQ-notification-087, REQ-notification-113, REQ-notification-114）
- [ ] `StoreTest`（target_type ごとの対象解決 + Notification::fake で assertSentTo の件数アサート + dispatched_count / dispatched_at 反映）（REQ-notification-081, REQ-notification-082, REQ-notification-083, REQ-notification-084）
- [ ] `StoreInvalidTargetTest`（target_type と target_* の不整合で 422）（REQ-notification-012, REQ-notification-013, NFR-notification-003）
- [ ] `StoreTargetNotFoundTest`(指定 certification / user が SoftDelete 済 / withdrawn で 422 or 空配信）（NFR-notification-003）
- [ ] `ShowTest`（admin のみ閲覧可）（REQ-notification-088）

### Schedule Command（`tests/Feature/Console/Notification/`）

- [ ] `SendMeetingRemindersCommandTest`（`--window=eve` で前日範囲 Meeting 抽出 + 通知 dispatch / `--window=one_hour_before` で +55..65min 範囲抽出 + 通知 dispatch / 重複起動で 2 回目 skip）（REQ-notification-074, REQ-notification-076）

### Policy（`tests/Unit/Policies/`）

- [ ] `NotificationPolicyTest`（自分の通知 view true / 他人の通知 view false）（REQ-notification-111）
- [ ] `AdminAnnouncementPolicyTest`（admin のみ viewAny / view / create true、coach / student false）（REQ-notification-114）

### View Composer（`tests/Feature/View/`）

- [ ] `NotificationBadgeComposerTest`（未認証 0 / 認証時の未読件数 / 99 超 → "99+"）（REQ-notification-100, REQ-notification-102, NFR-notification-005）

## Step 13: 動作確認 & 整形

- [ ] `sail bin pint --dirty` 整形
- [ ] `sail artisan test --filter=Notification` 通過
- [ ] `sail artisan test --filter=AdminAnnouncement` 通過
- [ ] `sail artisan migrate:fresh --seed` 後、開発用シーダーで以下を再現確認:
  - 受講生でログイン → TopBar 通知ベルバッジ表示
  - コーチから受講生に chat 送信 → 受講生 `/notifications` に 1 件追加
  - 受講生からコーチに chat 送信 → コーチ `/notifications` に 1 件追加（双方向）
  - admin が「全 active 受講生」宛にお知らせ配信 → 各受講生 `/notifications` に 1 件追加 + Mailpit（http://localhost:8025）でメール確認
  - 各通知行クリック → 既読化 + 関連画面遷移
  - 「全件既読」ボタン → 一括既読化
  - サイドバー「通知」のバッジ件数が連動更新
  - 受講生で修了申請 → admin 通知 **0 件** であること（admin は dashboard 確認）
  - 受講生が面談予約申請 → コーチ `/notifications` に「面談予約申請」通知 1 件
  - コーチ承認 → 受講生 `/notifications` に「面談承認」通知 1 件
  - コーチ拒否 → 受講生 `/notifications` に「面談拒否」通知 1 件（rejected_reason 含む）
  - 受講生キャンセル → コーチ `/notifications` に「面談キャンセル」通知 1 件
  - コーチキャンセル → 受講生 `/notifications` に「面談キャンセル」通知 1 件
  - 模擬試験提出 → 採点完了後に受験者 `/notifications` に 1 件
- [ ] Schedule Command 手動実行:
  - `sail artisan notifications:send-meeting-reminders --window=eve` を翌日 approved の Meeting がある状態で実行
  - 1 回目: 受講生 + コーチ両方の通知一覧に「面談リマインド（前日）」が 1 件追加されること
  - 2 回目（重複起動シミュレーション）: 同 `(meeting_id, window)` の重複通知が **追加されない**ことを確認
  - `sail artisan notifications:send-meeting-reminders --window=one_hour_before` を 1h 後 approved の Meeting がある状態で実行 → 受講生 + コーチ両方に「面談リマインド（1h 前）」が追加されること
  - 同 Meeting に対して `eve` と `one_hour_before` の両方が独立して配信されること（window 単位の独立性）
- [ ] Queue Worker 起動確認: `sail artisan queue:work --queue=default,notifications --tries=3 --backoff=10` で Mail 配信が非同期化されること（REQ-notification-124, REQ-notification-125）
- [ ] **Advance Broadcasting 動作確認**（Pusher 環境設定後）:
  - `BROADCAST_DRIVER=pusher` 設定 + `sail npm run dev` でフロント起動
  - 受講生 A でログイン後、別ウィンドウでコーチ B からメッセージ送信 → 受講生 A 側で画面リロード無しで TopBar ベルバッジ +1 + ドロップダウン先頭に行追加されること
  - Pusher Debug Console（dashboard.pusher.com）で `notifications.{user_id}` channel の event を確認
- [ ] `views/layouts/_partials/topbar.blade.php` のアクセシビリティ確認（`aria-label` / フォーカスリング / `Tab` キーで通知ベルに到達可能）
- [ ] 全ロール（admin / coach / student）でモバイルサイズ（`< lg:` ）でも通知ベル + ドロップダウンが破綻なく表示されること
