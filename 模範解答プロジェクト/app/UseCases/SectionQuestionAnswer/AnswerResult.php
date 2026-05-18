<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestionAnswer;

use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionAttempt;

/**
 * StoreAction が返す解答結果の値オブジェクト。
 *
 * - answer: 新規 INSERT した SectionQuestionAnswer 行
 * - attempt: UPSERT 後の SectionQuestionAttempt 行(最新値)
 * - correctOptionId / correctOptionBody / explanation: 結果画面の描画に使う SectionQuestion の正答情報
 */
final readonly class AnswerResult
{
    public function __construct(
        public SectionQuestionAnswer $answer,
        public SectionQuestionAttempt $attempt,
        public ?string $correctOptionId,
        public ?string $correctOptionBody,
        public ?string $explanation,
    ) {}
}
