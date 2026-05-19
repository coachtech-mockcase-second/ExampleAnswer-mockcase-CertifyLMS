<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TopBar 通知ベルの通知ポップオーバー (`/notifications/popover`) のクエリ検証。
 * tab で「全件 / 未読のみ」の切替を受け取り、最新 20 件を返す。
 */
class PopoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:all,unread'],
        ];
    }
}
