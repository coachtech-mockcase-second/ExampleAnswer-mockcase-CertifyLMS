<?php

declare(strict_types=1);

namespace App\Http\Requests\SectionImage;

use App\Models\Section;
use App\Models\SectionImage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 教材内画像のアップロードリクエスト。png / jpg / jpeg / webp の 2MB 以下に制限する。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            && ($this->user()?->can('create', [SectionImage::class, $section]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => '画像ファイル',
        ];
    }
}
