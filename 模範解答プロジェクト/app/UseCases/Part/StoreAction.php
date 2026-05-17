<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * Part の新規作成ユースケース。同一資格配下の MAX(order)+1 を採番し、status=Draft 固定で INSERT する。
 */
final class StoreAction
{
    /**
     * @param array{title: string, description?: ?string} $validated
     */
    public function __invoke(Certification $certification, array $validated): Part
    {
        return DB::transaction(function () use ($certification, $validated) {
            $maxOrder = $certification->parts()->lockForUpdate()->max('order') ?? 0;

            return $certification->parts()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => ContentStatus::Draft->value,
                'order' => $maxOrder + 1,
                'published_at' => null,
            ]);
        });
    }
}
