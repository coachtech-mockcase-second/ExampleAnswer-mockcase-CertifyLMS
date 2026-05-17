<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 公開中(published)の追加面談 SKU マスタが削除されようとした際に throw される(HTTP 409)。
 * 削除する前に下書き(draft)に戻すか、新規購入を止めるならアーカイブ(archived)を選択する。
 */
final class MeetingQuotaPlanNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            '公開中の追加面談プランは削除できません。先に下書きに戻すか、アーカイブしてください。',
            $previous,
        );
    }
}
