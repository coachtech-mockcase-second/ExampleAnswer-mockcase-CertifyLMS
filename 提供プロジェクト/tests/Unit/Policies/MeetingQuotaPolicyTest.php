<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\MeetingQuotaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingQuotaPolicy の判定を検証する Unit テスト。
 * viewHistory は本人のみ (auth.id === target.id) を網羅する。
 */
class MeetingQuotaPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_history_allowed_only_for_self(): void
    {
        // Arrange
        $self = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $policy = new MeetingQuotaPolicy;

        // Act & Assert
        $this->assertTrue($policy->viewHistory($self, $self), '本人は自分の履歴を閲覧可');
        $this->assertFalse($policy->viewHistory($self, $other), '他人の履歴は閲覧不可');
    }
}
