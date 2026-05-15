<?php

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(Certification $certification, User $actor, array $validated): Part
    {
        return DB::transaction(function () use ($certification, $validated) {
            $maxOrder = $certification->parts()->lockForUpdate()->max('order') ?? 0;

            return $certification->parts()->create([
                ...$validated,
                'status' => ContentStatus::Draft->value,
                'order' => $maxOrder + 1,
                'published_at' => null,
            ]);
        });
    }
}
