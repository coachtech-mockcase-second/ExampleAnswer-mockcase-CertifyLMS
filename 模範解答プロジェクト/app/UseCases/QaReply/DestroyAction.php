<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 投稿者本人による回答削除ユースケース。SoftDelete のみ実施しスレッドの status / resolved_at は変更しない
 * (質問者の解決判断を尊重する)。
 */
final class DestroyAction
{
    public function __invoke(QaReply $reply): void
    {
        DB::transaction(function () use ($reply): void {
            $reply->delete();
        });
    }
}
