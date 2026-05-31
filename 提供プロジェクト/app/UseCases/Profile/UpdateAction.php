<?php

declare(strict_types=1);

namespace App\UseCases\Profile;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 自分自身のプロフィール(`name` / `bio` / コーチのみ `meeting_url`)を更新するユースケース。
 *
 * 認証ユーザーが coach 以外のロールから `meeting_url` を送信した場合は silently drop し、
 * `users.meeting_url` を変更しない。`meeting_url` に空文字列が送信された場合は NULL を保存する
 * (オンボーディングで必須化されているが、後からクリアする運用余地を残す)。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: ?string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        return DB::transaction(function () use ($user, $validated) {
            $attrs = [
                'name' => $validated['name'],
                'bio' => $validated['bio'] ?? null,
            ];

            if ($user->role === UserRole::Coach && array_key_exists('meeting_url', $validated)) {
                $meetingUrl = $validated['meeting_url'];
                $attrs['meeting_url'] = ($meetingUrl === null || $meetingUrl === '') ? null : $meetingUrl;
            }

            $user->update($attrs);

            return $user->fresh();
        });
    }
}
