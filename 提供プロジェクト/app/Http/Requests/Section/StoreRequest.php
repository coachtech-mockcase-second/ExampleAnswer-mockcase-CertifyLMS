<?php

declare(strict_types=1);

namespace App\Http\Requests\Section;

use App\Models\Chapter;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Section 新規作成リクエスト。親 Chapter 配下に紐付け、title / description / body(Markdown 本文) を受け取る。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chapter = $this->route('chapter');

        return $chapter instanceof Chapter
            && ($this->user()?->can('create', [Section::class, $chapter]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:50000'],
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
            'body' => '本文',
        ];
    }
}
