<?php

declare(strict_types=1);

namespace App\Http\Requests\Section;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Section 編集中の Markdown プレビュー API リクエスト。MarkdownRenderingService で HTML 化されたサニタイズ済み出力を返却する。
 */
class PreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            && ($this->user()?->can('preview', $section) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
