<?php

declare(strict_types=1);

namespace App\Http\Requests\SectionQuestion;

use App\Enums\ContentStatus;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Section 紐づき問題一覧の入力検証 + 認可。
 *
 * @see \App\Http\Controllers\SectionQuestionController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            && ($this->user()?->can('viewAny', [\App\Models\SectionQuestion::class, $section]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'ulid'],
            'status' => ['nullable', 'in:'.implode(',', array_column(ContentStatus::cases(), 'value'))],
        ];
    }
}
