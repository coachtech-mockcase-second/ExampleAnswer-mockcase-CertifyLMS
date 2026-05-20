<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Chat\Moderation\IndexRequest;
use App\Models\ChatRoom;
use App\UseCases\Chat\Moderation\ShowAction;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 管理者向け chat 監査 Controller。全 ChatRoom / 全メッセージを **閲覧のみ** 可能。
 *
 * - index: 検索条件 (keyword) と一致する最新ルームへ即時 redirect、0 件なら empty-state
 * - show: 2-pane (rooms-pane + thread) 表示。検索条件は query string で保持し、左ペインの絞り込みに反映
 * - 送信フォームは Blade で描画されず、`Policy::sendMessage` も admin に対して常に false を返す
 * - admin の閲覧では `ChatMember.last_read_at` を更新せず、当事者の既読状態に影響しない
 */
class ChatRoomModerationController extends Controller
{
    public function index(IndexRequest $request): Renderable|RedirectResponse
    {
        $filters = $request->filters();

        $latest = ChatRoom::query()
            ->filterForAdmin($filters)
            ->orderByLastMessage()
            ->first();

        if ($latest !== null) {
            $params = array_merge(['room' => $latest], array_filter($filters, fn ($v) => $v !== null && $v !== ''));

            return redirect()->route('admin.chat-rooms.show', $params);
        }

        return view('chat-room.management.empty-state', ['filters' => $filters]);
    }

    public function show(ChatRoom $room, ShowAction $action, Request $request): View
    {
        $room = $action($room);

        $adminFilters = [
            'certification_id' => $request->input('certification_id'),
            'keyword' => $request->input('keyword'),
        ];

        $navRooms = ChatRoom::query()
            ->filterForAdmin($adminFilters)
            ->with(['enrollment.certification.coaches', 'enrollment.user', 'latestMessage.sender'])
            ->orderByLastMessage()
            ->limit(50)
            ->get();

        return view('chat-room.management.show', [
            'room' => $room,
            'messages' => $room->messages,
            'navRooms' => $navRooms,
            'adminFilters' => $adminFilters,
        ]);
    }
}
