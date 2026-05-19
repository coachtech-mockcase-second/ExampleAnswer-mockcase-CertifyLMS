<?php

declare(strict_types=1);

namespace App\Exceptions\SettingsProfile;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * アバター画像の Storage 保存に失敗した場合に投げる例外。
 *
 * DB の `users.avatar_url` を更新する前段(`Storage::disk('public')->putFileAs(...)`)で
 * 例外が発生したケースを表す。HTTP 500 として扱い、ユーザーには「時間をおいて再度お試しください」を促す。
 * 旧 avatar_url とファイルは未変更のまま保たれる(`Avatar\StoreAction` 側で保証)。
 */
final class AvatarStorageFailedException extends HttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(500, '画像のアップロードに失敗しました。時間をおいて再度お試しください。', $previous);
    }
}
