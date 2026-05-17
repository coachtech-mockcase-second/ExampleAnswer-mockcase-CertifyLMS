<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * Part の公開ユースケース。Draft → Published の遷移のみ許可する。
 */
final class PublishAction
{
    /**
     * @throws ContentInvalidTransitionException
     */
    public function __invoke(Part $part): Part
    {
        if ($part->status !== ContentStatus::Draft) {
            throw ContentInvalidTransitionException::forPart(
                $part->status,
                ContentStatus::Published,
            );
        }

        return DB::transaction(function () use ($part) {
            $part->update([
                'status' => ContentStatus::Published->value,
                'published_at' => now(),
            ]);

            return $part->fresh();
        });
    }
}
