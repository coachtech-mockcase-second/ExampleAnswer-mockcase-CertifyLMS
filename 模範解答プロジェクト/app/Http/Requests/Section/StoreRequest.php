<?php

namespace App\Http\Requests\Section;

use App\Models\Chapter;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chapter = $this->route('chapter');

        return $chapter instanceof Chapter
            && ($this->user()?->can('create', [Section::class, $chapter]) ?? false);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:50000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'description' => '説明',
            'body' => '本文',
        ];
    }
}
