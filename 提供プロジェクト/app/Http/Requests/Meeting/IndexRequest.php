<?php

declare(strict_types=1);

namespace App\Http\Requests\Meeting;

use App\Http\Controllers\MeetingController;
use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生本人の面談履歴一覧リクエスト。filter のクエリ受け取り + viewAny 認可のみを行う。
 *
 * @see MeetingController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Meeting::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'in:upcoming,past,all'],
        ];
    }
}
