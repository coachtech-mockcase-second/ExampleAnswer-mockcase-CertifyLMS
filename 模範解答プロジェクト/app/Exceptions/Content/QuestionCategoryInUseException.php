<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * QuestionCategory 削除時、SectionQuestion または MockExamQuestion(模試問題)から参照されている場合に throw される。
 * 共有マスタとして両系統からの参照を確認し、いずれかに参照ありなら削除を拒否する。
 */
final class QuestionCategoryInUseException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('このカテゴリは問題に紐付いているため削除できません。', $previous);
    }
}
