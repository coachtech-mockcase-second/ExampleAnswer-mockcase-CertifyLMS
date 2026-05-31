<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 公開中(Published)の教材階層・Section 紐づき問題に対して削除操作が試行された場合に throw される。
 *
 * 利用先: Part / Chapter / Section / SectionQuestion の DestroyAction。
 * Entity ごとの static ファクトリ(forPart / forChapter / forSection / forSectionQuestion)経由で呼出側からはメッセージ文字列を渡さない。
 */
final class ContentNotDeletableException extends ConflictHttpException
{
    public static function forPart(): self
    {
        return self::build('Part');
    }

    public static function forChapter(): self
    {
        return self::build('Chapter');
    }

    public static function forSection(): self
    {
        return self::build('Section');
    }

    public static function forSectionQuestion(): self
    {
        return self::build('演習問題');
    }

    private static function build(string $entityLabel): self
    {
        return new self(sprintf(
            '公開中の%sは削除できません。先に下書きに戻してから削除してください。',
            $entityLabel,
        ));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
