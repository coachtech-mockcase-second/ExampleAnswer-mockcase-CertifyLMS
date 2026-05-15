<?php

namespace Tests\Support;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\User;

trait ContentTestHelpers
{
    protected function assignCoach(User $coach, Certification $certification, ?User $admin = null): void
    {
        $admin = $admin ?? User::factory()->admin()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    protected function makePartChain(Certification $certification, string $status = 'published'): array
    {
        $part = Part::factory()->forCertification($certification)->state(['status' => $status])->create();
        $chapter = Chapter::factory()->forPart($part)->state(['status' => $status])->create();
        $section = Section::factory()->forChapter($chapter)->state(['status' => $status])->create();

        return [$part, $chapter, $section];
    }

    protected function makeCategory(Certification $certification, array $overrides = []): QuestionCategory
    {
        return QuestionCategory::factory()
            ->forCertification($certification)
            ->state($overrides)
            ->create();
    }
}
