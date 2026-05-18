<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AdminChatRoom\IndexRequest;
use App\Models\ChatRoom;
use App\UseCases\AdminChatRoom\IndexAction;
use App\UseCases\AdminChatRoom\ShowAction;
use Illuminate\View\View;

/**
 * 管理者向け chat 監査 Controller。全 ChatRoom / 全メッセージを **閲覧のみ** 可能。
 *
 * 送信フォームは Blade で描画されず、`Policy::sendMessage` も admin に対して常に false を返す。
 * admin の閲覧では `ChatMember.last_read_at` を更新せず、当事者の既読状態に影響しない。
 */
class AdminChatRoomController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $rooms = $action($request->filters());

        return view('admin.chat-rooms.index', [
            'rooms' => $rooms,
            'filter' => $request->filters(),
        ]);
    }

    public function show(ChatRoom $room, ShowAction $action): View
    {
        $room = $action($room);

        return view('admin.chat-rooms.show', [
            'room' => $room,
            'messages' => $room->messages,
        ]);
    }
}
