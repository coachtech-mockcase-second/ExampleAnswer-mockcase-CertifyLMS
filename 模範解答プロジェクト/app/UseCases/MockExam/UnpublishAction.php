<?php

declare(strict_types=1);

namespace App\UseCases\MockExam;

use App\Exceptions\MockExam\MockExamPublishNotAllowedException;
use App\Models\MockExam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 模試マスタの公開を停止するユースケース。
 *
 * `is_published = false` の状態から呼ばれた場合は `MockExamPublishNotAllowedException::forNotPublished()` を投げる。
 */
final class UnpublishAction
{
    /**
     * @throws MockExamPublishNotAllowedException
     */
    public function __invoke(MockExam $mockExam, User $auth): MockExam
    {
        if (! $mockExam->is_published) {
            throw MockExamPublishNotAllowedException::forNotPublished();
        }

        return DB::transaction(function () use ($mockExam, $auth) {
            $mockExam->update([
                'is_published' => false,
                'published_at' => null,
                'updated_by_user_id' => $auth->id,
            ]);

            return $mockExam->fresh();
        });
    }
}
