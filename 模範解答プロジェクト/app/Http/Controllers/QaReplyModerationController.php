<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\QaReply;
use App\UseCases\QaReply\Moderation\DestroyAction;
use Illuminate\Http\RedirectResponse;

/**
 * 管理者向け回答モデレーション Controller。SoftDelete のみ。
 *
 * `role:admin` Middleware で Controller 到達前にロール認可が行われる前提。Policy::delete は admin 常許可。
 */
class QaReplyModerationController extends Controller
{
    public function destroy(QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $action($reply);

        return redirect()
            ->route('admin.qa-board.show', $reply->qa_thread_id)
            ->with('success', '回答を削除しました。');
    }
}
