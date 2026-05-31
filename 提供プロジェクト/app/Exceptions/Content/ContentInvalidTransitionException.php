<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use App\Enums\ContentStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 教材階層・Section 紐づき問題の公開状態遷移(Draft ⇄ Published)が許可されない条件下で試行された場合に throw される。
 *
 * 利用先: Part / Chapter / Section / SectionQuestion の PublishAction / UnpublishAction。
 * Entity ごとの static ファクトリ(forPart / forChapter / forSection / forSectionQuestion)を経由して呼出側からはメッセージ文字列を渡さない。
 */
final class ContentInvalidTransitionException extends ConflictHttpException
{
    public static function forPart(ContentStatus $from, ContentStatus $to): self
    {
        return self::build('Part', $from, $to);
    }

    public static function forChapter(ContentStatus $from, ContentStatus $to): self
    {
        return self::build('Chapter', $from, $to);
    }

    public static function forSection(ContentStatus $from, ContentStatus $to): self
    {
        return self::build('Section', $from, $to);
    }

    public static function forSectionQuestion(ContentStatus $from, ContentStatus $to): self
    {
        return self::build('演習問題', $from, $to);
    }

    private static function build(string $entityLabel, ContentStatus $from, ContentStatus $to): self
    {
        return new self(sprintf(
            '%sの現在の状態(%s)から%sへの遷移は許可されていません。',
            $entityLabel,
            $from->label(),
            $to->label(),
        ));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
