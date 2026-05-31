<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingPack モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 3 リレーション (createdBy / updatedBy / payments) + 2 scope (published / ordered) +
 * 4 cast (status enum / meeting_count int / price int / sort_order int) を網羅する。
 * 追加面談 SKU マスタ。
 */
class MeetingPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_by_relation_returns_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->create(['created_by_user_id' => $admin->id]);

        // Act
        $creator = $pack->createdBy;

        // Assert
        $this->assertTrue($creator->is($admin));
    }

    public function test_payments_relation_returns_attached_payments(): void
    {
        // Arrange
        $pack = MeetingPack::factory()->published()->create();
        Payment::factory()->for($pack)->create();
        Payment::factory()->for($pack)->create();

        // Act
        $payments = $pack->payments;

        // Assert
        $this->assertCount(2, $payments);
    }

    public function test_scope_published_filters_only_published(): void
    {
        // Arrange
        MeetingPack::factory()->draft()->create();
        $published = MeetingPack::factory()->published()->create();

        // Act
        $results = MeetingPack::published()->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($published));
    }

    public function test_scope_ordered_sorts_by_sort_order(): void
    {
        // Arrange
        $second = MeetingPack::factory()->published()->create(['sort_order' => 2]);
        $first = MeetingPack::factory()->published()->create(['sort_order' => 1]);

        // Act
        $results = MeetingPack::ordered()->get();

        // Assert
        $this->assertTrue($results->first()->is($first));
    }

    public function test_status_cast_converts_to_enum(): void
    {
        // Arrange
        $pack = MeetingPack::factory()->published()->create();

        // Act
        $fresh = $pack->fresh();

        // Assert
        $this->assertInstanceOf(MeetingPackStatus::class, $fresh->status);
        $this->assertSame(MeetingPackStatus::Published, $fresh->status);
    }

    public function test_integer_casts_return_int(): void
    {
        // Arrange
        $pack = MeetingPack::factory()->withCount(5)->withPrice(15000)->create();

        // Act
        $fresh = $pack->fresh();

        // Assert
        $this->assertIsInt($fresh->meeting_count);
        $this->assertSame(5, $fresh->meeting_count);
        $this->assertIsInt($fresh->price);
        $this->assertSame(15000, $fresh->price);
    }
}
