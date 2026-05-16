<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentNotDeletableException;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

class DestroyAction
{
    public function __invoke(Part $part): void
    {
        if ($part->status !== ContentStatus::Draft) {
            throw new ContentNotDeletableException(entity: 'Part');
        }

        DB::transaction(fn () => $part->delete());
    }
}
