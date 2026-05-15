<?php

namespace App\UseCases\SectionImage;

use App\Exceptions\Content\SectionImageStorageException;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreAction
{
    public function __invoke(Section $section, User $actor, UploadedFile $file): SectionImage
    {
        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = "section-images/{$ulid}.{$ext}";

        Storage::disk('public')->putFileAs(
            'section-images',
            $file,
            "{$ulid}.{$ext}",
        );

        try {
            return DB::transaction(fn () => $section->images()->create([
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?? 0,
            ]));
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw new SectionImageStorageException(previous: $e);
        }
    }
}
