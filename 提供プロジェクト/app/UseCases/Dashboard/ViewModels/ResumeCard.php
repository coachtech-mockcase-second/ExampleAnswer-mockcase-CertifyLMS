<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard\ViewModels;

/**
 * 受講生ダッシュボードの「前回の続き」カードを表す ViewModel DTO。
 *
 * 最後に開いた Section(読了済なら同資格内の次の未読 Section)へ 1 タップで戻すための表示データを持つ。
 * Blade はプロパティアクセスのみで描画し、遷移先 URL は組み立て済みのものを受け取る。
 */
final readonly class ResumeCard
{
    public function __construct(
        public string $certificationName,
        public string $partTitle,
        public string $chapterTitle,
        public string $sectionTitle,
        public string $sectionUrl,
    ) {}
}
