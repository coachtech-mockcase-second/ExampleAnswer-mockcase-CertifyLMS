# ai-chat 実装タスク

> 模範解答プロジェクト/ への実装手順。`B-1` 採用により Step 1〜8 で **同期版を完成 → 動作確認 → Step 9 で SSE 化** の段階分け構成とする。各 Step は前段の完了が前提。コマンドはすべて `sail` プレフィックス必須（`tech.md`「コマンド慣習」参照）。

## Step 1: Migration + Model + Enum

- [ ] `database/migrations/{date}_create_ai_chat_conversations_table.php` 作成（REQ-ai-chat-010）
  - ULID 主キー / `user_id` cascade FK / `enrollment_id` set null FK / `section_id` set null FK / `title` string(100) / `last_message_at` nullable timestamp / `created_at` / `updated_at` / `deleted_at`
  - 複合 INDEX: `(user_id, last_message_at)` + `(user_id, section_id)`
- [ ] `database/migrations/{date}_create_ai_chat_messages_table.php` 作成（REQ-ai-chat-011）
  - ULID 主キー / `ai_chat_conversation_id` cascade FK / `role` string / `content` text / `status` string / `model` nullable string / `input_tokens` `output_tokens` `response_time_ms` unsignedInt nullable / `error_detail` nullable text / `created_at` / `updated_at`
  - 複合 INDEX: `(ai_chat_conversation_id, created_at)`
- [ ] `app/Models/AiChatConversation.php` 作成（REQ-ai-chat-010）
  - `use HasFactory, HasUlids, SoftDeletes`
  - `fillable`: `['user_id', 'enrollment_id', 'section_id', 'title', 'last_message_at']`
  - `casts`: `['last_message_at' => 'datetime']`
  - リレーション: `user()` `enrollment()` `section()` `messages()` `latestMessage()`（hasOne の最新 created_at）
- [ ] `app/Models/AiChatMessage.php` 作成（REQ-ai-chat-011）
  - `use HasFactory, HasUlids`（SoftDeletes 不要、cascade で削除）
  - `fillable`: `['ai_chat_conversation_id', 'role', 'content', 'status', 'model', 'input_tokens', 'output_tokens', 'response_time_ms', 'error_detail']`
  - `casts`: `['role' => AiChatMessageRole::class, 'status' => AiChatMessageStatus::class, 'input_tokens' => 'integer', 'output_tokens' => 'integer', 'response_time_ms' => 'integer']`
  - リレーション: `conversation()`
- [ ] `app/Enums/AiChatMessageRole.php` 作成（REQ-ai-chat-012）
  - `User` / `Assistant`、`label()` で「あなた」「AI」
- [ ] `app/Enums/AiChatMessageStatus.php` 作成（REQ-ai-chat-012）
  - `Pending` / `Streaming` / `Completed` / `Error`、`label()` 含む
- [ ] `database/factories/AiChatConversationFactory.php` / `AiChatMessageFactory.php` 作成
  - `state()` で `withSection()` / `withEnrollment()` / `widget()` / `general()` を提供
  - Message には `userMessage()` / `assistantCompleted()` / `assistantError()` / `assistantPending()` state
- [ ] User model に `aiChatConversations()` hasMany リレーションを追加（[[user-management]] と連携、既存 User Model に行追加のみ）
- [ ] `sail artisan migrate:fresh --seed` 成功確認
- [ ] `sail artisan tinker` で `AiChatConversation::factory()->withSection()->create()` と `AiChatMessage::factory()->userMessage()->create()` がエラーなく動作確認

## Step 2: Exception + Config + Logging channel

- [ ] `app/Exceptions/AiChat/AiChatConversationCreationDeniedException.php` 作成（REQ-ai-chat-022、`AccessDeniedHttpException` 継承、403）
- [ ] `app/Exceptions/AiChat/AiChatLlmFailedException.php` 作成（REQ-ai-chat-040 / 041、`HttpException(502)` 継承）
- [ ] `app/Exceptions/AiChat/AiChatRateLimitExceededException.php` 作成（REQ-ai-chat-060、`HttpException(429)` 継承、メッセージに limit 値を埋め込み可能）
- [ ] `app/Exceptions/AiChat/AiChatMessageNotRetryableException.php` 作成（REQ-ai-chat-043、`UnprocessableEntityHttpException` 継承、422）
- [ ] `app/Exceptions/AiChat/AiChatNotConfiguredException.php` 作成（REQ-ai-chat-082、`HttpException(500)` 継承）
- [ ] `app/Exceptions/AiChat/AiChatLlmApiException.php` 作成（Repository 内部例外、内部処理用、外部へは `AiChatLlmFailedException` に変換して throw）
- [ ] `config/ai-chat.php` 作成（REQ-ai-chat-050 / 080 / 081 / 090、design.md「Config」セクション参照）
  - `enabled` / `driver` / `streaming_enabled` / `gemini.*` / `daily_message_limit` / `history_window` / `max_context_tokens` / `system_prompt_template`
- [ ] `.env.example` に追記:
  - `AI_CHAT_ENABLED=true`
  - `AI_CHAT_DRIVER=gemini`
  - `AI_CHAT_STREAMING_ENABLED=true`
  - `AI_CHAT_DAILY_MESSAGE_LIMIT=50`
  - `AI_CHAT_HISTORY_WINDOW=20`
  - `AI_CHAT_MAX_CONTEXT_TOKENS=30000`
  - `GEMINI_API_KEY=`
  - `GEMINI_MODEL=gemini-1.5-flash`
- [ ] `config/logging.php` の `channels` 配列に `ai-chat` channel を追加（REQ-ai-chat-091、`daily` driver、`storage/logs/ai-chat.log`、14 日保持）
- [ ] `sail bin pint --dirty` で整形

## Step 3: LLM Repository 抽象 + Gemini 同期実装

- [ ] `app/ValueObjects/LlmChatResponse.php` 作成（REQ-ai-chat-080）
  - readonly properties: `content` / `model` / `inputTokens` / `outputTokens` / `responseTimeMs`
- [ ] `app/Repositories/Contracts/LlmRepositoryInterface.php` 作成（REQ-ai-chat-080）
  - `chat(string $systemPrompt, array $messages, ?string $model = null): LlmChatResponse`
  - `streamChat(string $systemPrompt, array $messages, ?string $model = null): \Generator` — シグネチャのみ、実装は Step 9
- [ ] `app/Repositories/GeminiLlmRepository.php` 作成（REQ-ai-chat-081）
  - constructor で `endpoint` / `apiKey` / `defaultModel` を受け取り
  - `chat()` 実装: `Http::retry(2, 100)->timeout(30)->post('.../models/{model}:generateContent?key=...')` で Gemini API 呼出 → JSON parse → `LlmChatResponse` 返却
  - エラー時は `AiChatLlmApiException` throw
  - `streamChat()` は Step 9 で実装、ここでは `throw new \BadMethodCallException('not implemented')` で仮置き
  - `formatMessages()` private method: OpenAI 形式 `[{role, content}]` → Gemini `contents: [{role, parts: [{text}]}]` 形式に変換
- [ ] `app/Providers/AppServiceProvider.php` の `register()` で binding 追加（REQ-ai-chat-080 / 082）
  - `$this->app->bind(LlmRepositoryInterface::class, ...)` で `config('ai-chat.driver')` 分岐
  - `config('ai-chat.gemini.api_key')` 空チェック → `AiChatNotConfiguredException`
- [ ] `tests/Unit/Repositories/GeminiLlmRepositoryTest.php` 作成（NFR-ai-chat-004）
  - 正常系: `Http::fake()` で Gemini レスポンスをスタブ → `chat()` の戻り値 `LlmChatResponse` の各フィールドを assert
  - エラー系: `Http::fake(fn () => Http::response(['error' => ...], 500))` → `AiChatLlmApiException` throw を assert
  - リトライ系: `Http::fakeSequence()` で 1 回目失敗 + 2 回目成功 → 最終的に成功する assert
- [ ] `sail artisan test --filter=GeminiLlmRepositoryTest` 全 pass
- [ ] `sail bin pint --dirty`

## Step 4: Service 層

- [ ] `app/Services/AiChatPromptBuilderService.php` 作成（REQ-ai-chat-050 / 051）
  - `build(AiChatConversation $conversation, User $user): string` 実装:
    1. `config('ai-chat.system_prompt_template')` 取得
    2. プレースホルダ `{user_name}` / `{certification_name}` / `{section_context}` / `{current_term}` を置換
    3. `section_id` 紐付け時、`Section.title` + `Section.body`（Markdown）を `{section_context}` に埋め込み
    4. 未紐付け時はそれぞれ「全般」「教材未指定」等のフォールバック文字列
  - `buildHistory(AiChatConversation $conversation): array` 実装:
    - 過去メッセージを `created_at` 昇順で取得（`status != error` フィルタ、`limit history_window`）
    - トークン数概算（1 文字 = 1 トークン）が `max_context_tokens` を超える場合、古い順に切り詰める
    - OpenAI 形式 `[{role: 'user'|'assistant', content: '...'}, ...]` で返却
- [ ] `app/Services/AiChatRateLimiterService.php` 作成（REQ-ai-chat-061）
  - `decrement(User $user): void` — `RateLimiter::for('ai-chat')` のキーを Cache 経由でデクリメント
  - `availableIn(User $user): int` — リセットまでの秒数（429 レスポンスのメッセージ用）
- [ ] `tests/Unit/Services/AiChatPromptBuilderServiceTest.php` 作成（NFR-ai-chat-004）
  - Section 紐付けあり: システムプロンプトに資格名 + Section title + body Markdown が含まれること
  - Section 紐付けなし: 「全般」フォールバックが入ること
  - 履歴切詰め: 50 メッセージ × 1000 文字を入れて `max_context_tokens=10000` に設定 → 切詰め後の文字数が 10000 以下
  - エラーメッセージは履歴から除外されること
- [ ] `tests/Unit/Services/AiChatRateLimiterServiceTest.php` 作成
  - `RateLimiter::hit()` で 1 加算 → `decrement()` で 1 減算 → 残り回数が変わらないこと
- [ ] `sail artisan test --filter='AiChatPromptBuilderServiceTest|AiChatRateLimiterServiceTest'` 全 pass
- [ ] `sail bin pint --dirty`

## Step 5: Action 層

- [ ] `app/UseCases/AiChat/IndexAction.php` 作成（REQ-ai-chat-030）
  - Eager Load: `enrollment.certification` / `section` / `latestMessage`
  - `whereNull('deleted_at')` + `orderByDesc('last_message_at')` + `paginate(20)->withQueryString()`
- [ ] `app/UseCases/AiChat/ShowAction.php` 作成
  - Eager Load: `enrollment.certification` / `section.chapter.part` / `messages` (orderBy created_at)
- [ ] `app/UseCases/AiChat/StoreAction.php` 作成（REQ-ai-chat-013 / 022 / 031 / 034）
  - constructor で `\App\UseCases\AiChatMessage\StoreAction $messageStore` を DI
  - `__invoke(User $user, ?string $enrollmentId, ?string $sectionId, ?string $initialMessage, bool $reuseExisting = false): AiChatConversation`
  - `DB::transaction()` 内:
    1. `$sectionId` 指定時、Section を `with('chapter.part')` で Eager Load → `$section->chapter->part->certification_id` を取得 → 受講生の Enrollment を `where('user_id', $user->id)->where('certification_id', $certificationId)->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])->first()` で検索 → 未登録時 `AiChatConversationCreationDeniedException` throw、見つかった Enrollment の id を `$enrollmentId` に補完
    2. `$reuseExisting=true && $sectionId !== null`: 既存会話検索 → あれば返却、なければ新規作成
    3. 新規作成時: `title` を `$initialMessage` 先頭 30 文字（マルチバイト Str::limit）、未指定なら「新規相談」、`last_message_at = now()`
    4. `$initialMessage` 指定時: `($this->messageStore)($conversation, $initialMessage)` を呼ぶ
- [ ] `app/UseCases/AiChat/UpdateAction.php` 作成（REQ-ai-chat-032）
  - `__invoke(AiChatConversation $conversation, array $validated): AiChatConversation`
- [ ] `app/UseCases/AiChat/DestroyAction.php` 作成（REQ-ai-chat-033）
  - `$conversation->delete()`（SoftDelete）
- [ ] `app/UseCases/AiChatMessage/StoreAction.php` 作成（REQ-ai-chat-040 / 061 / 091）
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
- [ ] `app/UseCases/AiChatMessage/RetryAction.php` 作成（REQ-ai-chat-043）
  - `__invoke(AiChatMessage $message): AiChatMessage`
  - `$message->status !== AiChatMessageStatus::Error` なら `AiChatMessageNotRetryableException` throw
  - assistant message を status=pending に戻し、`$this->store(...)` を呼ぶ（同じ user message を前提として LLM 再呼出）
- [ ] `app/UseCases/AiChatMessage/StreamAction.php` 作成（シグネチャのみ、実装は Step 9）
  - `__invoke(AiChatConversation $conversation, string $content): \Generator`
  - Step 9 まで `throw new \BadMethodCallException('not implemented')` で仮置き
- [ ] `tests/Feature/UseCases/AiChat/StoreActionTest.php` 作成
  - 受講生が登録資格内の Section で会話作成成功
  - 受講生が未登録資格の Section を指定 → `AiChatConversationCreationDeniedException` throw
  - `reuseExisting=true` で既存会話再開（新規作成されない）
  - Section 紐付け時に `enrollment_id` 自動補完
- [ ] `tests/Feature/UseCases/AiChatMessage/StoreActionTest.php` 作成
  - 正常系: `LlmRepositoryInterface` の Fake 実装で `LlmChatResponse` を返す → user + assistant message が DB に保存、conversation の `last_message_at` 更新
  - Gemini エラー時: Fake が `LlmApiException` throw → assistant message が `error` 状態 + `error_detail` 保存、Rate Limit カウンタ巻き戻し、`AiChatLlmFailedException` 再 throw
- [ ] `sail artisan test --filter='Ai(Chat|ChatMessage)/.*Action'` 全 pass
- [ ] `sail bin pint --dirty`

## Step 6: Controller + FormRequest + Policy + Route + Rate Limit 定義

- [ ] `app/Policies/AiChatConversationPolicy.php` 作成（REQ-ai-chat-020 / 021 / 023）
  - `viewAny` / `view` / `create` / `update` / `delete` を実装、admin/coach バイパスなし
- [ ] `app/Providers/AuthServiceProvider.php` の `$policies` に `AiChatConversation::class => AiChatConversationPolicy::class` を登録（自動検出で OK なら省略）
- [ ] `app/Http/Requests/AiChat/StoreRequest.php` 作成（REQ-ai-chat-031）
  - `authorize()`: `$this->user()->can('create', AiChatConversation::class)`
  - `rules()`: `enrollment_id` / `section_id` / `message` / `source`
- [ ] `app/Http/Requests/AiChat/UpdateRequest.php` 作成（REQ-ai-chat-032）
- [ ] `app/Http/Requests/AiChatMessage/StoreRequest.php` 作成（REQ-ai-chat-040）
- [ ] `app/Http/Requests/AiChatMessage/StreamRequest.php` 作成（REQ-ai-chat-041、`content` 1-2000 文字、authorize は conversation Policy::view）
- [ ] `app/Http/Controllers/AiChatConversationController.php` 作成（REQ-ai-chat-030 / 031 / 032 / 033）
  - `index(IndexAction $action)` / `show(AiChatConversation $conversation, ShowAction $action)`（`$this->authorize('view', $conversation)`）/ `store(StoreRequest $request, StoreAction $action)` / `update(...)` / `destroy(...)`
  - Controller method 名 = Action クラス名規約に厳格準拠（`backend-usecases.md`）
- [ ] `app/Http/Controllers/AiChatMessageController.php` 作成（REQ-ai-chat-040 / 043、stream は Step 9）
  - `store(AiChatConversation $conversation, StoreRequest $request, StoreAction $action)`
  - `retry(AiChatMessage $message, RetryAction $action)`
  - `stream` メソッドは Step 9 で実装、ここでは仮置き or 未定義のままで OK
- [ ] `routes/web.php` に ai-chat ルートグループ追加（REQ-ai-chat-020 / 060 / 090）
  - `if (config('ai-chat.enabled'))` ガード
  - `Route::middleware(['auth', 'role:student', EnsureActiveLearning::class])->prefix('ai-chat')->name('ai-chat.')->group(...)`（v3 で `EnsureActiveLearning` 追加、graduated もブロック）
  - メッセージ送信系のみ `throttle:ai-chat` Middleware 適用
- [ ] `app/Providers/RouteServiceProvider.php` の `configureRateLimiting()` に `RateLimiter::for('ai-chat', ...)` 追加（REQ-ai-chat-060）
  - `Limit::perDay(config('ai-chat.daily_message_limit', 50))->by($request->user()->id)`
  - `response()` で `AiChatRateLimitExceededException` throw
- [ ] `tests/Feature/Http/AiChat/IndexTest.php` 作成（REQ-ai-chat-020 / 021 / 030 / NFR-ai-chat-004）
  - student がアクセス → 200 + 自分の会話のみ表示
  - admin / coach → 403
  - 未認証 → /login へリダイレクト
- [ ] `tests/Feature/Http/AiChat/ShowTest.php` 作成
  - 自分の会話 → 200
  - 他受講生の会話 ID 直叩き → 403
- [ ] `tests/Feature/Http/AiChat/StoreTest.php` 作成
  - 正常系: student が POST → 303 redirect、DB に会話 + 初回メッセージ保存
  - admin / coach → 403
  - 未登録資格 Section 指定 → 403
- [ ] `tests/Feature/Http/AiChat/UpdateTest.php` / `DestroyTest.php` 作成
- [ ] `tests/Feature/Http/AiChatMessage/StoreTest.php` 作成（REQ-ai-chat-040 / 060）
  - 正常系: `LlmRepositoryInterface` Fake で成功レスポンス → 200 + JSON
  - Rate Limit 超過: 51 回連続 POST → 51 回目で 429 + `AiChatRateLimitExceededException` メッセージ
  - Gemini エラー時: Fake が例外 → 502 + assistant message が `error` 状態、Rate Limit カウンタ巻き戻し
- [ ] `tests/Feature/Http/AiChatMessage/RetryTest.php` 作成
  - error 状態 message → 200
  - completed 状態 message → 422 + `AiChatMessageNotRetryableException`
- [ ] `tests/Unit/Policies/AiChatConversationPolicyTest.php` 作成
  - 各 role × 各 method の真偽値テーブルを assert
- [ ] `sail artisan test --filter='Ai(Chat|ChatMessage|ConversationPolicy)'` 全 pass
- [ ] `sail bin pint --dirty`
- [ ] 動作確認（同期版、Blade なしで API のみ）: `sail artisan tinker` でテストデータ作成 → `curl` で `/ai-chat` `/ai-chat/conversations` `/ai-chat/conversations/{id}/messages` の各エンドポイントが認証付きで動作

## Step 7: Blade ビュー + 共通 partial

- [ ] サイドバー `resources/views/layouts/_partials/sidebar-student.blade.php` に「AI 相談」エントリ追加（既存 Wave 0b 雛形に項目追加、REQ-ai-chat-090）
  - `<x-nav.item route="ai-chat.index" icon="sparkles" label="AI 相談" />`
  - `Route::has()` ガードが効くため、`config('ai-chat.enabled') === false` でルート登録されなければ自動非表示
- [ ] `resources/views/ai-chat/index.blade.php` 作成（REQ-ai-chat-030）
  - `@extends('layouts.app')`
  - `<x-breadcrumb>` でパンくず
  - 「新規相談」ボタン（受講中資格セレクトを開くモーダル）
  - 会話一覧テーブル（`<x-table>`、コンテキストバッジ表示）
  - `<x-paginator :paginator="$conversations" />`
  - 0 件時 `<x-empty-state>`
- [ ] `resources/views/ai-chat/show.blade.php` 作成（REQ-ai-chat-031 / 032 / 033）
  - `@extends('layouts.app')`
  - `<x-breadcrumb>`
  - 会話タイトル + 編集ボタン + 削除ボタン
  - `@include('ai-chat._partials.message-list')` でメッセージリスト
  - `@include('ai-chat._partials.input-form')` で入力フォーム
- [ ] `resources/views/ai-chat/_partials/message-list.blade.php` 作成
  - `<ul role="log" aria-live="polite" aria-label="AI 相談メッセージ">` 構造
  - 各メッセージを `@include('ai-chat._partials.message-bubble')` で描画
- [ ] `resources/views/ai-chat/_partials/message-bubble.blade.php` 作成（REQ-ai-chat-012）
  - role=user: 右寄せ青系バブル / role=assistant: 左寄せグレー系バブル
  - status=error: 赤背景 + アイコン + 「再送信」ボタン
  - status=streaming/pending: スピナー表示
  - `{{ $message->content }}` で自動エスケープ（NFR-ai-chat-003）
- [ ] `resources/views/ai-chat/_partials/input-form.blade.php` 作成
  - `<x-form.textarea name="content" :maxlength="2000" />`
  - 送信ボタン（クリックで JS が制御、`data-action="send"`）
  - フォーム自体は `<form>` で囲み、CSRF token と conversation_id を hidden で含む
- [ ] `resources/views/components/ai-chat/floating-widget.blade.php` 作成（REQ-ai-chat-070 / 071 / 072 / 074 / NFR-ai-chat-007）
  - FAB: `<button id="ai-chat-fab" aria-label="AI 相談を開く" data-section-id="{{ $sectionId }}" class="fixed bottom-4 right-4 ...">🤖</button>`
  - モーダル（初期 hidden）: `<div id="ai-chat-widget-modal" role="dialog" aria-modal="true" aria-labelledby="ai-chat-widget-title" class="hidden fixed ...">`
    - ヘッダ: タイトル + コンテキストバッジ + フル画面ボタン + 閉じるボタン
    - 本文: メッセージリスト（フル画面と同じ partial を `@include`）
    - フッタ: 入力フォーム（同じ partial）
- [ ] `resources/views/layouts/app.blade.php` の末尾に Widget 条件レンダリング追加（REQ-ai-chat-070 / 090）
  ```blade
  @if(config('ai-chat.enabled') && auth()->check() && auth()->user()->role === \App\Enums\UserRole::Student)
      <x-ai-chat.floating-widget :section-id="$pageMeta['section_id'] ?? null" />
  @endif
  ```
- [ ] [[learning]] Feature の Section 表示 Controller（learning Feature spec 側で実装、本 spec のスコープ外）に `view()->share('pageMeta', ['section_id' => $section->id])` を追加することを **本 spec の関連 Feature コメントとして明示**（learning Feature の tasks.md と整合）
- [ ] `sail npm run dev` で Blade レンダリング確認、`http://localhost/ai-chat` で空一覧画面 → 「新規相談」モーダル → 会話作成 → 詳細画面遷移までを目視確認
- [ ] `sail bin pint --dirty`

## Step 8: JavaScript（同期版）

- [ ] `resources/js/utils/fetch-json.js` の `postJson` ヘルパ確認（既存、Wave 0b 提供）
- [ ] `resources/js/ai-chat/chat-client.js` 作成（同期版のみ、SSE 接続は Step 9）（REQ-ai-chat-040）
  - class `AiChatClient { sendSync(content): Promise<{user_message, assistant_message}> }`
  - エラー時に `onError({type: 'rate-limit' | 'http' | 'llm', ...})` callback 呼出
- [ ] `resources/js/ai-chat/message-renderer.js` 作成
  - `renderUserMessage(content): HTMLElement` / `renderAssistantMessage({content, status, model}): HTMLElement`
  - error 状態時の再送信ボタン handler 設定
- [ ] `resources/js/ai-chat/full-screen.js` 作成
  - 入力フォーム送信 → `AiChatClient.sendSync()` → 成功時にメッセージリストへ追記、失敗時にエラー表示
  - タイトル編集モーダル開閉
  - 会話削除確認モーダル
- [ ] `resources/js/ai-chat/floating-widget.js` 作成（REQ-ai-chat-071 / 073 / 074 / NFR-ai-chat-007）
  - FAB クリックで `#ai-chat-widget-modal` 表示、`Esc` で閉じる
  - sessionStorage で `ai-chat:current-conversation-id` を保持
  - 教材画面 (`data-section-id` あり) で開く → `POST /ai-chat/conversations?source=widget&section_id=...` で既存再開 or 新規作成
  - 教材以外で開く → 最新会話を取得（or 新規 in-memory 作成）
  - フル画面遷移ボタン → `window.location.href = '/ai-chat/conversations/{id}'`
  - フォーカストラップ実装（Tab で内部のみ）、aria 属性切替
- [ ] `resources/js/app.js` で `floating-widget.js` / `full-screen.js` を import + DOM ready で初期化
- [ ] `sail npm run dev` で Vite ビルド成功確認
- [ ] 動作確認（B-1 同期版完成判定）:
  - student ユーザーでログイン
  - サイドバー「AI 相談」→ `/ai-chat` 一覧画面表示
  - 「新規相談」モーダル → 受講中資格選択 → メッセージ入力 → 送信 → 詳細画面に user + assistant メッセージが同期表示
  - フル画面でメッセージ追加 → 再描画される
  - FAB クリック → ウィジェット展開 → 教材画面では Section コンテキスト付きで会話再開、教材以外では全般会話を再開
  - ウィジェットの「フル画面で開く」ボタンで `/ai-chat/conversations/{id}` 遷移
  - Gemini API キーをわざと空にして 500 エラー、戻して動作復活
  - Rate Limit 超過: `AI_CHAT_DAILY_MESSAGE_LIMIT=2` に設定 → 3 回目送信で 429 表示
- [ ] `sail bin pint --dirty`

> **🔵 B-1 中間チェックポイント**: ここまでで同期版 ai-chat が完成。Step 9 で SSE 化に進む。Step 9 で詰まった場合はここに戻り、`AI_CHAT_STREAMING_ENABLED=false` で同期モードに切り替えてリリース可能。

## Step 9: SSE ストリーミング化

- [ ] `app/Repositories/GeminiLlmRepository.php` の `streamChat()` を実装（REQ-ai-chat-041 / 081）
  - Gemini API の `streamGenerateContent` エンドポイント（または `generateContent?alt=sse`）を `Http::withOptions(['stream' => true])` で POST
  - レスポンスボディを chunk ごとに parse（`PsrResponse` の `getBody()->read()`）
  - 各 chunk から text を抽出して `yield $text`
  - 累積バッファに全 chunk を結合
  - すべて受信後に `usageMetadata` から `inputTokens` / `outputTokens` を取得
  - Generator を `return new LlmChatResponse(...)` で終了
  - エラー時は `AiChatLlmApiException` throw（chunk 受信中の中断含む）
- [ ] `app/UseCases/AiChatMessage/StreamAction.php` を実装（REQ-ai-chat-041 / 042）
  - 構造:
    ```php
    public function __invoke(AiChatConversation $conversation, string $content): \Generator
    {
        // 先行 DB::transaction で user + assistant message を INSERT して COMMIT
        // (中断耐性のため、ストリーミング前にメッセージレコードを永続化)
        $userMsg = ...; $assistantMsg = ...; (status=streaming)

        yield "event: meta\ndata: " . json_encode(['assistant_message_id' => $assistantMsg->id]) . "\n\n";

        $systemPrompt = $this->promptBuilder->build($conversation, $user);
        $history = $this->promptBuilder->buildHistory($conversation);

        $buffer = '';
        try {
            $stream = $this->llm->streamChat($systemPrompt, $history);
            foreach ($stream as $chunk) {
                $buffer .= $chunk;
                yield "event: chunk\ndata: " . json_encode(['text' => $chunk]) . "\n\n";
            }
            // Generator が return した値を取得
            $response = $stream->getReturn();

            $assistantMsg->update([
                'content' => $buffer,
                'model' => $response->model,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'response_time_ms' => $response->responseTimeMs,
                'status' => AiChatMessageStatus::Completed,
            ]);
            $conversation->update(['last_message_at' => now()]);

            yield "event: done\ndata: " . json_encode([...]) . "\n\n";
        } catch (AiChatLlmApiException $e) {
            $assistantMsg->update([
                'content' => $buffer, // 部分受信分を保存
                'error_detail' => $e->getMessage(),
                'status' => AiChatMessageStatus::Error,
            ]);
            $this->rateLimiter->decrement($conversation->user);
            Log::channel('ai-chat')->error(...);

            yield "event: error\ndata: " . json_encode(['message' => '...']) . "\n\n";
        }
    }
    ```
- [ ] `app/Http/Controllers/AiChatMessageController.php` の `stream` メソッドを実装（REQ-ai-chat-041）
  - `$this->authorize('view', $conversation);`
  - `if (! config('ai-chat.streaming_enabled')) abort(404);`
  - `return response()->stream(function () use ($conversation, $request, $action) { foreach ($action($conversation, $request->validated('content')) as $event) { echo $event; ob_flush(); flush(); } }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);`
- [ ] `resources/js/ai-chat/chat-client.js` に SSE 接続メソッド追加（REQ-ai-chat-041 / NFR-ai-chat-007）
  - `sendStream(content): Promise<void>` 実装
  - `fetch + ReadableStream` で SSE event を parse（`event:` / `data:` の改行区切り）
  - 各 event に応じて `onMeta` / `onChunk` / `onDone` / `onError` callback 呼出
  - 429 / 502 のステータスエラーは fetch レスポンスステータスで判定
- [ ] `full-screen.js` / `floating-widget.js` の送信処理を `sendSync` から `sendStream` に切替（`config('ai-chat.streaming_enabled')` の値を Blade で `data-streaming-enabled` 属性として渡し、JS で分岐 → false なら sendSync フォールバック）
- [ ] `tests/Unit/Repositories/GeminiLlmRepositoryStreamTest.php` 作成（NFR-ai-chat-004）
  - `Http::fakeSequence()` でストリーミングレスポンスをスタブ → Generator から yield された chunk を集めて全文 assert
  - エラー系: 中断時に `AiChatLlmApiException` throw を assert
- [ ] `tests/Feature/Http/AiChatMessage/StreamTest.php` 作成
  - SSE エンドポイントへの POST で `Content-Type: text/event-stream` レスポンス
  - レスポンスボディに `event: meta` / `event: chunk` / `event: done` が含まれることを assert
  - エラー時に `event: error` が含まれることを assert
  - `streaming_enabled=false` 時に 404 を assert
- [ ] `sail artisan test --filter='Stream'` 全 pass
- [ ] `sail npm run build` 成功確認
- [ ] 動作確認（B-1 SSE 版完成判定）:
  - メッセージ送信 → 応答が逐次的に画面に表示される（数百 ms ごとに chunk が追記される様子を目視）
  - ストリーミング中にブラウザリロード → 再ロード後にメッセージが `completed` または `error` 状態で表示される（中断耐性）
  - 同時 3 セッション送信 → すべて並列にストリーミング応答（Sail デフォルト worker 数 5 の範囲）
  - `AI_CHAT_STREAMING_ENABLED=false` に切替 → 同期 fetch にフォールバックして動作
  - Gemini API を意図的に止めて（無効な API キー）エラー event 配信 → 「再送信」ボタン押下で復活
- [ ] `sail bin pint --dirty`

## Step 10: 最終動作確認 + PR 準備

- [ ] 全テスト pass: `sail artisan test --filter='Ai(Chat|ChatMessage)'`
- [ ] `sail bin pint` で全体整形
- [ ] スクショ撮影:
  - AI 相談一覧画面（複数会話のコンテキストバッジ表示）
  - 会話詳細画面（user/assistant バブル）
  - フローティングウィジェット（教材閲覧中の展開状態）
  - エラー状態のメッセージ + 再送信ボタン
  - Rate Limit 超過時のエラー表示
- [ ] 動画撮影（動的機能、NFR-ai-chat-004 で必須）:
  - SSE ストリーミングで AI 応答が逐次表示される様子（数十秒）
  - FAB クリック → ウィジェット展開 → 教材 Section コンテキストでメッセージ送信
  - ストリーミング中にブラウザリロード → 中断耐性の確認
- [ ] PR 説明（`tech.md`「PR 記述 7 セクション必須」準拠）:
  1. **関連チケット**: 要件シート側の該当チケット（提供時に紐付け）
  2. **調査内容**: `docs/specs/ai-chat/*.md`、`backend-repositories.md`、`frontend-blade.md`、COACHTECH LMS の `AiChatbot*` 一式、iField LMS の `semantic-search/` spec
  3. **原因分析 / 設計判断**: Why の言語化（design.md「主要な設計判断」セクションを PR 内で再掲、特に Gemini Repository 抽象 / SSE 方式選定 / Section コンテキスト注入の経緯）
  4. **実装内容**: 振る舞い単位で箇条書き（「受講生が AI と相談できるようになった」「教材から FAB で文脈付き相談できる」「ストリーミングで逐次応答」等）
  5. **自動テスト**: 上記テスト結果サマリ
  6. **動作確認**: スクショ + 動画
  7. **レビュー観点 / 自己評価**: 不安な箇所（SSE の chunk parse 周辺 / PHP-FPM worker 制約 / Gemini API クォータ管理 / etc.）

## 完成判定（Definition of Done）

- [ ] 全 REQ-ai-chat-* / NFR-ai-chat-* 要件を実装が満たす
- [ ] 全自動テスト pass
- [ ] 同期版 + SSE 版の両モードで動作確認済
- [ ] Pint 整形済
- [ ] `frontend-ui-foundation.md` のアクセシビリティ要件（aria-label / role / focus trap / Esc）を満たす
- [ ] `backend-repositories.md` / `backend-usecases.md` / `backend-services.md` / `backend-policies.md` / `backend-tests.md` / `backend-exceptions.md` / `frontend-blade.md` / `frontend-javascript.md` / `frontend-tailwind.md` の各規約に準拠
- [ ] `config('ai-chat.enabled')=false` で全機能が無効化され、サイドバー / FAB が消える
