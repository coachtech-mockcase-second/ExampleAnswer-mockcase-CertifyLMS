<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 教材階層(Part / Chapter / Section)および Section 紐づき問題(SectionQuestion)の公開状態を表す共通 Enum。
 *
 * Draft: 下書き状態。受講生には非公開。コーチ / 管理者は管理画面で確認できる。
 * Published: 公開中。受講生からも閲覧可能。ただし親 Entity が Draft の場合は cascade で非公開扱いとなる。
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::Published => '公開中',
        };
    }
}
