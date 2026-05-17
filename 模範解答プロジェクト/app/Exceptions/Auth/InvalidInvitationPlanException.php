<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use LogicException;

/**
 * 招待発行ユースケースの Plan 引数の整合性違反を表す例外。
 *
 * 招待発行ロジックは「受講生招待 = Plan 必須 / コーチ招待 = Plan 禁止」を不変条件として扱う。
 * 通常は StoreRequest 段階で `required_if:role,student` / `prohibited_if:role,coach` ルールにより
 * バリデーションで弾かれるため、本例外は IssueInvitationAction を Wrapper を介さず直接呼んだ
 * プログラマミス時の防御線として機能する(到達した時点で呼出側のバグであり、500 で fail-fast する)。
 */
final class InvalidInvitationPlanException extends LogicException
{
    public static function forStudentMissingPlan(): self
    {
        return new self('受講生の招待では Plan の指定が必要です。');
    }

    public static function forCoachWithPlan(): self
    {
        return new self('コーチの招待では Plan を指定できません。');
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
