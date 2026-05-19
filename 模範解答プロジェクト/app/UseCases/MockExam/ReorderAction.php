<?php

declare(strict_types=1);

namespace App\UseCases\MockExam;

use App\Models\MockExam;
use Illuminate\Support\Facades\DB;

/**
 * 同一資格内の模試マスタの並び順(`order`) を一括更新するユースケース。
 */
final class ReorderAction
{
    /**
     * @param string $certificationId 対象資格 ID(整合性検証用、Controller 側で渡された値)
     * @param array<int, array{id: string, order: int}> $items 並び順を更新する MockExam 一覧
     */
    public function __invoke(string $certificationId, array $items): void
    {
        DB::transaction(function () use ($certificationId, $items) {
            foreach ($items as $item) {
                MockExam::query()
                    ->where('id', $item['id'])
                    ->where('certification_id', $certificationId)
                    ->update(['order' => $item['order']]);
            }
        });
    }
}
