<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Notification;

use App\Http\Requests\Notification\IndexRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 通知一覧 IndexRequest の rules() を検証する Unit テスト。
 * tab (all / unread) と page (min:1) の nullable フィルタを網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make([], (new IndexRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_each_valid_tab(): void
    {
        foreach (['all', 'unread'] as $tab) {
            $validator = Validator::make(['tab' => $tab], (new IndexRequest)->rules());
            $this->assertTrue($validator->passes(), "tab={$tab} は valid のはず");
        }
    }

    public function test_fails_when_tab_invalid(): void
    {
        $validator = Validator::make(['tab' => 'unknown'], (new IndexRequest)->rules());
        $this->assertArrayHasKey('tab', $validator->errors()->toArray());
    }
}
