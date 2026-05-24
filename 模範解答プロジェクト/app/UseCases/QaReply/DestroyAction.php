<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 質問への回答削除ユースケース。投稿者本人 / admin モデレーションの両方で共通利用される。
 * 物理削除のみ実施し、親スレッドの status / resolved_at は変更しない(質問者の解決判断を尊重する)。
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
