<?php

declare(strict_types=1);

namespace App\Services;

/**
 * LLM (Gemini など) のチャット応答結果を表す不変 DTO。
 *
 * Repository が外部 API レスポンスを正規化し、Action / Service 側に渡す境界の型。
 * content だけでなく model / token カウント / 応答時間を保持し、ログ / DB 永続化に活用する。
 *
 * 配置方針は CategoryHeatmapCell / StatsSummary / SectionQuestionScoreSummary 等の
 * 既存 DTO と同様、App\Services 直下に final readonly クラスとして置く。
 */
final readonly class LlmChatResponse
{
    public function __construct(
        public string $content,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $responseTimeMs,
    ) {}
}
