# ai-chat 実装タスク

> 模範解答プロジェクト/ への実装手順。Step 1〜8 で同期版 AI 相談を完成 → 動作確認 → Step 10 で会話タイトル LLM 自動生成 (任意拡張) → Step 11 が最終動作確認 + PR 準備。各 Step は前段の完了が前提。コマンドはすべて `sail` プレフィックス必須(`tech.md`「コマンド慣習」参照)。
>
> **2026-05 改訂**: 業界標準 + 必要最低限の設計に統一(requirements.md 冒頭の改訂注記参照):
> - **SSE ストリーミング (旧 Step 9) は完全削除** — 同期版のみで完結。Step 9 セクションは削除済
> - **`AiChatRateLimiterService` 削除** — Laravel 標準 throttle のみ
> - **「資格相談」モード UI 削除** — `users.default_enrollment_id` 経由で自動解決
> - **`current_term` / `max_context_tokens` 削除** — プロンプト・履歴処理をシンプル化

## Step 1: Migration + Model + Enum

- [x] `database/migrations/{date}_create_ai_chat_conversations_table.php` 作成（REQ-ai-chat-010）
  - ULID 主キー / `user_id` cascade FK / `enrollment_id` set null FK / `section_id` set null FK / `title` string(100) / `last_message_at` nullable timestamp / `created_at` / `updated_at`
  - 複合 INDEX: `(user_id, last_message_at)` + `(user_id, section_id)`
- [x] `database/migrations/{date}_create_ai_chat_messages_table.php` 作成（REQ-ai-chat-011）
  - ULID 主キー / `ai_chat_conversation_id` cascade FK / `role` string / `content` text / `status` string / `model` nullable string / `input_tokens` `output_tokens` `response_time_ms` unsignedInt nullable / `error_detail` nullable text / `created_at` / `updated_at`
  - 複合 INDEX: `(ai_chat_conversation_id, created_at)`
- [x] `app/Models/AiChatConversation.php` 作成（REQ-ai-chat-010）
  - `use HasFactory, HasUlids`
  - `fillable`: `['user_id', 'enrollment_id', 'section_id', 'title', 'last_message_at']`
  - `casts`: `['last_message_at' => 'datetime']`
  - リレーション: `user()` `enrollment()` `section()` `messages()` `latestMessage()`（hasOne の最新 created_at）
- [x] `app/Models/AiChatMessage.php` 作成（REQ-ai-chat-011）
  - `use HasFactory, HasUlids`（会話本体の物理削除に cascade で連動削除）
  - `fillable`: `['ai_chat_conversation_id', 'role', 'content', 'status', 'model', 'input_tokens', 'output_tokens', 'response_time_ms', 'error_detail']`
  - `casts`: `['role' => AiChatMessageRole::class, 'status' => AiChatMessageStatus::class, 'input_tokens' => 'integer', 'output_tokens' => 'integer', 'response_time_ms' => 'integer']`
  - リレーション: `conversation()`
- [x] `app/Enums/AiChatMessageRole.php` 作成（REQ-ai-chat-012）
  - `User` / `Assistant`、`label()` で「あなた」「AI」
- [x] `app/Enums/AiChatMessageStatus.php` 作成（REQ-ai-chat-012）
  - `Pending` / `Streaming` / `Completed` / `Error`、`label()` 含む
- [x] `database/factories/AiChatConversationFactory.php` / `AiChatMessageFactory.php` 作成
  - `state()` で `withSection()` / `withEnrollment()` / `widget()` / `general()` を提供
  - Message には `userMessage()` / `assistantCompleted()` / `assistantError()` / `assistantPending()` state
- [x] User model に `aiChatConversations()` hasMany リレーションを追加（[[user-management]] と連携、既存 User Model に行追加のみ）
- [x] `sail artisan migrate:fresh --seed` 成功確認
- [x] `sail artisan tinker` で `AiChatConversation::factory()->withSection()->create()` と `AiChatMessage::factory()->userMessage()->create()` がエラーなく動作確認

## Step 2: Exception + Config + Logging channel

- [x] `app/Exceptions/AiChat/AiChatConversationCreationDeniedException.php` 作成（REQ-ai-chat-022、`AccessDeniedHttpException` 継承、403）
- [x] `app/Exceptions/AiChat/AiChatLlmFailedException.php` 作成（REQ-ai-chat-040 / 041、`HttpException(502)` 継承）
- [x] `app/Exceptions/AiChat/AiChatRateLimitExceededException.php` 作成（REQ-ai-chat-060、`HttpException(429)` 継承、メッセージに limit 値を埋め込み可能）
- [x] `app/Exceptions/AiChat/AiChatMessageNotRetryableException.php` 作成（REQ-ai-chat-043、`UnprocessableEntityHttpException` 継承、422）
- [x] `app/Exceptions/AiChat/AiChatNotConfiguredException.php` 作成（REQ-ai-chat-082、`HttpException(500)` 継承）
- [x] `app/Exceptions/AiChat/AiChatLlmApiException.php` 作成（Repository 内部例外、内部処理用、外部へは `AiChatLlmFailedException` に変換して throw）
- [x] `config/ai-chat.php` 作成（REQ-ai-chat-050 / 080 / 081 / 090 / 100、design.md「Config」セクション参照）
  - `enabled` / `driver` / `streaming_enabled` / `gemini.*` / `daily_message_limit` / `history_window` / `max_context_tokens` / `title_generation_enabled` / `title_generation_prompt` / `system_prompt_template`
- [x] `.env.example` に追記（API キー取得手順コメント込み、行頭 `#` でコメント記述）
- [x] `config/logging.php` の `channels` 配列に `ai-chat` channel を追加（REQ-ai-chat-091、`daily` driver、`storage/logs/ai-chat.log`、14 日保持）
- [x] `sail bin pint --dirty` で整形

## Step 3: LLM Repository 抽象 + Gemini 同期実装

- [x] `app/Services/LlmChatResponse.php` 作成（REQ-ai-chat-080、既存 DTO 配置 `CategoryHeatmapCell` / `StatsSummary` 等と同じく `App\Services` 直下に `final readonly` で配置）
- [x] `app/Repositories/Contracts/LlmRepositoryInterface.php` 作成（REQ-ai-chat-080）
- [x] `app/Repositories/GeminiLlmRepository.php` 作成（REQ-ai-chat-081）
- [x] `app/Providers/AppServiceProvider.php` の `register()` で binding 追加（REQ-ai-chat-080 / 082）
- [x] `tests/Unit/Repositories/GeminiLlmRepositoryTest.php` 作成（NFR-ai-chat-004）
- [x] `sail artisan test --filter=GeminiLlmRepositoryTest` 全 pass
- [x] `sail bin pint --dirty`

## Step 4: Service 層

- [x] `app/Services/AiChatPromptBuilderService.php` 作成（REQ-ai-chat-050 / 051）
- [x] `app/Services/AiChatRateLimiterService.php` 作成（REQ-ai-chat-061）
- [x] `tests/Unit/Services/AiChatPromptBuilderServiceTest.php` 作成（NFR-ai-chat-004）
- [x] `tests/Unit/Services/AiChatRateLimiterServiceTest.php` 作成
- [x] `sail artisan test --filter='AiChatPromptBuilderServiceTest|AiChatRateLimiterServiceTest'` 全 pass
- [x] `sail bin pint --dirty`

## Step 5: Action 層

- [x] `app/UseCases/AiChat/IndexAction.php` 作成（REQ-ai-chat-030）
  - Eager Load: `enrollment.certification` / `section` / `latestMessage`
  - `orderByDesc('last_message_at')` + `paginate(20)->withQueryString()`
- [x] `app/UseCases/AiChat/ShowAction.php` 作成
  - Eager Load: `enrollment.certification` / `section.chapter.part` / `messages` (orderBy created_at)
- [x] `app/UseCases/AiChat/StoreAction.php` 作成（REQ-ai-chat-013 / 022 / 031 / 034）
  - constructor で `\App\UseCases\AiChatMessage\StoreAction $messageStore` を DI
  - `__invoke(User $user, ?string $enrollmentId, ?string $sectionId, ?string $initialMessage, bool $reuseExisting = false): AiChatConversation`
  - `DB::transaction()` 内:
    1. `$sectionId` 指定時、Section を `with('chapter.part')` で Eager Load → `$section->chapter->part->certification_id` を取得 → 受講生の Enrollment を `where('user_id', $user->id)->where('certification_id', $certificationId)->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])->first()` で検索 → 未登録時 `AiChatConversationCreationDeniedException` throw、見つかった Enrollment の id を `$enrollmentId` に補完
    2. `$reuseExisting=true && $sectionId !== null`: 既存会話検索 → あれば返却、なければ新規作成
    3. 新規作成時: `title` を `$initialMessage` 先頭 30 文字（マルチバイト Str::limit）、未指定なら「新規相談」、`last_message_at = now()`
    4. `$initialMessage` 指定時: `($this->messageStore)($conversation, $initialMessage)` を呼ぶ
- [x] `app/UseCases/AiChat/UpdateAction.php` 作成（REQ-ai-chat-032）
  - `__invoke(AiChatConversation $conversation, array $validated): AiChatConversation`
- [x] `app/UseCases/AiChat/DestroyAction.php` 作成（REQ-ai-chat-033）
  - `$conversation->delete()`（物理削除、配下の `ai_chat_messages` は FK cascadeOnDelete で連動削除）
- [x] `app/UseCases/AiChatMessage/StoreAction.php` 作成（REQ-ai-chat-040 / 061 / 091）
  - constructor で `LlmRepositoryInterface $llm`、`AiChatPromptBuilderService $promptBuilder`、`AiChatRateLimiterService $rateLimiter` を DI
  - `DB::transaction()` 内:
    1. user message INSERT (role=user, status=completed)
    2. assistant message INSERT (role=assistant, status=pending, content='')
    3. systemPrompt = promptBuilder->build()
    4. history = promptBuilder->buildHistory()
    5. try: `$response = $this->llm->chat($systemPrompt, $history);`
       - 成功: assistant message UPDATE (content / model / input_tokens / output_tokens / response_time_ms / status=completed)
    6. catch LlmApiException:
       - assistant message UPDATE (error_detail / status=error)
       - `$this->rateLimiter->decrement($user)`
       - `Log::channel('ai-chat')->error(...)`
       - `throw new AiChatLlmFailedException()`
    7. conversation UPDATE (`last_message_at = now()`)
    8. return [user_message, assistant_message]
- [x] `app/UseCases/AiChatMessage/RetryAction.php` 作成（REQ-ai-chat-043）
  - `__invoke(AiChatMessage $message): AiChatMessage`
  - `$message->status !== AiChatMessageStatus::Error` なら `AiChatMessageNotRetryableException` throw
  - assistant message を status=pending に戻し、`$this->store(...)` を呼ぶ（同じ user message を前提として LLM 再呼出）
- [x] `app/UseCases/AiChatMessage/StreamAction.php` 作成（シグネチャのみ、実装は Step 9）
  - `__invoke(AiChatConversation $conversation, string $content): \Generator`
  - Step 9 まで `throw new \BadMethodCallException('not implemented')` で仮置き
- [x] `tests/Feature/UseCases/AiChat/StoreActionTest.php` 作成
  - 受講生が登録資格内の Section で会話作成成功
  - 受講生が未登録資格の Section を指定 → `AiChatConversationCreationDeniedException` throw
  - `reuseExisting=true` で既存会話再開（新規作成されない）
  - Section 紐付け時に `enrollment_id` 自動補完
- [x] `tests/Feature/UseCases/AiChatMessage/StoreActionTest.php` 作成
  - 正常系: `LlmRepositoryInterface` の Fake 実装で `LlmChatResponse` を返す → user + assistant message が DB に保存、conversation の `last_message_at` 更新
  - Gemini エラー時: Fake が `LlmApiException` throw → assistant message が `error` 状態 + `error_detail` 保存、Rate Limit カウンタ巻き戻し、`AiChatLlmFailedException` 再 throw
- [x] `sail artisan test --filter='Ai(Chat|ChatMessage)/.*Action'` 全 pass
- [x] `sail bin pint --dirty`

## Step 6: Controller + FormRequest + Policy + Route + Rate Limit 定義

- [x] `app/Policies/AiChatConversationPolicy.php` 作成（REQ-ai-chat-020 / 021 / 023）
  - `viewAny` / `view` / `create` / `update` / `delete` を実装、admin/coach バイパスなし
- [x] `app/Providers/AuthServiceProvider.php` の `$policies` に `AiChatConversation::class => AiChatConversationPolicy::class` を登録（自動検出で OK なら省略）
- [x] `app/Http/Requests/AiChat/StoreRequest.php` 作成（REQ-ai-chat-031）
  - `authorize()`: `$this->user()->can('create', AiChatConversation::class)`
  - `rules()`: `enrollment_id` / `section_id` / `message` / `source`
- [x] `app/Http/Requests/AiChat/UpdateRequest.php` 作成（REQ-ai-chat-032）
- [x] `app/Http/Requests/AiChatMessage/StoreRequest.php` 作成（REQ-ai-chat-040）
- [x] `app/Http/Requests/AiChatMessage/StreamRequest.php` 作成（REQ-ai-chat-041、`content` 1-2000 文字、authorize は conversation Policy::view）
- [x] `app/Http/Controllers/AiChatConversationController.php` 作成（REQ-ai-chat-030 / 031 / 032 / 033）
  - `index(IndexAction $action)` / `show(AiChatConversation $conversation, ShowAction $action)`（`$this->authorize('view', $conversation)`）/ `store(StoreRequest $request, StoreAction $action)` / `update(...)` / `destroy(...)`
  - Controller method 名 = Action クラス名規約に厳格準拠（`backend-usecases.md`）
- [x] `app/Http/Controllers/AiChatMessageController.php` 作成（REQ-ai-chat-040 / 043、stream は Step 9）
  - `store(AiChatConversation $conversation, StoreRequest $request, StoreAction $action)`
  - `retry(AiChatMessage $message, RetryAction $action)`
  - `stream` メソッドは Step 9 で実装、ここでは仮置き or 未定義のままで OK
- [x] `routes/web.php` に ai-chat ルートグループ追加（REQ-ai-chat-020 / 060 / 090）
  - `if (config('ai-chat.enabled'))` ガード
  - `Route::middleware(['auth', 'role:student', EnsureActiveLearning::class])->prefix('ai-chat')->name('ai-chat.')->group(...)`（v3 で `EnsureActiveLearning` 追加、graduated もブロック）
  - メッセージ送信系のみ `throttle:ai-chat` Middleware 適用
- [x] `app/Providers/RouteServiceProvider.php` の `configureRateLimiting()` に `RateLimiter::for('ai-chat', ...)` 追加（REQ-ai-chat-060）
  - `Limit::perDay(config('ai-chat.daily_message_limit', 50))->by($request->user()->id)`
  - `response()` で `AiChatRateLimitExceededException` throw
- [x] `tests/Feature/Http/AiChat/IndexTest.php` 作成（REQ-ai-chat-020 / 021 / 030 / NFR-ai-chat-004）
  - student がアクセス → 200 + 自分の会話のみ表示
  - admin / coach → 403
  - 未認証 → /login へリダイレクト
- [x] `tests/Feature/Http/AiChat/ShowTest.php` 作成
  - 自分の会話 → 200
  - 他受講生の会話 ID 直叩き → 403
- [x] `tests/Feature/Http/AiChat/StoreTest.php` 作成
  - 正常系: student が POST → 303 redirect、DB に会話 + 初回メッセージ保存
  - admin / coach → 403
  - 未登録資格 Section 指定 → 403
- [x] `tests/Feature/Http/AiChat/UpdateTest.php` / `DestroyTest.php` 作成
- [x] `tests/Feature/Http/AiChatMessage/StoreTest.php` 作成（REQ-ai-chat-040 / 060）
  - 正常系: `LlmRepositoryInterface` Fake で成功レスポンス → 200 + JSON
  - Rate Limit 超過: 51 回連続 POST → 51 回目で 429 + `AiChatRateLimitExceededException` メッセージ
  - Gemini エラー時: Fake が例外 → 502 + assistant message が `error` 状態、Rate Limit カウンタ巻き戻し
- [x] `tests/Feature/Http/AiChatMessage/RetryTest.php` 作成
  - error 状態 message → 200
  - completed 状態 message → 422 + `AiChatMessageNotRetryableException`
- [x] `tests/Unit/Policies/AiChatConversationPolicyTest.php` 作成
  - 各 role × 各 method の真偽値テーブルを assert
- [x] `sail artisan test --filter='Ai(Chat|ChatMessage|ConversationPolicy)'` 全 pass
- [x] `sail bin pint --dirty`
- [x] 動作確認（同期版、Blade なしで API のみ）: `sail artisan tinker` でテストデータ作成 → `curl` で `/ai-chat` `/ai-chat/conversations` `/ai-chat/conversations/{id}/messages` の各エンドポイントが認証付きで動作

## Step 7: Blade ビュー + 共通 partial

- [x] サイドバー `resources/views/layouts/_partials/sidebar-student.blade.php` に「AI 相談」エントリ追加（既存 Wave 0b 雛形に項目追加、REQ-ai-chat-090）
  - `<x-nav.item route="ai-chat.index" icon="sparkles" label="AI 相談" />`
  - `Route::has()` ガードが効くため、`config('ai-chat.enabled') === false` でルート登録されなければ自動非表示
- [x] `resources/views/ai-chat/index.blade.php` 作成（REQ-ai-chat-030）
  - `@extends('layouts.app')`
  - `<x-breadcrumb>` でパンくず
  - 「新規相談」ボタン（受講中資格セレクトを開くモーダル）
  - 会話一覧テーブル（`<x-table>`、コンテキストバッジ表示）
  - `<x-paginator :paginator="$conversations" />`
  - 0 件時 `<x-empty-state>`
- [x] `resources/views/ai-chat/show.blade.php` 作成（REQ-ai-chat-031 / 032 / 033）
  - `@extends('layouts.app')`
  - `<x-breadcrumb>`
  - 会話タイトル + 編集ボタン + 削除ボタン
  - `@include('ai-chat._partials.message-list')` でメッセージリスト
  - `@include('ai-chat._partials.input-form')` で入力フォーム
- [x] `resources/views/ai-chat/_partials/message-list.blade.php` 作成
  - `<ul role="log" aria-live="polite" aria-label="AI 相談メッセージ">` 構造
  - 各メッセージを `@include('ai-chat._partials.message-bubble')` で描画
- [x] `resources/views/ai-chat/_partials/message-bubble.blade.php` 作成（REQ-ai-chat-012）
  - role=user: 右寄せ青系バブル / role=assistant: 左寄せグレー系バブル
  - status=error: 赤背景 + アイコン + 「再送信」ボタン
  - status=streaming/pending: スピナー表示
  - `{{ $message->content }}` で自動エスケープ（NFR-ai-chat-003）
- [x] `resources/views/ai-chat/_partials/input-form.blade.php` 作成
  - `<x-form.textarea name="content" :maxlength="2000" />`
  - 送信ボタン（クリックで JS が制御、`data-action="send"`）
  - フォーム自体は `<form>` で囲み、CSRF token と conversation_id を hidden で含む
- [x] `resources/views/components/ai-chat/floating-widget.blade.php` 作成（REQ-ai-chat-070 / 071 / 072 / 074 / NFR-ai-chat-007）
  - FAB: `<button id="ai-chat-fab" aria-label="AI 相談を開く" data-section-id="{{ $sectionId }}" class="fixed bottom-4 right-4 ...">🤖</button>`
  - モーダル（初期 hidden）: `<div id="ai-chat-widget-modal" role="dialog" aria-modal="true" aria-labelledby="ai-chat-widget-title" class="hidden fixed ...">`
    - ヘッダ: タイトル + コンテキストバッジ + フル画面ボタン + 閉じるボタン
    - 本文: メッセージリスト（フル画面と同じ partial を `@include`）
    - フッタ: 入力フォーム（同じ partial）
- [x] `resources/views/layouts/app.blade.php` の末尾に Widget 条件レンダリング追加（REQ-ai-chat-070 / 090）
  ```blade
  @if(config('ai-chat.enabled') && auth()->check() && auth()->user()->role === \App\Enums\UserRole::Student)
      <x-ai-chat.floating-widget :section-id="$pageMeta['section_id'] ?? null" />
  @endif
  ```
- [x] [[learning]] Feature の Section 表示 Controller（learning Feature spec 側で実装、本 spec のスコープ外）に `view()->share('pageMeta', ['section_id' => $section->id])` を追加することを **本 spec の関連 Feature コメントとして明示**（learning Feature の tasks.md と整合）
- [x] `sail npm run dev` で Blade レンダリング確認、`http://localhost/ai-chat` で空一覧画面 → 「新規相談」モーダル → 会話作成 → 詳細画面遷移までを目視確認
- [x] `sail bin pint --dirty`

## Step 8: JavaScript（同期版）

- [x] `resources/js/utils/fetch-json.js` の `postJson` ヘルパ確認（既存、Wave 0b 提供）
- [x] `resources/js/ai-chat/chat-client.js` 作成（同期版のみ、SSE 接続は Step 9）（REQ-ai-chat-040）
  - class `AiChatClient { sendSync(content): Promise<{user_message, assistant_message}> }`
  - エラー時に `onError({type: 'rate-limit' | 'http' | 'llm', ...})` callback 呼出
- [x] `resources/js/ai-chat/message-renderer.js` 作成
  - `renderUserMessage(content): HTMLElement` / `renderAssistantMessage({content, status, model}): HTMLElement`
  - error 状態時の再送信ボタン handler 設定
- [x] `resources/js/ai-chat/full-screen.js` 作成
  - 入力フォーム送信 → `AiChatClient.sendSync()` → 成功時にメッセージリストへ追記、失敗時にエラー表示
  - タイトル編集モーダル開閉
  - 会話削除確認モーダル
- [x] `resources/js/ai-chat/floating-widget.js` 作成（REQ-ai-chat-071 / 073 / 074 / NFR-ai-chat-007）
  - FAB クリックで `#ai-chat-widget-modal` 表示、`Esc` で閉じる
  - sessionStorage で `ai-chat:current-conversation-id` を保持
  - 教材画面 (`data-section-id` あり) で開く → `POST /ai-chat/conversations?source=widget&section_id=...` で既存再開 or 新規作成
  - 教材以外で開く → 最新会話を取得（or 新規 in-memory 作成）
  - フル画面遷移ボタン → `window.location.href = '/ai-chat/conversations/{id}'`
  - フォーカストラップ実装（Tab で内部のみ）、aria 属性切替
- [x] `resources/js/app.js` で `floating-widget.js` / `full-screen.js` を import + DOM ready で初期化
- [x] `sail npm run dev` で Vite ビルド成功確認
- [x] 動作確認（B-1 同期版完成判定）:
  - student ユーザーでログイン
  - サイドバー「AI 相談」→ `/ai-chat` 一覧画面表示
  - 「新規相談」モーダル → 受講中資格選択 → メッセージ入力 → 送信 → 詳細画面に user + assistant メッセージが同期表示
  - フル画面でメッセージ追加 → 再描画される
  - FAB クリック → ウィジェット展開 → 教材画面では Section コンテキスト付きで会話再開、教材以外では全般会話を再開
  - ウィジェットの「フル画面で開く」ボタンで `/ai-chat/conversations/{id}` 遷移
  - Gemini API キーをわざと空にして 500 エラー、戻して動作復活
  - Rate Limit 超過: `AI_CHAT_DAILY_MESSAGE_LIMIT=2` に設定 → 3 回目送信で 429 表示
- [x] `sail bin pint --dirty`

> **🔵 B-1 中間チェックポイント**: ここまでで同期版 ai-chat が完成。Step 9 で SSE 化に進む。Step 9 で詰まった場合はここに戻り、`AI_CHAT_STREAMING_ENABLED=false` で同期モードに切り替えてリリース可能。

## Step 10: 会話タイトル LLM 自動生成（B-2 任意拡張）

> 受講生体験向上のための任意拡張 Step。`AI_CHAT_TITLE_GENERATION_ENABLED=false` で無効化可能。Step 2 で config / env 雛形は既に設置済 (REQ-ai-chat-100)、本 Step では実装と統合のみ実施。Gemini クォータ逼迫環境では env を false にしてスキップしてよい。

- [x] `app/UseCases/AiChat/GenerateTitleAction.php` 作成（REQ-ai-chat-100）
  - constructor で `LlmRepositoryInterface $llm` / `AiChatRateLimiterService $rateLimiter` を DI
  - `__invoke(AiChatConversation $conversation): ?string`
    1. 最初の user message (`role=user`, `created_at` 昇順 first) を取得
    2. 最初の `status=completed` の assistant message を取得
    3. 両方揃わなければ `null` 返却（呼出側で fallback 維持）
    4. `config('ai-chat.title_generation_prompt')` を system prompt、`[{role: 'user', content: "ユーザー質問: {first_user}\n\nAI応答: {first_assistant}"}]` を messages として `$this->llm->chat()` 呼出
    5. 成功時: `trim($response->content)` → 100 文字 `mb_substr` で安全化 → 返却
    6. 失敗時: `$this->rateLimiter->decrement($conversation->user)` + `Log::channel('ai-chat')->warning(...)` + `null` 返却
- [x] `app/UseCases/AiChatMessage/StoreAction.php`（同期版）に統合（REQ-ai-chat-100）
  - constructor に `GenerateTitleAction $titleGenerator` を追加 DI
  - assistant message が `status=completed` で UPDATE された直後（DB::transaction の中、conversation `last_message_at` UPDATE の前後どちらでも可）に `maybeGenerateTitle($conversation)` を呼ぶ
  - `private function maybeGenerateTitle(AiChatConversation $conversation): void` 実装:
    1. `if (!config('ai-chat.title_generation_enabled')) return;`
    2. 当該 conversation の assistant completed メッセージ件数を count、`!== 1` ならスキップ
    3. `$newTitle = ($this->titleGenerator)($conversation);`
    4. `$newTitle !== null` なら `$conversation->update(['title' => $newTitle])`
  - 返却 JSON に `conversation` を含めるよう Controller / Action 仕様を調整（クライアント側でタイトル即時更新するため）
- [x] `app/UseCases/AiChatMessage/StreamAction.php`（SSE 版）に統合（REQ-ai-chat-100）
  - constructor に `GenerateTitleAction $titleGenerator` を追加 DI
  - assistant message が `status=completed` で UPDATE された直後、`event: done` を yield する **前** に同じく `maybeGenerateTitle($conversation)` を実行
  - タイトル更新成功時は **`event: done` の前に** `event: title-updated\ndata: {"title": "..."}\n\n` を yield
- [x] `resources/js/ai-chat/chat-client.js` を更新（REQ-ai-chat-100）
  - `onTitleUpdated` callback を constructor options に追加
  - SSE parse loop で `event: title-updated` を受信したら `onTitleUpdated({title})` 呼出
  - 同期送信 (`sendSync`) は応答 JSON の `conversation.title` を `onTitleUpdated` callback に渡す
- [x] `resources/js/ai-chat/full-screen.js` で `onTitleUpdated` を実装（タイトル DOM 要素を新タイトルで置換、`document.title` も更新）
- [x] `resources/js/ai-chat/floating-widget.js` でも同様、ウィジェットヘッダのコンテキストバッジ脇のタイトル表示を更新
- [x] `tests/Feature/UseCases/AiChat/GenerateTitleActionTest.php` 作成（NFR-ai-chat-004）
  - 正常系: `LlmRepositoryInterface` Fake が短い title 返却 → 戻り値 string、長さ 100 文字以内、trim 済
  - Fake が exception throw → null 返却、Rate Limiter decrement が呼ばれる
  - user message のみで assistant がまだ無い → null 返却
- [x] `tests/Feature/UseCases/AiChatMessage/StoreActionTitleTest.php` 作成
  - 初回メッセージペア完了 → conversation.title が LLM 生成タイトルに上書き
  - 2 回目以降のメッセージ送信 → タイトル変更されない
  - `AI_CHAT_TITLE_GENERATION_ENABLED=false` → タイトル変更されない（fallback 30 文字維持）
  - LlmRepositoryInterface Fake がタイトル生成のみ失敗（メッセージ送信本体は成功）→ メッセージ送信は 200 で完了、conversation.title は fallback 維持
- [x] `tests/Feature/Http/AiChatMessage/StreamTitleTest.php` 作成（SSE 版）
  - SSE レスポンスに `event: title-updated\ndata: {"title": "..."}\n\n` が `event: done` の前に含まれる
  - title 生成失敗時は title-updated event が含まれず、done event は通常通り出る
- [x] `sail artisan test --filter='GenerateTitle|StoreActionTitle|StreamTitle'` 全 pass
- [x] `sail bin pint --dirty`
- [x] 動作確認（B-2 完成判定）:
  - 新規会話 + 初回メッセージ送信 → 応答直後にタイトルが「学習相談」等の意味のある短文に変わる（同期版・SSE 版両方）
  - 同会話で 2 回目メッセージ送信 → タイトルは変更されない
  - `AI_CHAT_TITLE_GENERATION_ENABLED=false` 設定 → タイトル自動生成スキップ、先頭 30 文字 fallback のみ
  - タイトル生成中に Gemini API を意図的に落とす（API キーを壊す）→ メッセージ送信本体は成功表示、タイトルは fallback 維持、エラー表示は出ない（受講生体験を阻害しない）

## Step 11: 最終動作確認 + PR 準備

- [x] 全テスト pass: `sail artisan test --filter='Ai(Chat|ChatMessage)'`
- [x] `sail bin pint` で全体整形
- [x] スクショ撮影:
  - AI 相談一覧画面（複数会話のコンテキストバッジ表示、LLM 生成タイトル混在）
  - 会話詳細画面（user/assistant バブル）
  - フローティングウィジェット（教材閲覧中の展開状態）
  - エラー状態のメッセージ + 再送信ボタン
  - Rate Limit 超過時のエラー表示
  - タイトル自動生成 before/after（fallback の 30 文字カット → LLM 生成短文）
- [x] 動画撮影（動的機能、NFR-ai-chat-004 で必須）:
  - SSE ストリーミングで AI 応答が逐次表示される様子（数十秒）
  - FAB クリック → ウィジェット展開 → 教材 Section コンテキストでメッセージ送信
  - ストリーミング中にブラウザリロード → 中断耐性の確認
  - 初回メッセージ送信後にタイトルが自動更新される様子（数百 ms 後に DOM が書き換わる、SSE `title-updated` イベント）
- [x] PR 説明（`tech.md`「PR 記述 7 セクション必須」準拠）:
  1. **関連チケット**: 要件シート側の該当チケット（提供時に紐付け）
  2. **調査内容**: `docs/specs/ai-chat/*.md`、`backend-repositories.md`、`frontend-blade.md`、COACHTECH LMS の `AiChatbot*` 一式、iField LMS の `semantic-search/` spec
  3. **原因分析 / 設計判断**: Why の言語化（design.md「主要な設計判断」セクションを PR 内で再掲、特に Gemini Repository 抽象 / SSE 方式選定 / Section コンテキスト注入の経緯）
  4. **実装内容**: 振る舞い単位で箇条書き（「受講生が AI と相談できるようになった」「教材から FAB で文脈付き相談できる」「ストリーミングで逐次応答」等）
  5. **自動テスト**: 上記テスト結果サマリ
  6. **動作確認**: スクショ + 動画
  7. **レビュー観点 / 自己評価**: 不安な箇所（SSE の chunk parse 周辺 / PHP-FPM worker 制約 / Gemini API クォータ管理 / etc.）

## 完成判定（Definition of Done）

- [x] 全 REQ-ai-chat-* / NFR-ai-chat-* 要件を実装が満たす
- [x] 全自動テスト pass
- [x] 同期版 + SSE 版の両モードで動作確認済
- [x] Pint 整形済
- [x] `frontend-ui-foundation.md` のアクセシビリティ要件（aria-label / role / focus trap / Esc）を満たす
- [x] `backend-repositories.md` / `backend-usecases.md` / `backend-services.md` / `backend-policies.md` / `backend-tests.md` / `backend-exceptions.md` / `frontend-blade.md` / `frontend-javascript.md` / `frontend-tailwind.md` の各規約に準拠
- [x] `config('ai-chat.enabled')=false` で全機能が無効化され、サイドバー / FAB が消える
- [x] `config('ai-chat.title_generation_enabled')=false` でタイトル自動生成のみが無効化される（メッセージ送信本体は正常動作）
