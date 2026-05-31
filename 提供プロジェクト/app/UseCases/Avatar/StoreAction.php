<?php

declare(strict_types=1);

namespace App\UseCases\Avatar;

use App\Exceptions\SettingsProfile\AvatarStorageFailedException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * 認証ユーザー本人のアバター画像をアップロードして保存するユースケース。
 *
 * - (1) 新ファイルを `avatars/{ulid}.{ext}` パスへ Storage public driver で保存
 * - (2) `users.avatar_url` を `Storage::url(...)` の publicUrl で UPDATE(単一トランザクション)
 * - (3) UPDATE 成功後に旧 avatar が指していた Storage ファイルを best-effort で削除
 *
 * (1) 失敗時は AvatarStorageFailedException(500)、(2) 失敗時は (1) で保存した新ファイルを
 * Storage から削除してから例外を伝播する(旧ファイル / DB の avatar_url は未変更)。
 */
final class StoreAction
{
    public function __invoke(User $user, UploadedFile $file): User
    {
        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = "avatars/{$ulid}.{$ext}";

        try {
            Storage::disk('public')->putFileAs('avatars', $file, "{$ulid}.{$ext}");
        } catch (Throwable $e) {
            throw new AvatarStorageFailedException($e);
        }

        $oldUrl = $user->avatar_url;

        try {
            DB::transaction(function () use ($user, $path) {
                $user->update(['avatar_url' => Storage::url($path)]);
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        if ($oldUrl !== null && $oldUrl !== '') {
            $oldPath = self::resolveStoragePath($oldUrl);
            if ($oldPath !== null) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        return $user->fresh();
    }

    /**
     * 永続化されている `avatar_url`(例: `/storage/avatars/{ulid}.png`)から Storage public 配下の
     * 相対パス(例: `avatars/{ulid}.png`)を抽出する。URL の構造が想定外なら NULL を返し、
     * 旧ファイル削除をスキップする(orphan は残るが他人のファイルに触れない安全側に倒す)。
     */
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
