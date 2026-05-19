<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 資格マスタを更新するユースケース。`status` は本 Action では更新せず、公開状態遷移用 Action に責務分離する。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, category_id: string, difficulty: string, description?: ?string} $validated
     */
    public function __invoke(Certification $certification, User $admin, array $validated): Certification
    {
        return DB::transaction(function () use ($certification, $admin, $validated) {
            $certification->update([
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'difficulty' => $validated['difficulty'],
                'description' => $validated['description'] ?? null,
                'updated_by_user_id' => $admin->id,
            ]);

            return $certification->fresh();
        });
    }
}
