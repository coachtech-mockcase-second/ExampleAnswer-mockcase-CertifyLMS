<?php

namespace Tests\Feature\Http\Admin\Certification;

use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_certification_list(): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->published()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.certifications.index'));

        $response->assertOk();
        $response->assertViewIs('admin.certifications.index');
        $response->assertViewHas('certifications');
    }

    public function test_coach_and_student_cannot_access_admin_certifications_index(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($coach)
            ->get(route('admin.certifications.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.certifications.index'))
            ->assertForbidden();
    }

    public function test_keyword_filter_matches_code_or_name(): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->published()->create(['code' => 'CERT-AAA111', 'name' => 'TOEIC Test']);
        Certification::factory()->published()->create(['code' => 'CERT-BBB222', 'name' => 'PMP Certification']);

        $response = $this->actingAs($admin)->get(route('admin.certifications.index', ['keyword' => 'TOEIC']));

        $response->assertOk();
        $response->assertSee('CERT-AAA111');
        $response->assertDontSee('CERT-BBB222');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->draft()->create(['name' => 'Draft One']);
        Certification::factory()->published()->create(['name' => 'Published One']);
        Certification::factory()->archived()->create(['name' => 'Archived One']);

        $response = $this->actingAs($admin)->get(route('admin.certifications.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertSee('Draft One');
        $response->assertDontSee('Published One');
        $response->assertDontSee('Archived One');
    }

    public function test_category_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create(['name' => 'Tech Cert']);
        Certification::factory()->published()->create(['name' => 'Match', 'category_id' => $category->id]);
        Certification::factory()->published()->create(['name' => 'OtherCat']);

        $response = $this->actingAs($admin)->get(route('admin.certifications.index', ['category_id' => $category->id]));

        $response->assertOk();
        $response->assertSee('Match');
        $response->assertDontSee('OtherCat');
    }

    public function test_paginates_20_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->published()->count(22)->create();

        $response = $this->actingAs($admin)->get(route('admin.certifications.index'));

        $response->assertOk();
        $certs = $response->viewData('certifications');
        $this->assertSame(20, $certs->perPage());
        $this->assertSame(22, $certs->total());
    }
}
