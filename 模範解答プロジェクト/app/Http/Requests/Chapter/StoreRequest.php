<?php

declare(strict_types=1);

namespace App\Http\Requests\Chapter;

use App\Models\Chapter;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Chapter 新規作成リクエスト。親 Part 配下に紐付け、title / description を受け取る。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $part = $this->route('part');

        return $part instanceof Part
            && ($this->user()?->can('create', [Chapter::class, $part]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'description' => '説明',
        ];
    }
}
