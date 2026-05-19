<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexRequest;
use App\Http\Requests\Notification\PopoverRequest;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use App\UseCases\Notification\PopoverAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * 受信者本人の通知一覧 / 通知ポップオーバー / 既読化を提供する Controller。
 * 自分宛の通知のみ操作可能 (`NotificationPolicy::view/update` で検査)。
 */
class NotificationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $tab = $request->input('tab', 'all');
        $payload = $action($request->user(), is_string($tab) ? $tab : 'all');

        return view('notifications.index', $payload);
    }

    public function popover(PopoverRequest $request, PopoverAction $action): JsonResponse
    {
        $tab = $request->input('tab', 'all');
        $payload = $action($request->user(), is_string($tab) ? $tab : 'all');

        return response()->json($payload);
    }

    public function markAsRead(DatabaseNotification $notification, MarkAsReadAction $action, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $notification);
        $action($notification);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }

        $link = is_array($notification->data) ? $notification->data : [];
        $route = $link['link_route'] ?? null;
        $params = $link['link_params'] ?? [];

        if (is_string($route) && \Illuminate\Support\Facades\Route::has($route)) {
            return redirect()->route($route, is_array($params) ? $params : []);
        }

        return redirect()->route('notifications.index');
    }

    public function markAllAsRead(MarkAllAsReadAction $action, Request $request): RedirectResponse|JsonResponse
    {
        $action($request->user());

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return redirect()
            ->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }
}
