<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationInvalidTransitionException;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Certification\PublishAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_draft_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();

        $result = (new PublishAction)($cert, $admin);

        $this->assertSame(CertificationStatus::Published, $result->status);
        $this->assertNotNull($result->published_at);
        $this->assertSame($admin->id, $result->updated_by_user_id);
    }

    public function test_throws_when_already_published(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $this->expectException(CertificationInvalidTransitionException::class);

        (new PublishAction)($cert, $admin);
    }

    public function test_throws_when_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->archived()->create();

        $this->expectException(CertificationInvalidTransitionException::class);

        (new PublishAction)($cert, $admin);
    }
}
