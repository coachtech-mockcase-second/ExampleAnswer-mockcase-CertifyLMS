<?php

declare(strict_types=1);

namespace App\UseCases\Chat;

use App\Models\ChatRoom;
use App\Models\User;
use App\Services\ChatUnreadCountService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * コーチ専用のルーム一覧。デフォルトは「未読あり」フィルタで、資格 / 受講生名キーワード絞り込みも適用する。
 */
final class IndexAsCoachAction
{
    public function __construct(
        private readonly ChatUnreadCountService $unreadCount,
    ) {}

    /**
     * @param array{filter: string, certification_id: ?string, keyword: ?string} $filters
     *
     * @return LengthAwarePaginator<ChatRoom>
     */
    public function __invoke(User $coach, array $filters): LengthAwarePaginator
    {
        $query = ChatRoom::query()
            ->forUser($coach)
            ->with([
                'enrollment.certification.coaches',
                'enrollment.user',
                'members.user',
                'latestMessage.sender',
            ]);

        if (! empty($filters['certification_id'])) {
            $query->whereHas('enrollment', function ($q) use ($filters): void {
                $q->where('certification_id', $filters['certification_id']);
            });
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->whereHas('enrollment.user', function ($q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        if (($filters['filter'] ?? 'unread') === 'unread') {
            $query->whereExists(function ($q) use ($coach): void {
                $q->select(\DB::raw(1))
                    ->from('chat_messages')
                    ->whereColumn('chat_messages.chat_room_id', 'chat_rooms.id')
                    ->where('chat_messages.sender_user_id', '!=', $coach->id)
                    ->whereNull('chat_messages.deleted_at')
                    ->whereRaw(
                        'chat_messages.created_at > COALESCE((SELECT last_read_at FROM chat_members WHERE chat_members.chat_room_id = chat_rooms.id AND chat_members.user_id = ? AND chat_members.deleted_at IS NULL LIMIT 1), "1970-01-01")',
                        [$coach->id]
                    );
            });
        }

        /** @var LengthAwarePaginator<ChatRoom> $paginator */
        $paginator = $query->orderByLastMessage()->paginate(20);

        $paginator->getCollection()->each(function (ChatRoom $room) use ($coach): void {
            $room->setAttribute('unread_count', $this->unreadCount->messageCountInRoom($room, $coach));
            $room->setAttribute(
                'coach_unassigned',
                $room->enrollment->certification->coaches->isEmpty(),
            );
        });

        return $paginator;
    }
}
