<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Exceptions\AiChat\AiChatLlmApiException;
use App\Repositories\Contracts\LlmRepositoryInterface;
use App\Services\LlmChatResponse;

/**
 * テスト用 LLM Repository。Action / Controller の振る舞いを Gemini 接続なしで検証する。
 *
 * - 通常: コンストラクタで与えた応答 (LlmChatResponse) を返す
 * - shouldFail() を呼ぶと chat() が AiChatLlmApiException を投げる
 * - lastCall プロパティで「最後に渡された systemPrompt / messages」が後から検証できる
 */
final class FakeLlmRepository implements LlmRepositoryInterface
{
    public ?string $lastSystemPrompt = null;

    /** @var array<int, array{role: string, content: string}>|null */
    public ?array $lastMessages = null;

    public int $chatCallCount = 0;

    public function __construct(
        private LlmChatResponse $response,
        private bool $shouldFail = false,
        private string $failureMessage = 'Gemini API failed: HTTP 503',
    ) {}

    public static function withContent(string $content, string $model = 'gemini-2.5-flash'): self
    {
        return new self(new LlmChatResponse(
            content: $content,
            model: $model,
            inputTokens: 100,
            outputTokens: 50,
            responseTimeMs: 300,
        ));
    }

    public function failing(string $message = 'Gemini API failed: HTTP 503'): self
    {
        $this->shouldFail = true;
        $this->failureMessage = $message;

        return $this;
    }

    public function chat(string $systemPrompt, array $messages, ?string $model = null): LlmChatResponse
    {
        $this->chatCallCount++;
        $this->lastSystemPrompt = $systemPrompt;
        $this->lastMessages = $messages;

        if ($this->shouldFail) {
            throw new AiChatLlmApiException($this->failureMessage, httpStatus: 503);
        }

        return $this->response;
    }
}
