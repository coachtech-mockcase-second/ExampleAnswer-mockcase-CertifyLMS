<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 修了証 PDF の生成（Blade テンプレート → DomPDF → Storage 書き込み）が失敗した際の例外。
 *
 * `IssueAction` の DB::transaction 内で PDF 生成失敗時に throw され:
 * - DB トランザクションは ROLLBACK され Certificate INSERT が巻き戻る
 * - Storage に部分書き込みされた可能性のある PDF ファイルは Action 側の try-catch で明示削除される
 */
class CertificatePdfGenerationFailedException extends HttpException
{
    public function __construct(
        string $message = '修了証 PDF の生成に失敗しました。時間をおいて再度お試しください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(500, $message, $previous);
    }
}
