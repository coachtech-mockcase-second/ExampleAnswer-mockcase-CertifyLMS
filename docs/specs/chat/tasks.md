# chat タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-chat-NNN` / `NFR-chat-NNN` を参照。
> 本 Feature は [[auth]] / [[user-management]] / [[enrollment]] / [[notification]] / Wave 0b 共通基盤の **完了後** に実装する。
> chat 画面は **Basic / Advance ともに非同期方式**（Phase 0 合意 B 案）。Pusher 連携は本 Feature には含めず、リアルタイム通知 push は [[notification]] Feature の Advance Broadcasting セクションが担う。

## Step 1: Migration & Model

- [ ] migration: `create_chat_rooms_table`（ULID 主キー、`enrollment_id` UNIQUE 外部キー、`status` enum、`last_message_at` / `student_last_read_at` / `coach_last_read_at` nullable datetime、`(status, last_message_at)` 複合 INDEX、`last_message_at` 単体 INDEX、`SoftDeletes` 必須）（REQ-chat-001, REQ-chat-004, REQ-chat-030, NFR-chat-004）
- [ ] migration: `create_chat_messages_table`（ULID 主キー、`chat_room_id` 外部キー cascadeOnDelete、`sender_user_id` 外部キー restrictOnDelete、`body` text、`(chat_room_id, created_at)` 複合 INDEX、`sender_user_id` 単体 INDEX、`SoftDeletes` 必須）（REQ-chat-010, REQ-chat-011, NFR-chat-004）
- [ ] migration: `create_chat_attachments_table`（ULID 主キー、`chat_message_id` 外部キー cascadeOnDelete、`original_filename` / `stored_path` / `mime_type` / `file_size_bytes`、`SoftDeletes` 必須）（REQ-chat-022, REQ-chat-026）
- [ ] Enum: `App\Enums\ChatRoomStatus`（`Unattended` / `InProgress` / `Resolved`、`label()` / `badgeVariant()` メソッド）（REQ-chat-004）
- [ ] Model: `App\Models\ChatRoom`（`HasUlids` + `HasFactory` + `SoftDeletes`、`fillable` / `casts` / リレーション `enrollment` / `messages` / `latestMessage`、スコープ `scopeForStudent` / `scopeForCoach` / `scopeWithStatus` / `scopeOrderByLastMessage`）（REQ-chat-001, REQ-chat-040, REQ-chat-041）
- [ ] Model: `App\Models\ChatMessage`（`HasUlids` + `HasFactory` + `SoftDeletes`、リレーション `chatRoom` / `sender` / `attachments`、`booted()` の `created` フックで `chat_rooms.last_message_at` UPDATE、スコープ `scopeForRoom` / `scopeAfter`）（REQ-chat-010, REQ-chat-012, NFR-chat-003）
- [ ] Model: `App\Models\ChatAttachment`（`HasUlids` + `HasFactory` + `SoftDeletes`、リレーション `message`）（REQ-chat-026）
- [ ] Factory: `ChatRoomFactory`（`unattended()` / `inProgress()` / `resolved()` / `coachUnassigned()` state）、`ChatMessageFactory`（`fromStudent()` / `fromCoach()` state）、`ChatAttachmentFactory`（`image()` / `pdf()` state）
- [ ] Seeder: `ChatSeeder`（受講生 × 担当コーチの各状態サンプルルーム + 添付サンプル、開発・テスト用）

## Step 2: Policy

- [ ] `App\Policies\ChatRoomPolicy`（`viewAny` / `view` / `sendMessage` / `resolve`、UserRole enum + `view` の委譲）（REQ-chat-050, REQ-chat-051, REQ-chat-052, REQ-chat-054）
- [ ] `App\Policies\ChatAttachmentPolicy`（`download` を `ChatRoomPolicy::view` 委譲）（REQ-chat-053）
- [ ] `AuthServiceProvider::$policies` に登録 or 自動検出確認

## Step 3: HTTP 層

- [ ] `App\Http\Controllers\ChatRoomController` スケルトン（`index` / `indexAsCoach` / `show` / `storeMessage` / `resolve`、各 method は同名 Action を `__invoke` 呼出する薄いラッパー）（REQ-chat-040, REQ-chat-041, REQ-chat-007, REQ-chat-008）
- [ ] `App\Http\Controllers\Admin\ChatRoomController` スケルトン（`index` / `show`、admin 監査用、Action は `App\UseCases\Admin\Chat\*`）（REQ-chat-043）
- [ ] `App\Http\Controllers\ChatAttachmentController`（`download` の 1 method のみ、Action は `App\UseCases\ChatAttachment\DownloadAction`）（REQ-chat-023, REQ-chat-026）
- [ ] `App\Http\Requests\Chat\IndexRequest`（受講生のみ authorize、`page` rule）（REQ-chat-040）
- [ ] `App\Http\Requests\Chat\IndexAsCoachRequest`（コーチのみ authorize、`status` / `certification_id` / `keyword` / `page` rule）（REQ-chat-041, REQ-chat-042）
- [ ] `App\Http\Requests\Chat\StoreMessageRequest`（`body: required string max:2000` / `attachments: nullable array max:3` / `attachments.*: file mimes:png,jpg,jpeg,webp,pdf max:5120`、authorize は `Policy::sendMessage` 委譲）（REQ-chat-010, REQ-chat-011, REQ-chat-020, REQ-chat-021）
- [ ] `App\Http\Requests\Admin\Chat\IndexRequest`（admin のみ authorize、`student_name` / `coach_name` / `certification_id` / `status` / `page` rule）（REQ-chat-043）
- [ ] `routes/web.php`: `chat.index` / `coach.chat.index` / `chat.show` / `chat.storeMessage` / `chat.storeFirstMessage` / `chat.resolve` / `chat-attachments.download`（`signed` middleware）/ `admin.chat-rooms.index` / `admin.chat-rooms.show` を登録（REQ-chat-024, REQ-chat-040, REQ-chat-041, REQ-chat-042, REQ-chat-043, REQ-chat-044）

### 明示的に実装しない（規約として記録）

- メッセージの編集 / 削除エンドポイント（Controller method / Action / FormRequest / Route / Blade 編集 UI のいずれも作成しない）（REQ-chat-014）
- コーチ → 受講生の chat 新規開始エンドポイント（コーチからの初回送信ルートは作らず、`resolved` ルームへの再送信のみで「再オープン」を扱う、`product.md` state diagram に従う）

## Step 4: Action / Service / Exception

- [ ] `App\Services\ChatRoomStateService::nextStateOnSend(ChatRoomStatus, UserRole): ChatRoomStatus`（純粋関数、`match` でロジック網羅）（REQ-chat-006, REQ-chat-009）
- [ ] `App\Services\ChatUnreadCountService::messageCountInRoom(ChatRoom, User): int`（`sender != user` + `created_at > last_read_at` の COUNT）（REQ-chat-034, REQ-chat-035）
- [ ] `App\Services\ChatUnreadCountService::roomCountForUser(User): int`（1 クエリで集計、N+1 回避）（REQ-chat-036, NFR-chat-002）
- [ ] `App\UseCases\Chat\IndexAction`（受講生用、未読件数 + `coach_unassigned` フラグを attach、Eager Loading）（REQ-chat-040, REQ-chat-044, REQ-chat-045）
- [ ] `App\UseCases\Chat\IndexAsCoachAction`（コーチ用、`status` / `certification_id` / `keyword` フィルタ）（REQ-chat-041, REQ-chat-042, REQ-chat-045）
- [ ] `App\UseCases\Chat\ShowAction`（メッセージ Eager Loading + role 別 `last_read_at` UPDATE、admin は UPDATE しない）（REQ-chat-012, REQ-chat-031, REQ-chat-032, REQ-chat-033, REQ-chat-045）
- [ ] `App\UseCases\Chat\StoreMessageAction`（sender 分岐 / コーチ未割当検査 / FOR UPDATE / 状態遷移 / メッセージ INSERT / 添付保存 / 通知発火、すべて `DB::transaction()`、通知データに `chat_room_id` / `chat_message_id` / `sender_user_id` / `sender_name` / `body_preview` を渡す）（REQ-chat-002, REQ-chat-003, REQ-chat-005, REQ-chat-006, REQ-chat-008, REQ-chat-009, REQ-chat-022, REQ-chat-070, REQ-chat-071, REQ-chat-072, REQ-chat-073, NFR-chat-001）
- [ ] `App\UseCases\Chat\ResolveAction`（既 `Resolved` ガード + `Resolved` へ UPDATE）（REQ-chat-007, REQ-chat-008）
- [ ] `App\UseCases\Admin\Chat\IndexAction`（admin 監査用、全 ChatRoom 検索）（REQ-chat-043, REQ-chat-045）
- [ ] `App\UseCases\Admin\Chat\ShowAction`（`last_read_at` UPDATE せず）（REQ-chat-033, REQ-chat-043, REQ-chat-045）
- [ ] `App\UseCases\ChatAttachment\DownloadAction`（`Storage::disk('private')->download(...)`）（REQ-chat-026）
- [ ] `App\Exceptions\Chat\EnrollmentCoachNotAssignedForChatException`（HTTP 422）（REQ-chat-002, NFR-chat-006）
- [ ] `App\Exceptions\Chat\ChatRoomNotFoundException`（HTTP 404、Route Model Binding の二次防御用）（NFR-chat-006）
- [ ] `App\Exceptions\Chat\ChatRoomAlreadyResolvedException`（HTTP 409）（REQ-chat-007, REQ-chat-008, NFR-chat-006）

## Step 5: Blade ビュー

- [ ] `resources/views/chat/index.blade.php`（受講生ルーム一覧 + コーチ未割当バッジ）（REQ-chat-040, REQ-chat-044, NFR-chat-007）
- [ ] `resources/views/chat/coach-index.blade.php`（コーチ用一覧 + `<x-tabs>` + フィルタフォーム + 一覧 + `<x-paginator>`）（REQ-chat-041, REQ-chat-042, NFR-chat-007）
- [ ] `resources/views/chat/show.blade.php`（詳細、メッセージ一覧 + 送信フォーム、`@can('sendMessage')` で admin に送信フォーム非表示）（REQ-chat-012, REQ-chat-013, REQ-chat-015, NFR-chat-007）
- [ ] `resources/views/chat/_partials/message-item.blade.php`（自分 / 相手で左右切替、`<x-avatar>` + body + 添付一覧）（REQ-chat-013, NFR-chat-007）
- [ ] `resources/views/chat/_partials/attachment-list.blade.php`（`URL::temporarySignedRoute('chat-attachments.download', now()->addMinutes(10), [...])` で `<x-link-button>` 描画）（REQ-chat-024, NFR-chat-005, NFR-chat-007）
- [ ] `resources/views/chat/_partials/message-form.blade.php`（`<x-form.textarea name="body" :maxlength="2000">` + `<x-form.file name="attachments[]" multiple accept="image/png,image/jpeg,image/webp,application/pdf">` + 送信ボタン、`@can('sendMessage', $room)` で囲む）（REQ-chat-011, REQ-chat-015, REQ-chat-020, REQ-chat-021, NFR-chat-008）
- [ ] `resources/views/chat/_partials/empty-message.blade.php`（`<x-empty-state>` で 0 件状態）（REQ-chat-046, NFR-chat-007）
- [ ] `resources/views/admin/chat-rooms/index.blade.php`（全件一覧 + 検索フォーム）（REQ-chat-043, NFR-chat-007）
- [ ] `resources/views/admin/chat-rooms/show.blade.php`（admin 用、`<x-alert type="info">監査モード</x-alert>` + 送信フォーム描画なし）（REQ-chat-015, REQ-chat-043, NFR-chat-007）
- [ ] `App\View\Composers\SidebarBadgeComposer` に `chat-rooms` キーを追加（`ChatUnreadCountService::roomCountForUser` を呼ぶ、Wave 0b 整備済 Composer を本 Feature で拡張）（REQ-chat-036）

## Step 6: テスト

### Feature テスト（HTTP 層）

- [ ] `tests/Feature/Http/Chat/IndexTest.php`(受講生の自分のルーム一覧表示 / コーチ未割当バッジ表示 / 他受講生のルーム不可視 / 未読件数 attach 検証 / `admin` / `coach` ロールでの 403 確認)
- [ ] `tests/Feature/Http/Chat/IndexAsCoachTest.php`(担当ルーム一覧 / status フィルタ / 資格フィルタ / キーワード検索 / 他コーチのルーム不可視)
- [ ] `tests/Feature/Http/Chat/ShowTest.php`(当事者の閲覧で `last_read_at` 更新 / admin 閲覧で UPDATE されない / 他人アクセスで 403 / メッセージ時系列描画 / 添付付きメッセージ表示)
- [ ] `tests/Feature/Http/Chat/StoreMessageTest.php`(受講生初回送信で ChatRoom + Message INSERT + status=unattended / コーチ返信で status=unattended→in_progress / 受講生再送で in_progress→unattended / コーチ未割当 422 / body 超過 422 / 添付 4 件以上 422 / 不正拡張子 422 / サイズ超過 422 / 添付保存パス検証 / 通知 INSERT 検証 / トランザクション内整合性)
- [ ] `tests/Feature/Http/Chat/ResolveTest.php`(コーチが in_progress を resolved 化 / 受講生が in_progress を resolved 化 / 受講生が unattended を resolved 化試行で 403 / admin が resolve 試行で 403 / 既 resolved で 409)
- [ ] `tests/Feature/Http/ChatAttachment/DownloadTest.php`(当事者 signed URL でダウンロード成功 / signed なし URL で 403 / 期限切れ URL で 403 / 他人の signed URL 模倣で 403 / admin で 200)（REQ-chat-023, REQ-chat-024, REQ-chat-025, REQ-chat-053, NFR-chat-005）
- [ ] `tests/Feature/Http/Chat/CoachReassignmentTest.php`(コーチ変更後、旧コーチが ChatRoom にアクセスして 403 + chat 一覧から消える / 新コーチが既存ルームを継承して閲覧 + 送信できる / `coach_last_read_at` が旧コーチのまま保持されている / メッセージ履歴が残っている)（REQ-chat-060, REQ-chat-061, REQ-chat-062）
- [ ] `tests/Feature/Http/Admin/Chat/IndexTest.php`(admin 全件一覧 + 各種フィルタ / coach / student アクセスで 403)
- [ ] `tests/Feature/Http/Admin/Chat/ShowTest.php`(admin 詳細閲覧で `last_read_at` UPDATE されない / 送信フォーム非表示)

### Feature テスト（Action 層）

- [ ] `tests/Feature/UseCases/Chat/StoreMessageActionTest.php`(コーチ未割当例外 / 既存ルームへの追記 / 状態遷移網羅 / 添付保存 + DB 整合 / 通知発火検証 / トランザクション失敗時のロールバック)
- [ ] `tests/Feature/UseCases/Chat/ResolveActionTest.php`(冪等性例外 + 正常遷移)

### Unit テスト（Service / Policy）

- [ ] `tests/Unit/Services/ChatRoomStateServiceTest.php`(student × Unattended / student × InProgress / student × Resolved / coach × Unattended / coach × InProgress / coach × Resolved の 6 ケース網羅)
- [ ] `tests/Unit/Services/ChatUnreadCountServiceTest.php`(自分送信は未読カウント除外 / `last_read_at` NULL 時の挙動 / `roomCountForUser` の 1 クエリ集計検証)
- [ ] `tests/Unit/Policies/ChatRoomPolicyTest.php`(view / sendMessage / resolve の 3 × 3 ロール × ルーム状態の真偽値網羅、コーチ未割当時の sendMessage=false)
- [ ] `tests/Unit/Policies/ChatAttachmentPolicyTest.php`(`download` の 3 ロール × 当事者 / 他人ケース)

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Chat` 通過
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザでの主要画面動作確認:
  - [ ] 受講生で `/chat-rooms` を開きルーム一覧表示
  - [ ] コーチ未割当の Enrollment で「コーチ未割当」バッジ + 開く disabled が表示される
  - [ ] 受講生で初回メッセージ送信 → `ChatRoom` が unattended で生成、コーチ側で未対応バッジ点灯
  - [ ] コーチで `/coach/chat-rooms` を開き未対応ルーム一覧表示、status フィルタ / 資格フィルタ / キーワード検索が機能する
  - [ ] コーチが返信 → ルーム状態 in_progress、サイドバー未対応バッジ減少
  - [ ] 受講生で添付付き送信（PNG + PDF 2 ファイル）→ 詳細画面で添付リンク表示、signed URL でダウンロード成功
  - [ ] signed URL を 10 分以上経過後に再アクセスして 403
  - [ ] コーチで「解決済にする」→ status=resolved、サイドバーから消える
  - [ ] 受講生が resolved ルームに新規送信 → status=unattended に再遷移、コーチ側で未対応バッジが復活
  - [ ] admin で `/admin/chat-rooms` を開き全件監査閲覧、`/admin/chat-rooms/{room}` で送信フォーム非表示確認
  - [ ] 他受講生のルーム URL を直接叩いて 403 / 404
  - [ ] admin が `enrollment.assigned_coach_id` を変更したあと、旧コーチで該当ルームへアクセスして 403 + 一覧から消えること、新コーチで継承されたルームが見え送信できることを確認
- [ ] 通知連動の動作確認:
  - [ ] メッセージ送信後 `/notifications` で Database channel の通知が表示される
  - [ ] 通知クリックでルーム詳細へ遷移する
  - [ ] サイドバー TopBar の通知バッジ件数が増減する（Basic 範囲は遷移時更新）

> Advance Broadcasting によるリアルタイム push は [[notification]] Feature の Advance タスクで実装されるため、本 Feature のタスクには含まない。
