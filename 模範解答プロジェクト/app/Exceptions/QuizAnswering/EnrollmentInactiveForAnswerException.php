<?php

declare(strict_types=1);

namespace App\Exceptions\QuizAnswering;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 学習中(learning)または修了(passed)以外の Enrollment で解答送信を試みた際に発火する例外。
 *
 * 学習中止(failed)状態では Section 演習・苦手分野ドリルともに解答を記録できない。
 */
final class EnrollmentInactiveForAnswerException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('受講登録が学習中止状態のため、問題に解答できません。', $previous);
    }
}
