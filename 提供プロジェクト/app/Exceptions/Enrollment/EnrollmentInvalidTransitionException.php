<?php

declare(strict_types=1);

namespace App\Exceptions\Enrollment;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Enrollment 状態遷移マトリクスに違反する遷移操作を拒否する例外。
 *
 * 許容遷移: learning → passed / learning → failed / failed → learning。それ以外はすべて本例外で拒否される。
 */
final class EnrollmentInvalidTransitionException extends ConflictHttpException
{
    public static function forFail(): self
    {
        return new self('学習中(learning)の受講登録のみ学習中止に切り替えられます。');
    }

    public static function forResume(): self
    {
        return new self('学習中止(failed)の受講登録のみ学習中に戻せます。');
    }

    public static function forDestroy(): self
    {
        return new self('学習中(learning)の受講登録のみ受講解除できます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
