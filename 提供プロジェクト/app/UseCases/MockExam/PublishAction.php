<?php

declare(strict_types=1);

namespace App\UseCases\MockExam;

use App\Exceptions\MockExam\MockExamPublishNotAllowedException;
use App\Models\MockExam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 模試マスタを公開するユースケース。
 *
 * - 既に公開中なら `MockExamPublishNotAllowedException::forAlreadyPublished()`
 * - 問題が 0 件なら `MockExamPublishNotAllowedException::forNoQuestions()`
 */
final class PublishAction
{
    /**
     * @throws MockExamPublishNotAllowedException
     */
    public function __invoke(MockExam $mockExam, User $auth): MockExam
    {
        if ($mockExam->is_published) {
            throw MockExamPublishNotAllowedException::forAlreadyPublished();
        }

        $questionCount = $mockExam->mockExamQuestions()->count();
        if ($questionCount === 0) {
            throw MockExamPublishNotAllowedException::forNoQuestions();
        }

        return DB::transaction(function () use ($mockExam, $auth) {
            $mockExam->update([
                'is_published' => true,
                'published_at' => now(),
                'updated_by_user_id' => $auth->id,
            ]);

            return $mockExam->fresh();
        });
    }
}
