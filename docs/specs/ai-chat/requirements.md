# ai-chat 要件定義

> 業界標準 + 必要最低限の設計に統一(2026-05 改訂):
> - **SSE ストリーミング全削除**: 同期版のみ提供(受講生学習負担削減)
> - **メッセージ「再送信」専用動線削除**: AI 応答失敗時は assistant を `error` 状態で保存・表示するフォールバックのみとし、専用の retry エンドポイント / `RetryAction` / `AiChatMessageNotRetryableException` は持たない。受講生は同じ内容を送り直して再質問する
> - **「資格相談」モード UI 削除**: 資格コンテキストは `users.default_enrollment_id` 経由で自動付与
> - **タイトル LLM 自動生成は維持** (config OFF 可、毎回タイトル手入力する手間を回避する業界標準 UX)
> - **`max_context_tokens` 自前切り詰め削除**: `history_window` 件数だけのシンプル制御
> - **`AiChatRateLimiterService` 削除**: Laravel 標準 `throttle:ai-chat` のみに依存、Gemini 失敗時のクォータ補正は持たない
> - **Section コンテキストをパンくず化**: `{section_context}` には Section 本文を埋め込まず、Part > Chapter > Section の order + タイトルのみを埋め込む(Gemini 無料枠保護目的のコスト最適化、1 メッセージあたり Section 本文ぶんのトークンを固定削減)

## 概要

受講生が問題演習・教材で詰まった瞬間に Gemini API へ即座に相談できる学習補助チャット機能。**フル画面の AI 相談画面**(会話一覧 + 詳細)と、受講生の全画面に常駐する**フローティングチャットウィジェット**の 2 入口を提供する。教材 Section 閲覧中にウィジェットを開くと該当 Section の所在(Part > Chapter > Section の order + タイトル)がシステムプロンプトに自動注入され、教材文脈で回答が得られる。AI 応答は **同期 HTTP リクエスト**で受信する(数秒待ってまとめて表示)。会話履歴は両入口共通の DB レコードで統合管理される。

LLM 呼び出しは `LlmRepositoryInterface` を介して抽象化されており、既定実装は Gemini API(無料枠想定)。`config('ai-chat.driver')` で将来 OpenAI 等への差替が可能。

## 関連 Feature

- **依存先**: [[auth]](認証 + `EnsureUserRole` Middleware)、[[user-management]](User Model + `default_enrollment_id`)、[[enrollment]](資格紐付けの `Enrollment` 参照)、[[content-management]](Section 紐付けの `Section` 参照)
- **依存元**: [[learning]](教材 Section 閲覧画面で `view()->share('pageMeta', [...])` 経由で section コンテキストを Widget に渡す)、[[dashboard]](受講生ダッシュボードからの導線、サイドバー経由)
- **無関係**: [[notification]](AI 応答に通知は不要)、[[chat]] / [[qa-board]](補助線として並列、相互参照なし)

## スコープ外

- admin / coach の AI 相談機能利用(本 Feature は student 専用、admin / coach 画面に AI 相談画面 / FAB ともに表示しない)
- admin / coach による他受講生の AI 会話履歴閲覧(プライバシー / 監査外)
- 教材以外(Question / mock-exam 結果 / qa-board 投稿等)への会話コンテキスト紐付け(将来拡張余地、本 spec では Section 紐付けのみ)
- 完全な RAG(Embedding + vector 検索)(MySQL 8.0 ベース、pgvector / Embedding API 不採用)
- Gemini File API 経由の教材アップロード(無料枠 Flash で制約あり)
- プロンプトの admin 管理 UI / DB 管理 (`config/ai-chat.php` で完結)
- 期間経過後の自動削除 / Schedule Command による一括クリーンアップ(常に物理削除、削除は admin / 受講生本人の手動操作のみ)
- 受講生間での会話共有・公開
- 音声入力 / 画像添付 / マルチモーダル
- AI 応答へのフィードバック収集 (👍 / 👎) / 会話のエクスポート
- **SSE ストリーミング応答** (同期一本に集約、業界標準 UX より受講生学習負担を優先)
- **Gemini 失敗時の Rate Limit カウンタ補正** (Laravel 標準 throttle のみに統一、失敗分も日次 50 通にカウント)
- **max_context_tokens の自前切り詰め** (Gemini 側の context overflow エラーに任せる、history_window 件数だけで制御)

## ステークホルダー

- **受講生(student)**: 主利用者。AI 相談画面 + フローティングウィジェットを通じて学習補助を受ける
- **admin**: 機能のオン / オフ (`.env` の `AI_CHAT_ENABLED`)、Rate Limit 値の設定、Gemini API キーの管理
- **コーチ(coach)**: 利用しない (本 Feature 対象外)

## 要件

### モジュール総則

- The ai-chat Module shall student ロールのみが利用可能で、admin / coach に対しては機能を提供しない。
- The ai-chat Module shall LLM 呼び出しを `App\Repositories\Contracts\LlmRepositoryInterface` で抽象化し、`config('ai-chat.driver')` の値(既定 `gemini`)で実装を切替可能とする。
- The ai-chat Module shall ULID 主キー + `fillable` 明示の Eloquent モデル規約に準拠する(本 Feature では SoftDeletes は不採用、削除は常に物理削除)。
- The ai-chat Module shall 会話・メッセージのテーブル名を `ai_chat_conversations` / `ai_chat_messages` の snake_case 複数形で命名する。
- The ai-chat Module shall システムプロンプトを `config/ai-chat.php` に格納し、`AiChatPromptBuilderService` が動的変数(受講生名 / 紐付け資格名 / 紐付け Section のパンくず)を埋め込んで Gemini に渡す。

### REQ-ai-chat-010: 会話テーブル定義

The ai-chat Module shall `ai_chat_conversations` テーブルに以下のカラムを提供する:

- `id` ULID 主キー
- `user_id` ULID 外部キー(`users.id` 参照、`onDelete('cascade')`)
- `enrollment_id` nullable ULID 外部キー(`enrollments.id` 参照、`onDelete('set null')`)— Section 自動補完経由で確定する資格コンテキスト
- `section_id` nullable ULID 外部キー(`sections.id` 参照、`onDelete('set null')`)— 教材コンテキスト
- `title` string(最大 100 文字)— 会話タイトル
- `last_message_at` nullable timestamp — 一覧の並び順 + 既存会話再開の判定に使用
- `created_at` / `updated_at`

複合インデックス: `(user_id, last_message_at)` 降順並べ替え用 / `(user_id, section_id)` 教材紐付け会話の既存検索用。

### REQ-ai-chat-011: メッセージテーブル定義

The ai-chat Module shall `ai_chat_messages` テーブルに以下のカラムを提供する:

- `id` ULID 主キー
- `ai_chat_conversation_id` ULID 外部キー(`ai_chat_conversations.id` 参照、`onDelete('cascade')`)
- `role` string enum(`user` / `assistant`)— OpenAI 形式準拠
- `content` text — メッセージ本文(user の入力 or assistant の応答)
- `status` string enum(`pending` / `completed` / `error`)— assistant role のみ意味を持つ、user は常に `completed`
- `model` nullable string — assistant のみ、LLM モデル識別子(例: `gemini-2.5-flash-lite`)
- `input_tokens` nullable unsignedInteger — assistant のみ、Gemini API 計測値
- `output_tokens` nullable unsignedInteger — assistant のみ、Gemini API 計測値
- `response_time_ms` nullable unsignedInteger — assistant のみ、Gemini API 呼出開始 → 完了までの経過時間
- `error_detail` nullable text — assistant のみ、error 状態時の詳細(受講生には汎用文言を表示、これは内部ログ用)
- `created_at` / `updated_at`(会話本体の物理削除に cascade で連動削除)

複合インデックス: `(ai_chat_conversation_id, created_at)` 会話内のメッセージ取得用。

> 旧 `streaming` 状態は撤回。同期版のみで pending → completed / error の 3 値遷移とする。

### REQ-ai-chat-012: 状態 Enum

- The ai-chat Module shall `App\Enums\AiChatMessageRole` enum を提供する: `User` / `Assistant`、`label()` メソッドで「あなた」「AI」を返す。
- The ai-chat Module shall `App\Enums\AiChatMessageStatus` enum を提供する: `Pending`(user 送信直後 / assistant 作成直後) / `Completed`(応答完了) / `Error`(API エラー)、`label()` で「待機中」「完了」「エラー」を返す。

### REQ-ai-chat-013: コンテキスト整合性

- When 会話作成時に `section_id` が指定された場合, the ai-chat Module shall その Section の所属資格を `Section → Chapter → Part → certification_id` のリレーション経由で取得し、受講生の `Enrollment` の中から該当する `certification_id` の Enrollment を引いて `enrollment_id` に自動補完する。
- If 受講生が当該資格に `learning` / `passed` 状態の Enrollment を持たない場合, then the ai-chat Module shall 会話作成を拒否(HTTP 403)する。
- The ai-chat Module shall `section_id` が non-null の場合、`enrollment_id` も non-null とする整合性を Action / Service 側でガードする(DB 制約は不要)。
- The ai-chat Module shall `section_id` が null の場合、`enrollment_id` も null とする(= 全般相談モード、資格コンテキストは `AiChatPromptBuilderService` が動的に解決する)。

### REQ-ai-chat-020: アクセス制御 — ロール / ステータス

- The ai-chat Module shall 全 ai-chat エンドポイントに `auth` + `role:student` + `active-learning`(`EnsureActiveLearning`) Middleware を適用する。
- When admin / coach が `/ai-chat/*` の URL に直接アクセスした場合, the ai-chat Module shall HTTP 403 を返す。
- When 受講生のステータスが `in_progress` 以外(`invited` / `graduated` / `withdrawn`)の場合, the ai-chat Module shall HTTP 403 を返す(本プロジェクト統一のプラン機能ロック規約)。
- The ai-chat Module shall 受講生の全画面に FAB を表示し、admin / coach 画面 / graduated 受講生画面では FAB を一切レンダリングしない(Blade の条件分岐)。

### REQ-ai-chat-021: アクセス制御 — リソース所有権

- The ai-chat Module shall `AiChatConversationPolicy` で以下を制御する:
  - `viewAny(User $user)`: student + in_progress のみ true
  - `view(User $user, AiChatConversation $c)`: `$c->user_id === $user->id` のときのみ true、admin / coach も他者会話は false
  - `create(User $user)`: student + in_progress のみ true
  - `update(User $user, AiChatConversation $c)`: `$c->user_id === $user->id` のときのみ true(タイトル編集)
  - `delete(User $user, AiChatConversation $c)`: `$c->user_id === $user->id` のときのみ true
- When 受講生が他受講生の `ai_chat_conversation_id` を URL に指定した場合, the ai-chat Module shall HTTP 403(Policy 拒否)を返す。

### REQ-ai-chat-022: Section 紐付け検証

- When 会話作成時に `section_id` が指定され、その Section の所属資格に対して受講生が Enrollment を持たない場合, the ai-chat Module shall HTTP 403 を返す。
- The ai-chat Module shall `Enrollment.status` が `learning` / `passed` のいずれかの Enrollment のみを「紐付け可能」とみなす(`failed` は拒否)。

### REQ-ai-chat-030: 会話入口(最新会話リダイレクト方式)

- The ai-chat Module shall `GET /ai-chat` で受講生の最新会話(`last_message_at` 降順、NULL は末尾)へリダイレクトする。
- The ai-chat Module shall 会話が 1 件も存在しない場合、空状態ビュー(`ai-chat/empty-state.blade.php`)を表示する。
- The ai-chat Module shall 独立した会話一覧画面・ページネーションを提供しない(会話の切替は会話詳細画面のサイドリストで行う)。専用の `IndexAction` / 一覧 Blade は持たない。

### REQ-ai-chat-031: 会話作成

- The ai-chat Module shall `POST /ai-chat/conversations` で新規会話を作成する。
- The ai-chat Module shall リクエストボディに `section_id` / 初回 `message` / `source` を受け取る(すべて任意、`message` 未指定の場合は空の会話を作成、`message` 指定時は user message + assistant 応答までを一括処理)。
- The ai-chat Module shall 「資格相談モード」(= section_id 無し + 資格 ID 手動指定)の入力経路は持たない。資格コンテキストは `AiChatPromptBuilderService` が `user.default_enrollment_id` 経由で動的解決する。
- When 初回 `message` が指定された場合, the ai-chat Module shall タイトルが空のため `message` の先頭 30 文字(マルチバイト考慮)を `title` に **fallback 値として** 自動セットする(より良いタイトルは REQ-ai-chat-100 の LLM 自動生成で初回 assistant 応答完了後に上書きされる)。
- The ai-chat Module shall 会話作成成功時の応答を以下のように分岐する:
  - `Accept: application/json` (XHR / Widget) → `201 Created` (新規) / `200 OK` (既存再開) + JSON `{conversation, was_reused}`
  - それ以外 (HTML フォーム送信) → `303 See Other` で `/ai-chat/conversations/{id}` にリダイレクト

### REQ-ai-chat-032: 会話タイトル編集

- The ai-chat Module shall `PATCH /ai-chat/conversations/{id}` で `title` カラムのみ更新する。
- The ai-chat Module shall タイトルを 1〜100 文字の範囲で受け付け、空文字は拒否する。

### REQ-ai-chat-033: 会話削除

- The ai-chat Module shall `DELETE /ai-chat/conversations/{id}` で会話本体を物理削除する。配下の `ai_chat_messages` は FK の `cascadeOnDelete` で連動削除される。
- The ai-chat Module shall 削除後は会話一覧から除外し、URL 直叩きは HTTP 404(レコードが存在しないため Route Model Binding でヒットしない)で返す。

### REQ-ai-chat-034: 既存会話再開(同 Section)

- When 受講生が同じ `section_id` で会話作成 API を呼び出した場合, the ai-chat Module shall 既存の `(user_id, section_id)` の最新会話があればそれを返却し、新規作成しない(フローティングウィジェット用、`POST /ai-chat/conversations` のレスポンスは `200 OK`(既存)または `201 Created`(新規)で区別)。
- The ai-chat Module shall この自動再開挙動はフローティングウィジェット由来のリクエスト(クエリ `?source=widget`)でのみ有効とし、フル画面の「新規相談」フォーム経由は常に新規作成する。

### REQ-ai-chat-040: 同期メッセージ送信

- The ai-chat Module shall `POST /ai-chat/conversations/{id}/messages` で受講生のメッセージを受け付け、Gemini API へ同期呼出を実行し、応答を返す。
- The ai-chat Module shall 以下の処理を順に実行する:
  1. 先行 `DB::transaction()` で user role の `AiChatMessage` を INSERT(status=`completed`) + assistant role の `AiChatMessage` を INSERT(status=`pending`、空 content)→ COMMIT(中断耐性のため)
  2. transaction 外で `AiChatPromptBuilderService::build()` でシステムプロンプト組立 + `buildHistory()` で過去メッセージ取得(最新 N 件、N は `config('ai-chat.history_window', 20)`)
  3. `LlmRepositoryInterface::chat()` 呼び出し
  4. 成功時: assistant message を `content` + `model` + `input_tokens` + `output_tokens` + `response_time_ms` + status=`completed` で UPDATE
  5. 失敗時: assistant message を `error_detail` + status=`error` で UPDATE(user message は残す、受講生は同じ内容を送り直して再質問できる)
  6. `last_message_at` を `now()` で UPDATE
  7. 初回 (user, assistant completed) ペア成立時にタイトル LLM 自動生成を試行(REQ-ai-chat-100)
- The ai-chat Module shall リクエストボディに `content` を必須で受け取り、1〜2000 文字に制限する。
- The ai-chat Module shall 同期レスポンスは `200 OK` + JSON: `{ user_message: {...}, assistant_message: {...}, conversation: {...} }`。Gemini 失敗時は `502 Bad Gateway` + JSON `{message, upstream_status}`(上流の HTTP ステータスをクライアントに伝えて原因別の文言を出せるようにする)。

### REQ-ai-chat-042: 失敗時の DB 永続化

- If Gemini API 呼出が失敗した場合, then the ai-chat Module shall user メッセージ + assistant メッセージ(status=`error`)を DB に残し、`502 Bad Gateway` で例外を伝搬する。
- When 受講生が後に同会話を再ロードした場合, the ai-chat Module shall error メッセージがエラー表示のまま一覧に残る(受講生は同じ内容を送り直して再質問できる)。

### REQ-ai-chat-050: システムプロンプト組立

- The ai-chat Module shall `AiChatPromptBuilderService::build(AiChatConversation $c, User $user): string` メソッドを提供する。
- The ai-chat Module shall システムプロンプトテンプレを `config('ai-chat.system_prompt_template')` から取得し、以下のプレースホルダを動的置換する:
  - `{user_name}` — 受講生名
  - `{certification_context}` — 資格コンテキスト解決結果(後述)。資格紐付けが無い場合は空文字
  - `{section_context}` — `section_id` 紐付け時の Section パンくず(`Part {order}「{title}」 > Chapter {order}「{title}」 > Section {order}「{title}」` 形式、未紐付け時は空文字)。Section.body 本文は Gemini 無料枠保護のため埋め込まない
- The ai-chat Module shall 資格コンテキストの解決順序を以下とする:
  1. 会話の `enrollment_id` (= Section 自動補完経由で確定したもの)
  2. ユーザーの `default_enrollment_id` (受講生が「いつもこの資格」と明示設定したもの、`status IN (learning, passed)` のもののみ)
- The ai-chat Module shall 解決された Enrollment があれば `対象資格: {Certification.name}` をプロンプトに含め、無ければ `{certification_context}` を空文字で置換する。
- The ai-chat Module shall システムプロンプトに「Certify LMS の学習支援アシスタントである」「資格試験の合格を目標としている受講生をサポートする」「正答の暗記ではなく理解を促す回答を心がける」「不適切・差別的・違法な要求には応じない」旨を含める。
- The ai-chat Module shall `current_term` (受講ターム) はプロンプトに含めない (必要最低限の原則、Section パンくず + 資格名で AI は十分賢い)。

### REQ-ai-chat-051: 会話履歴の Gemini API への渡し方

- The ai-chat Module shall 過去メッセージを `config('ai-chat.history_window', 20)` 件まで遡って Gemini API のリクエスト履歴として渡す。
- The ai-chat Module shall `error` 状態のメッセージは履歴に含めない。
- The ai-chat Module shall トークン数の自前切り詰めは実装しない(`history_window` の件数制限のみ、Gemini 側の context overflow エラーに任せる)。

### REQ-ai-chat-060: Rate Limit — 日次上限

- The ai-chat Module shall Laravel `RateLimiter::for('ai-chat', ...)` で日次上限を定義し、メッセージ送信系 Route に `throttle:ai-chat` ミドルウェアを適用する。
- The ai-chat Module shall 上限値を `config('ai-chat.daily_message_limit', 50)` から取得し、`.env` の `AI_CHAT_DAILY_MESSAGE_LIMIT` で上書き可能とする。
- When 受講生が日次上限を超えた状態でメッセージ送信を試みた場合, the ai-chat Module shall HTTP 429 + 「本日の利用上限({limit} 通)に達しました。明日 0:00 以降に再度ご利用ください。」のエラーを返す。
- The ai-chat Module shall Gemini API 失敗時のクォータ補正(decrement)は実装しない(Laravel 標準 throttle のみに依存、失敗分も日次 50 通にカウントされる)。

### REQ-ai-chat-070: フローティングウィジェット — 表示範囲

- The ai-chat Module shall 受講生(status=`in_progress`)がログインしている場合、すべての画面(`layouts/app.blade.php` 継承画面全て)の右下に FAB を表示する。
- The ai-chat Module shall FAB は `<x-ai-chat.floating-widget />` Blade コンポーネントで実装し、`layouts/app.blade.php` の末尾で条件レンダリングする。
- The ai-chat Module shall admin / coach / graduated 受講生画面では FAB を一切レンダリングしない。
- The ai-chat Module shall ai-chat Feature 自身の画面(`/ai-chat/*` ルート)では FAB を非表示とする(重複防止)。

### REQ-ai-chat-071: フローティングウィジェット — Section コンテキスト自動付与

- When 教材閲覧画面([[learning]] Feature の Section 表示画面)にて FAB が表示される場合, the ai-chat Module shall Blade の `data-section-id` / `data-section-title` / `data-certification-name` 属性で FAB に Section ID と関連情報を渡す。
- The ai-chat Module shall この属性は [[learning]] Feature 側の Controller / View Composer が `view()->share('pageMeta', ['section_id'=>..., 'section_title'=>..., 'certification_name'=>...])` で渡したものを `layouts/app.blade.php` が読み取って使う。
- When 受講生が FAB をクリックしてウィジェットを開いた場合, the ai-chat Module shall JS が `data-section-id` を読み取り、ウィジェット側で「この Section の既存会話を再開 or 新規作成」(`?source=widget` クエリで `POST /ai-chat/conversations`)する。
- When 教材以外の画面で FAB が開かれた場合, the ai-chat Module shall `section_id` なしで「全般相談モード」の最新会話を再開 or 新規作成する。

### REQ-ai-chat-072: フローティングウィジェット — モーダル UI

- The ai-chat Module shall ウィジェット展開時に画面右下から立ち上がる **セミモーダル** を表示する(背景の教材閲覧を妨げない、`fixed bottom-5 right-5 w-96 h-[600px] z-200` 程度のサイズ)。
- The ai-chat Module shall モーダル内に以下を表示する:
  - ヘッダ: `🤖 AI 相談` タイトル、コンテキストバッジ(`📚 {Section.title}` / `🎓 {default_enrollment 資格名}` / `全般相談`)、`[↗] フル画面で開く` ボタン、`[✕]` 閉じるボタン
  - 本文: メッセージリスト(user / assistant のバブル UI、過去 20 件を初期表示、スクロールで遡る)
  - フッタ: テキスト入力 textarea + 送信ボタン(`⌘+Enter` / `Ctrl+Enter` で送信、単独 `Enter` は改行)、Rate Limit クォータ表示
- The ai-chat Module shall モバイル(`sm:` 未満)ではウィジェットを全画面化する。

### REQ-ai-chat-073: フローティングウィジェット — 状態保持

- The ai-chat Module shall ウィジェットの開閉状態をブラウザの `sessionStorage` に保存し、ページ遷移後も同じ会話を継続できるようにする(同タブ内、新タブには引き継がない)。
- The ai-chat Module shall ウィジェット内の `current_conversation_id` を `sessionStorage` に保持し、画面遷移直後でも前会話の続きが表示される。

### REQ-ai-chat-074: フローティングウィジェット — フル画面遷移

- When 受講生が `[↗]` ボタンをクリックした場合, the ai-chat Module shall `/ai-chat/conversations/{current_conversation_id}` に遷移し、ウィジェットを閉じる。
- When `current_conversation_id` が無い場合, the ai-chat Module shall `/ai-chat`(最新会話へリダイレクト、会話 0 件なら空状態)に遷移する。

### REQ-ai-chat-080: LLM Repository 抽象

- The ai-chat Module shall `App\Repositories\Contracts\LlmRepositoryInterface` を提供する:
  - `chat(string $systemPrompt, array $messages, ?string $model = null): LlmChatResponse` — 同期版
- The ai-chat Module shall `LlmChatResponse` を `App\Services\LlmChatResponse` (既存 PJ の `CategoryHeatmapCell` / `StatsSummary` 等の DTO 配置パターンと同じく `App\Services` 直下に `final readonly` クラスとして提供) する: `content` / `model` / `inputTokens` / `outputTokens` / `responseTimeMs`。
- The ai-chat Module shall `App\Repositories\GeminiLlmRepository` で `LlmRepositoryInterface` を実装し、`AppServiceProvider::register()` で binding する。
- The ai-chat Module shall `config('ai-chat.driver')` の値(既定 `gemini`)に応じて binding を切替可能とする。

### REQ-ai-chat-081: Gemini API 連携

- The ai-chat Module shall Gemini API のエンドポイント / モデル / API キーを `config/ai-chat.php` から取得する。
- The ai-chat Module shall Gemini API への HTTP 呼出を `Illuminate\Support\Facades\Http` で行い、手動 retry ループで `5xx` (一時障害) と `ConnectionException` のみリトライする(最大 2 回)。`429` (Rate Limit 超過) はリトライしない(Gemini 側 RPM/RPD をさらに圧迫しないため)。
- The ai-chat Module shall Gemini 失敗時のレスポンス body 先頭 500 文字を例外メッセージに含めて log channel `ai-chat` に記録する(クォータ詳細 / リセット時刻の特定用)。

### REQ-ai-chat-082: API キー未設定時の挙動

- If `config('ai-chat.gemini.api_key')` が空文字または null の場合, then the ai-chat Module shall アプリケーション起動時に `AiChatNotConfiguredException`(500 Internal Server Error)を throw し、受講生には「AI 相談機能は現在ご利用いただけません。管理者にお問い合わせください。」を表示する。

### REQ-ai-chat-090: 機能 OFF スイッチ

- When `config('ai-chat.enabled')` が `false` の場合, the ai-chat Module shall すべての ai-chat ルートを `404 Not Found` で返し、サイドバーの「AI 相談」メニュー項目と FAB を非表示にする。

### REQ-ai-chat-091: ログ記録

- The ai-chat Module shall LLM 呼出のリクエスト / レスポンス / エラーを Laravel ログ(channel: `ai-chat`、`config/logging.php` に新規 channel 追加)に記録する:
  - 成功時: `info` レベル、`conversation_id`, `message_id`, `model`, `input_tokens`, `output_tokens`, `response_time_ms` をログ
  - エラー時: `error` レベル、`conversation_id`, `message_id`, `error_class`, `error_message`, `http_status` をログ
- The ai-chat Module shall ログには受講生の `user_id` のみ含め、`email` / プロンプト本文 / AI 応答内容は含めない(PII / プライバシー保護)。

### REQ-ai-chat-100: 会話タイトル LLM 自動生成

> **位置付け**: ChatGPT / Claude.ai 等で標準採用される UX パターン。REQ-ai-chat-031 の「先頭 30 文字 fallback」より人間にとって意味のあるタイトルを LLM 生成する。`AI_CHAT_TITLE_GENERATION_ENABLED=false` で無効化可能。

- When 会話に「最初の user メッセージ」+「最初の `status=completed` の assistant メッセージ」のペアが揃った直後, the ai-chat Module shall `LlmRepositoryInterface::chat()` をタイトル生成専用プロンプトで 1 回呼び出し、生成されたタイトルで `ai_chat_conversations.title` を上書き UPDATE する。
- The ai-chat Module shall タイトル生成プロンプトを `config('ai-chat.title_generation_prompt')` から取得する。
- When タイトル生成に失敗した場合(Gemini API エラー / タイムアウト / 異常な空文字応答), the ai-chat Module shall REQ-ai-chat-031 で設定された「先頭 30 文字 fallback」を保持し、受講生にはエラー表示を行わない(タイトル生成は副作用であり、本流のチャット体験を阻害してはならない)。失敗は `warning` ログのみで通知。
- The ai-chat Module shall タイトル生成は会話あたり 1 回限り(最初の assistant 完了メッセージ時のみ)とし、2 回目以降のメッセージ送信時には実行しない。
- The ai-chat Module shall タイトルが受講生によって手動編集された後(REQ-ai-chat-032)でも、本 REQ の自動生成は「初回ペア時点」で既に実行済みのため再生成は行わない(手動編集が優先)。
- When `config('ai-chat.title_generation_enabled')` が `false` の場合, the ai-chat Module shall タイトル自動生成を実行せず、REQ-ai-chat-031 の fallback タイトルのみ使用する。

## 非機能要件(NFR)

### NFR-ai-chat-001: パフォーマンス

- The ai-chat Module shall `GET /ai-chat`(最新会話へのリダイレクト / 会話 0 件時の空状態表示)のレスポンスを 200ms 以内で返す。
- The ai-chat Module shall 会話詳細画面(`GET /ai-chat/conversations/{id}`)のレスポンスを 300ms 以内(メッセージ 100 件 / Eager Load 適用)で返す。
- The ai-chat Module shall 同期メッセージ送信は Gemini API レスポンス時間 + 200ms 以内で完了する。

### NFR-ai-chat-003: セキュリティ

- The ai-chat Module shall すべてのフォーム送信に CSRF トークンを必須とする(Blade `@csrf` + JS `X-CSRF-TOKEN` ヘッダ)。
- The ai-chat Module shall Gemini API のレスポンスを Blade で表示する際、`{{ $message->content }}` の自動エスケープを使用し、XSS を防ぐ。
- The ai-chat Module shall Gemini API キーをコード内にハードコードせず、`.env` 経由のみで管理する。

### NFR-ai-chat-004: テスト

- The ai-chat Module shall Feature テストで以下を網羅する: 認可(admin / coach の 403 + graduated の 403) / 他者会話アクセス(403) / Section 紐付け Policy(403) / メッセージ送信成功(200) / Gemini API エラー時 fallback(502 + assistant error 永続化) / Rate Limit 超過(429) / FAB の admin / coach / graduated 非表示。
- The ai-chat Module shall Unit テストで以下を網羅する: `LlmRepositoryInterface` の Fake 実装による差替 / `AiChatPromptBuilderService` のプレースホルダ置換(default_enrollment 解決ロジック含む) / `GeminiLlmRepository` の `Http::fake()` による正常系 + エラー系 + リトライ系。

### NFR-ai-chat-005: 受講生の Gemini 無料枠保護

- The ai-chat Module shall Rate Limit を `config('ai-chat.daily_message_limit', 50)` で既定 50 通 / 日 / ユーザーとし、Gemini 2.5 Flash の無料枠を超過しない設計とする。
- The ai-chat Module shall **受講生個人が自身の Google AI Studio API キーを取得する前提**で `.env` の `GEMINI_API_KEY` を設定するものとし、共有 API キー利用は採点環境のみ許容する。API キー取得手順は `.env.example` のコメントに記載する。

### NFR-ai-chat-006: 国際化

- The ai-chat Module shall UI 文言を日本語のみで実装する。
- The ai-chat Module shall システムプロンプトに「日本語で回答してください」を明示し、Gemini に日本語応答を強制する。

### NFR-ai-chat-007: アクセシビリティ

- The ai-chat Module shall フローティングウィジェットを WCAG 2.1 AA 相当で実装する:
  - FAB は `<button aria-label="AI 相談を開く">` で命名
  - モーダルは `role="dialog" aria-modal="true" aria-labelledby="ai-chat-widget-title"`
  - メッセージリストは `aria-live="polite"` で AI 応答追加を読み上げ可能にする
  - `Esc` キーでモーダルを閉じる
  - キーバインド: `⌘+Enter` / `Ctrl+Enter` で送信、単独 `Enter` は改行(他チャット UI と統一)

## 用語集

| 用語 | 定義 |
|---|---|
| AI 相談 | 本 Feature が提供する LLM チャット機能。受講生のみが利用 |
| FAB(Floating Action Button) | 全画面右下に常駐する丸い AI 相談起動ボタン |
| フローティングウィジェット | FAB クリックで右下から立ち上がるセミモーダルの AI 相談 UI |
| LLM Repository | LLM API(既定 Gemini)への呼出を抽象化する Repository。`LlmRepositoryInterface` 経由で実装差替可能 |
| システムプロンプト | LLM への入力の冒頭に付与する、AI の役割・制約・コンテキストを規定する文字列。`AiChatPromptBuilderService` が動的生成 |
| Section 紐付け | 会話に `section_id` を持たせ、システムプロンプトに該当 Section の Markdown 本文を埋め込むコンテキスト注入機構 |
| default_enrollment 経由の資格コンテキスト | 受講生の `default_enrollment_id` (サイドバー下のスイッチャーで設定) を `AiChatPromptBuilderService` が動的解決し、システムプロンプトに資格名を自動付与する仕組み |
| 全般相談モード | `default_enrollment_id` 未設定 + section_id 無しの会話 |
