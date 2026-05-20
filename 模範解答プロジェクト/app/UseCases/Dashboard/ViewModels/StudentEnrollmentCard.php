<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard\ViewModels;

use App\Enums\EnrollmentStatus;
use App\Enums\PassProbabilityBand;
use App\Enums\TermType;
use App\Services\Learning\LearningHourTargetSummary;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * 受講生ダッシュボードの「受講中資格カード」1 枚を表す ViewModel DTO。
 *
 * 受講中(learning) / 修了済(passed) 両方のカードを表現する。
 * Service 例外で取得失敗したセクションは nullable プロパティに null が入り、Blade 側で empty-state 表示に切り替える。
 */
final readonly class StudentEnrollmentCard
{
    /**
     * @param  Collection<int, \App\Models\QuestionCategory>  $weakCategories  上位 3 件まで、取得失敗時は空 collection
     */
    public function __construct(
        public string $enrollmentId,
        public string $certificationName,
        public EnrollmentStatus $status,
        public bool $isPassed,
        public ?CarbonInterface $examDate,
        public ?int $daysUntilExam,
        public ?float $progressRatio,
        public TermType $currentTerm,
        public ?LearningHourTargetSummary $learningHourTarget,
        public ?PassProbabilityBand $passProbabilityBand,
        public Collection $weakCategories,
        public bool $canReceiveCertificate,
        public ?string $certificateDownloadUrl,
    ) {}
}
