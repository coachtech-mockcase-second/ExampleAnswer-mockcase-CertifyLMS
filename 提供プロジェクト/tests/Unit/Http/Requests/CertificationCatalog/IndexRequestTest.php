<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\CertificationCatalog;

use App\Enums\CertificationDifficulty;
use App\Http\Requests\CertificationCatalog\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 資格カタログ一覧 IndexRequest の rules() を検証する Unit テスト。
 * category_id (nullable + exists) / difficulty (nullable + enum) を網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make([], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_valid_difficulty(): void
    {
        $validator = Validator::make([
            'difficulty' => CertificationDifficulty::cases()[0]->value,
        ], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_difficulty_invalid(): void
    {
        $validator = Validator::make(['difficulty' => 'unknown'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('difficulty', $validator->errors()->toArray());
    }
}
