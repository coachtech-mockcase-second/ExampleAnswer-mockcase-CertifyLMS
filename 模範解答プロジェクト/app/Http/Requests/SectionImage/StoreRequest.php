<?php

declare(strict_types=1);

namespace App\Http\Requests\SectionImage;

use App\Models\Section;
use App\Models\SectionImage;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            && ($this->user()?->can('create', [SectionImage::class, $section]) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => '画像ファイル',
        ];
    }
}
