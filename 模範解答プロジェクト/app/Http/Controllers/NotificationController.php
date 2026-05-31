<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexRequest;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * 受信者本人の通知一覧 / 詳細 / 既読化を提供する Controller。
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

    /**
     * 通知単体の詳細表示。お知らせのような遷移先となる業務画面を持たない自己完結型通知の全文をここで閲覧する。
     * 開いた時点で既読化する (一覧 / ポップオーバー経由は既読化済だが、メール内リンク等からの直接遷移にも対応するため)。
     */
    public function show(DatabaseNotification $notification, MarkAsReadAction $markAsRead): View
    {
        $this->authorize('view', $notification);
        $markAsRead($notification);

        return view('notifications.show', ['notification' => $notification]);
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

        if (is_string($route) && Route::has($route)) {
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
