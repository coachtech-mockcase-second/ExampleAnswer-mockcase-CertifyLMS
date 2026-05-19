<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;

/**
 * 受講生 / コーチ向けの回答 Controller。
 *
 * Policy::create は admin に対して常に false を返すため、admin は回答送信不可。
 * 削除は本人 + admin に許可されるが、admin モデレーション削除は AdminQaReplyController を使う想定。
 */
class QaReplyController extends Controller
{
    public function store(QaThread $thread, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $reply = $action($thread, $request->user(), $request->string('body')->toString());

        return redirect()
            ->route('qa-board.show', ['thread' => $thread->id])
            ->withFragment('reply-'.$reply->id)
            ->with('success', '回答を投稿しました。');
    }

    public function update(QaThread $thread, QaReply $reply, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($reply, $request->string('body')->toString());

        return redirect()
            ->route('qa-board.show', ['thread' => $thread->id])
            ->withFragment('reply-'.$reply->id)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $reply);

        $action($reply);

        return redirect()
            ->route('qa-board.show', ['thread' => $thread->id])
            ->with('success', '回答を削除しました。');
    }
}
