<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 招待発行・プラン延長で published 以外のプランが指定された際に throw される(HTTP 422)。
 */
class PlanNotPublishedException extends HttpException
{
    public function __construct(
        string $message = '公開中(published)のプランのみ招待・延長に利用できます。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(422, $message, $previous);
    }
}
