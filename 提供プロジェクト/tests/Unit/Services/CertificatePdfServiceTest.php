<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certificate;
use App\Services\CertificatePdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificatePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_pdf_and_stores_to_private_disk(): void
    {
        Storage::fake('private');

        $certificate = Certificate::factory()->create([
            'pdf_path' => 'certificates/test.pdf',
        ]);

        (new CertificatePdfService)->generate($certificate);

        Storage::disk('private')->assertExists('certificates/test.pdf');
    }

    public function test_renders_certificates_pdf_blade_template(): void
    {
        Storage::fake('private');

        $certificate = Certificate::factory()->create();

        (new CertificatePdfService)->generate($certificate);

        // PDF binary should be non-empty
        $content = Storage::disk('private')->get($certificate->pdf_path);
        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF', $content);
    }
}
