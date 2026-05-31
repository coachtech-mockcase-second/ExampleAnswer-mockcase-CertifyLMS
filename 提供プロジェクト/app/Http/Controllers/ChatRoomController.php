<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\Chat\CertificationCoachNotAssignedForChatException;
use App\Http\Requests\Chat\IndexAsCoachRequest;
use App\Http\Requests\Chat\IndexRequest;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Models\ChatRoom;
use App\Services\ChatUnreadCountService;
use App\UseCases\Chat\ShowAction;
use App\UseCases\Chat\StoreMessageAction;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Chat Controller。受講生 / コーチ / admin 共通で利用される。
 *
 * - index: 受講生 / コーチは参加ルームの最新へ redirect、admin は全ルーム横断で最新へ redirect、0 件なら empty-state
 * - indexAsCoach: コーチ専用、未読あり / filter / keyword の query string をそのまま保持して redirect
 * - show: ルーム詳細(rooms-pane + thread)。viewer の role に応じて navRoomsQuery を切替
 *   (student/coach は forUser、admin は filterForAdmin)
 * - storeMessage: メッセージ送信(`Policy::view` で authorize、コーチ未割当時は 422 を Controller で振り分け)
 */
class ChatRoomController extends Controller
{
    public function index(IndexRequest $request): Renderable|RedirectResponse
    {
        $viewer = $request->user();
        $filters = $request->filters();

        $query = ChatRoom::query();

        if ($viewer->role === UserRole::Admin) {
            $query->filterForAdmin($filters);
        } else {
            $query->forUser($viewer);
        }

        $latest = $query->orderByLastMessage()->first();

        if ($latest !== null) {
            if ($viewer->role === UserRole::Admin) {
                $params = array_merge(['room' => $latest], array_filter($filters, fn ($v) => $v !== null && $v !== ''));

                return redirect()->route('admin.chat-rooms.show', $params);
            }

            return redirect()->route('chat.show', $latest);
        }

        return view('chat-room.empty-state', ['filters' => $filters]);
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

    public function show(
        ChatRoom $room,
        ShowAction $action,
        Request $request,
        ChatUnreadCountService $unreadCount,
    ): View {
        $this->authorize('view', $room);

        $viewer = auth()->user();
        $room = $action($room, $viewer);

        $navRoomsQuery = ChatRoom::query()
            ->with(['enrollment.certification.coaches', 'enrollment.user', 'latestMessage.sender']);

        $coachFilters = null;
        $adminFilters = null;

        if ($viewer->role === UserRole::Admin) {
            $adminFilters = [
                'certification_id' => $request->input('certification_id'),
                'keyword' => $request->input('keyword'),
            ];
            $navRoomsQuery->filterForAdmin($adminFilters);
        } else {
            $navRoomsQuery->forUser($viewer);

            if ($viewer->role === UserRole::Coach) {
                // デフォルトはフィルタなし (= すべて表示)。query string で明示された場合のみ絞り込む。
                $coachFilters = [
                    'filter' => $request->string('filter', 'all')->toString(),
                    'certification_id' => $request->input('certification_id'),
                    'keyword' => $request->input('keyword'),
                ];
                $navRoomsQuery->filterForCoach($viewer, $coachFilters);
            }
        }

        $navRooms = $navRoomsQuery
            ->orderByLastMessage()
            ->limit(50)
            ->get();

        return view('chat-room.show', [
            'room' => $room,
            'messages' => $room->messages,
            'navRooms' => $navRooms,
            'navRoomUnreadCounts' => $unreadCount->messageCountsByRoomForUser($navRooms, $viewer),
            'coachFilters' => $coachFilters,
            'adminFilters' => $adminFilters,
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
