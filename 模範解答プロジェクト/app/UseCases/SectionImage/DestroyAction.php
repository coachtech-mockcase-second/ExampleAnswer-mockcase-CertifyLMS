<?php

declare(strict_types=1);

namespace App\UseCases\SectionImage;

use App\Models\SectionImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DestroyAction
{
    public function __invoke(SectionImage $image): void
    {
        DB::transaction(function () use ($image) {
            $path = $image->path;
            $image->delete();
            DB::afterCommit(fn () => Storage::disk('public')->delete($path));
        });
    }
}
