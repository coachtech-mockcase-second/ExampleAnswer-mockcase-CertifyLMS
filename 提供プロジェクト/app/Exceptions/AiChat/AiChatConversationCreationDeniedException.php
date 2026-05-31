<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * AI 相談会話の作成が業務ルール上拒否された場合に throw する例外。
 *
 * 主な発生場面: 教材コンテキスト(Section)指定で会話を作ろうとしたが、受講生が当該資格に
 * 学習中 / 修了状態の受講登録を持たない場合 (受講していない資格の教材で相談はできない仕様)。
 */
final class AiChatConversationCreationDeniedException extends AccessDeniedHttpException
{
    public function __construct(?string $message = null, ?\Throwable $previous = null)
    {
        parent::__construct(
            message: $message ?? '指定された教材の資格に登録していないため、この会話を作成できません。',
            previous: $previous,
        );
    }
}
