<?php

declare(strict_types=1);

namespace App\UseCases\QaReply\Moderation;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * admin モデレーション用の回答削除ユースケース。
 *
 * SoftDelete のみ実施し、親スレッドの status / resolved_at は変更しない (投稿者の解決判断を尊重)。
 * 回答件数はカウントから自動的に除外される (`withTrashed()` を明示しない限り)。
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
