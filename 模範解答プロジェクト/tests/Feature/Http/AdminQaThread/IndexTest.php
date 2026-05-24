<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread\Moderation;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_threads_across_published_and_unpublished_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();
        QaThread::factory()->forCertification($published)->count(2)->create();
        QaThread::factory()->forCertification($draft)->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk();
        $this->assertSame(5, $response->viewData('threads')->total());
    }

    public function test_non_admin_cannot_access(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        foreach ([$coach, $student] as $denied) {
            $response = $this->actingAs($denied)->get(route('admin.qa-board.index'));
            $this->assertContains($response->status(), [403, 404]);
        }
    }

}
