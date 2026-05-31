<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function policy(): QaReplyPolicy
    {
        return new QaReplyPolicy;
    }

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_create_admin_always_denied(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create()->load('certification');

        $this->assertFalse($this->policy()->create($admin, $thread));
    }

    public function test_create_coach_only_for_assigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $this->attachCoach($assigned, $coach);

        $assignedThread = QaThread::factory()->forCertification($assigned)->create()->load('certification');
        $otherThread = QaThread::factory()->forCertification($other)->create()->load('certification');

        $this->assertTrue($this->policy()->create($coach, $assignedThread));
        $this->assertFalse($this->policy()->create($coach, $otherThread));
    }

    public function test_create_student_only_for_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();

        $publishedThread = QaThread::factory()->forCertification($published)->create()->load('certification');
        $draftThread = QaThread::factory()->forCertification($draft)->create()->load('certification');

        $this->assertTrue($this->policy()->create($student, $publishedThread));
        $this->assertFalse($this->policy()->create($student, $draftThread));
    }

    public function test_update_only_author(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $this->assertTrue($this->policy()->update($author, $reply));
        $this->assertFalse($this->policy()->update($other, $reply));
        $this->assertFalse($this->policy()->update($admin, $reply));
    }

    public function test_delete_author_or_admin(): void
    {
        $author = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $this->assertTrue($this->policy()->delete($author, $reply));
        $this->assertTrue($this->policy()->delete($admin, $reply));
        $this->assertFalse($this->policy()->delete($other, $reply));
        $this->assertFalse($this->policy()->delete($coach, $reply));
    }
}
