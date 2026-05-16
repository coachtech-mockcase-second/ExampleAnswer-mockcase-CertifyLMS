<?php

declare(strict_types=1);

namespace App\Http\Requests\Section;

use App\Models\Chapter;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chapter = $this->route('chapter');

        return $chapter instanceof Chapter
            && ($this->user()?->can('reorder', [Section::class, $chapter]) ?? false);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }
}
