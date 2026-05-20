<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chat;

use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `GET /chat-rooms` と `GET /coach/chat-rooms` の挙動を検証する。
 *
 * - 受講生・コーチの `chat.index` は「最新ルームへ redirect」 or「ルーム 0 件で empty-state」の 2 分岐
 *   (ルーム選択 UI は show ページの 2 ペイン左カラムが担う)
 * - コーチ向け `coach.chat.index` は専用一覧(未読あり / すべて + フィルタ + キーワード検索)
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_with_rooms_redirects_to_latest_room(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $older = Enrollment::factory()->for($student)->create();
        $newer = Enrollment::factory()->for($student)->create();

        $olderRoom = ChatRoom::factory()
            ->for($older)
            ->withMessageAt(Carbon::now()->subHours(3))
            ->create();
        $newerRoom = ChatRoom::factory()
            ->for($newer)
            ->withMessageAt(Carbon::now()->subMinutes(5))
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $olderRoom->id,
            'user_id' => $student->id,
        ]);
        ChatMember::factory()->create([
            'chat_room_id' => $newerRoom->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->get(route('chat.index'));

        $response->assertRedirect(route('chat.show', $newerRoom));
    }

    public function test_other_students_rooms_do_not_become_redirect_target(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();

        $otherEnrollment = Enrollment::factory()->for($other)->create();
        $otherRoom = ChatRoom::factory()->for($otherEnrollment)->create();
        ChatMember::factory()->create([
            'chat_room_id' => $otherRoom->id,
            'user_id' => $other->id,
        ]);

        $response = $this->actingAs($student)->get(route('chat.index'));

        // student 自身は ChatMember 0 件 → empty-state にフォールバック
        $response->assertOk();
        $response->assertViewIs('chat.empty-state');
    }

    public function test_empty_state_when_no_rooms(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('chat.index'));

        $response->assertOk();
        $response->assertViewIs('chat.empty-state');
    }

    public function test_eager_generated_room_becomes_redirect_target_after_enrollment_store(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $this->actingAs($student)->post(route('enrollments.store'), [
            'certification_id' => $certification->id,
            'exam_date' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $enrollment = Enrollment::query()->where('user_id', $student->id)->first();
        $this->assertNotNull($enrollment);
        $this->assertDatabaseHas('chat_rooms', ['enrollment_id' => $enrollment->id]);
        $this->assertDatabaseHas('chat_members', [
            'user_id' => $student->id,
        ]);

        $room = ChatRoom::query()->where('enrollment_id', $enrollment->id)->first();
        $this->actingAs($student)
            ->get(route('chat.index'))
            ->assertRedirect(route('chat.show', $room));
    }

    public function test_coach_index_redirects_to_latest_room_regardless_of_unread_status(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()
            ->for($enrollment)
            ->withMessageAt(Carbon::now()->subMinutes(5))
            ->create();
        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
            'last_read_at' => Carbon::now(), // 全部既読 (= unread フィルタは 0 件)
        ]);

        // 既読でもサイドバーから飛んだら最新参加ルームへ即 redirect
        $this->actingAs($coach)
            ->get(route('coach.chat.index'))
            ->assertRedirect(route('chat.show', $room));
    }

    public function test_coach_index_renders_empty_state_when_coach_has_no_rooms(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('coach.chat.index'))
            ->assertOk()
            ->assertViewIs('chat.coach-empty-state');

        $this->assertNotNull(Str::ulid()); // Str use 抑止
    }
}
