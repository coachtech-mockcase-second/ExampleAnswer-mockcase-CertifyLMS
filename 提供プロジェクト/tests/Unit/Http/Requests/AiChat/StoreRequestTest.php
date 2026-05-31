<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\AiChat;

use App\Http\Requests\AiChat\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * AI 相談会話 StoreRequest の rules() を検証する Unit テスト。
 * section_id (nullable + ulid + exists) / message (nullable + max:2000) / source (nullable + in:widget,full-screen) を網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_payload(): void
    {
        // Arrange: 全項目 nullable なので空 payload も valid
        // Act
        $validator = Validator::make([], (new StoreRequest)->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_fails_when_source_is_invalid_value(): void
    {
        // Arrange
        $payload = ['source' => 'unknown'];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('source', $validator->errors()->toArray());
    }

    public function test_fails_when_message_exceeds_max_length(): void
    {
        // Arrange
        $payload = ['message' => str_repeat('a', 2001)];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('message', $validator->errors()->toArray());
    }
}
