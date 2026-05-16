# notification 要件定義

> **v3 改修反映**（2026-05-16）: `CompletionApproved` 通知の発火元を admin 承認 → 受講生 `ReceiveCertificateAction` に変更、`MeetingApproved` / `MeetingRejected` / `MeetingRequested` 通知撤回（自動割当のため）、`MeetingReserved` 通知をコーチ宛のみに変更、`PlanExpireSoon` 通知撤回（MVP 外）、`StagnationReminder` 通知撤回（滞留検知 v3 撤回）、chat 通知の双方向化（コーチ→受講生 + 受講生→コーチ全員）+ コーチ間は Database のみ。

## 概要

**受講生宛 + コーチ宛通知** を扱う通知配信基盤。各 Feature が起こした学習・運用イベントを、**Database + Mail channel の両方固定送信** で対象ユーザーに届ける。**admin 宛通知のみ不採用**（運用情報は [[dashboard]] / 各 Feature の管理画面で集約確認）。

通知種別ごとの ON/OFF 設定 UI は持たない。受信者は `/notifications` で通知一覧を時系列閲覧 + 既読化、TopBar 通知ベル + サイドバーバッジで未読件数を把握する。Advance では Pusher + WebSocket で TopBar ベルへリアルタイム push、Queue（database driver）でメール配信を非同期化する。

通知種別（v3 改修後）:

| # | 通知 | 受信者 | 起点 |
|---|---|---|---|
| 1 | `ChatMessageReceivedNotification` | 受講生 → コーチ全員（DB+Mail）、コーチ → 受講生（DB+Mail）、コーチ間 Database のみ | [[chat]] `StoreMessageAction` |
| 2 | `QaReplyReceivedNotification` | スレッド投稿者（受講生） | [[qa-board]] `QaReply\StoreAction` |
| 3 | `MockExamGradedNotification` | 受験者本人（受講生） | [[mock-exam]] `SubmitAction`（採点完了後） |
| 4 | `CompletionApprovedNotification` | 受講生本人 | [[enrollment]] `ReceiveCertificateAction`（受講生自己発火） |
| 5 | `MeetingReservedNotification` | **担当コーチ宛のみ**（受講生宛は予約 UI で即時確認のため不要） | [[mentoring]] `Meeting\StoreAction` |
| 6 | `MeetingCanceledNotification` | 相手方（受講生がキャンセルしたらコーチ、コーチがキャンセルしたら受講生） | [[mentoring]] `Meeting\CancelAction` |
| 7 | `MeetingReminderNotification` | 受講生 + コーチ両方 | 本 Feature の `SendMeetingRemindersCommand`（前日 18:00 + 1h 前の 2 回） |
| 8 | `AdminAnnouncementNotification` | 対象 student 集合 | 本 Feature の `Admin\AdminAnnouncement\StoreAction` |

**撤回された通知**:
- `MeetingApprovedNotification` / `MeetingRejectedNotification` / `MeetingRequestedNotification`（mentoring 申請承認フロー撤回）
- `PlanExpireSoonNotification`（MVP 外）
- `StagnationReminderNotification`（滞留検知 v3 撤回）

## ロールごとのストーリー

- **受講生（student）**: ログイン後 TopBar 通知ベルで未読件数を確認し、`/notifications` で時系列に通知を読む。受信種別は #1（コーチからの chat 新着）/ #2 / #3 / #4 / #6 / #7 / #8。
- **コーチ（coach）**: 通知種別 #1（受講生からの chat 新着 + 他コーチ間 DB のみ）/ #5（自動割当された面談予約）/ #6（受講生による面談キャンセル）/ #7 を受信。
- **管理者（admin）**: 通知の **配信元** + 管理者お知らせ配信 UI 操作。**自分宛の通知は受信しない**。

## 受け入れ基準（EARS形式）

### 機能要件 — データ基盤

- **REQ-notification-001**: The system shall Laravel 標準の `notifications` テーブル（`DatabaseNotification` 既定スキーマ）を採用する。
- **REQ-notification-002**: The system shall `notifications.id` を ULID で発行する（`BaseNotification::__construct` 内で `$this->id = (string) Str::ulid()` 事前確定）。
- **REQ-notification-003**: The system shall `notifications.data` JSON 内に共通キー（`notification_type` / `title` / `message` / `link_route` / `link_params`）を必ず含める。
- **REQ-notification-004**: The system shall `notifications.data` JSON 内に種別固有キーを併存させる。
- **REQ-notification-010**: The system shall 管理者お知らせ用に `admin_announcements` テーブル（ULID 主キー / `title` / `body` / `target_type` enum / `target_certification_id` ulid nullable / `target_user_id` ulid nullable / `created_by_user_id` ulid / `dispatched_count` / `dispatched_at` / SoftDeletes）を新設する。
- **REQ-notification-011**: The system shall `admin_announcements.target_type` を `App\Enums\AdminAnnouncementTargetType` Enum（`AllStudents` / `Certification` / `User`）で管理する。

### 機能要件 — Notification クラス共通基盤

- **REQ-notification-020**: The system shall `App\Notifications\BaseNotification` 抽象クラス（`extends Illuminate\Notifications\Notification implements ShouldQueue`）を提供する。
- **REQ-notification-021**: The system shall すべての Notification クラスの `via($notifiable)` を **固定値 `['database', 'mail']`**（Advance Broadcasting 有効時は `['database', 'mail', 'broadcast']`）として返却する。
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

### 機能要件 — mock-exam 採点完了通知（変更なし）

- **REQ-notification-050**: The system shall `App\UseCases\Notification\NotifyMockExamGradedAction` を提供する。
- **REQ-notification-052**: The system shall data に `mock_exam_session_id` / `mock_exam_id` / `mock_exam_title` / `score_percentage` / `passed`（bool） / `passing_score` を格納する。

### 機能要件 — 修了証発行通知（自己発火型に変更）

- **REQ-notification-060**: The system shall `App\UseCases\Notification\NotifyCompletionApprovedAction` を提供し、`__invoke(Enrollment $enrollment, Certificate $certificate)` シグネチャで **受講生本人** への通知を発火する。
- **REQ-notification-061**: **発火元は [[enrollment]] の `ReceiveCertificateAction`**（受講生「修了証を受け取る」ボタン押下時、admin 承認フローではない）。Mail 本文に修了証 PDF DL URL（`route('certificates.download', $certificate)`）を含める。
- **REQ-notification-062**: The system shall data に `enrollment_id` / `certification_id` / `certification_name` / `certificate_id` / `certificate_serial_no` / `passed_at` / `link_route='certificates.show'` を格納する。

### 機能要件 — mentoring 通知（撤回・変更）

- **REQ-notification-070**: The system shall `App\UseCases\Notification\NotifyMeetingReservedAction` を提供し、`__invoke(Meeting $meeting)` で **担当コーチ宛のみ** に通知を発火する（受講生宛は予約 UI で即時確認のため不要）。Mail 本文には `Meeting.scheduled_at` / 受講生名 / `topic` / `meeting_url_snapshot` を含める。
- **REQ-notification-071**: The system shall `App\UseCases\Notification\NotifyMeetingCanceledAction` を提供し、`__invoke(Meeting $meeting, User $actor)` で **相手方** に通知を発火する。送信元 actor が `student` ならコーチ宛、actor が `coach` なら受講生宛。Mail 本文には actor の役割表示を含める。
- **REQ-notification-072**: The system shall `App\UseCases\Notification\NotifyMeetingReminderAction` を提供し、`__invoke(Meeting $meeting, MeetingReminderWindow $window)` で受講生とコーチの両方に通知を発火する。
- **REQ-notification-073**: The system shall `App\Enums\MeetingReminderWindow` Enum を提供（`Eve` = 前日 18:00 / `OneHourBefore` = 1 時間前）。
- **REQ-notification-074**: When `NotifyMeetingReminderAction` が同一 `(meeting_id, window)` の組合せで再度呼ばれた場合, the system shall 既存通知の存在を JSON path クエリで検査して重複 dispatch をスキップする。
- **REQ-notification-075**: The system shall `MeetingRequestedNotification` / `MeetingApprovedNotification` / `MeetingRejectedNotification` を **提供しない**（自動割当フロー、申請・承認・拒否概念なし、v3 撤回）。
- **REQ-notification-076**: The system shall mentoring 通知すべての data に共通: `meeting_id` / `enrollment_id` / `coach_user_id` / `student_user_id` / `scheduled_at` / `topic` / `link_route='meetings.show'` を格納する。リマインダ通知は加えて `window` キーを含める。

### 機能要件 — 管理者お知らせ配信（変更なし）

- **REQ-notification-080**: The system shall `App\Http\Controllers\Admin\AdminAnnouncementController` を提供し、`index` / `create` / `store` / `show` の 4 メソッドを持つ。
- **REQ-notification-081**: The system shall `App\UseCases\Admin\AdminAnnouncement\StoreAction` を提供し、`__invoke(User $admin, array $validated): AdminAnnouncement` で AdminAnnouncement INSERT + 対象 User Collection 解決 + 各 User へ `NotifyAdminAnnouncementAction` 実行 + `dispatched_count` / `dispatched_at` UPDATE を 1 トランザクションで実行する。
- **REQ-notification-082**: When `target_type=AllStudents` の場合, the system shall `User::where('role', UserRole::Student)->where('status', UserStatus::InProgress)->get()` を対象とする。
- **REQ-notification-083**: When `target_type=Certification` の場合, the system shall `User::query()->where('role', UserRole::Student)->where('status', UserStatus::InProgress)->whereHas('enrollments', fn ($q) => $q->where('certification_id', $announcement->target_certification_id)->where('status', EnrollmentStatus::Learning))->get()` を対象とする。
- **REQ-notification-084**: When `target_type=User` の場合, the system shall `User::where('id', $announcement->target_user_id)->where('role', UserRole::Student)->where('status', UserStatus::InProgress)->get()` を対象とする。
- **REQ-notification-086**: The system shall AdminAnnouncement 通知の data に `admin_announcement_id` / `title` / `body` / `dispatched_at` / `target_type` / `link_route='notifications.index'` を格納する。
- **REQ-notification-089**: The system shall 配信済お知らせの再配信 / 取消 / 削除を提供しない。

### 機能要件 — 通知一覧・既読化（変更なし）

- **REQ-notification-090**: The system shall `/notifications` エンドポイントを提供する。
- **REQ-notification-091**: The system shall `auth()->user()->notifications()->paginate(20)` を時系列降順表示する。
- **REQ-notification-092**: The system shall 2 タブ（全件 / 未読のみ）を提供する。
- **REQ-notification-093**: When 受信者が通知行をクリックした場合, the system shall 既読化 + `data.link_route` への遷移を実行する。
- **REQ-notification-094**: The system shall `POST /notifications/read-all` で一括既読化を提供する。

### 機能要件 — TopBar 通知ベル / サイドバーバッジ（変更なし）

- **REQ-notification-100**: The system shall `NotificationBadgeComposer` で `$notificationBadge`（未読件数）を topbar / sidebar に渡す。
- **REQ-notification-101**: The system shall TopBar 通知ベルに `<x-badge variant="danger" size="sm">{count}</x-badge>` を重ねる（未読 > 0 時）。
- **REQ-notification-102**: When 未読件数 > 99 の場合, the system shall `99+` に固定する。
- **REQ-notification-103**: The system shall TopBar 通知ベルクリックでドロップダウン（最近 5 件 + 「すべての通知」リンク）を表示する。

### 機能要件 — 認可・スコープ

- **REQ-notification-110**: The system shall `/notifications` / `/notifications/{notification}/read` を `auth` middleware で保護する（ロール制約なし）。
- **REQ-notification-111**: The system shall `NotificationPolicy::view` で `$notification->notifiable_id === $auth->id` を判定する。
- **REQ-notification-113**: The system shall `/admin/announcements` を `auth + role:admin` で保護する。

### 機能要件 — Advance Broadcasting

- **REQ-notification-120**: The system shall 各 Notification クラスに `broadcastOn(): PrivateChannel` を実装し、`new PrivateChannel("notifications.{$notifiable->id}")` を返す。
- **REQ-notification-121**: The system shall `broadcastWith()` で TopBar 更新用最小フィールドを返す。
- **REQ-notification-123**: The system shall `routes/channels.php` に `Broadcast::channel('notifications.{userId}', fn (User $user, string $userId) => (string) $user->id === $userId)` を定義する。
- **REQ-notification-124**: The system shall Notification クラスに `ShouldQueue` を implement し、Queue（database driver）で非同期化する。

### 非機能要件

- **NFR-notification-001**: The system shall すべての状態変更を `DB::transaction()` で囲み、子通知 dispatch のいずれかが失敗した場合は親レコードもロールバックする。
- **NFR-notification-002**: The system shall 通知一覧クエリに N+1 を発生させず、`data` JSON 内のキャッシュ（`sender_name` / `certification_name` 等）で関連 Model の eager load を不要とする。
- **NFR-notification-003**: The system shall ドメイン例外を `app/Exceptions/Notification/` 配下に配置する（`AdminAnnouncementInvalidTargetException` / `AdminAnnouncementTargetNotFoundException`）。
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
  - [[mock-exam]] — `SubmitAction` の `DB::afterCommit` から `NotifyMockExamGradedAction` を呼ぶ
  - [[enrollment]] — `ReceiveCertificateAction` の `DB::afterCommit` から `NotifyCompletionApprovedAction` を呼ぶ
  - [[mentoring]] — `Meeting\StoreAction` / `Meeting\CancelAction` + Schedule Command `meetings:remind` / `meetings:remind-eve` から `NotifyMeeting*Action` を呼ぶ
  - [[dashboard]] — TopBar 通知ベル / サイドバー通知バッジを `NotificationBadgeComposer` から取得
- **依存先**:
  - [[auth]] — `User` Model
  - [[certification-management]] — `Certificate` Model（修了承認通知の DL URL 用）
