<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Part;

use App\Http\Requests\Part\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Part 更新 UpdateRequest の rules() を検証する Unit テスト。
 * Store と同じ rules（title + description）を valid + invalid で確認する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'title' => '更新後タイトル',
            'description' => '更新後説明',
        ], (new UpdateRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_description_exceeds_max(): void
    {
        $validator = Validator::make([
            'title' => 'タイトル',
            'description' => str_repeat('a', 1001),
        ], (new UpdateRequest)->rules());

        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }
}
