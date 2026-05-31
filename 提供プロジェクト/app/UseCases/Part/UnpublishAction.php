<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * Part の下書きへの巻き戻しユースケース。Published → Draft の遷移のみ許可する。
 */
final class UnpublishAction
{
    /**
     * @throws ContentInvalidTransitionException
     */
    public function __invoke(Part $part): Part
    {
        if ($part->status !== ContentStatus::Published) {
            throw ContentInvalidTransitionException::forPart(
                $part->status,
                ContentStatus::Draft,
            );
        }

        return DB::transaction(function () use ($part) {
            $part->update(['status' => ContentStatus::Draft->value]);

            return $part->fresh();
        });
    }
}
