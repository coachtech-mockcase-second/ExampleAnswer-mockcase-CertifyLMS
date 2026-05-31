<?php

declare(strict_types=1);

namespace App\UseCases\QuestionCategory;

use App\Exceptions\Content\QuestionCategoryInUseException;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 出題分野マスタの SoftDelete ユースケース。
 *
 * 共有マスタの削除ガードとして、演習問題(`\App\Models\SectionQuestion`) または模試問題
 * (`mock_exam_questions` テーブル) に 1 件でも参照が残っていれば QuestionCategoryInUseException(409)
 * を throw して削除を拒否する。模試問題テーブルが未マイグレーション環境では参照件数を 0 とみなす。
 */
final class DestroyAction
{
    /**
     * @throws QuestionCategoryInUseException
     */
    public function __invoke(QuestionCategory $category): void
    {
        $sectionQuestionCount = SectionQuestion::where('category_id', $category->id)
            ->count();

        $mockExamQuestionCount = Schema::hasTable('mock_exam_questions')
            ? DB::table('mock_exam_questions')
                ->where('category_id', $category->id)
                ->count()
            : 0;

        if ($sectionQuestionCount > 0 || $mockExamQuestionCount > 0) {
            throw new QuestionCategoryInUseException;
        }

        DB::transaction(fn () => $category->delete());
    }
}
