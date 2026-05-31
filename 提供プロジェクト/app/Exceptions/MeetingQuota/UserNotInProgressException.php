<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * graduated / withdrawn ユーザーが追加面談購入を試行した際に throw される(HTTP 403)。
 * 購入可能なのは受講中(in_progress)ユーザーのみ。
 *
 * 403 はデフォルトでは Laravel の 403 エラーページに任せる方針(Policy 拒否との互換性のため、
 * `App\Exceptions\Handler` は 403 を redirect 変換しない)。ただし本例外は「graduated / withdrawn 受講生が
 * プラン機能にアクセスしてきた」というドメインの状態違反であり、Policy 拒否(他ロールが admin 画面にアクセス
 * したような攻撃的アクセス)とは性質が異なる。**前のページに戻して flash error で理由を伝えるほうが UX が良い**
 * ため、本例外だけ個別 `render()` メソッドで redirect+flash 変換を実装する。
 */
final class UserNotInProgressException extends AccessDeniedHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('受講中のユーザーのみ追加面談を購入できます。', $previous);
    }

    /**
     * ブラウザ(HTML)経由は前のページに戻して flash error 表示。
     * JSON 経由は親クラス挙動(403 status + JSON body)に任せる(null を返してデフォルト処理に委譲)。
     */
    public function render(Request $request): ?RedirectResponse
    {
        if ($request->expectsJson()) {
            return null;
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
