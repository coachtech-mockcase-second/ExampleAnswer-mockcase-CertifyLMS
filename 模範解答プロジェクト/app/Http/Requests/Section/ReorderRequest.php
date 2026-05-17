<?php

declare(strict_types=1);

namespace App\Http\Requests\Section;

use App\Models\Chapter;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Section 並び替えリクエスト。親 Chapter 配下の Section ID 配列を表示順に受け取る。
 */
class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chapter = $this->route('chapter');

        return $chapter instanceof Chapter
            && ($this->user()?->can('reorder', [Section::class, $chapter]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'Section ID 配列',
        ];
    }
}
