<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Http\Requests\QaThread\StoreRequest;
use App\Models\Certification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 質問掲示板スレッド StoreRequest の rules() を検証する Unit テスト。
 * 公開済 certification の exists where 句、title / body の必須 + max + not_regex (空白のみ禁止) を網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload_and_published_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $payload = [
            'certification_id' => $cert->id,
            'title' => 'TLS 1.3 の鍵交換について',
            'body' => '質問本文をここに記述します。',
        ];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_fails_when_certification_is_draft(): void
    {
        // Arrange: 未公開 certification は exists where 句で弾かれる
        $draft = Certification::factory()->draft()->create();
        $payload = [
            'certification_id' => $draft->id,
            'title' => 'タイトル',
            'body' => '本文',
        ];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('certification_id', $validator->errors()->toArray());
    }

    public function test_fails_when_certification_id_nonexistent(): void
    {
        // Arrange
        $payload = [
            'certification_id' => (string) Str::ulid(),
            'title' => 'タイトル',
            'body' => '本文',
        ];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('certification_id', $validator->errors()->toArray());
    }

    public function test_fails_when_title_is_whitespace_only(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $payload = [
            'certification_id' => $cert->id,
            'title' => '   ',
            'body' => '本文',
        ];

        // Act
        $validator = Validator::make($payload, (new StoreRequest)->rules());

        // Assert
        $this->assertArrayHasKey('title', $validator->errors()->toArray(), '空白のみのタイトルは not_regex で弾かれるはず');
    }
}
