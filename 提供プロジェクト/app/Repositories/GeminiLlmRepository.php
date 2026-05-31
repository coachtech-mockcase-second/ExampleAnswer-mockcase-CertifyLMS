<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\AiChatMessageRole;
use App\Exceptions\AiChat\AiChatLlmApiException;
use App\Repositories\Contracts\LlmRepositoryInterface;
use App\Services\LlmChatResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Generative Language API (Gemini) を呼ぶ Repository 実装。
 *
 * - chat: generateContent (同期) を timeout(30) + 手動 retry (5xx のみ最大 2 回) で呼出
 * - 429 (Rate Limit) はリトライせず即時失敗 (Gemini RPM をさらに圧迫しないため)
 * - エラー (HTTP 4xx/5xx / timeout) は AiChatLlmApiException に正規化、Gemini レスポンス body の先頭 500 文字をメッセージに含める
 *
 * メッセージ形式は OpenAI 形式 ({role, content}) → Gemini 形式 ({role: user|model, parts: [{text}]}) に変換する。
 */
final class GeminiLlmRepository implements LlmRepositoryInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $apiKey,
        private readonly string $defaultModel,
    ) {}

    public function chat(string $systemPrompt, array $messages, ?string $model = null): LlmChatResponse
    {
        $start = microtime(true);
        $model ??= $this->defaultModel;
        $url = "{$this->endpoint}/models/{$model}:generateContent?key={$this->apiKey}";

        // 手動 retry: ConnectionException / 5xx のみ retry、429 (Rate Limit) は即時失敗。
        // Http::retry() は HTTP failed で自動 retry しないため、自分でループ制御する。
        $maxAttempts = 3;
        $response = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(30)->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => $this->formatMessages($messages),
                ]);
            } catch (ConnectionException $e) {
                if ($attempt >= $maxAttempts) {
                    throw new AiChatLlmApiException("Gemini API connection failed: {$e->getMessage()}", previous: $e);
                }
                usleep(100 * 1000);

                continue;
            }

            if ($response->serverError() && $attempt < $maxAttempts) {
                usleep(100 * 1000);

                continue;
            }
            break;
        }

        if ($response->failed()) {
            // Gemini のレスポンス body にクォータ詳細 (どの制限超過か、リセット時刻、quotaId)
            // が含まれるため、例外メッセージに body 先頭 2000 文字を含めてログから原因特定できるようにする
            $bodySnippet = mb_substr((string) $response->body(), 0, 2000);
            throw new AiChatLlmApiException(
                "Gemini API failed: HTTP {$response->status()} body={$bodySnippet}",
                httpStatus: $response->status(),
            );
        }

        $json = $response->json();
        $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($content === '') {
            throw new AiChatLlmApiException('Gemini API returned empty content');
        }

        return new LlmChatResponse(
            content: $content,
            model: $model,
            inputTokens: (int) ($json['usageMetadata']['promptTokenCount'] ?? 0),
            outputTokens: (int) ($json['usageMetadata']['candidatesTokenCount'] ?? 0),
            responseTimeMs: (int) ((microtime(true) - $start) * 1000),
        );
    }

    /**
     * OpenAI 形式 [{role: 'user'|'assistant', content}] を Gemini contents 形式 [{role: 'user'|'model', parts: [{text}]}] に変換する。
     *
     * Gemini は assistant role を "model" と呼ぶ。System prompt は本メソッドの対象外
     * (systemInstruction で別経路で渡すため)。
     *
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function formatMessages(array $messages): array
    {
        $contents = [];
        foreach ($messages as $message) {
            $role = $message['role'] === AiChatMessageRole::Assistant->value ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $message['content']]],
            ];
        }

        return $contents;
    }
}
