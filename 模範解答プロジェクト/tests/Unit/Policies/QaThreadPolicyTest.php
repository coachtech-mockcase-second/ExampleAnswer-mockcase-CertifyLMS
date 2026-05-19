<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function policy(): QaThreadPolicy
    {
        return new QaThreadPolicy;
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

    public function test_view_any_allows_all_roles(): void
    {
        foreach (['admin', 'coach', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();
            $this->assertTrue($this->policy()->viewAny($user));
        }
    }

    public function test_view_admin_allows_all_threads(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        $this->assertTrue($this->policy()->view($admin, $thread->fresh('certification')));
    }

    public function test_view_coach_allows_thread_in_assigned_certification_only(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $this->attachCoach($assigned, $coach);

        $assignedThread = QaThread::factory()->forCertification($assigned)->create()->load('certification');
        $otherThread = QaThread::factory()->forCertification($other)->create()->load('certification');

        $this->assertTrue($this->policy()->view($coach, $assignedThread));
        $this->assertFalse($this->policy()->view($coach, $otherThread));
    }

    public function test_view_student_allows_only_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();

        $publishedThread = QaThread::factory()->forCertification($published)->create()->load('certification');
        $draftThread = QaThread::factory()->forCertification($draft)->create()->load('certification');

        $this->assertTrue($this->policy()->view($student, $publishedThread));
        $this->assertFalse($this->policy()->view($student, $draftThread));
    }

    public function test_create_allows_only_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $this->assertFalse($this->policy()->create($admin));
        $this->assertFalse($this->policy()->create($coach));
        $this->assertTrue($this->policy()->create($student));
    }

    public function test_update_allows_only_author(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->assertTrue($this->policy()->update($author, $thread));
        $this->assertFalse($this->policy()->update($other, $thread));
        $this->assertFalse($this->policy()->update($admin, $thread));
        $this->assertFalse($this->policy()->update($coach, $thread));
    }

    public function test_delete_admin_can_delete_any(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->forThread($thread)->create();

        $this->assertTrue($this->policy()->delete($admin, $thread));
    }

    public function test_delete_author_can_delete_only_when_no_replies(): void
    {
        $author = User::factory()->student()->create();
        $threadNoReplies = QaThread::factory()->byUser($author)->create();
        $threadWithReplies = QaThread::factory()->byUser($author)->create();
        QaReply::factory()->forThread($threadWithReplies)->create();

        $this->assertTrue($this->policy()->delete($author, $threadNoReplies));
        $this->assertFalse($this->policy()->delete($author, $threadWithReplies));
    }

    public function test_delete_author_cannot_delete_when_only_soft_deleted_replies_exist(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->create();
        $reply = QaReply::factory()->forThread($thread)->create();
        $reply->delete();

        $this->assertFalse($this->policy()->delete($author, $thread->fresh()));
    }

    public function test_delete_disallows_non_author_non_admin(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->assertFalse($this->policy()->delete($other, $thread));
        $this->assertFalse($this->policy()->delete($coach, $thread));
    }

    public function test_resolve_and_unresolve_allow_only_author(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->assertTrue($this->policy()->resolve($author, $thread));
        $this->assertTrue($this->policy()->unresolve($author, $thread));

        foreach ([$other, $admin, $coach] as $denied) {
            $this->assertFalse($this->policy()->resolve($denied, $thread));
            $this->assertFalse($this->policy()->unresolve($denied, $thread));
        }
    }
}
