<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * NotificationResource の toArray 構造を検証する Unit テスト。
 * DatabaseNotification の data カラム (連想配列) を平坦化し、画面表示に必要なフィールドを返すことを網羅する。
 * data 欠損時のフォールバック (title='通知' / message='') も検証する。
 */
class NotificationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_array_flattens_notification_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->notifications()->create([
            'id' => (string) Str::ulid(),
            'type' => 'App\\Notifications\\Chat\\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '新着メッセージ',
                'message' => '本文プレビュー',
                'link_route' => 'chat.show',
                'link_params' => ['room' => 'room-1'],
            ],
            'read_at' => null,
        ]);
        $notification = $user->notifications()->first();

        // Act
        $array = (new NotificationResource($notification))->toArray(Request::create('/'));

        // Assert
        $this->assertSame('chat_message_received', $array['notification_type']);
        $this->assertSame('新着メッセージ', $array['title']);
        $this->assertSame('chat.show', $array['link_route']);
        $this->assertSame(['room' => 'room-1'], $array['link_params']);
        $this->assertNull($array['read_at'], '未読通知は read_at が null のはず');
    }

    public function test_to_array_falls_back_when_data_keys_missing(): void
    {
        // Arrange: data に title / message が無いケース
        $user = User::factory()->create();
        $user->notifications()->create([
            'id' => (string) Str::ulid(),
            'type' => 'App\\Notifications\\Test',
            'data' => ['notification_type' => 'unknown'],
            'read_at' => null,
        ]);
        $notification = $user->notifications()->first();

        // Act
        $array = (new NotificationResource($notification))->toArray(Request::create('/'));

        // Assert
        $this->assertSame('通知', $array['title'], 'title 欠損時はフォールバック値「通知」を返すはず');
        $this->assertSame('', $array['message'], 'message 欠損時は空文字を返すはず');
    }
}
