<?php

declare(strict_types=1);

namespace App\Exceptions\QuizAnswering;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 送信された選択肢(SectionQuestionOption)が出題中の SectionQuestion に属していない場合に発火する例外。
 *
 * 不正なフォーム改ざんや、前画面から戻った後に問題マスタが入れ替わったケースなどで発火する。
 */
final class SectionQuestionOptionMismatchException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('選択肢が問題と一致しません。最新の出題画面から再度解答してください。', $previous);
    }
}
