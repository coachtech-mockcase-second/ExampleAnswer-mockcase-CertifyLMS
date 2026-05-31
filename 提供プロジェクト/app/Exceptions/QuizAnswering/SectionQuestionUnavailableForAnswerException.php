<?php

declare(strict_types=1);

namespace App\Exceptions\QuizAnswering;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 解答送信時に対象の SectionQuestion が出題不可状態であった場合に発火する例外。
 *
 * 発火条件: SectionQuestion が下書き / SoftDelete 済、または親 Section / Chapter / Part のいずれかが
 * 下書き / SoftDelete 済(cascade visibility 違反)。
 */
final class SectionQuestionUnavailableForAnswerException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この問題は現在公開されておらず、解答できません。', $previous);
    }
}
