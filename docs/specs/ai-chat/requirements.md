# ai-chat 要件定義

## 概要

受講生が問題演習・教材で詰まった瞬間に Gemini API（既定）へ即座に相談できる学習補助チャット機能。**フル画面の AI 相談画面**（会話一覧 + 詳細）と、受講生の全画面に常駐する**フローティングチャットウィジェット**の 2 入口を提供する。教材 Section 閲覧中にウィジェットを開くと該当 Section 本文がシステムプロンプトに自動注入され、教材文脈で回答が得られる。AI 応答は **Server-Sent Events によるストリーミング** で逐次表示される。会話履歴は両入口共通の DB レコードで統合管理される。

LLM 呼び出しは `LlmRepositoryInterface` を介して抽象化されており、既定実装は Gemini API（無料枠想定）。`config('ai-chat.driver')` で将来 OpenAI 等への差替が可能。

## 関連 Feature

- **依存先**: [[auth]]（認証 + `EnsureUserRole` Middleware）、[[user-management]]（User Model）、[[enrollment]]（資格紐付けの `Enrollment` 参照）、[[content-management]]（Section 紐付けの `Section` 参照）
- **依存元**: [[learning]]（教材閲覧画面に「この内容を AI に聞く」ボタンを設置、`?section_id={id}` クエリで会話作成画面に遷移する導線を提供）、[[dashboard]]（受講生ダッシュボードからの導線、サイドバー経由）
- **無関係**: [[notification]]（AI 応答に通知は不要、自分で受信するため）、[[chat]] / [[qa-board]]（補助線として並列、相互参照なし）

## スコープ外

- admin / coach の AI 相談機能利用（本 Feature は student 専用、admin / coach 画面に AI 相談画面 / FAB ともに表示しない）
- admin / coach による他受講生の AI 会話履歴閲覧（プライバシー / 監査外、`chat` Feature とは方針が異なる）
- 教材以外（Question / mock-exam 結果 / qa-board 投稿等）への会話コンテキスト紐付け（将来拡張余地、本 spec では Section 紐付けのみ）
- 完全な RAG（Embedding + vector 検索）（Certify は MySQL 8.0 ベース、pgvector / Embedding API 不採用）
- Gemini File API 経由の教材アップロード（無料枠 Flash で制約あり、ファイル有効期限管理が過剰）
- プロンプトの admin 管理 UI / DB 管理（COACHTECH 流 `AiChatbotPrompt` テーブル / 管理画面は不採用、`config/ai-chat.php` で完結）
- 会話の物理削除 / 期間経過後の自動削除（SoftDeletes のみ、Schedule Command 不要）
- 受講生間での会話共有・公開（`qa-board` の役割、ai-chat は完全プライベート）
- 音声入力 / 画像添付 / マルチモーダル
- AI 応答に対する受講生のフィードバック収集（👍 / 👎 ボタン等）（将来拡張余地）
- 会話のエクスポート（CSV / PDF）

## ステークホルダー

- **受講生（student）**: 主利用者。AI 相談画面 + フローティングウィジェットを通じて学習補助を受ける
- **admin**: 機能のオン / オフ（`.env` の `AI_CHAT_ENABLED`）、Rate Limit 値の設定、Gemini API キーの管理
- **コーチ（coach）**: 利用しない（本 Feature 対象外）

## 要件

### モジュール総則

- The ai-chat Module shall student ロールのみが利用可能で、admin / coach に対しては機能を提供しない。
- The ai-chat Module shall LLM 呼び出しを `App\Repositories\Contracts\LlmRepositoryInterface` で抽象化し、`config('ai-chat.driver')` の値（既定 `gemini`）で実装を切替可能とする。
- The ai-chat Module shall ULID 主キー + SoftDeletes + `fillable` 明示の Eloquent モデル規約に準拠する。
- The ai-chat Module shall 会話・メッセージのテーブル名を `ai_chat_conversations` / `ai_chat_messages` の snake_case 複数形で命名する。
- The ai-chat Module shall システムプロンプトを `config/ai-chat.php` に格納し、`AiChatPromptBuilderService` が動的変数（受講生名 / 紐付け資格名 / 紐付け Section 本文）を埋め込んで Gemini に渡す。

### REQ-ai-chat-010: 会話テーブル定義

The ai-chat Module shall `ai_chat_conversations` テーブルに以下のカラムを提供する:

- `id` ULID 主キー
- `user_id` ULID 外部キー（`users.id` 参照、`onDelete('cascade')`）
- `enrollment_id` nullable ULID 外部キー（`enrollments.id` 参照、`onDelete('set null')`）— 資格コンテキスト
- `section_id` nullable ULID 外部キー（`sections.id` 参照、`onDelete('set null')`）— 教材コンテキスト
- `title` string（最大 100 文字）— 会話タイトル
- `last_message_at` nullable timestamp — 一覧の並び順 + 既存会話再開の判定に使用
- `created_at` / `updated_at` / `deleted_at`（SoftDeletes）

複合インデックス: `(user_id, last_message_at)` 降順並べ替え用 / `(user_id, section_id)` 教材紐付け会話の既存検索用。

### REQ-ai-chat-011: メッセージテーブル定義

The ai-chat Module shall `ai_chat_messages` テーブルに以下のカラムを提供する:

- `id` ULID 主キー
- `ai_chat_conversation_id` ULID 外部キー（`ai_chat_conversations.id` 参照、`onDelete('cascade')`）
- `role` string enum（`user` / `assistant`）— OpenAI 形式準拠
- `content` text — メッセージ本文（user の入力 or assistant の応答）
- `status` string enum（`pending` / `streaming` / `completed` / `error`）— assistant role のみ意味を持つ、user は常に `completed`
- `model` nullable string — assistant のみ、LLM モデル識別子（例: `gemini-1.5-flash`）
- `input_tokens` nullable unsignedInteger — assistant のみ、Gemini API 計測値
- `output_tokens` nullable unsignedInteger — assistant のみ、Gemini API 計測値
- `response_time_ms` nullable unsignedInteger — assistant のみ、Gemini API 呼出開始 → ストリーミング完了までの経過時間
- `error_detail` nullable text — assistant のみ、error 状態時の詳細（受講生には汎用文言を表示、これは内部ログ用）
- `created_at` / `updated_at`（SoftDeletes 不要 — 会話単位で SoftDelete されればメッセージは cascade 削除）

複合インデックス: `(ai_chat_conversation_id, created_at)` 会話内のメッセージ取得用。

### REQ-ai-chat-012: 状態 Enum

- The ai-chat Module shall `App\Enums\AiChatMessageRole` enum を提供する: `User` / `Assistant`、`label()` メソッドで「あなた」「AI」を返す。
- The ai-chat Module shall `App\Enums\AiChatMessageStatus` enum を提供する: `Pending`（user 送信直後 / assistant 作成直後）/ `Streaming`（SSE chunk 受信中）/ `Completed`（応答完了）/ `Error`（API エラー）、`label()` で「待機中」「応答中」「完了」「エラー」を返す。

### REQ-ai-chat-013: コンテキスト整合性

- When 会話作成時に `section_id` が指定された場合, the ai-chat Module shall その Section の所属資格を `Section → Chapter → Part → certification_id` のリレーション経由で取得し、受講生の `Enrollment` の中から該当する `certification_id` の Enrollment を引いて `enrollment_id` に自動補完する。
- If 受講生が当該資格に `learning` / `passed` 状態の Enrollment を持たない場合, then the ai-chat Module shall 会話作成を拒否（HTTP 403）する。
- The ai-chat Module shall `section_id` が non-null の場合、`enrollment_id` も non-null とする整合性をモデルレベルで保証する（DB 制約は不要、Service / Action 側でガード）。

### REQ-ai-chat-020: アクセス制御 — ロール

- The ai-chat Module shall 全 ai-chat エンドポイントに `EnsureUserRole:student` Middleware を適用し、admin / coach は HTTP 403 で拒否する。
- When admin / coach が `/ai-chat/*` の URL に直接アクセスした場合, the ai-chat Module shall HTTP 403 を返す。
- The ai-chat Module shall 受講生の全画面に FAB を表示し、admin / coach 画面では FAB を一切レンダリングしない（Blade の条件分岐）。

### REQ-ai-chat-021: アクセス制御 — リソース所有権

- The ai-chat Module shall `AiChatConversationPolicy` で以下を制御する:
  - `viewAny(User $user)`: student のみ true
  - `view(User $user, AiChatConversation $c)`: `$c->user_id === $user->id` のときのみ true、admin / coach も他者会話は false
  - `create(User $user)`: student のみ true
  - `update(User $user, AiChatConversation $c)`: `$c->user_id === $user->id` のときのみ true（タイトル編集）
  - `delete(User $user, AiChatConversation $c)`: `$c->user_id === $user->id` のときのみ true
- When 受講生が他受講生の `ai_chat_conversation_id` を URL に指定した場合, the ai-chat Module shall HTTP 403（Policy 拒否）を返す。

### REQ-ai-chat-022: アクセス制御 — Section 紐付け検証

- When 会話作成時に `section_id` が指定され、その Section の所属資格に対して受講生が Enrollment を持たない場合, the ai-chat Module shall HTTP 403 を返す。
- The ai-chat Module shall `Enrollment.status` が `learning` / `passed` のいずれかの Enrollment のみを「紐付け可能」とみなす（`paused` / `failed` は拒否しない、過去資格の振り返り相談を許容）。

### REQ-ai-chat-023: withdrawn ユーザー遮断

- While `auth()->user()->status === 'withdrawn'` の場合, the ai-chat Module shall すべての ai-chat エンドポイントを HTTP 403 で拒否し、FAB を非表示にする（[[auth]] の Fortify 認証ガードで status=active を強制している前提で、本 Feature では追加チェック不要だが Policy の `viewAny` で防衛的に確認する）。

### REQ-ai-chat-030: 会話一覧

- The ai-chat Module shall `GET /ai-chat` で受講生の会話一覧画面を表示する。
- The ai-chat Module shall 一覧を `last_message_at` 降順（NULL は末尾）で表示し、SoftDeletes 済みの会話は除外する。
- The ai-chat Module shall 各会話エントリに以下を表示する: タイトル / 直近メッセージ送信時刻（相対表記、例「3 時間前」）/ コンテキストバッジ（`📚 {Section.title}` / `🎓 {Certification.name}` / `(全般)`）。
- The ai-chat Module shall ページネーション（20 件 / page）を Laravel `paginate()` で提供する。

### REQ-ai-chat-031: 会話作成

- The ai-chat Module shall `POST /ai-chat/conversations` で新規会話を作成する。
- The ai-chat Module shall リクエストボディに `enrollment_id` / `section_id` / 初回 `message` を受け取る（すべて任意、`message` 未指定の場合は空の会話を作成、`message` 指定時は user message + assistant 応答までを一括処理）。
- When 初回 `message` が指定された場合, the ai-chat Module shall タイトルが空のため `message` の先頭 30 文字（マルチバイト考慮）を `title` に自動セットする。
- The ai-chat Module shall 会話作成成功時、`303 See Other`（Web フォーム経由）で `/ai-chat/conversations/{id}` にリダイレクトする、または `201 Created` + JSON（API 経由）で会話オブジェクトを返す。

### REQ-ai-chat-032: 会話タイトル編集

- The ai-chat Module shall `PATCH /ai-chat/conversations/{id}` で `title` カラムのみ更新する。
- The ai-chat Module shall タイトルを 1〜100 文字の範囲で受け付け、空文字は拒否する。

### REQ-ai-chat-033: 会話削除

- The ai-chat Module shall `DELETE /ai-chat/conversations/{id}` で SoftDelete を実行する（`deleted_at` セット）。
- The ai-chat Module shall 削除後は会話一覧から除外し、URL 直叩きも HTTP 404（SoftDeleted は通常 Route Model Binding でヒットしない）で返す。

### REQ-ai-chat-034: 既存会話再開（同 Section）

- When 受講生が同じ `section_id` で会話作成 API を呼び出した場合, the ai-chat Module shall 既存の `(user_id, section_id, deleted_at IS NULL)` の最新会話があればそれを返却し、新規作成しない（フローティングウィジェット用、`POST /ai-chat/conversations` のレスポンスは `200 OK`（既存）または `201 Created`（新規）で区別）。
- The ai-chat Module shall この自動再開挙動はフローティングウィジェット由来のリクエスト（クエリ `?source=widget`）でのみ有効とし、フル画面の「新規相談」フォーム経由は常に新規作成する。

### REQ-ai-chat-040: 同期メッセージ送信（B-1 Step N: 基礎実装）

- The ai-chat Module shall `POST /ai-chat/conversations/{id}/messages` で受講生のメッセージを受け付け、Gemini API へ同期呼出を実行し、応答を返す。
- The ai-chat Module shall 以下の処理を `DB::transaction()` 境界で実行する:
  1. user role の `AiChatMessage` を INSERT（status=`completed`）
  2. assistant role の `AiChatMessage` を INSERT（status=`pending`）
  3. システムプロンプト組立 + 過去メッセージ取得（最新 N 件、N は `config('ai-chat.history_window', 20)`）
  4. `LlmRepositoryInterface::chat()` 呼び出し
  5. 成功時: assistant message を `content` + `model` + `input_tokens` + `output_tokens` + `response_time_ms` + status=`completed` で UPDATE
  6. 失敗時: assistant message を `error_detail` + status=`error` で UPDATE（user message は残す）
  7. `last_message_at` を `now()` で UPDATE
- The ai-chat Module shall リクエストボディに `content` を必須で受け取り、1〜2000 文字に制限する。
- The ai-chat Module shall 同期レスポンスは `200 OK` + JSON: `{ user_message: {...}, assistant_message: {...} }`。

### REQ-ai-chat-041: ストリーミング送信エンドポイント（B-1 Step N+1: SSE 化）

- The ai-chat Module shall `POST /ai-chat/conversations/{id}/messages/stream` で Server-Sent Events 形式のストリーミング送信を提供する。
- When `config('ai-chat.streaming_enabled')` が `false` の場合, the ai-chat Module shall このエンドポイントを `404 Not Found` で返し、同期版（`POST .../messages`）にフォールバックさせる。
- The ai-chat Module shall ストリーミングレスポンスの `Content-Type` を `text/event-stream`、`Cache-Control: no-cache`、`X-Accel-Buffering: no` でヘッダ送信する。
- The ai-chat Module shall 以下の SSE event を chunk 配信する:
  - `event: meta` / `data: { "assistant_message_id": "..." }` — ストリーミング開始時に 1 回
  - `event: chunk` / `data: { "text": "..." }` — Gemini API から受信した text chunk ごと
  - `event: done` / `data: { "input_tokens": N, "output_tokens": N, "response_time_ms": N }` — 完了時 1 回
  - `event: error` / `data: { "message": "..." }` — エラー発生時 1 回
- The ai-chat Module shall ストリーミング中の chunk は DOM に逐次追記し、メッセージのバッファリングは行わない（chunk を受信したらすぐ表示）。
- The ai-chat Module shall ストリーミング中に発生した chunk をサーバー側でメモリにバッファし、ストリーミング完了時に assistant message の `content` カラムに全文を一括 UPDATE する（chunk ごとの DB UPDATE は性能上行わない）。

### REQ-ai-chat-042: ストリーミング中断耐性

- If クライアントがストリーミング中に接続を切断した場合, then the ai-chat Module shall サーバー側はストリーミング完了まで処理を継続し、最終的にメッセージを `completed` / `error` 状態で DB に保存する。
- When 受講生が後に同会話を再ロードした場合, the ai-chat Module shall 切断時の assistant メッセージが状態 `completed`（または `error`）として一覧表示される。

### REQ-ai-chat-043: メッセージ再送信

- The ai-chat Module shall `POST /ai-chat/conversations/{id}/messages/{message_id}/retry` で `error` 状態の assistant メッセージを再生成する。
- When 受講生が retry を呼び出した場合, the ai-chat Module shall 該当 assistant メッセージを SoftDelete せず status=`pending` に戻し、同じ user メッセージを文脈として LLM 呼出を再実行する。
- The ai-chat Module shall retry は `error` 状態のメッセージに対してのみ可能とし、`completed` メッセージへの retry は HTTP 422 で拒否する。

### REQ-ai-chat-050: システムプロンプト組立

- The ai-chat Module shall `AiChatPromptBuilderService::build(AiChatConversation $c, User $user): string` メソッドを提供する。
- The ai-chat Module shall システムプロンプトテンプレを `config('ai-chat.system_prompt_template')` から取得し、以下のプレースホルダを動的置換する:
  - `{user_name}` — 受講生名
  - `{certification_name}` — `enrollment_id` 紐付け時の資格名（未紐付け時は「全般」）
  - `{section_context}` — `section_id` 紐付け時の Section タイトル + 本文 Markdown（未紐付け時は空文字列）
  - `{current_term}` — Enrollment の `current_term`（`basic_learning` / `mock_practice`）の日本語ラベル
- The ai-chat Module shall システムプロンプトに「Certify LMS の学習支援アシスタントである」「資格試験の合格を目標としている受講生をサポートする」「正答の暗記ではなく理解を促す回答を心がける」「不適切・差別的・違法な要求には応じない」旨を含める。

### REQ-ai-chat-051: 会話履歴の Gemini API への渡し方

- The ai-chat Module shall 過去メッセージを `config('ai-chat.history_window', 20)` 件まで遡って Gemini API のリクエスト履歴として渡す。
- The ai-chat Module shall `error` 状態のメッセージは履歴に含めない。

### REQ-ai-chat-060: Rate Limit — 日次上限

- The ai-chat Module shall Laravel `RateLimiter::for('ai-chat', ...)` で日次上限を定義し、`Route` に `throttle:ai-chat` ミドルウェアを適用する。
- The ai-chat Module shall 上限値を `config('ai-chat.daily_message_limit', 50)` から取得し、`.env` の `AI_CHAT_DAILY_MESSAGE_LIMIT` で上書き可能とする。
- When 受講生が日次上限を超えた状態でメッセージ送信を試みた場合, the ai-chat Module shall HTTP 429 + 「本日の利用上限（{limit} 通）に達しました。明日 0:00 以降に再度ご利用ください。」のエラーを返す。
- The ai-chat Module shall Rate Limit カウンタは Laravel の Cache 駆動（`cache.default`）で管理し、日次（24 時間）でリセットする。

### REQ-ai-chat-061: Rate Limit — Gemini API 側障害時の挙動

- If Gemini API 呼出が失敗した場合（HTTP 5xx / タイムアウト / レート制限 429）, then the ai-chat Module shall その失敗を Rate Limit カウンタにカウントしない（受講生のクォータを保護）。
- The ai-chat Module shall この実装を `LlmRepositoryInterface::chat()` 呼出後の応答判定で行い、成功時のみ `RateLimiter::hit()` を呼ぶ方式を採る（Laravel 標準 `throttle` ミドルウェアは「リクエスト到達」でカウントするため、エンドポイント側で `RateLimiter` を直接操作する補助ロジックを併用する）。

### REQ-ai-chat-070: フローティングウィジェット — 表示範囲

- The ai-chat Module shall 受講生がログインしている場合、すべての画面（`layouts/app.blade.php` 継承画面全て）の右下に FAB を表示する。
- The ai-chat Module shall FAB は `<x-ai-chat.floating-widget />` Blade コンポーネントで実装し、`layouts/app.blade.php` の末尾で `@if(auth()->check() && auth()->user()->role === UserRole::Student) <x-ai-chat.floating-widget /> @endif` 条件レンダリングする。
- The ai-chat Module shall admin / coach 画面では FAB を一切レンダリングしない（コンポーネントが呼ばれない）。

### REQ-ai-chat-071: フローティングウィジェット — Section コンテキスト自動付与

- When 教材閲覧画面（[[learning]] Feature の Section 表示画面、URL パターン `/contents/{section_id}` または同等）にて FAB が表示される場合, the ai-chat Module shall Blade の `data-section-id` 属性で FAB に Section ID を渡す。
- When 受講生が FAB をクリックしてウィジェットを開いた場合, the ai-chat Module shall JS が `data-section-id` を読み取り、ウィジェット側で「この Section の既存会話を再開 or 新規作成」（`?source=widget` クエリで `POST /ai-chat/conversations`）する。
- When 教材以外の画面で FAB が開かれた場合, the ai-chat Module shall `section_id` なしで「全般相談モード」の最新会話を再開 or 新規作成する。

### REQ-ai-chat-072: フローティングウィジェット — モーダル UI

- The ai-chat Module shall ウィジェット展開時に画面右下から立ち上がる **セミモーダル** を表示する（背景の教材閲覧を妨げない、`fixed bottom-4 right-4 w-96 h-[600px] z-50` 程度のサイズ）。
- The ai-chat Module shall モーダル内に以下を表示する:
  - ヘッダ: `🤖 AI 相談` タイトル、コンテキストバッジ（`📚 {Section.title}` 等）、`[↗] フル画面で開く` ボタン、`[✕]` 閉じるボタン
  - 本文: メッセージリスト（user / assistant のバブル UI、過去 20 件を初期表示、スクロールで遡る）
  - フッタ: テキスト入力 textarea + 送信ボタン、送信中はローディングスピナー、Rate Limit 接近時は警告表示
- The ai-chat Module shall モバイル（`md:` 未満）ではウィジェットを全画面化する（`md:fixed md:bottom-4 md:right-4` → `inset-0`）。

### REQ-ai-chat-073: フローティングウィジェット — 状態保持

- The ai-chat Module shall ウィジェットの開閉状態をブラウザの `sessionStorage` に保存し、ページ遷移後も同じ会話を継続できるようにする（同タブ内、新タブには引き継がない）。
- The ai-chat Module shall ウィジェット内の `current_conversation_id` を `sessionStorage` に保持し、画面遷移直後でも前会話の続きが表示される。

### REQ-ai-chat-074: フローティングウィジェット — フル画面遷移

- When 受講生が `[↗]` ボタンをクリックした場合, the ai-chat Module shall `/ai-chat/conversations/{current_conversation_id}` に遷移し、ウィジェットを閉じる。
- The ai-chat Module shall フル画面遷移後もウィジェットの再オープンは可能（ただし `?source=widget` 経由ではないため新規会話扱い）。

### REQ-ai-chat-080: LLM Repository 抽象

- The ai-chat Module shall `App\Repositories\Contracts\LlmRepositoryInterface` を提供する:
  - `chat(string $systemPrompt, array $messages, ?string $model = null): LlmChatResponse` — 同期版
  - `streamChat(string $systemPrompt, array $messages, ?string $model = null): \Generator` — SSE 用、`yield` で chunk text を返す
- The ai-chat Module shall `LlmChatResponse` value object を提供する: `content` / `model` / `input_tokens` / `output_tokens` / `response_time_ms` プロパティ。
- The ai-chat Module shall `App\Repositories\GeminiLlmRepository` で `LlmRepositoryInterface` を実装し、`AppServiceProvider::register()` で `$this->app->bind(LlmRepositoryInterface::class, GeminiLlmRepository::class)` 形式で binding する。
- The ai-chat Module shall `config('ai-chat.driver')` の値（既定 `gemini`）に応じて binding を切替可能とする（将来 `openai` 等の値で別 Repository を bind するためのフック）。

### REQ-ai-chat-081: Gemini API 連携

- The ai-chat Module shall Gemini API のエンドポイント / モデル / API キーを `config/ai-chat.php` から取得する:
  - `gemini.endpoint`（既定 `https://generativelanguage.googleapis.com/v1beta`）
  - `gemini.model`（既定 `gemini-1.5-flash`）
  - `gemini.api_key`（`.env` の `GEMINI_API_KEY`）
- The ai-chat Module shall Gemini API への HTTP 呼出を `Illuminate\Support\Facades\Http` で行い、`->retry(2, 100)->timeout(30)` で耐障害性を確保する。
- The ai-chat Module shall ストリーミングは Gemini API の `streamGenerateContent` エンドポイント（または `generateContent` の `alt=sse` モード）を利用する。

### REQ-ai-chat-082: API キー未設定時の挙動

- If `config('ai-chat.gemini.api_key')` が空文字または null の場合, then the ai-chat Module shall アプリケーション起動時に `AiChatNotConfiguredException`（500 Internal Server Error）を throw し、受講生には「AI 相談機能は現在ご利用いただけません。管理者にお問い合わせください。」を表示する。

### REQ-ai-chat-090: 機能 OFF スイッチ

- When `config('ai-chat.enabled')` が `false` の場合, the ai-chat Module shall すべての ai-chat ルートを `404 Not Found` で返し、サイドバーの「AI 相談」メニュー項目と FAB を非表示にする（`Route::has('ai-chat.index')` の `<x-nav.item>` ガード + Blade の `@if(config('ai-chat.enabled'))` ガード）。

### REQ-ai-chat-091: ログ記録

- The ai-chat Module shall LLM 呼出のリクエスト / レスポンス / エラーを Laravel ログ（`channel: ai-chat`、`config/logging.php` に新規 channel 追加）に記録する:
  - 成功時: `info` レベル、`conversation_id`, `message_id`, `model`, `input_tokens`, `output_tokens`, `response_time_ms` をログ
  - エラー時: `error` レベル、`conversation_id`, `message_id`, `error_class`, `error_message`, `http_status`（Gemini 側ステータス）をログ
- The ai-chat Module shall ログには受講生の `user_id` / `email` を含めない（PII 保護、ログ仕様として `user_id` のみ）。
- The ai-chat Module shall システムプロンプト本文・ユーザー入力・AI 応答内容そのものはログに記録しない（プライバシー / 個人学習履歴の保護）。

### REQ-ai-chat-092: 監査用エクスポート

- The ai-chat Module shall [[analytics-export]] の `GET /api/v1/admin/...` 配下に **本 Feature の API を追加しない**（admin / coach の他者会話閲覧禁止と一貫）。
- The ai-chat Module shall 受講生自身の会話履歴を CSV / JSON でダウンロードする機能は提供しない（スコープ外、将来拡張余地）。

## 非機能要件（NFR）

### NFR-ai-chat-001: パフォーマンス

- The ai-chat Module shall 会話一覧画面（`GET /ai-chat`）のレスポンスを 200ms 以内（自分の会話 100 件 / ページネーション 20 件 / Eager Load 適用）で返す。
- The ai-chat Module shall 会話詳細画面（`GET /ai-chat/conversations/{id}`）のレスポンスを 300ms 以内（メッセージ 100 件 / Eager Load 適用）で返す。
- The ai-chat Module shall 同期メッセージ送信は Gemini API レスポンス時間 + 200ms 以内で完了する（DB 操作 + プロンプト組立のオーバーヘッドを 200ms 以内に抑える）。
- The ai-chat Module shall SSE 初回 chunk 到達までの時間（Time to First Token）を Gemini API レスポンス時間に依存する範囲で最小化する。

### NFR-ai-chat-002: スケーラビリティ

- The ai-chat Module shall PHP-FPM のプロセス占有を考慮し、SSE エンドポイントの最大同時接続数を Sail デフォルトの worker 数（5）の範囲内で運用する（教育 PJ スコープ）。本番運用時は worker 数調整 + Reverb / Pusher への移行検討が必要だが本 Feature では扱わない。

### NFR-ai-chat-003: セキュリティ

- The ai-chat Module shall すべてのフォーム送信に CSRF トークンを必須とする（Blade `@csrf` + JS `X-CSRF-TOKEN` ヘッダ）。
- The ai-chat Module shall SSE エンドポイントは `POST + fetch ReadableStream` 方式で実装し（`EventSource` は GET のみで CSRF ヘッダ送信不可のため不採用）、CSRF を強制する。
- The ai-chat Module shall Gemini API のレスポンスを Blade で表示する際、`{{ $message->content }}` の自動エスケープを使用し、XSS を防ぐ。Markdown レンダリングする場合は `league/commonmark` の `safe_links_policy` + `unallowed_attributes` を適用する。
- The ai-chat Module shall Gemini API キーをコード内にハードコードせず、`.env` 経由のみで管理する。

### NFR-ai-chat-004: テスト

- The ai-chat Module shall Feature テストで以下を網羅する: 認可（admin / coach の 403）/ 他者会話アクセス（403）/ Section 紐付け Policy（403）/ メッセージ送信成功（200）/ Gemini API エラー時 fallback（500 ではなく 200 + error メッセージ表示）/ Rate Limit 超過（429）/ FAB の admin / coach 非表示。
- The ai-chat Module shall Unit テストで以下を網羅する: `LlmRepositoryInterface` の Fake 実装による差替 / `AiChatPromptBuilderService` のプレースホルダ置換 / `GeminiLlmRepository` の `Http::fake()` による正常系 + エラー系 / SSE chunk のスタブ収集（`Http::fakeSequence` + Generator）。
- The ai-chat Module shall ストリーミングテストは `Http::fake()` でストリーミングレスポンスをスタブ化し、Generator から yield された chunk を assert する。

### NFR-ai-chat-005: 受講生の Gemini 無料枠保護

- The ai-chat Module shall Rate Limit を `config('ai-chat.daily_message_limit', 50)` で既定 50 通 / 日 / ユーザーとし、Gemini 1.5 Flash の無料枠（15 RPM / 1500 RPD / 100 万トークン/月、2026 年時点）を超過しない設計とする。
- The ai-chat Module shall システムプロンプトとメッセージ履歴の合計トークン数が `config('ai-chat.max_context_tokens', 30000)` を超える場合、古い履歴から順に切り詰めて API 送信する（履歴を切り詰めても、過去メッセージレコード自体は DB に残す）。

### NFR-ai-chat-006: 国際化

- The ai-chat Module shall UI 文言を日本語のみで実装する（多言語化は CLAUDE.md スコープ外）。
- The ai-chat Module shall システムプロンプトに「日本語で回答してください」を明示し、Gemini に日本語応答を強制する。

### NFR-ai-chat-007: アクセシビリティ

- The ai-chat Module shall フローティングウィジェットを WCAG 2.1 AA 相当で実装する:
  - FAB は `<button aria-label="AI 相談を開く">` で命名
  - モーダルは `role="dialog" aria-modal="true" aria-labelledby="ai-chat-widget-title"`
  - メッセージリストは `aria-live="polite"` で SSE chunk 追記を読み上げ可能にする
  - `Esc` キーでモーダルを閉じる、フォーカストラップを実装

## 用語集

| 用語 | 定義 |
|---|---|
| AI 相談 | 本 Feature が提供する LLM チャット機能。受講生のみが利用 |
| FAB（Floating Action Button） | 全画面右下に常駐する丸い AI 相談起動ボタン |
| フローティングウィジェット / モーダル | FAB クリックで右下から立ち上がるセミモーダルの AI 相談 UI |
| LLM Repository | LLM API（既定 Gemini）への呼出を抽象化する Repository。`LlmRepositoryInterface` 経由で実装差替可能 |
| システムプロンプト | LLM への入力の冒頭に付与する、AI の役割・制約・コンテキストを規定する文字列。`AiChatPromptBuilderService` が動的生成 |
| SSE（Server-Sent Events） | HTTP レスポンスを `Content-Type: text/event-stream` で chunk 配信するブラウザ標準のサーバープッシュ方式。本 Feature では LLM 応答の逐次表示に使用 |
| Section 紐付け | 会話に `section_id` を持たせ、システムプロンプトに該当 Section の Markdown 本文を埋め込むコンテキスト注入機構 |
| 全般相談モード | `enrollment_id` / `section_id` ともに未紐付けの会話。資格・教材に依存しない一般的な学習相談 |
