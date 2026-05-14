<?php

namespace Tests\Feature\UseCases\Certification;

use App\Exceptions\Certification\CertificationNotDeletableException;
use App\Models\Certification;
use App\UseCases\Certification\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deletes_draft_certification(): void
    {
        $cert = Certification::factory()->draft()->create();

        (new DestroyAction())($cert);

        $this->assertSoftDeleted('certifications', ['id' => $cert->id]);
    }

    public function test_throws_when_published(): void
    {
        $cert = Certification::factory()->published()->create();

        $this->expectException(CertificationNotDeletableException::class);

        (new DestroyAction())($cert);
    }

    public function test_throws_when_archived(): void
    {
        $cert = Certification::factory()->archived()->create();

        $this->expectException(CertificationNotDeletableException::class);

        (new DestroyAction())($cert);
    }
}
