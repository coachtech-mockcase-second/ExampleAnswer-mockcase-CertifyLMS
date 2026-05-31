<?php

declare(strict_types=1);

namespace App\UseCases\SectionImage;

use App\Models\SectionImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 教材内画像の削除ユースケース。
 *
 * DB 側を SoftDelete してから commit 後に Storage 上の実ファイルを削除する。
 * トランザクション ROLLBACK 時には Storage 削除をスキップし、不可逆な実ファイル削除を防ぐ。
 */
final class DestroyAction
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
