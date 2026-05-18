<?php

declare(strict_types=1);

namespace App\UseCases\AdminChatRoom;

use App\Models\ChatRoom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 管理者向け chat 一覧 Action。資格 / 受講生キーワード(名前 + メール)で横断検索可能。
 */
final class IndexAction
{
    /**
     * @param array{certification_id: ?string, keyword: ?string} $filters
     *
     * @return LengthAwarePaginator<ChatRoom>
     */
    public function __invoke(array $filters): LengthAwarePaginator
    {
        $query = ChatRoom::query()
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

        return $query->orderByLastMessage()->paginate(20);
    }
}
