<?php

declare(strict_types=1);

namespace App\Http\Requests\Part;

use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Part 更新リクエスト。title / description のみ更新可、status は別エンドポイントで遷移する。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $part = $this->route('part');

        return $part instanceof Part
            && ($this->user()?->can('update', $part) ?? false);
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
