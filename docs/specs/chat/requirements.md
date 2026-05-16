# chat 要件定義

> **v3 改修反映**（2026-05-16）+ **E-2 添付削除**:
> - 1on1 → **1 資格 1 グループルーム**(受講生 + 担当資格コーチ全員)、`ChatMember` 中間テーブル新設、`ChatRoom.status` 削除
> - **Pusher Broadcasting** によるリアルタイム配信、未読バッジは `ChatMember.last_read_at` で個人別管理
> - **添付ファイル機能を完全撤回**(`chat_attachments` テーブル / `ChatAttachment` Model / Storage private / signed URL すべて削除)。短期相談用途のため、画像 / ファイル共有は qa-board / 教材本文 / 面談メモへ誘導
> - `EnsureActiveLearning` Middleware 連動(`graduated` ユーザーは chat 利用不可)

## 概要

受講生と **担当資格コーチ集合** が **資格単位の 1 グループルームで非同期 + リアルタイムにメッセージをやり取りする** Feature。1 Enrollment あたり 1 ChatRoom、参加者は `ChatMember` 中間テーブルで管理。**テキスト本文のみ**(添付ファイル非対応)。状態管理は持たず、Pusher Broadcasting で新着メッセージを当事者に即時 push、未読バッジは個人別の `last_read_at` で集計する。

## ロールごとのストーリー

- **受講生(student)**: 受講中の資格ごとに **担当資格コーチ全員と 1 つのグループルーム** でテキストメッセージを送り、コーチからの返信を未読バッジで気付き、リアルタイムで新着メッセージを受信する。担当資格にコーチが 1 人も割当てられていない場合は「コーチ未割当」と表示され、メッセージ送信を試みると 422 エラーで遮断される。
- **コーチ(coach)**: 自分が担当する資格に登録した受講生集合の各 ChatRoom にアクセス。未読あり一覧から返信を開始、リアルタイムで新着メッセージを受信する。他コーチが先に返信していても、すべてのコーチが対等にチャットに参加する。
- **管理者(admin)**: 監査目的で全 ChatRoom / 全メッセージを **閲覧のみ** 可。送信フォームは Blade で表示されず、POST も Policy で拒否される。

## 受け入れ基準(EARS形式)

### 機能要件 — ChatRoom と ChatMember

- **REQ-chat-001**: The system shall ULID 主キー / SoftDeletes を備えた `chat_rooms` テーブルを提供し、`enrollment_id`(FK, UNIQUE) / `last_message_at`(datetime, nullable) / timestamps / `deleted_at` を保持する。**`status` カラムは持たない**。
- **REQ-chat-002**: The system shall ULID 主キー / SoftDeletes を備えた `chat_members` テーブル(新設)を提供し、`chat_room_id`(FK, cascadeOnDelete)/ `user_id`(FK)/ `last_read_at`(datetime, nullable)/ `joined_at`(datetime, NOT NULL)/ timestamps / `deleted_at` を保持する。`(chat_room_id, user_id)` UNIQUE。
- **REQ-chat-003**: When 受講生が初回メッセージを送信した際, the system shall 送信処理と同一トランザクション内で (1) `ChatRoom` が未存在なら作成、(2) **当該 `Enrollment.certification` の担当コーチ集合全員 + 受講生本人** の `ChatMember` を一括 INSERT(既存メンバーは skip)、(3) `ChatMessage` を INSERT、を実行する。
- **REQ-chat-004**: When 受講生が担当コーチ未割当の `Enrollment`(`certification.coaches` 0 件)に対してメッセージ送信を試みた場合, the system shall HTTP 422 で拒否し「担当コーチが割り当てられていません」エラーメッセージを返す。
- **REQ-chat-005**: When 担当コーチ集合の変更(`certification_coach_assignments` 追加 / 削除)が発生した際, the system shall 本 Feature の Event Listener で対応する全 ChatRoom の `ChatMember` を同期する。

### 機能要件 — ChatMessage 送受信(テキストのみ、E-2 で添付撤回)

- **REQ-chat-010**: The system shall ULID 主キー / SoftDeletes を備えた `chat_messages` テーブルを提供し、`chat_room_id`(FK, cascadeOnDelete)/ `sender_user_id`(FK)/ `body`(text, NOT NULL, max 2000)/ timestamps / `deleted_at` を保持する。
- **REQ-chat-011**: The system shall 当事者(`ChatMember` で参加している User)のみがメッセージ送信できるよう Policy で制御する(admin は送信不可、閲覧のみ)。
- **REQ-chat-012**: When ルーム詳細画面が表示される場合, the system shall メッセージを `created_at ASC` で時系列表示し、現在ログイン User の `ChatMember.last_read_at = now()` を UPDATE する(admin は除く)。
- **REQ-chat-013**: When メッセージが描画される場合, the system shall 自分の送信したメッセージ / 他者(受講生 vs コーチ vs 他コーチ)を視覚的に区別表示する。
- **REQ-chat-014**: The system shall メッセージの **編集 / 削除を提供しない**(学習相談の改竄防止 + admin 監査の信頼性)。
- **REQ-chat-015**: When `admin` ロールがルーム詳細画面を閲覧する場合, the system shall メッセージ送信フォームを描画しないし、`POST /chat-rooms/{room}/messages` も Policy で 403 を返す。admin の閲覧では `ChatMember.last_read_at` を更新しない。

### 機能要件 — 未読・既読バッジ(ChatMember 単位、E-2 で要点明示)

- **REQ-chat-030**: The system shall ログイン User の **ルーム別未読件数** を `COUNT(chat_messages WHERE chat_room_id = ? AND sender_user_id != $auth->id AND (created_at > ChatMember.last_read_at OR ChatMember.last_read_at IS NULL) AND deleted_at IS NULL)` で算出する。
- **REQ-chat-031**: The system shall サイドバーには **自分が ChatMember として参加しているルームのうち、未読メッセージを 1 件以上含むルームの総数** を `SidebarBadgeComposer` 経由で渡す。
- **REQ-chat-032**: When 受講生またはコーチがルーム詳細画面を `GET` した場合, the system shall **自分自身の `ChatMember.last_read_at = now()` のみ** を UPDATE する。**他のコーチの `last_read_at` には影響しない**(個人別既読、グループ chat として自然な仕様: ある コーチが既読を付けても、別の コーチには未読バッジが残る)。
- **REQ-chat-033**: The system shall コーチが「未読あり」ルームをドリルダウンする一覧を、`ChatMember.user_id = auth.id AND (last_read_at IS NULL OR last_read_at < ChatMessage.created_at)` を満たすルームでフィルタする。

### 機能要件 — Pusher リアルタイム配信

- **REQ-chat-040**: The system shall メッセージ INSERT 後、`DB::afterCommit()` で `Broadcast::on(new PrivateChannel("chat-room.{chat_room_id}"))` 経由でブロードキャストし、当該 ChatRoom の全 ChatMember に新着メッセージを即時 push する。
- **REQ-chat-041**: The system shall `App\Events\ChatMessageSent` ブロードキャストイベントを実装し、`broadcastWith()` で `{ id, body, sender_user_id, sender_name, sender_role, created_at }` を返す(添付撤回により `attachments` フィールドなし)。
- **REQ-chat-042**: The system shall `routes/channels.php` に `Broadcast::channel('chat-room.{chatRoomId}', fn (User $user, string $chatRoomId) => ChatMember::where('chat_room_id', $chatRoomId)->where('user_id', $user->id)->whereNull('deleted_at')->exists())` を定義し、ChatMember のみ subscribe を許可する。
- **REQ-chat-043**: The system shall chat 画面のクライアント JS(`resources/js/chat/realtime.js`)で Echo + Pusher を初期化し、`chat-room.{id}` channel を購読、`.listen('ChatMessageSent', callback)` でメッセージリストに追記する。
- **REQ-chat-044**: The system shall Pusher 接続情報を `.env`(`PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_ID` / `PUSHER_APP_CLUSTER`)で管理する。
- **REQ-chat-045**: The system shall Pusher が未設定 / 通信失敗の場合でも、ページリロードで最新メッセージを取得できる構成とする(Pusher は補助、DB が真実源)。

### 機能要件 — 一覧・閲覧画面

- **REQ-chat-050**: When 受講生が `GET /chat-rooms` にアクセスした場合, the system shall 自分が ChatMember の全 `ChatRoom` を `enrollment.certification.name` / 担当コーチ名一覧 / 自分宛未読件数 / `last_message_at` / 最終メッセージプレビュー付きで `last_message_at DESC` 順に一覧表示する。
- **REQ-chat-051**: When コーチが `GET /coach/chat-rooms` にアクセスした場合, the system shall 自分が ChatMember の全 `ChatRoom` を同等のメタ情報付きで、デフォルトは「未読あり」フィルタで表示する。
- **REQ-chat-052**: The system shall コーチ一覧画面に「未読あり / すべて」フィルタ、資格フィルタ、受講生名キーワード検索を提供する。
- **REQ-chat-053**: When `admin` が `GET /admin/chat-rooms` にアクセスした場合, the system shall 全 `ChatRoom` を一覧表示し、受講生名 / 担当コーチ名 / 資格名でフィルタ可能とする。
- **REQ-chat-054**: When 担当コーチが未割当の `Enrollment` を一覧表示する場合, the system shall ルーム行を「コーチ未割当」バッジで表示し、行クリックを disabled とする。

### 機能要件 — 認可・スコープ制御

- **REQ-chat-060**: The system shall `ChatRoomPolicy::view($user, $room)` を以下で判定する: `admin` は常に true / `coach` / `student` は `ChatMember::where('chat_room_id', $room->id)->where('user_id', $user->id)->whereNull('deleted_at')->exists()`。
- **REQ-chat-061**: The system shall `ChatRoomPolicy::sendMessage($user, $room)` を以下で判定する: `admin` は false / `coach` / `student` は `view` と同条件かつ `$room->enrollment->certification->coaches->count() > 0`(担当コーチ存在)。
- **REQ-chat-063**: When 受講生またはコーチが `User.status != UserStatus::InProgress` の場合, then the system shall `EnsureActiveLearning` Middleware で 403 を返す(`graduated` ユーザーは chat 利用不可)。

### 機能要件 — 通知連動

- **REQ-chat-070**: When 受講生がメッセージを送信した場合, the system shall [[notification]] の `NotifyChatMessageReceivedAction($message)` を `DB::afterCommit()` で呼び、**担当コーチ全員** へ Database + Mail channel で通知発火する。
- **REQ-chat-071**: When コーチがメッセージを送信した場合, the system shall [[notification]] の `NotifyChatMessageReceivedAction($message)` を呼び、**受講生本人** へ Database + Mail channel で通知発火する。他のコーチへの通知は **Database のみ**。
- **REQ-chat-072**: The system shall `NotifyChatMessageReceivedAction` 内で送信者 role + ChatMember 全員を解決し、Pusher Broadcasting と DB 通知 / Mail 通知の発火を一元管理する。

### 非機能要件

- **NFR-chat-001**: The system shall メッセージ送信 / ChatMember 作成 / Pusher ブロードキャスト dispatch / 通知 dispatch を同一 `DB::transaction()` 内で実行し、いずれかの失敗で全体ロールバックする(Pusher 配信は `DB::afterCommit()` で行う)。
- **NFR-chat-002**: The system shall 一覧 / 詳細クエリで Eager Loading(`with(['enrollment.certification', 'enrollment.user', 'members.user', 'latestMessage'])`)を使用し N+1 を回避する。
- **NFR-chat-003**: The system shall `chat_rooms.last_message_at` を denormalize で保持し、`chat_messages` 作成時に Model `booted` フックで UPDATE する。
- **NFR-chat-004**: The system shall `(enrollment_id)` UNIQUE / `(chat_room_id, created_at)` 複合 INDEX / `(chat_member.user_id, last_read_at)` 複合 INDEX / `(chat_room_id, user_id)` UNIQUE INDEX を備える。
- **NFR-chat-006**: The system shall ドメイン例外を `app/Exceptions/Chat/` 配下に配置する(`CertificationCoachNotAssignedForChatException` のみ)。
- **NFR-chat-007**: The system shall Blade を共通コンポーネント API のみで構成する。
- **NFR-chat-008**: The system shall アクセシビリティ要件を満たし、メッセージ送信フォームに `aria-label`、未読バッジに `aria-label="未読 N 件"`、リアルタイム追加メッセージは `aria-live="polite"` で読み上げ可能とする。
- **NFR-chat-009**: The system shall Pusher channel の購読権限を `routes/channels.php` で厳格に判定し、認可されない User は subscribe できない。

## スコープ外

- **ChatRoom ステータス機能(未対応 / 対応中 / 解決済)** — v3 撤回
- **添付ファイル(画像 / PDF 等のメッセージ添付)** — **E-2 撤回**(短期相談用途、画像共有は [[qa-board]] / 教材内画像 / 面談メモへ誘導、ファイル共有は LMS 外サービスへ)
- **メッセージ全文検索** — 短期相談用途、長期参照は [[qa-board]] / 面談メモへ
- **ピン留め / リアクション / ハイライト** — スコープ外
- **メッセージ編集 / 削除** — 学習相談の改竄防止 + admin 監査の信頼性
- **複数受講生グループチャット** — 1 Enrollment = 1 受講生 + N コーチ固定
- **コーチ → 受講生の chat 開始** — 初回送信は受講生のみ
- **既読マーク表示**(相手側の last_read_at を公開する仕様) — 未読バッジは自分側のみ集計
- **音声 / ビデオ通話** — [[mentoring]] と同様、スコープ外

## 関連 Feature

- **依存先**:
  - [[auth]] — `User` モデル / ロール / `auth` ガード / `EnsureActiveLearning` Middleware
  - [[enrollment]] — `Enrollment.user_id` / `Enrollment.certification_id` の参照
  - [[certification-management]] — `Certification.coaches` リレーション(`certification_coach_assignments` 経由)
- **依存元**:
  - [[notification]] — `NotifyChatMessageReceivedAction` の被呼び出し元
  - [[dashboard]] — coach Dashboard の「未読 chat 件数」表示
