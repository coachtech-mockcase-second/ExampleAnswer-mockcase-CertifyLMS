<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\AiChatMessageRole;
use App\Exceptions\AiChat\AiChatLlmApiException;
use App\Repositories\GeminiLlmRepository;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiLlmRepositoryTest extends TestCase
{
    private function makeRepository(): GeminiLlmRepository
    {
        return new GeminiLlmRepository(
            endpoint: 'https://generativelanguage.googleapis.com/v1beta',
            apiKey: 'fake-key',
            defaultModel: 'gemini-2.5-flash-lite',
        );
    }

    public function test_chat_returns_value_object_on_success(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'ハッシュ法はキー値ペアを高速に格納する手法です。']]],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 120,
                    'candidatesTokenCount' => 80,
                ],
            ], 200),
        ]);

        $response = $this->makeRepository()->chat(
            systemPrompt: 'You are a helpful tutor.',
            messages: [
                ['role' => AiChatMessageRole::User->value, 'content' => 'ハッシュ法について教えて'],
            ],
        );

        $this->assertSame('ハッシュ法はキー値ペアを高速に格納する手法です。', $response->content);
        $this->assertSame('gemini-2.5-flash-lite', $response->model);
        $this->assertSame(120, $response->inputTokens);
        $this->assertSame(80, $response->outputTokens);
        $this->assertGreaterThanOrEqual(0, $response->responseTimeMs);
    }

    public function test_chat_throws_api_exception_on_http_error(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 500]], 500),
        ]);

        $this->expectException(AiChatLlmApiException::class);
        $this->expectExceptionMessage('Gemini API failed: HTTP 500');

        $this->makeRepository()->chat('sys', [['role' => 'user', 'content' => 'hello']]);
    }

    public function test_chat_throws_api_exception_on_empty_content(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '']]]]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 0],
            ], 200),
        ]);

        $this->expectException(AiChatLlmApiException::class);
        $this->expectExceptionMessage('empty content');

        $this->makeRepository()->chat('sys', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_retries_then_succeeds(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'transient'], 503)
            ->push([
                'candidates' => [['content' => ['parts' => [['text' => 'リトライ後の応答']]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
            ], 200);

        $response = $this->makeRepository()->chat('sys', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('リトライ後の応答', $response->content);
    }

    public function test_chat_passes_correct_payload(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
            ], 200),
        ]);

        $this->makeRepository()->chat(
            systemPrompt: 'system here',
            messages: [
                ['role' => AiChatMessageRole::User->value, 'content' => 'q1'],
                ['role' => AiChatMessageRole::Assistant->value, 'content' => 'a1'],
                ['role' => AiChatMessageRole::User->value, 'content' => 'q2'],
            ],
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'gemini-2.5-flash-lite:generateContent')) {
                return false;
            }
            $data = $request->data();
            if (($data['systemInstruction']['parts'][0]['text'] ?? null) !== 'system here') {
                return false;
            }
            // assistant is mapped to "model" in Gemini contents format
            $roles = array_map(fn ($c) => $c['role'], $data['contents']);

            return $roles === ['user', 'model', 'user'];
        });
    }
}
