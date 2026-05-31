<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\User;
use App\Policies\ChapterPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ChapterPolicy の判定を検証する Unit テスト。
 * admin: 全 ability 可 / coach: 担当資格のみ可 / student: published のみ view 可 を網羅する。
 */
class ChapterPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_do_everything(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->for($cert)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();
        $policy = new ChapterPolicy;

        $this->assertTrue($policy->viewAny($admin, $part));
        $this->assertTrue($policy->view($admin, $chapter));
        $this->assertTrue($policy->create($admin, $part));
        $this->assertTrue($policy->update($admin, $chapter));
        $this->assertTrue($policy->delete($admin, $chapter));
        $this->assertTrue($policy->publish($admin, $chapter));
    }

    public function test_coach_can_manage_only_assigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedPart = Part::factory()->for($assignedCert)->published()->create();
        $otherPart = Part::factory()->for($otherCert)->published()->create();
        $assignedChapter = Chapter::factory()->for($assignedPart)->published()->create();
        $otherChapter = Chapter::factory()->for($otherPart)->published()->create();
        $policy = new ChapterPolicy;

        $this->assertTrue($policy->update($coach, $assignedChapter));
        $this->assertFalse($policy->update($coach, $otherChapter));
    }

    public function test_student_can_view_only_published_chapter(): void
    {
        $student = User::factory()->student()->create();
        $publishedChapter = Chapter::factory()->published()->create();
        $draftChapter = Chapter::factory()->draft()->create();
        $policy = new ChapterPolicy;

        $this->assertTrue($policy->view($student, $publishedChapter));
        $this->assertFalse($policy->view($student, $draftChapter));
    }

    public function test_student_cannot_manage(): void
    {
        $student = User::factory()->student()->create();
        $chapter = Chapter::factory()->published()->create();
        $part = $chapter->part;
        $policy = new ChapterPolicy;

        $this->assertFalse($policy->viewAny($student, $part));
        $this->assertFalse($policy->update($student, $chapter));
        $this->assertFalse($policy->delete($student, $chapter));
    }
}
