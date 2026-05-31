<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Section;

use App\Http\Requests\Section\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Section 新規作成 StoreRequest の rules() を検証する Unit テスト。
 * title / description / body の必須 + 文字数上限を Validator::make で網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $payload = ['title' => 'IAM ロール', 'description' => '説明', 'body' => '本文'];
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_fails_when_title_missing(): void
    {
        // Arrange & Act
        $validator = Validator::make(['body' => '本文'], (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    public function test_fails_when_body_missing(): void
    {
        // Arrange & Act
        $validator = Validator::make(['title' => 'タイトル'], (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('body', $validator->errors()->toArray());
    }

    public function test_fails_when_body_exceeds_50000_chars(): void
    {
        // Arrange & Act
        $validator = Validator::make([
            'title' => 'タイトル',
            'body' => str_repeat('a', 50001),
        ], (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('body', $validator->errors()->toArray());
    }
}
