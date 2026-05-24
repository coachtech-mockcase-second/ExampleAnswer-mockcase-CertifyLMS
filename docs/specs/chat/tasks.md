# chat タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-chat-NNN` / `NFR-chat-NNN` を参照。
> 本 Feature は [[auth]] / [[user-management]] / [[enrollment]] / [[certification-management]] / [[notification]] / Wave 0b 共通基盤の **完了後** に実装する。
> **v3 改修 + E-2 添付削除 + E-3 ChatRoom eager 生成**: 1 Enrollment = グループルーム + N ChatMember、`ChatRoom.status` 削除、**添付ファイル完全撤回**(テーブル / Model / Storage / signed URL 一切持たない)、**`ChatRoom + ChatMember` を `Enrollment` 作成と同一トランザクションで eager 生成**(`StoreFirstMessageAction` ラッパー / `chat.storeFirstMessage` ルート / `StoreFirstMessageRequest` / `sendMessageForEnrollment` Policy / union 引数分岐をすべて撤回)、Pusher Broadcasting、`EnsureActiveLearning` Middleware 連動。

## Step 1: Migration

- [x] migration: `create_chat_rooms_table`(ULID 主キー、`enrollment_id` UNIQUE 外部キー `restrictOnDelete`、`last_message_at` nullable datetime、`last_message_at` 単体 INDEX)(REQ-chat-001)
  - **`status` カラムは持たない**(v3 撤回)
- [x] migration: `create_chat_members_table`(ULID 主キー、`chat_room_id` 外部キー `cascadeOnDelete`、`user_id` 外部キー `restrictOnDelete`、`last_read_at` nullable datetime、`joined_at` datetime NOT NULL、`(chat_room_id, user_id)` UNIQUE、`(user_id, last_read_at)` 複合 INDEX)(REQ-chat-002)
- [x] migration: `create_chat_messages_table`(ULID 主キー、`chat_room_id` 外部キー `cascadeOnDelete`、`sender_user_id` 外部キー `restrictOnDelete`、`body` text、`(chat_room_id, created_at)` 複合 INDEX、`sender_user_id` 単体 INDEX)(REQ-chat-010)

### 明示的に持たない migration(E-2 撤回)

- **`create_chat_attachments_table`** — 添付ファイル機能完全撤回

## Step 2: Model / Factory / Seeder

- [x] Model: `App\Models\ChatRoom`(`HasUlids` + `HasFactory`、`fillable` / `casts: last_message_at => datetime`、リレーション `enrollment` / `messages` / `members` / `latestMessage`、スコープ `scopeForUser(User)` / `scopeOrderByLastMessage`)
- [x] Model: `App\Models\ChatMember`(`HasUlids` + `HasFactory`、`fillable` / `casts: last_read_at => datetime, joined_at => datetime`、リレーション `chatRoom` / `user`、スコープ `scopeForRoom` / `scopeForUser` / `scopeUnread`)
- [x] Model: `App\Models\ChatMessage`(`HasUlids` + `HasFactory`、リレーション `chatRoom` / `sender`、`booted()::created` フックで `chat_rooms.last_message_at` UPDATE、**`hasMany(ChatAttachment)` 削除**(E-2))
- [x] Factory: `ChatRoomFactory`(`coachUnassigned()` state)、`ChatMemberFactory`(`asStudent()` / `asCoach()` / `unread()` / `read()` state)、`ChatMessageFactory`(`fromStudent()` / `fromCoach()` state)
- [x] Seeder: `ChatSeeder`(各 Enrollment ごとに ChatRoom + 全 ChatMember + サンプルメッセージ)

### 明示的に持たない Model / Factory(E-2 撤回)

- **`App\Models\ChatAttachment`** — 添付機能撤回
- **`ChatAttachmentFactory`** — 同上

## Step 3: Service

- [x] `App\Services\ChatMemberSyncService::syncForRoom(ChatRoom)`(REQ-chat-003, REQ-chat-005、受講登録時の eager 生成 / 担当コーチ変更時の差分追加に共用、受講生 + 担当コーチ集合を `ChatMember` に upsert)
- [x] `App\Services\ChatMemberSyncService::syncForCertification(Certification)`(REQ-chat-005、該当資格の全 ChatRoom に対し `syncForRoom` を反復)
- [x] `App\Services\ChatUnreadCountService::messageCountInRoom(ChatRoom, User): int`(REQ-chat-030)
- [x] `App\Services\ChatUnreadCountService::messageCountsByRoomForUser(iterable<ChatRoom>, User): array<string, int>`(REQ-chat-050.5、rooms-pane 用 N+1 回避バルク集計)
- [x] `App\Services\ChatUnreadCountService::roomCountForUser(User): int`(REQ-chat-031, NFR-chat-002)

> **E-3**: `ChatMemberSyncService::syncForRoom` の **呼出元は本 Feature ではなく [[enrollment]] の `Enrollment\StoreAction` + 本 Feature の `SyncChatMembersOnCoachAssignmentChanged` Listener** の 2 箇所のみ。chat 側 Action からは呼ばない(`StoreMessageAction` は ChatRoom 確定で受け取り、メンバー整合は行わない)。

## Step 4: Policy

- [x] `App\Policies\ChatRoomPolicy::viewAny(User): bool`(REQ-chat-060)
- [x] `App\Policies\ChatRoomPolicy::view(User, ChatRoom): bool`(admin true / coach・student は `ChatMember::exists()`)(REQ-chat-060)
- [x] `App\Policies\ChatRoomPolicy::sendMessage(User, ChatRoom): bool`(admin false / view 条件 + `certification.coaches.isNotEmpty()`)(REQ-chat-061)
- [x] `AuthServiceProvider::$policies` に登録

### 明示的に持たない Policy(E-2 / E-3 撤回)

- **`App\Policies\ChatAttachmentPolicy`** — 添付機能撤回(E-2)
- **`App\Policies\ChatRoomPolicy::sendMessageForEnrollment`** — ChatRoom eager 生成で `Enrollment` ベース認可不要(E-3、REQ-chat-062)

## Step 5: Event / Listener / Broadcasting 認可

- [x] `App\Events\ChatMessageSent`(`ShouldBroadcast` 実装、`PrivateChannel("chat-room.{id}")`、`broadcastAs(): 'ChatMessageSent'`、**`broadcastWith()` で `{ id, chat_room_id, body, sender_user_id, sender_name, sender_role, created_at }` 返却**(E-2 で `attachments` フィールド削除))(REQ-chat-040, REQ-chat-041)
- [x] `App\Listeners\SyncChatMembersOnCoachAssignmentChanged`(`ShouldQueue`、`database` queue、`CertificationCoachAttached` / `CertificationCoachDetached` 購読)(REQ-chat-005)
- [x] `EventServiceProvider::$listen` で Listener 登録
- [x] `routes/channels.php` に `Broadcast::channel('chat-room.{chatRoomId}', fn (User $user, $chatRoomId) => ChatMember::where(...)->exists())` 追加(REQ-chat-042, NFR-chat-009)
- [x] `.env.example` に `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_ID` / `PUSHER_APP_CLUSTER` / `VITE_PUSHER_APP_KEY` / `VITE_PUSHER_APP_CLUSTER` 記載(REQ-chat-044)

## Step 6: HTTP 層

- [x] `App\Http\Controllers\ChatRoomController` スケルトン(`index` / `indexAsCoach` / `show` / `storeMessage`、各 method = 同名 Action `__invoke`)
- [x] `App\Http\Controllers\ChatRoomModerationController`(`index` / `show`、既存 `EnrollmentManagementController` と同じくフラット namespace、`backend-http.md`「ロール別 namespace 禁止」準拠)
- [x] **`App\Http\Controllers\ChatAttachmentController` は作成しない**(E-2 撤回)
- [x] **`ChatRoomController::storeFirstMessage` method 作成しない**(E-3 撤回、送信はすべて `storeMessage` 経由)
- [x] `App\Http\Requests\Chat\IndexRequest`(`page` rule、authorize: `student` or `coach`)
- [x] `App\Http\Requests\Chat\IndexAsCoachRequest`(`filter` / `certification_id` / `keyword` / `page`、authorize: coach のみ)
- [x] **`App\Http\Requests\Chat\StoreMessageRequest`(E-2 簡素化 + E-3 で唯一の送信用 Request)** — `body: required string max:2000` のみ、**`attachments` rules 削除**、authorize: `Policy::sendMessage` 委譲
- [x] **`App\Http\Requests\Chat\StoreFirstMessageRequest` は作成しない**(E-3 撤回)
- [x] `App\Http\Requests\Chat\Moderation\IndexRequest`(`ChatRoomModerationController` namespace に揃える)
- [x] `routes/web.php`:
  - `chat.index` / `coach.chat.index`(`role:student,coach` + `EnsureActiveLearning`)
  - `chat.show` / `chat.storeMessage`(`EnsureActiveLearning`)
  - **`chat-attachments.download` ルート追加しない**(E-2 撤回)
  - **`chat.storeFirstMessage` ルート追加しない**(E-3 撤回、ChatRoom eager 生成で初回送信専用 endpoint 不要)
  - `admin.chat-rooms.index` / `admin.chat-rooms.show`(`role:admin`)

## Step 7: Action / Exception

- [x] `App\UseCases\Chat\IndexAction`(`whereHas('members')` + 未読件数 attach + `coach_unassigned` フラグ attach + Eager Loading + paginate)
- [x] `App\UseCases\Chat\IndexAsCoachAction`(`filter=unread` デフォルト + 絞り込み)
- [x] `App\UseCases\Chat\ShowAction`(eager load + **viewer 自身の `ChatMember.last_read_at` のみ UPDATE**(個人別既読、admin 除外))(REQ-chat-012, REQ-chat-032)
- [x] **`App\UseCases\Chat\StoreMessageAction`(E-2 簡素化 + E-3 でシグネチャ統一)** — `__invoke(User $sender, ChatRoom $room, array $validated): ChatMessage`、`ChatMessage` INSERT / 送信者の `ChatMember.last_read_at` UPDATE / `DB::afterCommit()` で Broadcast + 通知 dispatch、**`ChatRoom|Enrollment` union 引数 / `resolveOrCreateRoom` / `firstOrCreate` / `lockForUpdate` / `ChatMemberSyncService` 呼出 / 添付保存ロジックすべて持たない**(担当コーチ未割当検査は Controller 上で `Policy::sendMessage` が担い、ChatRoom + ChatMember 生成は [[enrollment]] `StoreAction` が担う)
- [x] **`App\UseCases\Chat\StoreFirstMessageAction` 作成しない**(E-3 撤回、ChatRoom eager 生成でラッパー不要)
- [x] `App\UseCases\Chat\Moderation\IndexAction` / `ShowAction`(`ChatRoomModerationController` namespace に揃える)
- [x] **`App\UseCases\ChatAttachment\DownloadAction` 作成しない**(E-2 撤回)
- [x] `App\Exceptions\Chat\CertificationCoachNotAssignedForChatException`(HTTP 422)(REQ-chat-004、Controller で `Policy::sendMessage` が false を返した時に担当コーチ 0 件のケースのみこの例外を throw、それ以外は `AuthorizationException` 403)
- [x] `App\Notifications\Chat\ChatMessageReceivedNotification`(database + mail 配信、role 別チャネル切替)+ `App\UseCases\Notification\NotifyChatMessageReceivedAction`(ラッパー Action) + `notifications` テーブル migration([[notification]] Feature 未着手のため chat 側で先行追加、REQ-chat-070〜072)
- [x] `App\UseCases\Enrollment\StoreAction` を **同一トランザクションで `ChatRoom` + `ChatMember` を eager 生成** するよう更新(REQ-chat-003、E-3)

### 明示的に持たない Exception(E-2 撤回)

- 添付関連例外すべて

## Step 8: Blade ビュー

- [x] `resources/views/chat/index.blade.php`(受講生用一覧 + `coach_unassigned` バッジ)
- [x] `resources/views/chat/coach-index.blade.php`(コーチ用一覧 + tabs + フィルタ + paginator)
- [x] `resources/views/chat/show.blade.php`(詳細、メンバー一覧 + メッセージ一覧 + 送信フォーム、`aria-live="polite"` リスト、`@vite('resources/js/chat/realtime.js')`)
- [x] `resources/views/chat/_partials/message-item.blade.php`(自分 / 相手 / sender_role 表示)
- [x] `resources/views/chat/_partials/message-template.blade.php`(JS 用 `<template id="chat-message-template">`)
- [x] **`resources/views/chat/_partials/message-form.blade.php`(E-2 簡素化)** — `<x-form.textarea>` + 送信ボタンのみ、**`<x-form.file>` 完全削除**
- [x] `resources/views/chat/_partials/member-list.blade.php`(受講生 + 担当コーチ全員)
- [x] `resources/views/chat/_partials/empty-message.blade.php`(空状態)
- [x] `resources/views/admin/chat-rooms/index.blade.php`(全件 + 検索)
- [x] `resources/views/admin/chat-rooms/show.blade.php`(監査モード)
- [x] `App\View\Composers\SidebarBadgeComposer` の `unattendedChat` キーを `ChatUnreadCountService::roomCountForUser` で埋める

### 明示的に持たない Blade(E-2 撤回)

- **`chat/_partials/attachment-list.blade.php`** — 添付一覧表示なし

## Step 9: JS リアルタイム

- [x] **`resources/js/chat/realtime.js`(E-2 簡素化)** — Echo + Pusher 初期化、`.listen('.ChatMessageSent', cb)` で `<template>` 複製してメッセージ list に append、**`attachments` 描画ループ完全削除**
- [x] `resources/js/bootstrap.js` で Echo の global 初期化準備
- [x] `vite.config.js` の input に `resources/js/chat/realtime.js` 追加

## Step 10: テスト

### Feature(HTTP)

- [x] `tests/Feature/Http/Chat/IndexTest.php`(受講登録直後の受講生が `/chat-rooms` で eager 生成された ChatRoom を見られることを確認、コーチ「未読あり」フィルタ含む)
- [x] (`IndexAsCoach` 検証は `IndexTest::test_coach_index_filters_unread_by_default` に統合)
- [x] `tests/Feature/Http/Chat/ShowTest.php`(**viewer 自身の `last_read_at` のみ更新**、他 ChatMember は変化なし)
- [x] **`tests/Feature/Http/Chat/StoreMessageTest.php`(E-2 で添付テスト削除 + E-3 で送信テスト集約)** — body 必須 / body 超過 / 非 ChatMember 送信 403 / 担当コーチ 0 件 422 (`postJson`) + HTML POST は flash error redirect (`Handler.php` の `REDIRECT_BACK_STATUSES` 規約) / Broadcast 発火 / 通知 INSERT / 編集・削除エンドポイント不在で `PUT/DELETE /chat-rooms/{room}/messages/{message}` が 404 (REQ-chat-014)
- [x] `tests/Feature/Http/Chat/CoachAssignmentChangeTest.php`(ChatMember 同期、コーチ追加で全該当 ChatRoom に ChatMember INSERT)
- [x] `tests/Feature/Http/Chat/EnsureActiveLearningTest.php`(graduated 403)
- [x] `tests/Feature/Http/Chat\Moderation/IndexTest.php` / `ShowTest.php`

### 明示的に持たないテスト(E-2 / E-3 撤回)

- **`tests/Feature/Http/ChatAttachment/DownloadTest.php`** — 添付 DL 機能なし(E-2)
- **`tests/Feature/Http/Chat/StoreFirstMessageTest.php`** — ChatRoom eager 生成のため初回送信専用 endpoint なし(E-3、対応テストは [[enrollment]] 側 `StoreActionTest` に統合)
- **`attachments` silently drop テスト** — 提供 PJ に送信フォーム / JS 経路がないため後方互換性の根拠が薄い(E-3)

### Feature(Broadcasting)

- [x] (`ChatMessageSent` Event payload 検証は `tests/Unit/Events/ChatMessageSentEventTest.php` に集約、`Event::fake` 発火検証は `StoreMessageTest::test_student_can_send_message...` に統合)
- [x] `tests/Feature/Broadcasting/ChannelAuthorizationTest.php`(`routes/channels.php` callback)

### Feature(Action)

- [x] **`tests/Feature/UseCases/Chat/StoreMessageActionTest.php`(E-2 簡素化 + E-3 でシグネチャ簡素化)** — ChatMessage INSERT / 送信者の `last_read_at` UPDATE / Broadcast + 通知 dispatch / **`__invoke(User, ChatRoom, array)` シグネチャを ReflectionMethod で assert**(E-3 撤回確認、union 引数や `firstOrCreate`/`lockForUpdate` ロジック非存在の根拠)
- [x] (`ShowAction` の viewer 別 `last_read_at` UPDATE / admin non-update 検証は `tests/Feature/Http/Chat/ShowTest.php` に統合)

### Unit(Service / Policy / Event)

- [x] `tests/Unit/Services/ChatMemberSyncServiceTest.php`(enrollment 経由の eager 生成と Listener 経由の差分追加の両ケースを網羅)
- [x] `tests/Unit/Services/ChatUnreadCountServiceTest.php`
- [x] `tests/Unit/Policies/ChatRoomPolicyTest.php`(view / sendMessage の真偽値網羅 + **`sendMessageForEnrollment` メソッドが存在しないことを `method_exists` で assert**(REQ-chat-062、E-3))
- [x] `tests/Unit/Events/ChatMessageSentEventTest.php`(`broadcastWith` で **`attachments` フィールド含まれない**(E-2))

### 明示的に持たないテスト(E-2 撤回)

- **`tests/Unit/Policies/ChatAttachmentPolicyTest.php`**

### Listener テスト

- [x] (`SyncChatMembersOnCoachAssignmentChanged` Listener の検証は `tests/Feature/Http/Chat/CoachAssignmentChangeTest.php` に集約)

## Step 11: 動作確認 & 整形

- [x] `sail artisan test --filter=Chat|Chat\Moderation|ChatRoomPolicy|ChatMessageSent|ChatMemberSync|ChatUnreadCount|ChannelAuthorization` 通過(34 tests / 82 assertions)
- [x] `sail artisan test`(全 755 tests / 1592 assertions、既存破壊なし)
- [x] `sail bin pint --dirty` 整形(passed)
- [x] `sail artisan migrate:fresh --seed` で `ChatSeeder` 投入確認(10 rooms / 22 members / 5 messages)
- [ ] ブラウザ動作確認（基本動線）:
  - [ ] 受講登録直後の受講生で `/chat-rooms` を開き、**eager 生成された ChatRoom が一覧に表示される**(E-3 確認、メッセージ 0 件でも表示)
  - [ ] 担当コーチ未割当の Enrollment で「コーチ未割当」バッジ + メッセージ送信ボタン disabled(ルーム自体は表示される、E-3)
  - [ ] 受講生でメッセージ送信 → 既存 `ChatRoom` に `ChatMessage` INSERT、コーチ側で未読バッジ点灯
  - [ ] コーチで `/coach/chat-rooms` を開き「未読あり」フィルタデフォルト
  - [ ] コーチ A が返信 → コーチ A の `last_read_at` のみ更新、**コーチ B の `last_read_at` は変化なし**(個人別既読、E-2 仕様確認)
  - [ ] 受講生のメッセージ送信フォームに **添付フィールドが表示されない**(E-2 確認)
  - [ ] admin で `/admin/chat-rooms` を開き全件監査閲覧
  - [ ] `graduated` ユーザーで `/chat-rooms` → 403
  - [ ] `POST /enrollments/{enrollment}/chat/messages` URL 直叩き → 404(E-3 撤回確認、ルート定義なし)
- [ ] ブラウザ動作確認（Pusher リアルタイム）:
  - [ ] Tab A(受講生)で `/chat-rooms/{id}` を開き、Tab B(コーチ)で同じルームを開く
  - [ ] Tab A で送信 → Tab B のメッセージリストにリアルタイム追加(リロード不要)
  - [ ] 非 ChatMember で同 channel subscribe 試行 → 403
- [ ] 通知連動の動作確認:
  - [ ] 受講生送信 → 担当コーチ全員に Database + Mail 通知発火
  - [ ] コーチ送信 → 受講生に Database + Mail、他コーチに Database のみ
- [ ] **E-2 撤回確認**:
  - [ ] `/chat-attachments/...` URL 直叩き → 404(ルート定義なし)
- [ ] **E-3 撤回確認**:
  - [ ] `POST /enrollments/{enrollment}/chat/messages` URL 直叩き → 404
  - [ ] 受講登録 (`POST /enrollments`) 直後に `chat_rooms` テーブルに enrollment_id 一致行が存在し、`chat_members` に受講生 + 担当コーチ集合が登録されている
  - [ ] 受講登録時に担当コーチ 0 件の場合は `chat_members` に受講生のみ登録、後で `certification_coach_assignments` を追加すると Listener が ChatMember を差分追加する
