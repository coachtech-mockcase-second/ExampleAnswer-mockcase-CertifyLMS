<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AnswerSource;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SectionQuestionAnswer モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 3 リレーション (user / sectionQuestion / selectedOption) +
 * 主要 scope 4 (forUser / forSection / correct / incorrect) + 3 cast (is_correct bool / answered_at datetime / source enum) を網羅する。
 */
class SectionQuestionAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $answer = SectionQuestionAnswer::factory()->for($user)->create();

        // Act
        $owner = $answer->user;

        // Assert
        $this->assertTrue($owner->is($user));
    }

    public function test_section_question_relation_returns_target_question(): void
    {
        // Arrange
        $question = SectionQuestion::factory()->published()->create();
        $answer = SectionQuestionAnswer::factory()->for($question, 'sectionQuestion')->create();

        // Act
        $target = $answer->sectionQuestion;

        // Assert
        $this->assertTrue($target->is($question));
    }

    public function test_selected_option_relation_returns_chosen_option(): void
    {
        // Arrange
        $question = SectionQuestion::factory()->published()->create();
        $option = SectionQuestionOption::factory()->for($question, 'sectionQuestion')->correct()->create();
        $answer = SectionQuestionAnswer::factory()
            ->for($question, 'sectionQuestion')
            ->for($option, 'selectedOption')
            ->create();

        // Act
        $chosen = $answer->selectedOption;

        // Assert
        $this->assertNotNull($chosen);
        $this->assertTrue($chosen->is($option));
    }

    public function test_scope_for_user_filters_by_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $own = SectionQuestionAnswer::factory()->for($student)->create();
        SectionQuestionAnswer::factory()->create();

        // Act
        $results = SectionQuestionAnswer::forUser($student)->get();

        // Assert
        $this->assertCount(1, $results, '対象 user の answer のみが取得されるはず');
        $this->assertTrue($results->first()->is($own));
    }

    public function test_scope_for_section_filters_by_section_id(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();
        $sectionQuestion = SectionQuestion::factory()->for($section)->published()->create();
        $matching = SectionQuestionAnswer::factory()->for($sectionQuestion, 'sectionQuestion')->create();
        SectionQuestionAnswer::factory()->create();

        // Act
        $results = SectionQuestionAnswer::forSection($section->id)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($matching));
    }

    public function test_scope_correct_filters_only_correct_answers(): void
    {
        // Arrange
        $correct = SectionQuestionAnswer::factory()->correct()->create();
        SectionQuestionAnswer::factory()->incorrect()->create();

        // Act
        $results = SectionQuestionAnswer::correct()->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($correct));
    }

    public function test_is_correct_and_source_casts(): void
    {
        // Arrange
        $answer = SectionQuestionAnswer::factory()->correct()->source(AnswerSource::SectionQuiz)->create();

        // Act
        $fresh = $answer->fresh();

        // Assert
        $this->assertIsBool($fresh->is_correct);
        $this->assertTrue($fresh->is_correct);
        $this->assertInstanceOf(AnswerSource::class, $fresh->source, 'source は AnswerSource enum にキャストされるはず');
        $this->assertSame(AnswerSource::SectionQuiz, $fresh->source);
    }

    public function test_answered_at_cast_returns_carbon(): void
    {
        // Arrange
        $answer = SectionQuestionAnswer::factory()->create([
            'answered_at' => '2026-05-20 12:00:00',
        ]);

        // Act
        $fresh = $answer->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->answered_at);
    }
}
