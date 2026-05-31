<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\CertificationCoachAttached;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CertificationCoachAttached イベントが certification / coach / admin の 3 引数を保持することを検証する Unit テスト。
 * 担当コーチ割当時に発火し、Chat メンバー同期リスナーが受け取る。
 */
class CertificationCoachAttachedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_holds_certification_coach_and_admin(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();

        // Act
        $event = new CertificationCoachAttached($cert, $coach, $admin);

        // Assert
        $this->assertTrue($event->certification->is($cert), 'event->certification は割当対象資格を保持するはず');
        $this->assertTrue($event->coach->is($coach));
        $this->assertTrue($event->admin->is($admin));
    }
}
