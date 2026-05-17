<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitation;

use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理者の招待再送信 (`POST /admin/users/{user}/resend-invitation`) の認可ラッパー。
 *
 * 入力フィールドを持たない(再送信対象の User は URL parameter で渡される)ため rules() は空。
 * 認可は `InvitationPolicy::create` で admin のみ許可する。
 */
class ResendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invitation::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
