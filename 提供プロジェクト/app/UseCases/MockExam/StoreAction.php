<?php

declare(strict_types=1);

namespace App\UseCases\MockExam;

use App\Models\MockExam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 模試マスタを新規作成するユースケース。`is_published = false` で INSERT し、admin / coach を created_by / updated_by に記録する。
 */
final class StoreAction
{
    /**
     * @param array{certification_id: string, title: string, description?: ?string, order: int, passing_score: int} $validated
     */
    public function __invoke(User $auth, array $validated): MockExam
    {
        return DB::transaction(fn () => MockExam::create([
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'order' => $validated['order'],
            'passing_score' => $validated['passing_score'],
            'is_published' => false,
            'published_at' => null,
            'created_by_user_id' => $auth->id,
            'updated_by_user_id' => $auth->id,
        ]));
    }
}
