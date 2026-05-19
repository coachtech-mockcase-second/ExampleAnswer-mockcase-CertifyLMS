<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 通知一覧 (`/notifications`) のクエリパラメータ検証。
 * tab で「全件 / 未読のみ」を、page で paginate 位置を受け取る。
 */
class IndexRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
