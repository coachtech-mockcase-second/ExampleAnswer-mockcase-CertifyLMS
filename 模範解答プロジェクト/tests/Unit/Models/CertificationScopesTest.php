<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificationScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_published_returns_only_published_certifications(): void
    {
        Certification::factory()->draft()->create();
        Certification::factory()->published()->create();
        Certification::factory()->archived()->create();

        $results = Certification::query()->published()->get();

        $this->assertCount(1, $results);
        $this->assertSame('published', $results->first()->status->value);
    }

    public function test_scope_assigned_to_returns_only_coach_assigned_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $assigned = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assigned->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $notAssigned = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $notAssigned->id,
            'user_id' => $otherCoach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $results = Certification::query()->assignedTo($coach)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($assigned));
    }

    public function test_scope_keyword_matches_name_only(): void
    {
        $foo = Certification::factory()->draft()->create(['name' => 'Foo Certificate']);
        Certification::factory()->draft()->create(['name' => 'Bar Certificate']);
        $baz = Certification::factory()->draft()->create(['name' => 'Baz Foundation']);

        $byFoo = Certification::query()->keyword('Foo')->get();
        $this->assertCount(1, $byFoo);
        $this->assertTrue($byFoo->first()->is($foo));

        $byFoundation = Certification::query()->keyword('Foundation')->get();
        $this->assertCount(1, $byFoundation);
        $this->assertTrue($byFoundation->first()->is($baz));

        $empty = Certification::query()->keyword(null)->get();
        $this->assertCount(3, $empty);
    }
}
