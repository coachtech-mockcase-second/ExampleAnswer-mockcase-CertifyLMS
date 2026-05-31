<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notification\IndexRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use App\UseCases\Notification\Api\IndexAction;
use App\UseCases\Notification\Api\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 受信者本人の通知 JSON API。Sanctum Cookie 認証で保護され、認証ユーザー自身の通知のみ操作可能。
 */
class NotificationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): AnonymousResourceCollection
    {
        $tab = (string) $request->input('tab', 'all');
        $perPage = (int) $request->input('per_page', 20);

        return NotificationResource::collection($action($request->user(), $tab, $perPage));
    }

    public function markAsRead(DatabaseNotification $notification, MarkAsReadAction $action): JsonResponse
    {
        $this->authorize('update', $notification);
        $action($notification);

        return response()->json(['status' => 'ok']);
    }

    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): JsonResponse
    {
        $count = $action($request->user());

        return response()->json(['status' => 'ok', 'updated' => $count]);
    }
}
