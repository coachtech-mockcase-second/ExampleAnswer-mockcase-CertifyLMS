# notification 要件定義

## 概要

**受講生宛通知のみ**を扱う通知配信基盤。各 Feature が起こした受講生視点で価値の高いイベント（面談承認 / 修了承認・修了証発行 / 模試採点完了 / 管理者お知らせ / Q&A 回答受信 / chat 新着（コーチ → 受講生）/ 面談前日リマインド）の **7 種類のみ**を、**Database + Mail channel の両方固定送信**で受講生に届ける。admin / coach 宛通知はすべて [[dashboard]] の集約表示に委ね、本 Feature では扱わない。

通知種別ごとの ON/OFF 設定 UI も持たず、`UserNotificationSetting` テーブル / `NotificationType` Enum / `NotificationChannel` Enum / `RespectsUserPreference` trait といった設定マトリクス基盤は **不採用**（[[settings-profile]] にも通知設定タブを置かない）。受講生は `/notifications` で通知一覧を時系列閲覧し、TopBar 通知ベル + サイドバーバッジで未読件数を把握する。Advance では Pusher + WebSocket で TopBar ベルへリアルタイム push、Queue（database driver）でメール配信を非同期化する。

通知配信の **Notification クラス・ラッパー Action・Schedule Command・通知一覧 UI** はすべて本 Feature が一括所有し、各 Feature の Action は `app(NotifyXxxAction::class)($entity)` を呼ぶだけ。これにより通知発火ロジックを 1 箇所で保証し、変更コストを局所化する。

## ロールごとのストーリー

- **受講生（student）**: ログイン後 TopBar 通知ベルで未読件数を確認し、`/notifications` で時系列に通知を読む。面談承認・修了承認 / 修了証発行・模試採点完了・管理者お知らせ・Q&A 自分質問への回答・コーチからの chat 新着・面談前日リマインドの **7 種類**を受け取る。通知行クリックで既読化 + 関連リソース画面へ遷移する。**通知設定 UI は提供しないため**、すべての種別が常に Database + Mail で届く。
- **コーチ（coach）**: 通知の **配信元**として `app(NotifyMeetingApprovedAction::class)($meeting)` 等を各 Action 内で呼び出すのみ。**自分宛の通知は受信しない**（修了申請通知 / 面談予約申請通知等は不採用）。担当受講生からの chat / 未回答 Q&A / 面談予定等の運用情報は [[dashboard]] の coach 用ウィジェットで集約確認する。
- **管理者（admin）**: 通知の **配信元** + **管理者お知らせ配信** UI 操作。`/admin/announcements` で配信フォーム + 履歴閲覧。**自分宛の通知は受信しない**（修了申請受付通知等は不採用）。修了申請待ち / 滞留検知 / 全体 KPI 等は [[dashboard]] の admin 用ウィジェットで集約確認する。

## 受け入れ基準（EARS形式）

### 機能要件 — データ基盤

- **REQ-notification-001**: The system shall Laravel 標準の `notifications` テーブル（`DatabaseNotification` 既定スキーマ: `id` / `type` / `notifiable_type` / `notifiable_id` / `data` JSON / `read_at` / `created_at` / `updated_at`）を採用する。
- **REQ-notification-002**: The system shall `notifications.id` を ULID で発行する（Laravel の `DatabaseChannel::send` が `$notification->id` を参照する仕様に対し、`App\Notifications\BaseNotification::__construct` 内で `$this->id = (string) Str::ulid()` を事前確定）。
- **REQ-notification-003**: The system shall `notifications.data` JSON 内に **共通キー**（`notification_type`: 文字列識別子、`title`: 表示タイトル、`message`: 表示本文プレビュー、`link_route`: 遷移先ルート名、`link_params`: 遷移先パラメータ連想配列）を必ず含める。
- **REQ-notification-004**: The system shall `notifications.data` JSON 内に **種別固有キー**を併存させ、通知一覧画面と Mail テンプレが共通キーと種別固有キーを併用してレンダリングできる構造とする。
- **REQ-notification-010**: The system shall 管理者お知らせ用に `admin_announcements` テーブル（ULID 主キー / `title` / `body` / `target_type` enum / `target_certification_id` ulid nullable / `target_user_id` ulid nullable / `created_by_user_id` ulid / `dispatched_count` unsignedInteger / `dispatched_at` datetime / SoftDeletes）を新設する。
- **REQ-notification-011**: The system shall `admin_announcements.target_type` を `App\Enums\AdminAnnouncementTargetType` Enum（`AllStudents` / `Certification` / `User`）で管理する。
- **REQ-notification-012**: The system shall `admin_announcements.target_certification_id` を `target_type=Certification` のときのみ NOT NULL、それ以外は NULL に保つ（Action 側のドメインガード + データ整合性検査で担保）。
- **REQ-notification-013**: The system shall `admin_announcements.target_user_id` を `target_type=User` のときのみ NOT NULL、それ以外は NULL に保つ（同上のガード方針）。

### 機能要件 — Notification クラス共通基盤

- **REQ-notification-020**: The system shall `App\Notifications\BaseNotification` 抽象クラス（`extends Illuminate\Notifications\Notification implements ShouldQueue`）を提供し、`__construct` 内で `$this->id = (string) Str::ulid()` を設定する。子 Notification クラスは `parent::__construct()` を必ず呼ぶ。
- **REQ-notification-021**: The system shall すべての Notification クラスの `via($notifiable)` を **固定値 `['database', 'mail']`**（Advance の Broadcasting 有効時は `['database', 'mail', 'broadcast']`）として返却し、ユーザー設定による分岐を一切行わない（設定 UI を持たない方針と整合）。
- **REQ-notification-022**: The system shall 各通知種別に対応する `App\UseCases\Notification\Notify{Type}Action.php` ラッパー Action を `app/UseCases/Notification/` 配下に提供し、各 Feature の Action から `app(NotifyXxxAction::class)($entity)` で呼び出せるようにする。
- **REQ-notification-023**: When ラッパー Action `Notify{Type}Action::__invoke` が呼ばれる際, the system shall 受信者の解決 → 受信者の `status === active` 確認 → `$user->notify(new {Type}Notification(...))` を実行する。
- **REQ-notification-024**: If 受信者の `User.status` が `withdrawn` の場合, then the system shall 該当受信者への通知 dispatch をスキップする（`UserStatus` Enum 比較）。
- **REQ-notification-025**: The system shall 通知の `toMail` を `Illuminate\Notifications\Messages\MailMessage` で構成し、独立 `Mailable` クラスは作成しない（テンプレは `MailMessage` の `->greeting() / ->line() / ->action()` で完結）。
- **REQ-notification-026**: The system shall すべての受講生宛通知の受信者ロールを `UserRole::Student` に限定する（chat 通知のみ「コーチ → 受講生」方向のみ通知発火、受講生 → コーチ送信時は通知 dispatch しない）。

### 機能要件 — chat 新着通知

- **REQ-notification-030**: The system shall `App\UseCases\Notification\NotifyChatMessageReceivedAction` を提供し、`__invoke(ChatMessage $message)` シグネチャで受信者（受講生）への通知を発火する。
- **REQ-notification-031**: When chat メッセージの送信者がコーチの場合, the system shall 受信者（受講生）に Database + Mail channel の両方で通知を発行する。
- **REQ-notification-032**: If chat メッセージの送信者が受講生の場合, then the system shall 通知 dispatch を **スキップ**する（コーチ宛通知ゼロの方針、コーチは [[dashboard]] の「未対応 chat 件数」ウィジェットで確認）。
- **REQ-notification-033**: The system shall chat 通知の `data` に `chat_room_id` / `chat_message_id` / `sender_user_id` / `sender_name` / `body_preview`（先頭 100 字）/ `link_route='chat.show'` / `link_params={'room': $room->id}` を格納する。

### 機能要件 — Q&A 回答受信通知

- **REQ-notification-040**: The system shall `App\UseCases\Notification\NotifyQaReplyReceivedAction` を提供し、`__invoke(QaReply $reply)` シグネチャで受信者（スレッド投稿者）への通知を発火する。
- **REQ-notification-041**: If `$reply->user_id === $reply->thread->user_id` の場合（自己回答）, then the system shall 通知を dispatch しない。
- **REQ-notification-042**: The system shall Q&A 通知を Database + Mail channel の両方で発行する。
- **REQ-notification-043**: The system shall Q&A 通知の `data` に `qa_thread_id` / `qa_reply_id` / `replier_user_id` / `replier_name` / `thread_title` / `body_preview`（先頭 100 字）/ `link_route='qa-board.show'` / `link_params={'thread': $thread->id}` を格納する。

### 機能要件 — mock-exam 採点完了通知

- **REQ-notification-050**: The system shall `App\UseCases\Notification\NotifyMockExamGradedAction` を提供し、`__invoke(MockExamSession $session)` シグネチャで受験者本人への通知を発火する。
- **REQ-notification-051**: When mock-exam セッションが採点完了した場合, the system shall Database + Mail channel の両方で通知を発行する。
- **REQ-notification-052**: The system shall mock-exam 通知の `data` に `mock_exam_session_id` / `mock_exam_id` / `mock_exam_title` / `score_percentage` / `passed`（bool） / `passing_score` を格納する。
- **REQ-notification-053**: The system shall mock-exam 通知の `link_route` に `mock-exams.sessions.show` を設定し、`link_params` に `mockExam` と `session` を渡す。

### 機能要件 — enrollment 修了承認通知

- **REQ-notification-060**: The system shall `App\UseCases\Notification\NotifyCompletionApprovedAction` を提供し、`__invoke(Enrollment $enrollment, Certificate $certificate)` シグネチャで受講生本人への通知を発火する。
- **REQ-notification-061**: When 修了認定が承認され Certificate が発行された場合, the system shall Database + Mail channel の両方で通知を発行し、Mail 本文に修了証ダウンロード URL（`route('certificates.download', $certificate)`）を含める。
- **REQ-notification-062**: The system shall 修了承認通知の `data` に `enrollment_id` / `certification_id` / `certification_name` / `certificate_id` / `certificate_serial_no` / `passed_at` / `link_route='certificates.show'` / `link_params={'certificate': $certificate->id}` を格納する。
- **REQ-notification-063**: The system shall 受講生による修了申請時の admin 宛通知を **発火しない**（admin は [[dashboard]] の「修了申請待ち一覧」ウィジェットで確認、メール通知不要）。

### 機能要件 — mentoring 通知

- **REQ-notification-070**: The system shall `App\UseCases\Notification\NotifyMeetingApprovedAction` を提供し、`__invoke(Meeting $meeting)` シグネチャで受講生への通知を発火する。Mail 本文には `meeting_url_snapshot` と `scheduled_at` を含める。
- **REQ-notification-071**: The system shall `App\UseCases\Notification\NotifyMeetingReminderAction` を提供し、`__invoke(Meeting $meeting)` シグネチャで受講生への通知を発火する。1 時間前リマインドは **採用しない**、前日 18:00 リマインドのみ。
- **REQ-notification-072**: When `NotifyMeetingReminderAction` が同一 `meeting_id` で再度呼ばれた場合, the system shall **既存通知が `notifications` テーブルに存在するか** `data->>'$.meeting_id'` の JSON path クエリで検査し、既存の場合は新規 dispatch をスキップする（Schedule Command の重複起動下でも 1 通のみ配信）。
- **REQ-notification-073**: The system shall mentoring 通知 2 種類すべてを Database + Mail channel の両方で発行する。
- **REQ-notification-074**: The system shall mentoring 通知の `data` に共通: `meeting_id` / `enrollment_id` / `coach_user_id` / `scheduled_at` / `topic` / `link_route='meetings.show'` / `link_params={'meeting': $meeting->id}` を格納する。
- **REQ-notification-075**: The system shall 面談予約申請通知（コーチ宛）/ 面談拒否通知（受講生宛）/ 面談キャンセル通知（相手方宛）/ 1 時間前リマインド を **発火しない**（受講生宛・前日 18 時のみに絞り込み）。

### 機能要件 — 管理者お知らせ配信

- **REQ-notification-080**: The system shall `App\Http\Controllers\Admin\AdminAnnouncementController` を提供し、`index` / `create` / `store` / `show` の 4 メソッドを持つ（`update` / `destroy` は提供しない、配信済お知らせは記録として温存）。
- **REQ-notification-081**: The system shall `App\UseCases\Admin\AdminAnnouncement\StoreAction` を提供し、`__invoke(User $admin, array $validated): AdminAnnouncement` シグネチャで以下を 1 トランザクション内で実行する: (1) `admin_announcements` INSERT、(2) `target_type` に応じた対象 User Collection の解決、(3) 各対象 User へ `App\UseCases\Notification\NotifyAdminAnnouncementAction` を実行、(4) `dispatched_count` / `dispatched_at` UPDATE、(5) commit。
- **REQ-notification-082**: When `target_type=AllStudents` の場合, the system shall `User::where('role', UserRole::Student)->where('status', UserStatus::Active)->get()` を対象とする。
- **REQ-notification-083**: When `target_type=Certification` の場合, the system shall `User::query()->where('role', UserRole::Student)->where('status', UserStatus::Active)->whereHas('enrollments', fn ($q) => $q->where('certification_id', $announcement->target_certification_id)->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Paused]))->get()` を対象とする。
- **REQ-notification-084**: When `target_type=User` の場合, the system shall `User::where('id', $announcement->target_user_id)->where('role', UserRole::Student)->where('status', UserStatus::Active)->get()` を対象とする（受講生のみ配信、status 違反時は空 Collection で配信件数 0）。
- **REQ-notification-085**: The system shall 管理者お知らせ通知を Database + Mail channel の両方で発行する。
- **REQ-notification-086**: The system shall 管理者お知らせ通知の `data` に `admin_announcement_id` / `title` / `body`（全文）/ `dispatched_at` / `target_type` / `link_route='notifications.index'`（管理者お知らせは外部画面遷移不要、通知一覧本体で本文表示）を格納する。
- **REQ-notification-087**: The system shall `AdminAnnouncementController::index` で配信済お知らせを `dispatched_at DESC` で 20 件ページネーション表示する（タイトル / 配信日時 / 対象種別 / 配信件数）。
- **REQ-notification-088**: The system shall `AdminAnnouncementController::show` で個別お知らせの詳細（タイトル全文 / 本文全文 / 対象種別の解決結果サマリ / `dispatched_count` / `dispatched_at`）を表示する。
- **REQ-notification-089**: The system shall 配信済お知らせの再配信 / 取消 / 削除を提供しない（誤送信防止 + 配信履歴の不変保証）。

### 機能要件 — 通知一覧・既読化

- **REQ-notification-090**: The system shall 受講生向けの `/notifications` エンドポイント（`App\Http\Controllers\NotificationController::index`）を提供する。コーチ / admin もログインしていれば自分宛通知（実質ゼロ件）の閲覧画面にアクセス可能だが、通常運用では空表示。
- **REQ-notification-091**: The system shall `NotificationController::index` で `auth()->user()->notifications()->paginate(20)` の結果を時系列降順で表示する。
- **REQ-notification-092**: The system shall 通知一覧画面に **2 タブ**（全件 / 未読のみ）を提供し、`?tab=unread` で未読のみフィルタする。
- **REQ-notification-093**: When 受講生が通知行をクリックした場合, the system shall `POST /notifications/{notification}/read` で個別既読化（`read_at = now()`）を実行し、`data.link_route` / `data.link_params` の遷移先へリダイレクトする。
- **REQ-notification-094**: The system shall `POST /notifications/read-all` で `auth()->user()->unreadNotifications->markAsRead()` の一括既読化を提供する。
- **REQ-notification-095**: The system shall 通知が未読 / 既読を `read_at` の有無で判定し、未読は `<x-badge variant="danger">未読</x-badge>` で視覚的に区別する。

### 機能要件 — TopBar 通知ベル / サイドバーバッジ

- **REQ-notification-100**: The system shall `App\View\Composers\NotificationBadgeComposer` を提供し、`layouts/_partials/topbar.blade.php` および `sidebar-{role}.blade.php` に `$notificationBadge`（未読件数 int / 99 超は 99 で打ち切り表示判定用 raw count も同梱）を渡す。
- **REQ-notification-101**: The system shall TopBar 通知ベルを `<button>` でラップし、未読件数 > 0 のとき `<x-badge variant="danger" size="sm">{count}</x-badge>` を重ねる。
- **REQ-notification-102**: When 未読件数 > 99 の場合, the system shall TopBar 通知ベルバッジ表示を `99+` に固定する。
- **REQ-notification-103**: The system shall TopBar 通知ベルクリックで **ドロップダウン** を表示し、最近 5 件の通知プレビュー + 「すべての通知を見る」リンク（`route('notifications.index')`）を表示する。
- **REQ-notification-104**: The system shall サイドバー「通知」メニュー項目（[[frontend-ui-foundation]] サイドバー構造定義済）に同じ未読件数バッジを表示する。

### 機能要件 — 認可・スコープ

- **REQ-notification-110**: The system shall `/notifications` および `/notifications/{notification}/read` を `auth` middleware で保護する（未認証は `/login` リダイレクト、ロール制約なし）。
- **REQ-notification-111**: The system shall `App\Policies\NotificationPolicy` を提供し、`view(User $auth, DatabaseNotification $notification): bool` を `$notification->notifiable_id === $auth->id && $notification->notifiable_type === User::class` で判定する。
- **REQ-notification-112**: The system shall `markAsRead` / `markAllAsRead` 系操作で他人の通知を既読化できないよう `auth()->user()->notifications()` 経由で必ずスコープ化する。
- **REQ-notification-113**: The system shall `/admin/announcements` 系ルートを `auth + role:admin` middleware で保護する。
- **REQ-notification-114**: The system shall `App\Policies\AdminAnnouncementPolicy` を提供し、`viewAny` / `view` / `create` のすべてで `$auth->role === UserRole::Admin` を判定する（`update` / `delete` は不採用）。

### 機能要件 — Advance Broadcasting

- **REQ-notification-120**: The system shall 各 Notification クラスに `broadcastOn(): PrivateChannel` メソッドを実装し、`new PrivateChannel("notifications.{$notifiable->id}")` を返す。
- **REQ-notification-121**: The system shall 各 Notification クラスに `broadcastWith(): array` メソッドを実装し、TopBar ドロップダウン更新に必要な最小フィールド（`id` / `notification_type` / `title` / `message` / `created_at`）を返す。
- **REQ-notification-122**: When 受講生が認証後画面を開いた状態で新着通知が dispatch された場合, the system shall Pusher 経由で `notifications.{user_id}` channel に push し、クライアント JS（`resources/js/notification/realtime.js`）が TopBar ベルバッジ件数とドロップダウン内容を **画面リロード無し** で更新する。
- **REQ-notification-123**: The system shall `routes/channels.php` に `Broadcast::channel('notifications.{userId}', fn (User $user, string $userId) => (string) $user->id === $userId)` を定義し、自分宛 channel のみ subscribe を許可する。
- **REQ-notification-124**: The system shall Notification クラスに `ShouldQueue` を implement し、Mail channel 配信 + Broadcasting を Queue（database driver）の Worker 経由で非同期化する。
- **REQ-notification-125**: The system shall Queue Worker を `sail artisan queue:work --queue=default,notifications --tries=3 --backoff=10` で起動する運用前提で、`config/queue.php` の `database` driver 設定を Wave 0b で整備済として参照する。

### 非機能要件

- **NFR-notification-001**: The system shall すべての状態変更（`AdminAnnouncement` INSERT + 子通知 INSERT 群）を `DB::transaction()` で囲み、子通知 dispatch のいずれかが失敗した場合は親レコードの `dispatched_count` / `dispatched_at` を含めて全体ロールバックする。
- **NFR-notification-002**: The system shall 通知一覧クエリ（`auth()->user()->notifications()`）に N+1 を発生させず、`data` JSON 内のキャッシュ（`sender_name` / `certification_name` 等）で関連 Model の eager load を不要とする。
- **NFR-notification-003**: The system shall ドメイン例外を `app/Exceptions/Notification/` 配下に配置し、`AdminAnnouncementInvalidTargetException`（HTTP 422）/ `AdminAnnouncementTargetNotFoundException`（HTTP 404）を提供する。
- **NFR-notification-004**: The system shall 通知一覧 / 管理者お知らせ画面のすべての Blade を `frontend-blade.md`「共通コンポーネント API」のみで構成し、独自 UI コンポーネントを追加しない。
- **NFR-notification-005**: The system shall 通知ベルバッジ集計（`NotificationBadgeComposer`）を 1 リクエスト 1 回の `count()` クエリに抑える。
- **NFR-notification-006**: The system shall Mail テンプレ（`MailMessage` の `->greeting() / ->line() / ->action()`）を日本語で構成し、件名 prefix を `【Certify LMS】` で統一する。
- **NFR-notification-007**: The system shall Schedule Command の重複起動下でも `notifications:send-meeting-reminders` が同一通知を重複配信しないよう、`NotifyMeetingReminderAction` 内に既存通知存在検査を組み込む。
- **NFR-notification-008**: The system shall Pusher 接続情報（`PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_ID` / `PUSHER_APP_CLUSTER`）を `.env` で管理し、コード内ハードコーディングを禁止する。`.env.example` には dummy 値を記述する。
- **NFR-notification-009**: The system shall Notification クラスのテストで `Notification::fake()` を利用し、実 Mail 送信なしで dispatch を assert する（`Notification::assertSentTo($user, MeetingApprovedNotification::class)`）。
- **NFR-notification-010**: The system shall 通知ベル / 通知一覧の WCAG 2.1 AA 準拠（`aria-label="未読 N 件"` / `role="alert"` 等）を `frontend-ui-foundation.md`「アクセシビリティ要件」に従う。

## スコープ外

- **通知種別 × channel ごとの ON/OFF 設定 UI**: 全通知が Database + Mail 固定送信、ユーザー設定は採用しない（product.md「## スコープ外」参照）。`UserNotificationSetting` / `NotificationType` Enum / `NotificationChannel` Enum / `RespectsUserPreference` trait はすべて未採用
- **admin / coach 宛通知**: 修了申請受付通知（admin 宛）/ 面談予約申請通知（コーチ宛）/ 面談キャンセル通知（相手方）/ chat 受講生送信時のコーチ宛通知 など、admin / coach が受信する通知はすべて未採用。運用情報は [[dashboard]] で集約確認
- **学習途絶リマインド通知**: 受講生本人へのメールは送らない（[[dashboard]] が受講生向けに「最終学習日」「ストリーク途切れ」を視覚化する責務、`StagnationDetectionService` は admin/coach 用滞留検知リストの集計でのみ利用）
- **進捗節目達成通知**（Section / Chapter / Part 完了 / 資格 50% 等）: ゲーミフィケーション系として product.md「## スコープ外」記載、ストリーク + 進捗ゲージ + 個人目標タイムラインで代替
- **面談予約申請 / 拒否 / キャンセル通知**: dashboard の面談一覧 + 通知一覧（承認・リマインドのみ）で十分
- **面談 1 時間前リマインド**: 前日 18 時のみ採用、1 時間前リマインドは過剰として不採用
- **モバイルプッシュ通知**（FCM / APNs）: 教育PJスコープ外、Web 通知のみ
- **通知のスヌーズ / リマインダ再設定 / 通知音 / デスクトップ通知**: 標準 LMS 範囲外
- **通知の永続削除 / アーカイブ**: 既読のみで運用、`notifications` テーブルの物理削除は管理運用領域
- **管理者お知らせの再配信 / 編集 / 取消 / 予約配信**: 即時配信のみ、誤送信防止のため不可
- **管理者お知らせのリッチテキスト / Markdown / 画像添付**: テキストのみ（XSS 対策で `nl2br(e($body))` パターン）
- **コーチによる担当受講生への一斉メッセージ**: chat の 1on1 のみ、admin お知らせとは別軸（実装するなら chat 拡張 or 新 Feature）

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[chat]] — `StoreMessageAction` から、送信者がコーチの場合のみ `NotifyChatMessageReceivedAction` を呼ぶ（受講生送信時は呼ばない）
  - [[qa-board]] — `QaReply\StoreAction` から `NotifyQaReplyReceivedAction` を呼ぶ
  - [[mock-exam]] — `MockExamSession\SubmitAction` の `DB::afterCommit` から `NotifyMockExamGradedAction` を呼ぶ
  - [[enrollment]] — `Admin\Enrollment\ApproveCompletionAction` の `DB::afterCommit` から `NotifyCompletionApprovedAction` を呼ぶ（`RequestCompletionAction` からは通知発火しない、admin 宛通知不採用方針）
  - [[mentoring]] — `ApproveAction` から `NotifyMeetingApprovedAction` を呼ぶ（`StoreAction` / `RejectAction` / `CancelAction` からは通知発火しない、受講生宛のみ採用方針）。Schedule Command `notifications:send-meeting-reminders` は本 Feature が所有
  - [[dashboard]] — TopBar 通知ベル / サイドバー通知バッジを `NotificationBadgeComposer` から取得。admin / coach 向け運用情報（修了申請待ち / 未対応 chat / 滞留検知等）は dashboard 側で集約表示し、本 Feature の通知配信には依存しない
- **依存先**（本 Feature が前提とする）:
  - [[auth]] — `User` Model（`Notifiable` trait 適用済）
  - [[certification-management]] — `Certificate` Model（修了承認通知の Mail 内ダウンロード URL 用）
  - [[settings-profile]] — **通知設定タブ / `UserNotificationSetting` モデル / `NotificationType` Enum / `NotificationChannel` Enum は不要**（協調修正で settings-profile spec から削除）
