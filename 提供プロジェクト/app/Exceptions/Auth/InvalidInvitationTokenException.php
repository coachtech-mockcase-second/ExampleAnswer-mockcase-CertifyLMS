<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidInvitationTokenException extends HttpException
{
    public function __construct(
        string $message = '招待リンクが無効または期限切れです。管理者へお問い合わせください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(410, $message, $previous);
    }
}
