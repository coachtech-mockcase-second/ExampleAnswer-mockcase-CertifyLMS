<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MockExamSessionStatus;
use App\Enums\PassProbabilityBand;
use App\Models\Enrollment;
use App\Models\MockExamSession;
use App\Models\QuestionCategory;
use App\Services\Contracts\WeaknessAnalysisServiceContract;
use Illuminate\Support\Collection;

/**
 * 模試の解答ログから受講生の弱点分野・合格可能性を集計する Service。
 *
 * 受講生ダッシュボード / 模試結果画面 / 苦手分野ドリル導線で利用される。
 * quiz-answering Feature の Contract に対する正規実装として MockExamServiceProvider で bind される。
 *
 * 集計対象: 直近 3 件の Graded セッションを `getRecentGradedSessions()` で取得し、各 API で共通利用する。
 */
final class WeaknessAnalysisService implements WeaknessAnalysisServiceContract
{
    /**
     * 「直近 3 件の Graded セッションでの正答率が `passing_score_snapshot * 0.70` 未満」のカテゴリを返す。
     *
     * @return Collection<int, QuestionCategory>
     */
    public function getWeakCategories(Enrollment $enrollment): Collection
    {
        $sessions = $this->getRecentGradedSessions($enrollment);

        if ($sessions->isEmpty()) {
            return collect();
        }

        $sessionIds = $sessions->pluck('id')->all();
        $passingScore = (int) $sessions->avg('passing_score_snapshot');
        $threshold = $passingScore * 0.70;

        $rows = \DB::table('mock_exam_answers as a')
            ->join('mock_exam_questions as q', 'q.id', '=', 'a.mock_exam_question_id')
            ->whereIn('a.mock_exam_session_id', $sessionIds)
            ->groupBy('q.category_id')
            ->selectRaw('q.category_id as category_id, AVG(a.is_correct) * 100 as correct_rate')
            ->get();

        $weakCategoryIds = $rows
            ->filter(fn ($row) => (float) $row->correct_rate < $threshold)
            ->pluck('category_id')
            ->all();

        if (empty($weakCategoryIds)) {
            return collect();
        }

        return QuestionCategory::query()
            ->whereIn('id', $weakCategoryIds)
            ->ordered()
            ->get()
            ->values();
    }

    /**
     * 指定セッションの分野別ヒートマップ(各 QuestionCategory ごとの正答率) を返す。
     *
     * @return Collection<int, CategoryHeatmapCell>
     */
    public function getHeatmap(MockExamSession $session): Collection
    {
        $rows = \DB::table('mock_exam_answers as a')
            ->join('mock_exam_questions as q', 'q.id', '=', 'a.mock_exam_question_id')
            ->join('question_categories as c', 'c.id', '=', 'q.category_id')
            ->where('a.mock_exam_session_id', $session->id)
            ->groupBy('q.category_id', 'c.name', 'c.sort_order')
            ->orderBy('c.sort_order')
            ->selectRaw('q.category_id as category_id, c.name as category_name, COUNT(*) as total_count, SUM(a.is_correct) as correct_count')
            ->get();

        return $rows->map(function ($row) {
            $total = (int) $row->total_count;
            $correct = (int) $row->correct_count;
            $rate = $total > 0 ? round($correct / $total * 100, 2) : 0.0;

            return new CategoryHeatmapCell(
                categoryId: (string) $row->category_id,
                categoryName: (string) $row->category_name,
                totalCount: $total,
                correctCount: $correct,
                correctRate: $rate,
            );
        })->values();
    }

    /**
     * 複数 MockExamSession の分野別ヒートマップを一括取得する (運用エクスポート API の N+1 回避用)。
     *
     * graded セッションのみ集計対象とし、それ以外のセッション ID は空配列をキー含みで返さない
     * (Resource 側で `?? []` フォールバックする)。各要素は category_id / category_name / correct / total / rate の
     * 連想配列で、Apps Script から扱いやすい snake_case の素データ形式に揃える。
     *
     * @param Collection<int, MockExamSession> $sessions
     *
     * @return array<string, array<int, array{category_id: string, category_name: string, correct: int, total: int, rate: float}>>
     */
    public function batchHeatmap(Collection $sessions): array
    {
        if ($sessions->isEmpty()) {
            return [];
        }

        $gradedSessionIds = $sessions
            ->filter(fn (MockExamSession $session) => $session->status === MockExamSessionStatus::Graded)
            ->pluck('id')
            ->all();

        if ($gradedSessionIds === []) {
            return [];
        }

        $rows = \DB::table('mock_exam_answers as a')
            ->join('mock_exam_questions as q', 'q.id', '=', 'a.mock_exam_question_id')
            ->join('question_categories as c', 'c.id', '=', 'q.category_id')
            ->whereIn('a.mock_exam_session_id', $gradedSessionIds)
            ->groupBy('a.mock_exam_session_id', 'q.category_id', 'c.name', 'c.sort_order')
            ->orderBy('a.mock_exam_session_id')
            ->orderBy('c.sort_order')
            ->selectRaw('a.mock_exam_session_id as session_id, q.category_id as category_id, c.name as category_name, COUNT(*) as total_count, SUM(a.is_correct) as correct_count')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $total = (int) $row->total_count;
            $correct = (int) $row->correct_count;
            $rate = $total > 0 ? round($correct / $total * 100, 2) : 0.0;

            $result[$row->session_id][] = [
                'category_id' => (string) $row->category_id,
                'category_name' => (string) $row->category_name,
                'correct' => $correct,
                'total' => $total,
                'rate' => $rate,
            ];
        }

        return $result;
    }

    /**
     * 直近 3 件の Graded セッションの平均得点率と `passing_score * 0.90 / 0.70` で 3 バンドに分け、
     * 採点済セッションが 0 件の場合は Unknown を返す。
     */
    public function getPassProbabilityBand(Enrollment $enrollment): PassProbabilityBand
    {
        $sessions = $this->getRecentGradedSessions($enrollment);

        if ($sessions->isEmpty()) {
            return PassProbabilityBand::Unknown;
        }

        $avgScore = (float) $sessions->avg('score_percentage');
        $passingScore = (int) $sessions->avg('passing_score_snapshot');
        $safeThreshold = $passingScore * 0.90;
        $warningThreshold = $passingScore * 0.70;

        if ($avgScore >= $safeThreshold) {
            return PassProbabilityBand::Safe;
        }

        if ($avgScore >= $warningThreshold) {
            return PassProbabilityBand::Warning;
        }

        return PassProbabilityBand::Danger;
    }

    /**
     * 当該 Enrollment の直近 3 件の Graded セッションを取得する。
     *
     * @return Collection<int, MockExamSession>
     */
    private function getRecentGradedSessions(Enrollment $enrollment): Collection
    {
        return MockExamSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', MockExamSessionStatus::Graded->value)
            ->orderByDesc('graded_at')
            ->limit(3)
            ->get();
    }
}
