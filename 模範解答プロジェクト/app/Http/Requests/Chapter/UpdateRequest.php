<?php

declare(strict_types=1);

namespace App\Http\Requests\Chapter;

use App\Models\Chapter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Chapter 更新リクエスト。title / description のみ更新可、status は別エンドポイントで遷移する。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chapter = $this->route('chapter');

        return $chapter instanceof Chapter
            && ($this->user()?->can('update', $chapter) ?? false);
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
