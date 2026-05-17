<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 資格マスタを新規作成するユースケース。`status=draft` で INSERT し、admin を created_by / updated_by に記録する。
 */
final class StoreAction
{
    /**
     * @param  array{name: string, category_id: string, difficulty: string, description?: ?string}  $validated
     */
    public function __invoke(User $admin, array $validated): Certification
    {
        return DB::transaction(fn () => Certification::create([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'difficulty' => $validated['difficulty'],
            'description' => $validated['description'] ?? null,
            'status' => CertificationStatus::Draft->value,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
            'published_at' => null,
            'archived_at' => null,
        ]));
    }
}
