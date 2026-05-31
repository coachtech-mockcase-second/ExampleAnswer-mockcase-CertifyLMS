<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard\ViewModels;

use App\Models\MeetingPack;
use Illuminate\Support\Collection;

/**
 * 受講生ダッシュボード上部のプラン情報パネルを表す ViewModel DTO。
 *
 * Plan 名 / プラン残日数 / 残面談回数 / 追加面談購入 CTA(MeetingPack 一覧)を集約する。
 * 残面談回数が 0 の場合は受講生に追加面談購入を強調表示する判断材料となる。
 */
final readonly class PlanInfoPanel
{
    /**
     * @param Collection<int, MeetingPack> $meetingPacks 購入動線で表示する published 状態の SKU 一覧
     */
    public function __construct(
        public ?string $planName,
        public ?int $courseDaysRemaining,
        public int $meetingsRemaining,
        public Collection $meetingPacks,
    ) {}
}
