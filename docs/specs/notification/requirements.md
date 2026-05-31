# notification 要件定義

> **v3 改修反映**（2026-05-16）: `MeetingApproved` / `MeetingRejected` / `MeetingRequested` 通知撤回（自動割当のため）、`MeetingReserved` 通知をコーチ宛のみに変更、`PlanExpireSoon` 通知撤回（MVP 外）、`StagnationReminder` 通知撤回（滞留検知 v3 撤回）、chat 通知の双方向化（コーチ→受講生 + 受講生→コーチ全員）+ コーチ間は Database のみ。
>
> **2026-05-18 UX 改修**: TopBar ベルクリック時の UI を「ドロップダウン（最近 5 件）」→ **「通知ポップオーバー（ベル横アンカー、最新 20 件 + 全件/未読タブ + バルク既読）」** に変更（Stripe / Jira / GitHub 等の業界標準パターン準拠）。`/notifications` フルページは併存（サイドバー「通知」ナビ / ポップオーバー フッターリンクから到達、URL アドレッサブル / 深掘り用）。

## 概要

**受講生宛 + コーチ宛通知** を扱う通知配信基盤。各 Feature が起こした学習・運用イベントを、**Database + Mail channel の両方固定送信** で対象ユーザーに届ける。**admin 宛通知のみ不採用**（運用情報は [[dashboard]] / 各 Feature の管理画面で集約確認）。

通知種別ごとの ON/OFF 設定 UI は持たない。受信者は **TopBar 通知ベルクリックで通知ポップオーバー（ベル横アンカー、最新 20 件 + 全件/未読タブ + バルク既読）** を開いて素早く処理し（学習中のチラ見・即既読化、全利用シーンの ~80% をカバー）、深掘り（フィルタ / 過去スクロール / URL 共有）は `/notifications` フルページで行う。サイドバーバッジで未読件数を把握する。**TopBar ベルへのリアルタイム反映は Pusher + WebSocket（Laravel Broadcasting）で push、通知ポップオーバー open 中は DOM もリアルタイム同期、メール配信は Queue（database driver）で非同期化** する。

通知種別（v3 改修後）:

| # | 通知 | 受信者 | 起点 |
|---|---|---|---|
| 1 | `ChatMessageReceivedNotification` | 受講生 → コーチ全員（DB+Mail）、コーチ → 受講生（DB+Mail）、コーチ間 Database のみ | [[chat]] `StoreMessageAction` |
| 2 | `QaReplyReceivedNotification` | スレッド投稿者（受講生） | [[qa-board]] `QaReply\StoreAction` |
| 5 | `MeetingReservedNotification` | **担当コーチ宛のみ**（受講生宛は予約 UI で即時確認のため不要） | [[mentoring]] `Meeting\StoreAction` |
| 6 | `MeetingCanceledNotification` | 相手方（受講生がキャンセルしたらコーチ、コーチがキャンセルしたら受講生） | [[mentoring]] `Meeting\CancelAction` |
| 7 | `MeetingReminderNotification` | 受講生 + コーチ両方 | 本 Feature の `SendMeetingRemindersCommand`（前日 18:00 + 1h 前の 2 回） |
| 8 | `AnnouncementNotification` | 対象 student 集合 | 本 Feature の `Announcement\StoreAction` |

**撤回された通知**:
- `MockExamGradedNotification` / `NotifyMockExamGradedAction`（[[mock-exam]] 側で提出後の Controller redirect で結果画面に遷移するため、通知不要と判断）
- `CompletionApprovedNotification` / `NotifyCompletionApprovedAction`（受講生の自己操作で完結、遷移先画面の PDF DL リンクで通知冗長）
- `MeetingApprovedNotification` / `MeetingRejectedNotification` / `MeetingRequestedNotification`（mentoring 申請承認フロー撤回）
- `PlanExpireSoonNotification`（MVP 外）
- `StagnationReminderNotification`（滞留検知 v3 撤回）

## ロールごとのストーリー

- **受講生（student）**: ログイン後 TopBar 通知ベルで未読件数を確認し、`/notifications` で時系列に通知を読む。受信種別は #1（コーチからの chat 新着）/ #2 / #6 / #7 / #8。
- **コーチ（coach）**: 通知種別 #1（受講生からの chat 新着 + 他コーチ間 DB のみ）/ #5（自動割当された面談予約）/ #6（受講生による面談キャンセル）/ #7 を受信。
- **管理者（admin）**: 通知の **配信元** + 管理者お知らせ配信 UI 操作。**自分宛の通知は受信しない**。

## 受け入れ基準（EARS形式）

### 機能要件 — データ基盤

- **REQ-notification-001**: The system shall Laravel 標準の `notifications` テーブル（`DatabaseNotification` 既定スキーマ）を採用する。
- **REQ-notification-002**: The system shall `notifications.id` を ULID で発行する（`BaseNotification::__construct` 内で `$this->id = (string) Str::ulid()` 事前確定）。
- **REQ-notification-003**: The system shall `notifications.data` JSON 内に共通キー（`notification_type` / `title` / `message` / `link_route` / `link_params`）を必ず含める。
- **REQ-notification-004**: The system shall `notifications.data` JSON 内に種別固有キーを併存させる。
- **REQ-notification-010**: The system shall 管理者お知らせ用に `admin_announcements` テーブル（ULID 主キー / `title` / `body` / `target_type` enum / `target_certification_id` ulid nullable / `target_user_id` ulid nullable / `created_by_user_id` ulid / `dispatched_count` / `dispatched_at`）を新設する。
- **REQ-notification-011**: The system shall `admin_announcements.target_type` を `App\Enums\AnnouncementTargetType` Enum（`AllStudents` / `Certification` / `User`）で管理する。

### 機能要件 — Notification クラス共通基盤

- **REQ-notification-020**: The system shall `App\Notifications\BaseNotification` 抽象クラス（`extends Illuminate\Notifications\Notification implements ShouldQueue`）を提供する。
- **REQ-notification-021**: The system shall すべての Notification クラスの `via($notifiable)` を **固定値 `['database', 'mail', 'broadcast']`** として返却する（3 channel 同時配信）。
- **REQ-notification-022**: The system shall 各通知種別に対応する `App\UseCases\Notification\Notify{Type}Action.php` ラッパー Action を提供し、各 Feature の Action から `app(NotifyXxxAction::class)($entity)` で呼び出せるようにする。
- **REQ-notification-023**: When ラッパー Action `Notify{Type}Action::__invoke` が呼ばれる際, the system shall 受信者の解決 → 受信者の `status === in_progress` 確認（`graduated` / `withdrawn` 宛通知はスキップ）→ `$user->notify(new {Type}Notification(...))` を実行する。
- **REQ-notification-024**: If 受信者の `User.status` が `withdrawn` または `graduated` の場合, then the system shall 該当受信者への通知 dispatch をスキップする。
- **REQ-notification-025**: The system shall 通知の `toMail` を `Illuminate\Notifications\Messages\MailMessage` で構成する（独立 Mailable は作らない）。
- **REQ-notification-026**: The system shall 通知の受信者ロールを `UserRole::Student` または `UserRole::Coach` に限定する（`UserRole::Admin` 宛通知は発火しない）。

### 機能要件 — chat 新着通知（双方向 + コーチ間 DB のみ）

- **REQ-notification-030**: The system shall `App\UseCases\Notification\NotifyChatMessageReceivedAction` を提供し、`__invoke(ChatMessage $message)` シグネチャで以下を実行する: (1) 送信者を取得、(2) ChatRoom の `ChatMember` 全員を取得、(3) 送信者を除く各 ChatMember に対し以下のロジックで通知を dispatch:
  - 送信者が `student` の場合 → 受信者全員（コーチ）に `Database + Mail`
  - 送信者が `coach` の場合 → 受信者が `student` なら `Database + Mail`、`coach`（他コーチ）なら **`Database` のみ**（コーチ間の連絡過剰防止）
- **REQ-notification-033**: The system shall chat 通知の `data` に `chat_room_id` / `chat_message_id` / `sender_user_id` / `sender_name` / `sender_role` / `body_preview`（先頭 100 字）/ `link_route='chat.show'` / `link_params={'room': $room->id}` を格納する。

### 機能要件 — Q&A 回答受信通知（変更なし）

- **REQ-notification-040**: The system shall `App\UseCases\Notification\NotifyQaReplyReceivedAction` を提供する。
- **REQ-notification-041**: If `$reply->user_id === $reply->thread->user_id` の場合, then 通知を dispatch しない。
- **REQ-notification-042**: The system shall Q&A 通知を Database + Mail channel の両方で発行する。
- **REQ-notification-043**: The system shall data に `qa_thread_id` / `qa_reply_id` / `replier_user_id` / `replier_name` / `thread_title` / `body_preview` / `link_route='qa-board.show'` を格納する。

### 機能要件 — mentoring 通知（撤回・変更）

- **REQ-notification-070**: The system shall `App\UseCases\Notification\NotifyMeetingReservedAction` を提供し、`__invoke(Meeting $meeting)` で **担当コーチ宛のみ** に通知を発火する（受講生宛は予約 UI で即時確認のため不要）。Mail 本文には `Meeting.scheduled_at` / 受講生名 / `topic` / `meeting_url_snapshot` を含める。
- **REQ-notification-071**: The system shall `App\UseCases\Notification\NotifyMeetingCanceledAction` を提供し、`__invoke(Meeting $meeting, User $actor)` で **相手方** に通知を発火する。送信元 actor が `student` ならコーチ宛、actor が `coach` なら受講生宛。Mail 本文には actor の役割表示を含める。
- **REQ-notification-072**: The system shall `App\UseCases\Notification\NotifyMeetingReminderAction` を提供し、`__invoke(Meeting $meeting, MeetingReminderWindow $window)` で受講生とコーチの両方に通知を発火する。
- **REQ-notification-073**: The system shall `App\Enums\MeetingReminderWindow` Enum を提供（`Eve` = 前日 18:00 / `OneHourBefore` = 1 時間前）。
- **REQ-notification-074**: When `NotifyMeetingReminderAction` が同一 `(meeting_id, window)` の組合せで再度呼ばれた場合, the system shall 既存通知の存在を JSON path クエリで検査して重複 dispatch をスキップする。
- **REQ-notification-075**: The system shall `MeetingRequestedNotification` / `MeetingApprovedNotification` / `MeetingRejectedNotification` を **提供しない**（自動割当フロー、申請・承認・拒否概念なし、v3 撤回）。
- **REQ-notification-076**: The system shall mentoring 通知すべての data に共通: `meeting_id` / `enrollment_id` / `coach_user_id` / `student_user_id` / `scheduled_at` / `topic` / `link_route='meetings.show'` を格納する。リマインダ通知は加えて `window` キーを含める。

### 機能要件 — 管理者お知らせ配信（変更なし）

- **REQ-notification-080**: The system shall `App\Http\Controllers\AnnouncementController` を提供し、`index` / `create` / `store` / `show` の 4 メソッドを持つ。
- **REQ-notification-081**: The system shall `App\UseCases\Announcement\StoreAction` を提供し、`__invoke(User $admin, array $validated): Announcement` で Announcement INSERT + 対象 User Collection 解決 + 各 User へ `NotifyAnnouncementAction` 実行 + `dispatched_count` / `dispatched_at` UPDATE を 1 トランザクションで実行する。
- **REQ-notification-082**: When `target_type=AllStudents` の場合, the system shall `User::where('role', UserRole::Student)->where('status', UserStatus::InProgress)->get()` を対象とする。
- **REQ-notification-083**: When `target_type=Certification` の場合, the system shall `User::query()->where('role', UserRole::Student)->where('status', UserStatus::InProgress)->whereHas('enrollments', fn ($q) => $q->where('certification_id', $announcement->target_certification_id)->where('status', EnrollmentStatus::Learning))->get()` を対象とする。
- **REQ-notification-084**: When `target_type=User` の場合, the system shall `User::where('id', $announcement->target_user_id)->where('role', UserRole::Student)->where('status', UserStatus::InProgress)->get()` を対象とする。
- **REQ-notification-086**: The system shall Announcement 通知の data に `admin_announcement_id` / `title` / `body` / `dispatched_at` / `target_type` / `link_route='notifications.show'` / `link_params={notification: <通知 id>}` を格納する（お知らせは遷移先となる業務画面を持たないため、通知行クリック時は通知詳細ページへ遷移する）。
- **REQ-notification-087**: The system shall 受講生がお知らせ通知をクリックした際に通知詳細ページ（`NotificationController::show` / `GET /notifications/{notification}` / route 名 `notifications.show` / `views/notifications/show.blade.php`）へ遷移させ、通知ペイロードの本文全文を表示する。認可は `NotificationPolicy::view`（受信者本人かのみ）で行い、表示時に既読化する。
- **REQ-notification-089**: The system shall 配信済お知らせの再配信 / 取消 / 削除を提供しない。

### 機能要件 — 通知一覧・既読化（変更なし）

- **REQ-notification-090**: The system shall `/notifications` エンドポイントを提供する。
- **REQ-notification-091**: The system shall `auth()->user()->notifications()->paginate(20)` を時系列降順表示する。
- **REQ-notification-092**: The system shall 2 タブ（全件 / 未読のみ）を提供する。
- **REQ-notification-093**: When 受信者が通知行をクリックした場合, the system shall 既読化 + `data.link_route` への遷移を実行する。
- **REQ-notification-094**: The system shall `POST /notifications/read-all` で一括既読化を提供する。

### 機能要件 — TopBar 通知ベル / サイドバーバッジ / 通知ポップオーバー

- **REQ-notification-100**: The system shall `NotificationBadgeComposer` で `$notificationBadge`（未読件数）を topbar / sidebar に渡す。
- **REQ-notification-101**: The system shall TopBar 通知ベルに `<x-badge variant="danger" size="sm">{count}</x-badge>` を重ねる（未読 > 0 時）。
- **REQ-notification-102**: When 未読件数 > 99 の場合, the system shall `99+` に固定する。
- **REQ-notification-103**: The system shall TopBar 通知ベルクリック時に **通知ポップオーバー（ベル横アンカー、Stripe / Jira / GitHub 風のドロップダウン Popover）** を開閉する。ポップオーバーには以下を表示する: (1) ヘッダ: 「全件」/「未読」の 2 タブ + 「全件既読」ボタン、(2) ボディ: 最新 20 件の通知行（種別アイコン + タイトル + プレビュー + 経過時間 + 未読ドット）、(3) フッター: 「すべての通知を見る →」リンク（→ `route('notifications.index')`）。ポップオーバーは ESC キー / 外側クリック / フッターリンク遷移で close する。行クリック時は REQ-notification-093 に従い既読化 + `data.link_route` への遷移を実行し、遷移前にポップオーバーを close する。
- **REQ-notification-104**: The system shall 通知ポップオーバーをベルアイコンの右下にアンカーし、固定幅 380-420px（デスクトップ・モバイル共通）+ 画面端から最小 8px の余白を確保する。コンテンツは `max-height: 70vh` で内部スクロール、`shadow-lg` / `rounded-lg` / `border` で浮遊感を付与する。
- **REQ-notification-105**: The system shall 通知ポップオーバー内容取得用に `GET /api/v1/notifications?tab={all|unread}&per_page=20` エンドポイント（route 名 `api.v1.notifications.index`、`auth:sanctum` ミドルウェアで保護される Sanctum SPA Cookie 認証）を提供し、最新 20 件 + 未読件数（meta 情報）を返す。JS フロントは `/sanctum/csrf-cookie` を初回取得後、`fetch(..., { credentials: 'include' })` で叩く。
- **REQ-notification-106**: When Pusher broadcast を受信した場合, the system shall TopBar / サイドバーバッジを +1 更新し、通知ポップオーバーが open 状態であれば先頭に新規行を prepend する（リロード不要）。

### 機能要件 — 認可・スコープ

- **REQ-notification-110**: The system shall `/notifications` / `/notifications/{notification}/read` を `auth` middleware で保護する（ロール制約なし）。
- **REQ-notification-111**: The system shall `NotificationPolicy::view` で `$notification->notifiable_id === $auth->id` を判定する。
- **REQ-notification-113**: The system shall `/admin/announcements` を `auth + role:admin` で保護する。

### 機能要件 — Broadcasting（Pusher リアルタイム push）

- **REQ-notification-120**: The system shall 各 Notification クラスに `broadcastOn(): PrivateChannel` を実装し、`new PrivateChannel("notifications.{$notifiable->id}")` を返す。
- **REQ-notification-121**: The system shall `broadcastWith()` で TopBar 更新用最小フィールドを返す。
- **REQ-notification-123**: The system shall `routes/channels.php` に `Broadcast::channel('notifications.{userId}', fn (User $user, string $userId) => (string) $user->id === $userId)` を定義する。
- **REQ-notification-124**: The system shall Notification クラスに `ShouldQueue` を implement し、Queue（database driver）で非同期化する。

### 非機能要件

- **NFR-notification-001**: The system shall すべての状態変更を `DB::transaction()` で囲み、子通知 dispatch のいずれかが失敗した場合は親レコードもロールバックする。
- **NFR-notification-002**: The system shall 通知一覧クエリに N+1 を発生させず、`data` JSON 内のキャッシュ（`sender_name` / `certification_name` 等）で関連 Model の eager load を不要とする。
- **NFR-notification-003**: The system shall ドメイン例外を `app/Exceptions/Notification/` 配下に配置する（`AnnouncementInvalidTargetException` / `AnnouncementTargetNotFoundException`）。
- **NFR-notification-005**: The system shall 通知ベルバッジ集計を 1 リクエスト 1 回の `count()` クエリに抑える。
- **NFR-notification-006**: The system shall Mail テンプレを日本語で構成し、件名 prefix を `【Certify LMS】` で統一する。
- **NFR-notification-007**: The system shall Schedule Command の重複起動下でも `notifications:send-meeting-reminders` が同一通知を重複配信しないようガードする。
- **NFR-notification-008**: The system shall Pusher 接続情報を `.env` で管理する。

## スコープ外

- **通知種別 × channel ごとの ON/OFF 設定 UI** — 全通知が Database + Mail 固定送信
- **admin 宛通知** — 運用情報は [[dashboard]] / 各 Feature の管理画面で集約
- **学習途絶リマインド通知** — v3 撤回（滞留検知 Service 自体を持たない）
- **プラン期限間近通知** — MVP 外
- **進捗節目達成通知** — ゲーミフィケーション系撤回
- **mentoring 申請・承認・拒否通知** — v3 撤回（自動割当）
- **モバイルプッシュ通知** — 教育PJスコープ外
- **管理者お知らせの再配信 / 編集 / 取消**

## 関連 Feature

- **依存元**:
  - [[chat]] — `StoreMessageAction` から `NotifyChatMessageReceivedAction` を呼ぶ
  - [[qa-board]] — `QaReply\StoreAction` から `NotifyQaReplyReceivedAction` を呼ぶ
  - [[mentoring]] — `Meeting\StoreAction` / `Meeting\CancelAction` + Schedule Command `meetings:remind` / `meetings:remind-eve` から `NotifyMeeting*Action` を呼ぶ
  - [[dashboard]] — TopBar 通知ベル / サイドバー通知バッジを `NotificationBadgeComposer` から取得
- **依存先**:
  - [[auth]] — `User` Model
