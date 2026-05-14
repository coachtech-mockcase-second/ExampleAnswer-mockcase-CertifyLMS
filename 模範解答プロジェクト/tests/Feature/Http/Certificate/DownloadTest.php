<?php

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_download_own_pdf(): void
    {
        Storage::fake('private');

        $owner = User::factory()->student()->create();
        $cert = Certificate::factory()->for($owner)->create([
            'pdf_path' => 'certificates/test.pdf',
        ]);
        Storage::disk('private')->put($cert->pdf_path, '%PDF-1.4 dummy');

        $response = $this->actingAs($owner)->get(route('certificates.download', $cert));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("certificate-{$cert->serial_no}.pdf", $response->headers->get('Content-Disposition'));
    }

    public function test_other_student_cannot_download(): void
    {
        Storage::fake('private');

        $owner = User::factory()->student()->create();
        $stranger = User::factory()->student()->create();
        $cert = Certificate::factory()->for($owner)->create();

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
        // No put → file not exists

        $response = $this->actingAs($owner)->get(route('certificates.download', $cert));

        $response->assertNotFound();
    }
}
