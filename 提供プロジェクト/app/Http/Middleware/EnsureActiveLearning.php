<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 受講中(in_progress)以外のユーザーをプラン機能から弾く Middleware。
 *
 * 卒業(graduated)ユーザーはログイン可能だが、学習 / 演習 / 模試 / 面談 / 追加面談購入 / qa-board / chat / ai-chat 等の
 * プラン機能には進めない。ただし、プロフィール / 修了証 PDF DL / 通知一覧は引き続き利用可能のため、
 * 本 Middleware は該当ルートグループに対してのみ適用する(全 auth グループには適用しない)。
 */
final class EnsureActiveLearning
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->status !== UserStatus::InProgress) {
            abort(403, 'プラン期間が満了しました。プラン機能はご利用いただけません。プロフィール / 修了証は引き続きアクセス可能です。');
        }

        return $next($request);
    }
}
