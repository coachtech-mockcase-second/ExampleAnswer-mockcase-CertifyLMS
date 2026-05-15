<?php

namespace App\Http\Requests\Chapter;

use App\Models\Chapter;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $part = $this->route('part');

        return $part instanceof Part
            && ($this->user()?->can('reorder', [Chapter::class, $part]) ?? false);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }
}
