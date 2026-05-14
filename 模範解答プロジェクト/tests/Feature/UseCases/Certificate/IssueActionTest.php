<?php

namespace Tests\Feature\UseCases\Certificate;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Certification\EnrollmentNotPassedException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CertificatePdfGenerator;
use App\UseCases\Certificate\IssueAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class IssueActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_issues_certificate_for_passed_enrollment(): void
    {
        Storage::fake('private');
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $certificate = $action($enrollment, $admin);

        $this->assertNotNull($certificate);
        $this->assertSame('CT-202605-00001', $certificate->serial_no);
        $this->assertSame($enrollment->user_id, $certificate->user_id);
        $this->assertSame($enrollment->id, $certificate->enrollment_id);
        $this->assertSame($admin->id, $certificate->issued_by_user_id);
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
        Storage::disk('private')->assertExists($certificate->pdf_path);
    }

    public function test_throws_when_enrollment_not_passed(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        $action = $this->app->make(IssueAction::class);

        $this->expectException(EnrollmentNotPassedException::class);

        $action($enrollment, $admin);
    }

    public function test_throws_when_passed_status_but_passed_at_is_null(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create([
            'status' => EnrollmentStatus::Passed->value,
            'passed_at' => null,
        ]);

        $action = $this->app->make(IssueAction::class);

        $this->expectException(EnrollmentNotPassedException::class);

        $action($enrollment, $admin);
    }

    public function test_is_idempotent_on_repeated_call(): void
    {
        Storage::fake('private');

        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $first = $action($enrollment, $admin);
        $second = $action($enrollment, $admin);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Certificate::query()->where('enrollment_id', $enrollment->id)->count());
    }

    public function test_does_not_regenerate_pdf_on_idempotent_call(): void
    {
        Storage::fake('private');

        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->passed()->create();

        $pdfMock = Mockery::mock(CertificatePdfGenerator::class);
        $pdfMock->shouldReceive('generate')->once();
        $this->app->instance(CertificatePdfGenerator::class, $pdfMock);

        $action = $this->app->make(IssueAction::class);
        $action($enrollment, $admin);
        $action($enrollment, $admin);

        $this->addToAssertionCount(1);
    }
}
