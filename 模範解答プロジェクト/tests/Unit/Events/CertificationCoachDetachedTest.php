<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\CertificationCoachDetached;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CertificationCoachDetached イベントが certification / coach / admin の 3 引数を保持することを検証する Unit テスト。
 * 担当コーチ解除時に発火し、Chat メンバー同期リスナーが受け取る。
 */
class CertificationCoachDetachedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_holds_certification_coach_and_admin(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();

        // Act
        $event = new CertificationCoachDetached($cert, $coach, $admin);

        // Assert
        $this->assertTrue($event->certification->is($cert), 'event->certification は解除対象資格を保持するはず');
        $this->assertTrue($event->coach->is($coach));
        $this->assertTrue($event->admin->is($admin));
    }
}
