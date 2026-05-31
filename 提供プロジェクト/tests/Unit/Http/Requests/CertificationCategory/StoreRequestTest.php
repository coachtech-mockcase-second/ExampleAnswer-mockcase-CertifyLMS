<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\CertificationCategory;

use App\Http\Requests\CertificationCategory\StoreRequest;
use App\Models\CertificationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 資格カテゴリマスタ新規作成 StoreRequest の rules() を検証する Unit テスト。
 * name (required + max:50) / slug (required + max:60 + unique) を網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'name' => 'インフラ系',
            'slug' => 'infrastructure',
        ], (new StoreRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_slug_already_exists(): void
    {
        $existing = CertificationCategory::factory()->create(['slug' => 'existing-slug']);
        $validator = Validator::make([
            'name' => '別カテゴリ',
            'slug' => $existing->slug,
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_fails_when_name_exceeds_max(): void
    {
        $validator = Validator::make([
            'name' => str_repeat('あ', 51),
            'slug' => 'sample-slug',
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }
}
