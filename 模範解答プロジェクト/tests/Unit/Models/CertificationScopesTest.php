<?php

namespace Tests\Unit\Models;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $assigned = Certification::factory()->published()->create();
        $assigned->coaches()->syncWithoutDetaching([
            $coach->id => [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'assigned_by_user_id' => User::factory()->admin()->create()->id,
                'assigned_at' => now(),
            ],
        ]);

        $notAssigned = Certification::factory()->published()->create();
        $notAssigned->coaches()->syncWithoutDetaching([
            $otherCoach->id => [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'assigned_by_user_id' => User::factory()->admin()->create()->id,
                'assigned_at' => now(),
            ],
        ]);

        $results = Certification::query()->assignedTo($coach)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($assigned));
    }

    public function test_scope_keyword_matches_code_or_name(): void
    {
        Certification::factory()->draft()->create(['code' => 'CERT-AAA111', 'name' => 'Foo Certificate']);
        Certification::factory()->draft()->create(['code' => 'CERT-BBB222', 'name' => 'Bar Certificate']);
        Certification::factory()->draft()->create(['code' => 'CERT-CCC333', 'name' => 'Baz Foundation']);

        $byCode = Certification::query()->keyword('AAA')->get();
        $this->assertCount(1, $byCode);
        $this->assertSame('CERT-AAA111', $byCode->first()->code);

        $byName = Certification::query()->keyword('Foundation')->get();
        $this->assertCount(1, $byName);
        $this->assertSame('CERT-CCC333', $byName->first()->code);

        $empty = Certification::query()->keyword(null)->get();
        $this->assertCount(3, $empty);
    }
}
