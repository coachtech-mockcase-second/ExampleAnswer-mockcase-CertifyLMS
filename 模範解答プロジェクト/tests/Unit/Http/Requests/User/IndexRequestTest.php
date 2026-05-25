<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\User;

use App\Enums\UserStatus;
use App\Http\Requests\User\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * User 一覧 IndexRequest の rules() を検証する Unit テスト。
 * filter (keyword / role / status enum / page) の nullable 検証を網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_all_filters_valid(): void
    {
        $validator = Validator::make([
            'keyword' => 'tanaka',
            'role' => 'coach',
            'status' => UserStatus::InProgress->value,
            'page' => 2,
        ], (new IndexRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_role_invalid_value(): void
    {
        $validator = Validator::make(['role' => 'superuser'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
    }

    public function test_fails_when_status_not_in_enum(): void
    {
        $validator = Validator::make(['status' => 'unknown_status'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }
}
