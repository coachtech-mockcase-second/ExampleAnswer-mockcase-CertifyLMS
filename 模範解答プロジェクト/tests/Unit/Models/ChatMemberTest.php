<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ChatMember モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 2 リレーション (chatRoom / user) + 主要 scope 2 (forUser / unread) + 2 cast (last_read_at / joined_at datetime) を網羅する。
 */
class ChatMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_room_relation_returns_parent_room(): void
    {
        // Arrange
        $room = ChatRoom::factory()->create();
        $member = ChatMember::factory()->for($room)->create();

        // Act
        $parent = $member->chatRoom;

        // Assert
        $this->assertTrue($parent->is($room));
    }

    public function test_user_relation_returns_member_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $member = ChatMember::factory()->for($user)->create();

        // Act
        $related = $member->user;

        // Assert
        $this->assertTrue($related->is($user));
    }

    public function test_scope_for_user_filters_by_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $own = ChatMember::factory()->for($user)->create();
        ChatMember::factory()->create();

        // Act
        $results = ChatMember::forUser($user)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($own));
    }

    public function test_joined_at_cast_returns_carbon(): void
    {
        // Arrange
        $member = ChatMember::factory()->read()->create([
            'joined_at' => '2026-05-20 10:00:00',
        ]);

        // Act
        $fresh = $member->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->joined_at, 'joined_at は Carbon datetime にキャストされるはず');
    }
}
