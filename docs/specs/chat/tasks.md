# chat タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-chat-NNN` / `NFR-chat-NNN` を参照。
> 本 Feature は [[auth]] / [[user-management]] / [[enrollment]] / [[certification-management]] / [[notification]] / Wave 0b 共通基盤の **完了後** に実装する。
> **v3 改修 + E-2 添付削除**: 1 Enrollment = グループルーム + N ChatMember、`ChatRoom.status` 削除、**添付ファイル完全撤回**(テーブル / Model / Storage / signed URL 一切持たない)、Pusher Broadcasting、`EnsureActiveLearning` Middleware 連動。

## Step 1: Migration

- [ ] migration: `create_chat_rooms_table`(ULID 主キー、`enrollment_id` UNIQUE 外部キー `restrictOnDelete`、`last_message_at` nullable datetime、`last_message_at` 単体 INDEX、`SoftDeletes`)(REQ-chat-001)
  - **`status` カラムは持たない**(v3 撤回)
- [ ] migration: `create_chat_members_table`(ULID 主キー、`chat_room_id` 外部キー `cascadeOnDelete`、`user_id` 外部キー `restrictOnDelete`、`last_read_at` nullable datetime、`joined_at` datetime NOT NULL、`(chat_room_id, user_id)` UNIQUE、`(user_id, last_read_at)` 複合 INDEX、`SoftDeletes`)(REQ-chat-002)
- [ ] migration: `create_chat_messages_table`(ULID 主キー、`chat_room_id` 外部キー `cascadeOnDelete`、`sender_user_id` 外部キー `restrictOnDelete`、`body` text、`(chat_room_id, created_at)` 複合 INDEX、`sender_user_id` 単体 INDEX、`SoftDeletes`)(REQ-chat-010)

### 明示的に持たない migration(E-2 撤回)

- **`create_chat_attachments_table`** — 添付ファイル機能完全撤回

## Step 2: Model / Factory / Seeder

- [ ] Model: `App\Models\ChatRoom`(`HasUlids` + `HasFactory` + `SoftDeletes`、`fillable` / `casts: last_message_at => datetime`、リレーション `enrollment` / `messages` / `members` / `latestMessage`、スコープ `scopeForUser(User)` / `scopeOrderByLastMessage`)
- [ ] Model: `App\Models\ChatMember`(`HasUlids` + `HasFactory` + `SoftDeletes`、`fillable` / `casts: last_read_at => datetime, joined_at => datetime`、リレーション `chatRoom` / `user`、スコープ `scopeForRoom` / `scopeForUser` / `scopeUnread`)
- [ ] Model: `App\Models\ChatMessage`(`HasUlids` + `HasFactory` + `SoftDeletes`、リレーション `chatRoom` / `sender`、`booted()::created` フックで `chat_rooms.last_message_at` UPDATE、**`hasMany(ChatAttachment)` 削除**(E-2))
- [ ] Factory: `ChatRoomFactory`(`coachUnassigned()` state)、`ChatMemberFactory`(`asStudent()` / `asCoach()` / `unread()` / `read()` state)、`ChatMessageFactory`(`fromStudent()` / `fromCoach()` state)
- [ ] Seeder: `ChatSeeder`(各 Enrollment ごとに ChatRoom + 全 ChatMember + サンプルメッセージ)

### 明示的に持たない Model / Factory(E-2 撤回)

- **`App\Models\ChatAttachment`** — 添付機能撤回
- **`ChatAttachmentFactory`** — 同上

## Step 3: Service

- [ ] `App\Services\ChatMemberSyncService::syncForRoom(ChatRoom)`(REQ-chat-003, REQ-chat-005)
- [ ] `App\Services\ChatMemberSyncService::syncForCertification(Certification)`(REQ-chat-005)
- [ ] `App\Services\ChatUnreadCountService::messageCountInRoom(ChatRoom, User): int`(REQ-chat-030)
- [ ] `App\Services\ChatUnreadCountService::roomCountForUser(User): int`(REQ-chat-031, NFR-chat-002)

## Step 4: Policy

- [ ] `App\Policies\ChatRoomPolicy::viewAny(User): bool`(REQ-chat-060)
- [ ] `App\Policies\ChatRoomPolicy::view(User, ChatRoom): bool`(admin true / coach・student は `ChatMember::exists()`)(REQ-chat-060)
- [ ] `App\Policies\ChatRoomPolicy::sendMessage(User, ChatRoom): bool`(admin false / view 条件 + `certification.coaches.isNotEmpty()`)(REQ-chat-061)
- [ ] `App\Policies\ChatRoomPolicy::sendMessageForEnrollment(User, Enrollment): bool`(REQ-chat-061)
- [ ] `AuthServiceProvider::$policies` に登録

### 明示的に持たない Policy(E-2 撤回)

- **`App\Policies\ChatAttachmentPolicy`** — 添付機能撤回

## Step 5: Event / Listener / Broadcasting 認可

- [ ] `App\Events\ChatMessageSent`(`ShouldBroadcast` 実装、`PrivateChannel("chat-room.{id}")`、`broadcastAs(): 'ChatMessageSent'`、**`broadcastWith()` で `{ id, chat_room_id, body, sender_user_id, sender_name, sender_role, created_at }` 返却**(E-2 で `attachments` フィールド削除))(REQ-chat-040, REQ-chat-041)
- [ ] `App\Listeners\SyncChatMembersOnCoachAssignmentChanged`(`ShouldQueue`、`database` queue、`CertificationCoachAttached` / `CertificationCoachDetached` 購読)(REQ-chat-005)
- [ ] `EventServiceProvider::$listen` で Listener 登録
- [ ] `routes/channels.php` に `Broadcast::channel('chat-room.{chatRoomId}', fn (User $user, $chatRoomId) => ChatMember::where(...)->exists())` 追加(REQ-chat-042, NFR-chat-009)
- [ ] `.env.example` に `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_ID` / `PUSHER_APP_CLUSTER` / `VITE_PUSHER_APP_KEY` / `VITE_PUSHER_APP_CLUSTER` 記載(REQ-chat-044)

## Step 6: HTTP 層

- [ ] `App\Http\Controllers\ChatRoomController` スケルトン(`index` / `indexAsCoach` / `show` / `storeMessage` / `storeFirstMessage`、各 method = 同名 Action `__invoke`)
- [ ] `App\Http\Controllers\Admin\ChatRoomController`(`index` / `show`)
- [ ] **`App\Http\Controllers\ChatAttachmentController` は作成しない**(E-2 撤回)
- [ ] `App\Http\Requests\Chat\IndexRequest`(`page` rule、authorize: `student` or `coach`)
- [ ] `App\Http\Requests\Chat\IndexAsCoachRequest`(`filter` / `certification_id` / `keyword` / `page`、authorize: coach のみ)
- [ ] **`App\Http\Requests\Chat\StoreMessageRequest`(E-2 簡素化)** — `body: required string max:2000` のみ、**`attachments` rules 削除**、authorize: `Policy::sendMessage` 委譲
- [ ] **`App\Http\Requests\Chat\StoreFirstMessageRequest`** — 同 rules
- [ ] `App\Http\Requests\Admin\Chat\IndexRequest`
- [ ] `routes/web.php`:
  - `chat.index` / `coach.chat.index`(`role:student,coach` + `EnsureActiveLearning`)
  - `chat.show` / `chat.storeMessage` / `chat.storeFirstMessage`(`EnsureActiveLearning`)
  - **`chat-attachments.download` ルート追加しない**(E-2 撤回)
  - `admin.chat-rooms.index` / `admin.chat-rooms.show`(`role:admin`)

## Step 7: Action / Exception

- [ ] `App\UseCases\Chat\IndexAction`(`whereHas('members')` + 未読件数 attach + `coach_unassigned` フラグ attach + Eager Loading + paginate)
- [ ] `App\UseCases\Chat\IndexAsCoachAction`(`filter=unread` デフォルト + 絞り込み)
- [ ] `App\UseCases\Chat\ShowAction`(eager load + **viewer 自身の `ChatMember.last_read_at` のみ UPDATE**(個人別既読、admin 除外))(REQ-chat-012, REQ-chat-032)
- [ ] **`App\UseCases\Chat\StoreMessageAction`(E-2 簡素化)** — sender 分岐 / 担当コーチ未割当検査 / `firstOrCreate` + `ChatMemberSyncService::syncForRoom` / `lockForUpdate` / ChatMember 存在検証 / `ChatMessage` INSERT / 送信者の `last_read_at` UPDATE / `DB::afterCommit()` で Broadcast + 通知 dispatch、**添付保存ロジック完全削除**
- [ ] **`App\UseCases\Chat\StoreFirstMessageAction`** — `ChatRoomController::storeFirstMessage` 対応ラッパー、`__invoke(User $sender, Enrollment $enrollment, array $validated): ChatMessage`、内部で `StoreMessageAction` を呼ぶだけの薄いラッパー(`.claude/rules/backend-usecases.md`「Controller method 名 = Action クラス名」規約準拠)
- [ ] `App\UseCases\Admin\Chat\IndexAction` / `ShowAction`
- [ ] **`App\UseCases\ChatAttachment\DownloadAction` 作成しない**(E-2 撤回)
- [ ] `App\Exceptions\Chat\CertificationCoachNotAssignedForChatException`(HTTP 422)(REQ-chat-004)

### 明示的に持たない Exception(E-2 撤回)

- 添付関連例外すべて

## Step 8: Blade ビュー

- [ ] `resources/views/chat/index.blade.php`(受講生用一覧 + `coach_unassigned` バッジ)
- [ ] `resources/views/chat/coach-index.blade.php`(コーチ用一覧 + tabs + フィルタ + paginator)
- [ ] `resources/views/chat/show.blade.php`(詳細、メンバー一覧 + メッセージ一覧 + 送信フォーム、`aria-live="polite"` リスト、`@vite('resources/js/chat/realtime.js')`)
- [ ] `resources/views/chat/_partials/message-item.blade.php`(自分 / 相手 / sender_role 表示)
- [ ] `resources/views/chat/_partials/message-template.blade.php`(JS 用 `<template id="chat-message-template">`)
- [ ] **`resources/views/chat/_partials/message-form.blade.php`(E-2 簡素化)** — `<x-form.textarea>` + 送信ボタンのみ、**`<x-form.file>` 完全削除**
- [ ] `resources/views/chat/_partials/member-list.blade.php`(受講生 + 担当コーチ全員)
- [ ] `resources/views/chat/_partials/empty-message.blade.php`(空状態)
- [ ] `resources/views/admin/chat-rooms/index.blade.php`(全件 + 検索)
- [ ] `resources/views/admin/chat-rooms/show.blade.php`(監査モード)
- [ ] `App\View\Composers\SidebarBadgeComposer` に `chat-rooms` キー追加

### 明示的に持たない Blade(E-2 撤回)

- **`chat/_partials/attachment-list.blade.php`** — 添付一覧表示なし

## Step 9: JS リアルタイム

- [ ] **`resources/js/chat/realtime.js`(E-2 簡素化)** — Echo + Pusher 初期化、`.listen('.ChatMessageSent', cb)` で `<template>` 複製してメッセージ list に append、**`attachments` 描画ループ完全削除**
- [ ] `resources/js/bootstrap.js` で Echo の global 初期化準備
- [ ] `vite.config.js` の input に `resources/js/chat/realtime.js` 追加

## Step 10: テスト

### Feature(HTTP)

- [ ] `tests/Feature/Http/Chat/IndexTest.php`
- [ ] `tests/Feature/Http/Chat/IndexAsCoachTest.php`
- [ ] `tests/Feature/Http/Chat/ShowTest.php`(**viewer 自身の `last_read_at` のみ更新**、他 ChatMember は変化なし)
- [ ] **`tests/Feature/Http/Chat/StoreFirstMessageTest.php`(E-2 で添付テスト削除)** — body 必須 / 担当コーチ 0 件 422 / ChatMember 集合 INSERT / Broadcast 発火 / 通知 INSERT
- [ ] **`tests/Feature/Http/Chat/StoreMessageTest.php`(E-2 で添付テスト削除)** — body 必須 / body 超過 422 / 非 ChatMember 送信 403 / **`attachments` フィールド送信時に silently drop**(後方互換性確認)
- [ ] `tests/Feature/Http/Chat/CoachAssignmentChangeTest.php`(ChatMember 同期)
- [ ] `tests/Feature/Http/Chat/EnsureActiveLearningTest.php`(graduated 403)
- [ ] `tests/Feature/Http/Admin/Chat/IndexTest.php` / `ShowTest.php`

### 明示的に持たないテスト(E-2 撤回)

- **`tests/Feature/Http/ChatAttachment/DownloadTest.php`** — 添付 DL 機能なし

### Feature(Broadcasting)

- [ ] `tests/Feature/Broadcasting/ChatMessageSentTest.php`(`Event::fake([ChatMessageSent::class])` で発火検証 / **payload に `attachments` フィールド含まれない**(E-2) / `PrivateChannel("chat-room.{id}")` チャネル名)
- [ ] `tests/Feature/Broadcasting/ChannelAuthorizationTest.php`(`routes/channels.php` callback)

### Feature(Action)

- [ ] **`tests/Feature/UseCases/Chat/StoreMessageActionTest.php`(E-2 簡素化)** — 担当コーチ未割当 / ChatRoom upsert / ChatMember 整合 / Broadcast + 通知 / トランザクションロールバック / **添付保存呼出なし**(E-2)
- [ ] `tests/Feature/UseCases/Chat/ShowActionTest.php`(viewer 別 `last_read_at` UPDATE / admin の non-update)

### Unit(Service / Policy / Event)

- [ ] `tests/Unit/Services/ChatMemberSyncServiceTest.php`
- [ ] `tests/Unit/Services/ChatUnreadCountServiceTest.php`
- [ ] `tests/Unit/Policies/ChatRoomPolicyTest.php`(view / sendMessage の真偽値網羅)
- [ ] `tests/Unit/Events/ChatMessageSentEventTest.php`(`broadcastWith` で **`attachments` フィールド含まれない**(E-2))

### 明示的に持たないテスト(E-2 撤回)

- **`tests/Unit/Policies/ChatAttachmentPolicyTest.php`**

### Listener テスト

- [ ] `tests/Feature/Listeners/SyncChatMembersOnCoachAssignmentChangedTest.php`

## Step 11: 動作確認 & 整形

- [ ] `sail artisan test --filter=Chat` 通過
- [ ] `sail bin pint --dirty` 整形
- [ ] `sail artisan migrate:fresh --seed` で `ChatSeeder` 投入確認
- [ ] ブラウザ動作確認（基本動線）:
  - [ ] 受講生で `/chat-rooms` を開きルーム一覧表示
  - [ ] 担当コーチ未割当の Enrollment で「コーチ未割当」バッジ + 開く disabled
  - [ ] 受講生で初回メッセージ送信 → `ChatRoom` + 全 `ChatMember` 生成、コーチ側で未読バッジ点灯
  - [ ] コーチで `/coach/chat-rooms` を開き「未読あり」フィルタデフォルト
  - [ ] コーチ A が返信 → コーチ A の `last_read_at` のみ更新、**コーチ B の `last_read_at` は変化なし**(個人別既読、E-2 仕様確認)
  - [ ] 受講生のメッセージ送信フォームに **添付フィールドが表示されない**(E-2 確認)
  - [ ] admin で `/admin/chat-rooms` を開き全件監査閲覧
  - [ ] `graduated` ユーザーで `/chat-rooms` → 403
- [ ] ブラウザ動作確認（Pusher リアルタイム）:
  - [ ] Tab A(受講生)で `/chat-rooms/{id}` を開き、Tab B(コーチ)で同じルームを開く
  - [ ] Tab A で送信 → Tab B のメッセージリストにリアルタイム追加(リロード不要)
  - [ ] 非 ChatMember で同 channel subscribe 試行 → 403
- [ ] 通知連動の動作確認:
  - [ ] 受講生送信 → 担当コーチ全員に Database + Mail 通知発火
  - [ ] コーチ送信 → 受講生に Database + Mail、他コーチに Database のみ
- [ ] **E-2 撤回確認**:
  - [ ] `/chat-attachments/...` URL 直叩き → 404(ルート定義なし)
  - [ ] 添付フィールド送信時 → silently drop で 200(`attachments` rule なし、後方互換性)
