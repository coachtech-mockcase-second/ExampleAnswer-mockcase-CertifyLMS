<?php

declare(strict_types=1);

namespace App\Http\Requests\Chapter;

use App\Models\Chapter;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $part = $this->route('part');

        return $part instanceof Part
            && ($this->user()?->can('create', [Chapter::class, $part]) ?? false);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'description' => '説明',
        ];
    }
}
