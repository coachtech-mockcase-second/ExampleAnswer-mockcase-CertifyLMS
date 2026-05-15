<?php

namespace App\Http\Requests\Section;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class PreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            && ($this->user()?->can('preview', $section) ?? false);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
