<?php

namespace App\UseCases\Section;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentNotDeletableException;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

class DestroyAction
{
    public function __invoke(Section $section): void
    {
        if ($section->status !== ContentStatus::Draft) {
            throw new ContentNotDeletableException(entity: 'Section');
        }

        DB::transaction(fn () => $section->delete());
    }
}
