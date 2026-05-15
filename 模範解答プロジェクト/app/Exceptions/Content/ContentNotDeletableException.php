<?php

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ContentNotDeletableException extends ConflictHttpException
{
    public function __construct(
        string $entity = 'Content',
        ?\Throwable $previous = null,
    ) {
        $message = sprintf('公開中の%sは削除できません。先に非公開化してから削除してください。', $entity);

        parent::__construct($message, $previous);
    }
}
