<?php

declare(strict_types=1);

namespace App\UseCases\Avatar;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 認証ユーザー本人のアバター画像を削除し、`users.avatar_url` を NULL に戻すユースケース。
 *
 * 単一トランザクション内で (1) Storage からファイル削除、(2) `users.avatar_url = NULL` UPDATE を実施する。
 * Storage に該当ファイルがなかった場合(既に削除済 / 想定外パス)は静かに skip して DB のみ NULL 化する
 * (UI 側ではイニシャルアバターにフォールバックする `<x-avatar>` が描画される)。
 */
final class DestroyAction
{
    public function __invoke(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $oldUrl = $user->avatar_url;
            if ($oldUrl !== null && $oldUrl !== '') {
                $oldPath = self::resolveStoragePath($oldUrl);
                if ($oldPath !== null) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $user->update(['avatar_url' => null]);

            return $user->fresh();
        });
    }

    private static function resolveStoragePath(string $url): ?string
    {
        $urlPath = parse_url($url, PHP_URL_PATH);
        if (! is_string($urlPath) || $urlPath === '') {
            return null;
        }

        $trimmed = ltrim($urlPath, '/');
        if (! str_starts_with($trimmed, 'storage/')) {
            return null;
        }

        return substr($trimmed, strlen('storage/'));
    }
}
