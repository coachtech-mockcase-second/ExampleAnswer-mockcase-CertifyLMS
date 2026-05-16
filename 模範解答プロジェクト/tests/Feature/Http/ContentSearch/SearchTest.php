<?php

declare(strict_types=1);

namespace Tests\Feature\Http\ContentSearch;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_student_finds_published_section_in_enrolled_certification(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'certification_id' => $cert->id]);

        [$part, $chapter, $section] = $this->makePartChain($cert, 'published');
        $section->update(['title' => 'TCP/IP 入門', 'body' => 'TCP/IP は ...']);

        $this->actingAs($student)
            ->get(route('contents.search', ['certification_id' => $cert->id, 'keyword' => 'TCP']))
            ->assertOk()
            ->assertSee('TCP/IP 入門');
    }

    public function test_draft_section_not_returned(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'certification_id' => $cert->id]);

        [$part, $chapter, $section] = $this->makePartChain($cert, 'draft');
        $section->update(['title' => 'Hidden topic', 'body' => 'Hidden topic body']);

        $this->actingAs($student)
            ->get(route('contents.search', ['certification_id' => $cert->id, 'keyword' => 'Hidden']))
            ->assertOk()
            ->assertDontSee('Hidden topic');
    }

    public function test_unenrolled_certification_returns_empty(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        // Enrollment なし

        [$part, $chapter, $section] = $this->makePartChain($cert, 'published');
        $section->update(['title' => 'Database basics', 'body' => 'SQL ...']);

        $this->actingAs($student)
            ->get(route('contents.search', ['certification_id' => $cert->id, 'keyword' => 'SQL']))
            ->assertOk()
            ->assertDontSee('Database basics');
    }

    public function test_empty_keyword_returns_empty_paginator(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'certification_id' => $cert->id]);
        [$part, $chapter, $section] = $this->makePartChain($cert, 'published');
        $section->update(['title' => 'UNIQUE_SEARCH_TITLE', 'body' => 'body']);

        $this->actingAs($student)
            ->get(route('contents.search', ['certification_id' => $cert->id]))
            ->assertOk()
            ->assertDontSee('UNIQUE_SEARCH_TITLE');
    }
}
