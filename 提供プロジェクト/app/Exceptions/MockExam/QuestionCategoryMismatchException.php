<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 模試問題の作成・更新時に、指定された QuestionCategory が親 MockExam の Certification 配下に属していない場合の例外(HTTP 422)。
 *
 * QuestionCategory は Certification ごとに保持されるため、異なる資格のカテゴリを誤って指定するのを防ぐ。
 */
final class QuestionCategoryMismatchException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('指定された出題分野はこの模試の資格に登録されていません。', $previous);
    }
}
