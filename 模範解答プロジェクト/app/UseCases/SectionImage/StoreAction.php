<?php

declare(strict_types=1);

namespace App\UseCases\SectionImage;

use App\Exceptions\Content\SectionImageStorageException;
use App\Models\Section;
use App\Models\SectionImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 教材内画像のアップロードユースケース。
 *
 * `section-images/{ulid}.{ext}` 形式で public disk に保存し、DB へメタデータ(path / original_filename / mime_type / size_bytes)を INSERT する。
 * Storage 保存と DB INSERT を単一トランザクション内で実行し、いずれかが失敗した場合は ROLLBACK + 保険的に Storage を削除して orphan ファイルを残さない。
 */
final class StoreAction
{
    /**
     * @throws SectionImageStorageException
     */
    public function __invoke(Section $section, UploadedFile $file): SectionImage
    {
        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = "section-images/{$ulid}.{$ext}";

        try {
            return DB::transaction(function () use ($section, $file, $path, $ulid, $ext) {
                Storage::disk('public')->putFileAs(
                    'section-images',
                    $file,
                    "{$ulid}.{$ext}",
                );

                return $section->images()->create([
                    'path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?? 0,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw new SectionImageStorageException($e);
        }
    }
}
