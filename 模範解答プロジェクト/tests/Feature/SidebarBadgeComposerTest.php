<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\View\Composers\SidebarBadgeComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

class SidebarBadgeComposerTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    private function compose(User $actingAs): array
    {
        $this->actingAs($actingAs);
        $composer = app(SidebarBadgeComposer::class);
        $view = View::make('placeholders.coming-soon', ['feature' => 'dashboard']);
        $composer->compose($view);

        return $view->getData()['sidebarBadges'];
    }

    public function test_coach_pending_questions_counts_only_assigned_open_no_reply_threads(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $this->attachCoach($assigned, $coach);

        // 担当資格 - 未回答 (集計対象)
        $countedA = QaThread::factory()->forCertification($assigned)->unresolved()->count(2)->create();
        // 担当資格 - 回答済 (集計除外)
        $alreadyAnswered = QaThread::factory()->forCertification($assigned)->unresolved()->create();
        QaReply::factory()->forThread($alreadyAnswered)->create();
        // 担当資格 - 解決済 (集計除外)
        QaThread::factory()->forCertification($assigned)->resolved()->create();
        // 担当外資格 (集計除外)
        QaThread::factory()->forCertification($other)->unresolved()->create();

        $badges = $this->compose($coach);

        $this->assertSame(2, $badges['pendingQuestions']);
    }

    public function test_student_pending_questions_is_zero(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        QaThread::factory()->forCertification($cert)->unresolved()->count(5)->create();

        $badges = $this->compose($student);

        $this->assertSame(0, $badges['pendingQuestions']);
    }

    public function test_admin_pending_questions_is_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        QaThread::factory()->forCertification($cert)->unresolved()->count(5)->create();

        $badges = $this->compose($admin);

        $this->assertSame(0, $badges['pendingQuestions']);
    }
}
