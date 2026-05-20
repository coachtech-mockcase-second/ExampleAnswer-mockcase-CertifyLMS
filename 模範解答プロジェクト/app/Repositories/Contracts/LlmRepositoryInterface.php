<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Exceptions\AiChat\AiChatLlmApiException;
use App\Services\LlmChatResponse;

/**
 * LLM API への呼出を抽象化する Repository インタフェース。
 *
 * 既定実装は GeminiLlmRepository。テストでは Fake 実装に差替する。
 */
interface LlmRepositoryInterface
{
    /**
     * 同期チャット呼出。完全な応答を返す。
     *
     * @param  array<int, array{role: string, content: string}>  $messages  OpenAI 形式の会話履歴
     *
     * @throws AiChatLlmApiException API 呼出失敗 (HTTP 5xx / timeout / quota など)
     */
    public function chat(string $systemPrompt, array $messages, ?string $model = null): LlmChatResponse;
}
