<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certificate;
use App\Services\CertificateSerialNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CertificateSerialNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_first_serial_for_current_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

        $serial = (new CertificateSerialNumberService)->generate();

        $this->assertSame('CT-202605-00001', $serial);
    }

    public function test_increments_within_same_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

        Certificate::factory()->withSerial('CT-202605-00042')->create();

        $serial = (new CertificateSerialNumberService)->generate();

        $this->assertSame('CT-202605-00043', $serial);
    }

    public function test_resets_counter_for_new_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-30 23:59:00'));
        Certificate::factory()->withSerial('CT-202605-00099')->create();

        Carbon::setTestNow(Carbon::parse('2026-06-01 00:00:01'));

        $serial = (new CertificateSerialNumberService)->generate();

        $this->assertSame('CT-202606-00001', $serial);
    }

    public function test_pads_to_five_digits(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

        Certificate::factory()->withSerial('CT-202605-00007')->create();

        $serial = (new CertificateSerialNumberService)->generate();

        $this->assertSame('CT-202605-00008', $serial);
        $this->assertMatchesRegularExpression('/^CT-\d{6}-\d{5}$/', $serial);
    }
}
