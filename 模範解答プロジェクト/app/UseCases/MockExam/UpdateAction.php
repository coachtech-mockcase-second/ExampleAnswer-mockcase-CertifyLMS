<?php

declare(strict_types=1);

namespace App\UseCases\MockExam;

use App\Models\MockExam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 模試マスタを更新するユースケース。`certification_id` は不可変で、`title` / `description` / `order` / `passing_score` のみ更新する。
 */
final class UpdateAction
{
    /**
     * @param  array{title: string, description?: ?string, order: int, passing_score: int}  $validated
     */
    public function __invoke(MockExam $mockExam, User $auth, array $validated): MockExam
    {
        return DB::transaction(function () use ($mockExam, $auth, $validated) {
            $mockExam->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'order' => $validated['order'],
                'passing_score' => $validated['passing_score'],
                'updated_by_user_id' => $auth->id,
            ]);

            return $mockExam->fresh();
        });
    }
}
