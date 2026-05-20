<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChat\StoreRequest;
use App\Http\Requests\AiChat\UpdateRequest;
use App\Models\AiChatConversation;
use App\Models\User;
use App\UseCases\AiChat\DestroyAction;
use App\UseCases\AiChat\ShowAction;
use App\UseCases\AiChat\StoreAction;
use App\UseCases\AiChat\UpdateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AI 相談会話の CRUD Controller (受講生専用画面)。
 * メソッドは Action クラス名と一致 (index → IndexAction、store → StoreAction…)。
 */
class AiChatConversationController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', AiChatConversation::class);

        $latest = $this->user()->aiChatConversations()
            ->orderByRaw('last_message_at IS NULL ASC')
            ->orderByDesc('last_message_at')
            ->first();

        if ($latest !== null) {
            return redirect()->route('ai-chat.conversations.show', $latest);
        }

        return view('ai-chat.empty-state');
    }

    public function show(AiChatConversation $conversation, ShowAction $action, Request $request): View|JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation = $action($conversation);

        // ウィジェット側で過去メッセージを復元するための XHR (Accept: application/json) は JSON で返す
        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'last_message_at' => $conversation->last_message_at,
                    'section_id' => $conversation->section_id,
                ],
                'messages' => $conversation->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role->value,
                    'content' => $m->content,
                    'status' => $m->status->value,
                    'created_at' => $m->created_at,
                ])->values(),
            ]);
        }

        return view('ai-chat.show', [
            'conversation' => $conversation,
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $result = $action(
            user: $this->user(),
            sectionId: $validated['section_id'] ?? null,
            initialMessage: $validated['message'] ?? null,
            reuseExisting: ($validated['source'] ?? null) === 'widget',
        );

        // フローティングウィジェット等の XHR (Accept: application/json) は JSON で返し、
        // redirect follow に依存しない経路で会話 ID を取得できるようにする。
        if ($request->expectsJson()) {
            return response()->json(
                ['conversation' => $result['conversation'], 'was_reused' => $result['was_reused']],
                $result['was_reused'] ? 200 : 201,
            );
        }

        return redirect()
            ->route('ai-chat.conversations.show', $result['conversation'])
            ->with('success', $result['was_reused'] ? '会話を再開しました。' : '新しい相談を開始しました。');
    }

    public function update(AiChatConversation $conversation, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $action($conversation, $request->validated());

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(AiChatConversation $conversation, DestroyAction $action, Request $request): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $action($conversation);

        return redirect()
            ->route('ai-chat.index')
            ->with('success', '会話を削除しました。');
    }

    private function user(): User
    {
        $user = request()->user();
        \assert($user instanceof User);

        return $user;
    }
}
