<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * プランの削除が不可能な状態で DELETE が要求された際に throw される(HTTP 409)。
 *
 * 2 種類の不可状態を区別する:
 * - status が下書き以外(published / archived) → forStatusViolation()
 * - 受講者(users.plan_id)が紐づいている → forUsersAttached()
 *
 * 各状況のメッセージは static ファクトリで提供する(呼出側に文字列責務を持たせない)。
 */
final class PlanNotDeletableException extends ConflictHttpException
{
    public static function forStatusViolation(): self
    {
        return new self('下書き状態のプランのみ削除できます。先に下書きに戻すか、アーカイブを利用してください。');
    }

    public static function forUsersAttached(): self
    {
        return new self('このプランは受講者が紐づいているため削除できません。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
