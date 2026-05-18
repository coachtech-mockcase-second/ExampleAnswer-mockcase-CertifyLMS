<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\Chat\CertificationCoachNotAssignedForChatException;
use App\Http\Requests\Chat\IndexAsCoachRequest;
use App\Http\Requests\Chat\IndexRequest;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Models\ChatRoom;
use App\UseCases\Chat\IndexAsCoachAction;
use App\UseCases\Chat\ShowAction;
use App\UseCases\Chat\StoreMessageAction;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 受講生 / コーチ向け chat Controller。
 *
 * - index: 受講生 / コーチを最新ルームへ即時 redirect、ルーム 0 件なら empty-state ビュー
 *   (ルーム選択 UI は show ページの 2 ペイン左カラムに集約しているため、index 単体の一覧は不要)
 * - indexAsCoach: コーチ専用、デフォルトで「未読あり」フィルタ
 * - show: ルーム詳細(rooms drawer + thread)
 * - storeMessage: メッセージ送信(StoreMessageRequest で `Policy::view` が authorize 担当、
 *   コーチ未割当時は 422 を Controller で振り分け)
 */
class ChatRoomController extends Controller
{
    public function index(IndexRequest $request): Renderable|RedirectResponse
    {
        $latest = ChatRoom::query()
            ->forUser($request->user())
            ->orderByLastMessage()
            ->first();

        if ($latest !== null) {
            return redirect()->route('chat.show', $latest);
        }

        return view('chat.empty-state');
    }

    public function indexAsCoach(IndexAsCoachRequest $request, IndexAsCoachAction $action): View
    {
        $rooms = $action(auth()->user(), $request->filters());

        return view('chat.coach-index', [
            'rooms' => $rooms,
            'filter' => $request->filters(),
        ]);
    }

    public function show(ChatRoom $room, ShowAction $action): View
    {
        $this->authorize('view', $room);

        $viewer = auth()->user();
        $room = $action($room, $viewer);

        $navRooms = ChatRoom::query()
            ->forUser($viewer)
            ->with(['enrollment.certification', 'enrollment.user', 'latestMessage.sender'])
            ->orderByLastMessage()
            ->limit(50)
            ->get();

        return view('chat.show', [
            'room' => $room,
            'messages' => $room->messages,
            'navRooms' => $navRooms,
        ]);
    }

    /**
     * @throws CertificationCoachNotAssignedForChatException
     */
    public function storeMessage(
        ChatRoom $room,
        StoreMessageRequest $request,
        StoreMessageAction $action,
    ): RedirectResponse {
        $user = $request->user();

        $room->loadMissing('enrollment.certification.coaches');

        if ($user->role !== UserRole::Admin
            && $room->enrollment->certification->coaches->isEmpty()) {
            throw new CertificationCoachNotAssignedForChatException;
        }

        $action($user, $room, $request->validated());

        return redirect()
            ->route('chat.show', $room)
            ->with('success', 'メッセージを送信しました。');
    }
}
