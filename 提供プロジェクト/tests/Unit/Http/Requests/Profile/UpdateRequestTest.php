<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Profile;

use App\Http\Requests\Profile\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Profile 更新 UpdateRequest の rules() を検証する Unit テスト。
 * name (required + max:50) / bio (nullable + max:1000) / meeting_url (nullable + url) を valid + invalid で網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        $validator = Validator::make([
            'name' => '田中 太郎',
            'bio' => 'AWS 学習中',
            'meeting_url' => 'https://meet.example.test/abc',
        ], (new UpdateRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_meeting_url_not_url_format(): void
    {
        $validator = Validator::make([
            'name' => '田中',
            'meeting_url' => 'not-a-url',
        ], (new UpdateRequest)->rules());

        $this->assertArrayHasKey('meeting_url', $validator->errors()->toArray());
    }

    public function test_fails_when_name_exceeds_max(): void
    {
        $validator = Validator::make([
            'name' => str_repeat('あ', 51),
        ], (new UpdateRequest)->rules());

        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }
}
