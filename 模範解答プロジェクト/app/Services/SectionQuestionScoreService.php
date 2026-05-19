<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 教材画面の演習タブ / Section 詳細画面の演習リンクに表示する Section 単位のスコアサマリを集計する Service。
 *
 * 「1 ラウンド」= その Section 配下の公開済 SectionQuestion を全部解いた直近の一連の解答群、と定義する。
 * 全問解答を 1 度も完了していない Section については bestScore / latestScore を null として返す。
 */
final class SectionQuestionScoreService
{
    public function summarize(User $user, Section $section): SectionQuestionScoreSummary
    {
        $publishedQuestionIds = SectionQuestion::query()
            ->where('section_id', $section->id)
            ->where('status', ContentStatus::Published->value)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($publishedQuestionIds === []) {
            return SectionQuestionScoreSummary::empty();
        }

        return $this->buildSummary($user, $publishedQuestionIds);
    }

    /**
     * Enrollment 配下の全 Section についてサマリを 1 ショットの集約クエリで構築する。
     * Section→SectionQuestion→SectionQuestionAttempt / SectionQuestionAnswer をまとめて読み込み、
     * メモリ上で集計することでセクション数 N に比例した SELECT 増を防ぐ。
     *
     * @return Collection<string, SectionQuestionScoreSummary> キーは Section.id
     */
    public function batchSummarize(User $user, Enrollment $enrollment): Collection
    {
        $publishedQuestions = SectionQuestion::query()
            ->whereHas(
                'section.chapter.part',
                fn ($q) => $q->where('certification_id', $enrollment->certification_id),
            )
            ->where('status', ContentStatus::Published->value)
            ->orderBy('id')
            ->get(['id', 'section_id', 'order']);

        $questionsBySectionId = $publishedQuestions->groupBy('section_id');
        $questionIds = $publishedQuestions->pluck('id')->all();

        if ($questionIds === []) {
            return $this->mergeMissingSections(collect(), $enrollment);
        }

        $attempts = SectionQuestionAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('section_question_id', $questionIds)
            ->get(['section_question_id', 'attempt_count', 'correct_count', 'last_answered_at'])
            ->groupBy('section_question_id');

        $answers = SectionQuestionAnswer::query()
            ->where('user_id', $user->id)
            ->whereIn('section_question_id', $questionIds)
            ->orderBy('answered_at')
            ->get(['section_question_id', 'is_correct', 'answered_at'])
            ->groupBy('section_question_id');

        $result = collect();

        foreach ($questionsBySectionId as $sectionId => $sectionQuestions) {
            $ids = $sectionQuestions->pluck('id')->all();

            $sectionAttempts = collect($ids)->flatMap(fn ($id) => $attempts->get($id, collect()));
            $sectionAnswers = collect($ids)
                ->flatMap(fn ($id) => $answers->get($id, collect()))
                ->sortBy('answered_at')
                ->values();

            $result->put((string) $sectionId, $this->buildSummaryFromCollections($ids, $sectionAttempts, $sectionAnswers));
        }

        return $this->mergeMissingSections($result, $enrollment);
    }

    /**
     * @param array<int, string> $publishedQuestionIds
     */
    private function buildSummary(User $user, array $publishedQuestionIds): SectionQuestionScoreSummary
    {
        $attempts = SectionQuestionAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('section_question_id', $publishedQuestionIds)
            ->get(['section_question_id', 'attempt_count', 'correct_count', 'last_answered_at']);

        $answers = SectionQuestionAnswer::query()
            ->where('user_id', $user->id)
            ->whereIn('section_question_id', $publishedQuestionIds)
            ->orderBy('answered_at')
            ->get(['section_question_id', 'is_correct', 'answered_at']);

        return $this->buildSummaryFromCollections($publishedQuestionIds, $attempts, $answers);
    }

    /**
     * @param array<int, string> $publishedQuestionIds
     * @param Collection<int, SectionQuestionAttempt> $attempts
     * @param Collection<int, SectionQuestionAnswer> $answers
     */
    private function buildSummaryFromCollections(
        array $publishedQuestionIds,
        Collection $attempts,
        Collection $answers,
    ): SectionQuestionScoreSummary {
        $totalAttempts = (int) $attempts->sum('attempt_count');
        $totalCorrect = (int) $attempts->sum('correct_count');
        $latestAnsweredAt = $attempts->max('last_answered_at');

        $accuracy = $totalAttempts > 0 ? $totalCorrect / $totalAttempts : null;

        $rounds = $this->groupIntoRounds($answers, $publishedQuestionIds);
        [$bestScore, $latestScore] = $this->extractRoundScores($rounds);

        return new SectionQuestionScoreSummary(
            attemptCount: $totalAttempts,
            bestScore: $bestScore,
            latestScore: $latestScore,
            latestAnsweredAt: $latestAnsweredAt !== null ? Carbon::parse($latestAnsweredAt) : null,
            accuracyRate: $accuracy,
        );
    }

    /**
     * @param Collection<int, Collection<int, SectionQuestionAnswer>> $rounds
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function extractRoundScores(Collection $rounds): array
    {
        if ($rounds->isEmpty()) {
            return [null, null];
        }

        $bestScore = null;
        foreach ($rounds as $round) {
            $score = $round->where('is_correct', true)->count();
            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
            }
        }

        $latestScore = $rounds->last()->where('is_correct', true)->count();

        return [$bestScore, $latestScore];
    }

    /**
     * 解答ログを「全 SectionQuestion を最初に解き終えるまで」を 1 ラウンドとして区切る。
     * 同一ラウンド内で同じ SectionQuestion を複数回解いた場合は、最初の正誤判定をそのラウンドのスコアとして採用する。
     *
     * @param Collection<int, SectionQuestionAnswer> $answers
     * @param array<int, string> $publishedQuestionIds
     *
     * @return Collection<int, Collection<int, SectionQuestionAnswer>>
     */
    private function groupIntoRounds(Collection $answers, array $publishedQuestionIds): Collection
    {
        $rounds = collect();
        $currentRound = collect();
        $seen = [];

        foreach ($answers as $answer) {
            $questionId = $answer->section_question_id;

            if (isset($seen[$questionId])) {
                continue;
            }

            $currentRound->push($answer);
            $seen[$questionId] = true;

            if (count($seen) === count($publishedQuestionIds)) {
                $rounds->push($currentRound);
                $currentRound = collect();
                $seen = [];
            }
        }

        return $rounds;
    }

    /**
     * 公開済 SectionQuestion を 1 件も持たない Section について empty summary を補完する。
     *
     * @param Collection<string, SectionQuestionScoreSummary> $summaries
     *
     * @return Collection<string, SectionQuestionScoreSummary>
     */
    private function mergeMissingSections(Collection $summaries, Enrollment $enrollment): Collection
    {
        $allSectionIds = Section::query()
            ->whereHas(
                'chapter.part',
                fn ($q) => $q->where('certification_id', $enrollment->certification_id),
            )
            ->pluck('id');

        foreach ($allSectionIds as $sectionId) {
            $key = (string) $sectionId;
            if (! $summaries->has($key)) {
                $summaries->put($key, SectionQuestionScoreSummary::empty());
            }
        }

        return $summaries;
    }
}
