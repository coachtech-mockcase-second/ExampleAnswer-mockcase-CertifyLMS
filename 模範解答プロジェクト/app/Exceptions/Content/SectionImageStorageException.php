<?php

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\HttpException;

class SectionImageStorageException extends HttpException
{
    public function __construct(
        string $message = '画像の保存に失敗しました。時間をおいて再度お試しください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(500, $message, $previous);
    }
}
