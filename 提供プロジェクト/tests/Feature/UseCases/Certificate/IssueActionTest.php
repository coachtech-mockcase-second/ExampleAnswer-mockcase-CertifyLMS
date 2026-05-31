<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certificate;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Certification\CertificateAlreadyIssuedException;
use App\Exceptions\Certification\CertificateGenerationFailedException;
use App\Exceptions\Certification\EnrollmentNotPassedException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CertificatePdfService;
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

        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $certificate = $action($enrollment);

        $this->assertNotNull($certificate);
        $this->assertSame($enrollment->user_id, $certificate->user_id);
        $this->assertSame($enrollment->id, $certificate->enrollment_id);
        $this->assertSame($enrollment->certification_id, $certificate->certification_id);
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
        Storage::disk('private')->assertExists($certificate->pdf_path);
    }

    public function test_throws_when_enrollment_not_passed(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $action = $this->app->make(IssueAction::class);

        $this->expectException(EnrollmentNotPassedException::class);

        $action($enrollment);
    }

    public function test_throws_when_passed_status_but_passed_at_is_null(): void
    {
        $enrollment = Enrollment::factory()->create([
            'status' => EnrollmentStatus::Passed->value,
            'passed_at' => null,
        ]);

        $action = $this->app->make(IssueAction::class);

        $this->expectException(EnrollmentNotPassedException::class);

        $action($enrollment);
    }

    public function test_throws_already_issued_on_duplicate_enrollment_call(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $action($enrollment);

        $this->expectException(CertificateAlreadyIssuedException::class);

        $action($enrollment);
    }

    public function test_only_one_certificate_remains_after_duplicate_call_attempt(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $action($enrollment);

        try {
            $action($enrollment);
        } catch (CertificateAlreadyIssuedException $e) {
            // expected
        }

        $this->assertSame(1, Certificate::query()->where('enrollment_id', $enrollment->id)->count());
    }

    public function test_rolls_back_certificate_and_deletes_pdf_on_pdf_generation_failure(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $pdfMock = Mockery::mock(CertificatePdfService::class);
        $pdfMock->shouldReceive('generate')->once()->andThrow(new \RuntimeException('forced PDF failure'));
        $this->app->instance(CertificatePdfService::class, $pdfMock);

        $this->expectException(CertificateGenerationFailedException::class);

        try {
            $action = $this->app->make(IssueAction::class);
            $action($enrollment);
        } finally {
            // PDF 生成失敗時の Storage 保険削除を兼ねた挙動: DB は ROLLBACK で巻き戻り、Storage も orphan ファイルを残さない
            $this->assertSame(0, Certificate::query()->where('enrollment_id', $enrollment->id)->count());
            $remaining = Storage::disk('private')->allFiles('certificates');
            $this->assertEmpty($remaining, 'PDF orphan file should be cleaned up on rollback');
        }
    }

    public function test_pdf_content_does_not_contain_certification_code_label(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $certificate = $action($enrollment);

        $pdfBinary = Storage::disk('private')->get($certificate->pdf_path);
        $this->assertNotEmpty($pdfBinary);
        // PDF テンプレに「資格コード」項目を出力しないことを binary 探索で確認(spec REQ-068 の 7 要素のみ構成を担保)
        $this->assertStringNotContainsString('資格コード', (string) $pdfBinary);
    }
}
