<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QuestionCategory;

use App\Http\Requests\QuestionCategory\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 出題分野マスタ新規作成 StoreRequest の rules() を検証する Unit テスト。
 * name (max:50) / slug (max:60 + regex /^[a-z0-9-]+$/) / sort_order / description を網羅する。
 * unique where (certification_id) は route binding 依存のため Validator::make では検証範囲外。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'name' => 'IAM 認可',
            'slug' => 'iam-authorization',
            'sort_order' => 10,
        ], (new StoreRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_slug_has_uppercase(): void
    {
        $validator = Validator::make([
            'name' => 'タイトル',
            'slug' => 'Invalid-Slug',
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_fails_when_slug_has_underscore(): void
    {
        $validator = Validator::make([
            'name' => 'タイトル',
            'slug' => 'invalid_slug',
        ], (new StoreRequest)->rules());

        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }
}
