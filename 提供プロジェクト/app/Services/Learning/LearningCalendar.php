<?php

declare(strict_types=1);

namespace App\Services\Learning;

/**
 * 受講生ダッシュボードの学習カレンダー (日別学習時間ヒートマップ) のデータ DTO。
 * LearningCalendarService::build の戻り値。草グリッドの描画 (濃淡レベル離散化・グリッド構築) は
 * フロント (resources/js/dashboard/learning-calendar.js) が daysMap を読んで行う。
 */
final readonly class LearningCalendar
{
    /**
     * @param  array<string, int>  $daysMap  'Y-m-d' → その日の学習時間 (分)
     * @param  int  $monthTotalMinutes  今日が属する月の学習時間合計 (分)
     * @param  string  $today  グリッドの基準日 ('Y-m-d')
     */
    public function __construct(
        public array $daysMap,
        public int $monthTotalMinutes,
        public string $today,
    ) {}
}
