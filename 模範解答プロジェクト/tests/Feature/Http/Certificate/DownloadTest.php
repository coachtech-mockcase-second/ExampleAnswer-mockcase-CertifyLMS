<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    private function preparePdf(Certificate $certificate): void
    {
        Storage::disk('private')->put($certificate->pdf_path, '%PDF-1.4 dummy');
    }

    public function test_owner_can_download_own_pdf(): void
    {
        Storage::fake('private');

        $owner = User::factory()->student()->create();
        $cert = Certificate::factory()->for($owner)->create([
            'pdf_path' => 'certificates/test.pdf',
        ]);
        $this->preparePdf($cert);

        $response = $this->actingAs($owner)->get(route('certificates.download', $cert));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("certificate-{$cert->id}.pdf", $response->headers->get('Content-Disposition'));
    }

    public function test_graduated_owner_can_still_download(): void
    {
        Storage::fake('private');

        $owner = User::factory()->student()->graduated()->create();
        $cert = Certificate::factory()->for($owner)->create();
        $this->preparePdf($cert);

        $response = $this->actingAs($owner)->get(route('certificates.download', $cert));

        $response->assertOk();
    }

    public function test_admin_can_download_any_pdf(): void
    {
        Storage::fake('private');

        $admin = User::factory()->admin()->create();
        $cert = Certificate::factory()->create();
        $this->preparePdf($cert);

        $response = $this->actingAs($admin)->get(route('certificates.download', $cert));

        $response->assertOk();
    }

    public function test_coach_can_download_assigned_certification_pdf(): void
    {
        Storage::fake('private');

        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
        ]);
        $cert = Certificate::factory()->create(['certification_id' => $certification->id]);
        $this->preparePdf($cert);

        $response = $this->actingAs($coach)->get(route('certificates.download', $cert));

        $response->assertOk();
    }

    public function test_coach_cannot_download_unassigned_certification_pdf(): void
    {
        Storage::fake('private');

        $coach = User::factory()->coach()->create();
        $cert = Certificate::factory()->create();
        $this->preparePdf($cert);

        $response = $this->actingAs($coach)->get(route('certificates.download', $cert));

        $response->assertForbidden();
    }

    public function test_other_student_cannot_download(): void
    {
        Storage::fake('private');

        $owner = User::factory()->student()->create();
        $stranger = User::factory()->student()->create();
        $cert = Certificate::factory()->for($owner)->create();
        $this->preparePdf($cert);

        $response = $this->actingAs($stranger)->get(route('certificates.download', $cert));

        $response->assertForbidden();
    }

    public function test_returns_404_when_pdf_file_missing(): void
    {
        Storage::fake('private');

        $owner = User::factory()->student()->create();
        $cert = Certificate::factory()->for($owner)->create([
            'pdf_path' => 'certificates/missing.pdf',
        ]);

        $response = $this->actingAs($owner)->get(route('certificates.download', $cert));

        $response->assertNotFound();
    }
}
