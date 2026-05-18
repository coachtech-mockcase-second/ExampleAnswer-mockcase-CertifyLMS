<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * SectionQuestionAnswer の出題経路を表す Enum。
 *
 * - SectionQuiz: 教材 Section 詳細から起動する Section 紐づき問題演習
 * - WeakDrill: 模試結果の弱点判定に連動した苦手分野ドリル
 */
enum AnswerSource: string
{
    case SectionQuiz = 'section_quiz';
    case WeakDrill = 'weak_drill';

    public function label(): string
    {
        return match ($this) {
            self::SectionQuiz => 'Section演習',
            self::WeakDrill => '苦手分野ドリル',
        };
    }
}
 