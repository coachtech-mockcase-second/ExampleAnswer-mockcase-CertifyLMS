<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Enrollment;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\UseCases\Enrollment\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrollment 詳細取得 Action `ShowAction` の eager load を検証する Feature テスト。
 * goals が表示順(未達成優先 → 目標期日昇順 → 達成済は末尾)で読み込まれることを網羅する。
 */
class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_goals_in_display_order(): void
    {
        // Arrange: 達成済(期日最短) + 未達成(期日遠 / 近) を投入し、表示順で並ぶことを確認する
        $enrollment = Enrollment::factory()->learning()->create();
        EnrollmentGoal::factory()->for($enrollment)->achieved()->create([
            'title' => 'achieved', 'target_date' => now()->addDay()->toDateString(),
        ]);
        EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => 'far', 'target_date' => now()->addDays(30)->toDateString(), 'achieved_at' => null,
        ]);
        EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => 'near', 'target_date' => now()->addDays(5)->toDateString(), 'achieved_at' => null,
        ]);

        // Act
        $result = app(ShowAction::class)($enrollment);

        // Assert
        $this->assertSame(
            ['near', 'far', 'achieved'],
            $result->goals->pluck('title')->all(),
            'ShowAction は goals を未達成優先 → 期日昇順 → 達成済末尾で eager load するはず',
        );
    }
}
