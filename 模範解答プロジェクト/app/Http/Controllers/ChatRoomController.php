<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\Chat\CertificationCoachNotAssignedForChatException;
use App\Http\Requests\Chat\IndexAsCoachRequest;
use App\Http\Requests\Chat\IndexRequest;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Models\ChatRoom;
use App\UseCases\Chat\ShowAction;
use App\UseCases\Chat\StoreMessageAction;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 受講生 / コーチ向け chat Controller。
 *
 * - index / indexAsCoach: 最新ルームへ即時 redirect、0 件なら empty-state
 *   (ルーム選択 UI は show ページの 2 ペイン左カラムに集約しているため、独立した一覧画面は不要)
 *   コーチ向けは filter / keyword の query string をそのまま保持して redirect する
 * - show: ルーム詳細(rooms-pane + thread)。コーチが閲覧時は query string の filter / keyword を
 *   rooms-pane の絞り込みに反映する
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

        return view('chat-room.empty-state');
    }

    public function indexAsCoach(IndexAsCoachRequest $request): Renderable|RedirectResponse
    {
        $coach = $request->user();

        // 参加ルームのうち最新の 1 件へ即時 redirect。フィルタ (未読あり/keyword) は
        // 左ペインの opt-in 操作として扱い、navigation entry では適用しない (生徒の
        // chat.index と同じ「サイドバー → 即 show」の UX に揃える)。
        $latest = ChatRoom::query()
            ->forUser($coach)
            ->orderByLastMessage()
            ->first();

        if ($latest !== null) {
            return redirect()->route('chat.show', $latest);
        }

        return view('chat-room.coach-empty-state', ['filters' => $request->filters()]);
    }

    public function show(ChatRoom $room, ShowAction $action, Request $request): View
    {
        $this->authorize('view', $room);

        $viewer = auth()->user();
        $room = $action($room, $viewer);

        $navRoomsQuery = ChatRoom::query()
            ->forUser($viewer)
            ->with(['enrollment.certification.coaches', 'enrollment.user', 'latestMessage.sender']);

        $coachFilters = null;
        if ($viewer->role === UserRole::Coach) {
            // デフォルトはフィルタなし (= すべて表示)。query string で明示された場合のみ絞り込む。
            $coachFilters = [
                'filter' => $request->string('filter', 'all')->toString(),
                'certification_id' => $request->input('certification_id'),
                'keyword' => $request->input('keyword'),
            ];
            $navRoomsQuery->filterForCoach($viewer, $coachFilters);
        }

        $navRooms = $navRoomsQuery
            ->orderByLastMessage()
            ->limit(50)
            ->get();

        return view('chat-room.show', [
            'room' => $room,
            'messages' => $room->messages,
            'navRooms' => $navRooms,
            'coachFilters' => $coachFilters,
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
