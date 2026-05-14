# chat 要件定義

## 概要

受講生と担当コーチが **資格（[[enrollment]]）単位の 1on1 ルームで非同期にメッセージをやり取り** する Feature。テキスト本文 + 画像 / PDF 添付（最大 3 ファイル × 5MB）をサポートし、ルーム状態（`unattended` / `in_progress` / `resolved`）の遷移と未読バッジで「面談予約より軽い相談」と「公開掲示板 ([[qa-board]]) では出しづらい個別相談」の中間を埋める。chat 画面自体は Basic / Advance ともに非同期方式（送信 → リロード / 画面遷移で取得 + ルーム × ロール単位の `last_read_at` で未読件数を算出）。リアルタイム push は [[notification]] 側で TopBar 通知ベルへ集約する設計（B 案、Phase 0 合意）。

## ロールごとのストーリー

- **受講生（student）**: 受講中の資格ごとに担当コーチへ 1on1 でテキスト + 添付（画像 / PDF）を送り、コーチからの返信を未読バッジで気付き、解決したと感じたら自分でルームを `resolved` 化する。担当コーチ未割当の Enrollment は一覧に「コーチ未割当」と表示され、メッセージ送信を試みると 422 エラーで遮断される。
- **コーチ（coach）**: 担当受講生からの全ルームを「未対応 / 対応中 / 解決済」フィルタと資格 / 受講生名検索で一覧化し、未対応ルームから返信を開始（状態が `in_progress` へ自動遷移）。対応完了で `resolved` 化、`dashboard` の滞留検知から声かけする際は解決済ルームへ新規送信して `unattended` に戻す。
- **管理者（admin）**: 監査目的で全ルーム / 全メッセージ / 全添付を **閲覧のみ** 可。送信フォームは Blade で表示されず、POST も Policy で拒否される。

## 受け入れ基準（EARS形式）

### 機能要件 — ChatRoom 管理

- **REQ-chat-001**: The chat Module shall 各 `Enrollment` に対して受講生 × 担当コーチの 1on1 ルームを最大 1 つ持つ（`(enrollment_id)` UNIQUE）。
- **REQ-chat-002**: When 受講生が担当コーチ未割当の `Enrollment`（`assigned_coach_id IS NULL`）に対してメッセージ送信を試みた場合, the chat Module shall HTTP 422 で拒否し「担当コーチが割り当てられていません」エラーメッセージを返す。
- **REQ-chat-003**: When 受講生が `ChatRoom` の存在しない `Enrollment` に対して初回メッセージを送信した場合, the chat Module shall 送信処理と同一トランザクション内で `ChatRoom` を `status = unattended` で INSERT してから `ChatMessage` を INSERT する。
- **REQ-chat-004**: The chat Module shall `ChatRoom.status` を `unattended`（未対応） / `in_progress`（対応中） / `resolved`（解決済） の 3 値で管理する（`product.md` 「## ステータス遷移 F」準拠）。
- **REQ-chat-005**: When 受講生が初回メッセージを送信した場合, the chat Module shall `ChatRoom.status` を `unattended` で生成する。
- **REQ-chat-006**: When コーチが `unattended` ルームに最初の返信メッセージを送信した場合, the chat Module shall `ChatRoom.status` を `in_progress` に遷移させる。
- **REQ-chat-007**: When コーチが `in_progress` ルームに対して「解決済にする」操作を実行した場合, the chat Module shall `ChatRoom.status` を `resolved` に遷移させる。
- **REQ-chat-008**: When 受講生が `in_progress` ルームに対して「解決済にする」操作を実行した場合, the chat Module shall `ChatRoom.status` を `resolved` に遷移させる（受講生主導の「ありがとう」終了動線、生徒も `resolved` 化可）。
- **REQ-chat-009**: When 受講生が `in_progress` または `resolved` のルームに新規メッセージを送信した場合, the chat Module shall `ChatRoom.status` を `unattended` に再遷移させる（コーチへの「再要対応」シグナル）。

### 機能要件 — ChatMessage 送受信

- **REQ-chat-010**: The chat Module shall 当事者（`enrollment.user_id` = 受講生 / `enrollment.assigned_coach_id` = コーチ）のみがメッセージ送信できるよう Policy で制御する。
- **REQ-chat-011**: The chat Module shall `ChatMessage.body` の最大長を **2000 文字** とし、これを超える入力は FormRequest バリデーションで HTTP 422 を返す。
- **REQ-chat-012**: When ルーム詳細画面が表示される場合, the chat Module shall メッセージを `created_at ASC`（古い → 新しい）で時系列表示する。
- **REQ-chat-013**: When メッセージが描画される場合, the chat Module shall 自分の送信したメッセージとそれ以外を視覚的に区別（自分 = 右寄せ / 別色、Wave 0a Design System のセマンティックトークン準拠）表示する。
- **REQ-chat-014**: The chat Module shall メッセージの **編集 / 削除を提供しない**（学習相談の改竄防止 + admin 監査の信頼性、Phase 0 合意）。
- **REQ-chat-015**: When `admin` ロールがルーム詳細画面を閲覧する場合, the chat Module shall メッセージ送信フォームを描画しないし、`POST /chat-rooms/{room}/messages` も Policy で 403 を返す。

### 機能要件 — ChatAttachment 添付

- **REQ-chat-020**: The chat Module shall メッセージ送信時に画像（PNG / JPG / WebP）または PDF を **1 メッセージあたり最大 3 ファイル**、**1 ファイルあたり最大 5MB** まで添付可能とする。
- **REQ-chat-021**: If 添付ファイルの拡張子が PNG / JPG / WebP / PDF 以外の場合, then the chat Module shall HTTP 422 で拒否し「画像（PNG / JPG / WebP）または PDF のみ添付できます」エラーメッセージを返す。
- **REQ-chat-022**: The chat Module shall 添付ファイルを **Laravel Storage の `private` driver**（`storage/app/private/chat-attachments/{room-ulid}/{message-ulid}/{ulid}.{ext}`）に保存する。
- **REQ-chat-023**: The chat Module shall 添付ファイル配信を **`AttachmentController::download`** 経由で行い、Policy で当事者（および admin）のみ閲覧可とする。
- **REQ-chat-024**: When `AttachmentController::download` がアクセスされた場合, the chat Module shall 署名付き URL（`URL::temporarySignedRoute`、有効期限 10 分）でのアクセスのみ許可する。
- **REQ-chat-025**: If 署名付き URL の有効期限が切れていた、または署名が不一致だった場合, then the chat Module shall HTTP 403 を返す。
- **REQ-chat-026**: The chat Module shall `ChatAttachment` テーブルに `chat_message_id` / `original_filename` / `stored_path` / `mime_type` / `file_size_bytes` を保存し、ダウンロード時に `Content-Disposition: attachment; filename="{original_filename}"` でレスポンスする。

### 機能要件 — 既読・未読バッジ

- **REQ-chat-030**: The chat Module shall `chat_rooms.student_last_read_at` と `chat_rooms.coach_last_read_at` の 2 カラムでロール別の既読時刻を保持する。
- **REQ-chat-031**: When 受講生がルーム詳細画面を `GET` した場合, the chat Module shall `chat_rooms.student_last_read_at = now()` を UPDATE する。
- **REQ-chat-032**: When コーチがルーム詳細画面を `GET` した場合, the chat Module shall `chat_rooms.coach_last_read_at = now()` を UPDATE する。
- **REQ-chat-033**: When `admin` がルーム詳細画面を `GET` した場合, the chat Module shall `student_last_read_at` / `coach_last_read_at` を UPDATE しない（admin の閲覧は既読扱いに含めない）。
- **REQ-chat-034**: The chat Module shall 受講生の **ルーム別未読件数** を `COUNT(chat_messages WHERE chat_room_id = ? AND created_at > student_last_read_at AND sender_user_id != self_user_id AND deleted_at IS NULL)` で算出する。
- **REQ-chat-035**: The chat Module shall コーチの **ルーム別未読件数** を `COUNT(chat_messages WHERE chat_room_id = ? AND created_at > coach_last_read_at AND sender_user_id != self_user_id AND deleted_at IS NULL)` で算出する。
- **REQ-chat-036**: The chat Module shall サイドバー（`<x-nav.item>` の `:badge`）には **自分宛で未読メッセージを 1 件以上含むルームの総数** を `SidebarBadgeComposer` 経由で渡す（個別メッセージ件数ではなくルーム単位、`frontend-ui-foundation.md` 「サイドバー実装規約」準拠）。

### 機能要件 — 一覧・閲覧画面

- **REQ-chat-040**: When 受講生が `GET /chat-rooms` にアクセスした場合, the chat Module shall 自分が当事者の全 `ChatRoom` を `enrollment.certification.name` / `assignedCoach.name` / `status` / 自分宛未読件数 / 最終メッセージ日時 / 最終メッセージプレビュー（先頭 60 文字）付きで `last_message_at DESC` 順に一覧表示する。
- **REQ-chat-041**: When コーチが `GET /coach/chat-rooms` にアクセスした場合, the chat Module shall 自分が `enrollment.assigned_coach_id` の全 `ChatRoom` を同等のメタ情報付きで、デフォルトは `status = unattended` でフィルタした上で `last_message_at DESC` 順に一覧表示する。
- **REQ-chat-042**: The chat Module shall コーチ一覧画面に `status` フィルタ（`unattended` / `in_progress` / `resolved` / `all`）、資格フィルタ（`certification_id`）、受講生名キーワード検索（`enrollment.user.name` 部分一致）を提供する。
- **REQ-chat-043**: When `admin` が `GET /admin/chat-rooms` にアクセスした場合, the chat Module shall 全 `ChatRoom` を一覧表示し、受講生名 / コーチ名 / 資格名でフィルタ可能とする（admin 監査画面）。
- **REQ-chat-044**: When 担当コーチが未割当の `Enrollment` を受講生一覧に表示する場合, the chat Module shall ルーム行を `<x-badge variant="warning">コーチ未割当</x-badge>` で表示し、行クリック / 「開く」ボタンを `disabled` 状態とする。
- **REQ-chat-045**: The chat Module shall 一覧クエリで `with(['enrollment.certification', 'enrollment.user', 'enrollment.assignedCoach', 'latestMessage'])` を eager load し N+1 を避ける（NFR-chat-002）。
- **REQ-chat-046**: When ルームが 1 件も存在しないロールがアクセスした場合, the chat Module shall `<x-empty-state>` で「まだメッセージはありません」を表示する。

### 機能要件 — 認可・スコープ制御

- **REQ-chat-050**: The chat Module shall `ChatRoomPolicy::view($user, $room)` を以下で判定する: `admin` は常に true / `coach` は `$room->enrollment->assigned_coach_id === $user->id` / `student` は `$room->enrollment->user_id === $user->id`。
- **REQ-chat-051**: The chat Module shall `ChatRoomPolicy::sendMessage($user, $room)` を以下で判定する: `admin` は常に false（監査閲覧専用、送信不可）/ `coach` / `student` は `view` と同条件かつ `$room->enrollment->assigned_coach_id IS NOT NULL`。
- **REQ-chat-052**: The chat Module shall `ChatRoomPolicy::resolve($user, $room)` を以下で判定する: `admin` は false / `coach` は `view` と同条件 / `student` は `view` と同条件かつ `$room->status === ChatRoomStatus::InProgress`（`resolved` ルームを生徒が再 `resolved` 化することは UI と整合させ拒否）。
- **REQ-chat-053**: The chat Module shall `ChatAttachmentPolicy::download($user, $attachment)` を `$attachment->message->chatRoom` に対する `ChatRoomPolicy::view` 委譲で判定する。
- **REQ-chat-054**: If 当事者以外（他受講生 / 他コーチ）がルーム / メッセージ送信 / 添付 URL に直接アクセスした場合, then the chat Module shall Policy で 403、Route Model Binding 失敗時は 404 を返す。

### 機能要件 — コーチ変更時の継承

- **REQ-chat-060**: When `enrollment.assigned_coach_id` が変更（[[enrollment]] の admin 操作）された場合, the chat Module shall 既存 `ChatRoom` を新コーチに引き継ぎ、既存 `ChatMessage` / `ChatAttachment` は履歴として保持する（chat 側で追加の migration / 状態変更は不要、Policy 判定が新 `assigned_coach_id` を参照することで自動的に切り替わる）。
- **REQ-chat-061**: When 旧コーチがコーチ変更後にルームへアクセスした場合, the chat Module shall `ChatRoomPolicy::view` が false を返し、コーチの chat 一覧から該当ルームが消える。
- **REQ-chat-062**: The chat Module shall コーチ変更で旧コーチの `coach_last_read_at` をクリアせず保持する（履歴の整合性、新コーチ初回 GET で `now()` に上書きされる）。

### 機能要件 — 通知連動

- **REQ-chat-070**: When 受講生がメッセージを送信した場合, the chat Module shall [[notification]] Feature の `NotifyNewChatMessageAction` を同一トランザクション内で呼び、担当コーチに **Database channel のみ**（Mail channel なし、Phase 0 合意）で通知を INSERT する。
- **REQ-chat-071**: When コーチがメッセージを送信した場合, the chat Module shall [[notification]] Feature の `NotifyNewChatMessageAction` を呼び、受講生に Database channel のみで通知を INSERT する。
- **REQ-chat-072**: The chat Module shall 通知データに `chat_room_id` / `chat_message_id` / `sender_user_id` / `sender_name` / `body_preview`（先頭 60 文字）を含め、通知一覧クリック時にルーム詳細へ遷移可能とする。
- **REQ-chat-073**: If メッセージ送信トランザクションが失敗した場合, then the chat Module shall 通知 INSERT も一緒にロールバックする（不整合通知の防止、NFR-chat-001）。

### 非機能要件

- **NFR-chat-001**: The chat Module shall メッセージ送信 / ルーム状態遷移 / 添付保存 / 通知 INSERT を **同一 `DB::transaction()` 内** で実行し、いずれかの失敗で全体ロールバックする。
- **NFR-chat-002**: The chat Module shall 一覧・詳細クエリで Eager Loading（`with(...)`）を使用し N+1 を回避する。
- **NFR-chat-003**: The chat Module shall `chat_rooms.last_message_at` を denormalize で保持し、`chat_messages` 作成時に Model `booted` フックで UPDATE することで一覧の `ORDER BY last_message_at DESC` を index で高速化する。
- **NFR-chat-004**: The chat Module shall `(enrollment_id)` UNIQUE / `(assigned_coach_id, status, last_message_at)` 複合 INDEX / `(chat_room_id, created_at)` 複合 INDEX を備える。
- **NFR-chat-005**: The chat Module shall 添付ファイル配信を `URL::temporarySignedRoute` の 10 分有効期限で行い、URL 流出時の被害を最小化する。
- **NFR-chat-006**: The chat Module shall ドメイン例外を `app/Exceptions/Chat/` 配下に具象クラスとして配置する（`ChatRoomNotFoundException` / `EnrollmentCoachNotAssignedForChatException` / `ChatRoomAlreadyResolvedException` 等、`backend-exceptions.md` 準拠）。
- **NFR-chat-007**: The chat Module shall Blade を `frontend-blade.md` の共通コンポーネント API（`<x-button>` / `<x-form.textarea>` / `<x-form.file>` / `<x-badge>` / `<x-avatar>` / `<x-empty-state>` / `<x-card>` / `<x-tabs>` / `<x-paginator>`）のみで構成し、独自スタイルの新規定義を行わない。
- **NFR-chat-008**: The chat Module shall アクセシビリティ要件（`frontend-ui-foundation.md` 「アクセシビリティ要件」）を満たし、メッセージ送信フォームに `aria-label`、添付ファイル選択に `accept` 属性、未読バッジに `aria-label="未読 N 件"` を付与する。

## スコープ外

- **リアルタイム push（chat 画面の即時更新）** — Phase 0 合意「B 案」により [[notification]] 側で Broadcasting 化、chat 画面自体は Basic / Advance とも非同期方式のまま
- **メッセージ全文検索** — `product.md` 「## スコープ外」明示通り未対応（短期相談用途、長期参照は [[qa-board]] / 面談メモへ誘導）
- **ピン留め / リアクション / ハイライト** — `product.md` 「## スコープ外」明示通り未対応
- **メッセージ編集 / 削除** — Phase 0 合意により提供しない（誤送信は新メッセージで訂正、admin 監査の信頼性確保）
- **画像 / PDF 以外の添付**（ZIP / 動画 / Office 形式 等） — `product.md` 「## スコープ外」明示通り未対応
- **グループチャット / 複数コーチ参加** — 1 ChatRoom = 1 受講生 + 1 担当コーチ の固定 2 者構成（複数コーチ担当の場合も `Enrollment.assigned_coach_id` で 1 名に確定、`product.md` ロール定義準拠）
- **コーチ → 受講生の chat 開始**（コーチ主導の新規ルーム作成） — 初回送信は受講生のみが起こせる、コーチからは `resolved` ルームへの再送信で再オープンする動線（[[dashboard]] 滞留検知から声かけ）
- **メッセージの既読を相手に通知**（既読マーク / 「既読」表示） — 未読バッジは自分側のみ集計、相手の既読状態は表示しない
- **音声 / ビデオ通話** — [[mentoring]] でも面談実施手段はスコープ外、chat も同様
- **通知の Mail channel** — Phase 0 合意により Database channel のみ（短期相談用途）

## 関連 Feature

- **依存先**（本 Feature が前提とする）:
  - [[auth]] — `User` モデル / ロール / `auth` ガード
  - [[enrollment]] — `Enrollment.user_id` / `Enrollment.assigned_coach_id` / `Enrollment.certification_id` の参照、ルーム 1 対 1 の親
  - [[user-management]] — admin のコーチ変更操作（`assigned_coach_id` 変更後の Policy 自動切り替えで本 Feature が追従）
- **依存元**（本 Feature を利用する）:
  - [[notification]] — `NotifyNewChatMessageAction` の被呼び出し元、Database channel での通知 INSERT を提供。Advance Broadcasting 時は notification 側で全通知種別を TopBar 通知ベルへ push（chat はそのうちの 1 種別、chat 画面の即時更新ではない点に注意）
  - [[dashboard]] — coach Dashboard の「未対応 chat 件数」「未読 chat 件数」表示、`SidebarBadgeComposer` 経由で `chat-rooms` メニュー項目のバッジ
  - [[settings-profile]] — `UserNotificationSetting` の `chat_new_message` トグル参照（受講生がコーチからの新着 chat 通知を OFF にできる、ただし未読バッジ自体は OFF 不可）
