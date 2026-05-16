<?php

declare(strict_types=1);

namespace App\Http\Requests\Question;

use App\Enums\ContentStatus;
use App\Enums\QuestionDifficulty;
use App\Models\Certification;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification instanceof Certification
            && ($this->user()?->can('viewAny', [Question::class, $certification]) ?? false);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'ulid'],
            'difficulty' => ['nullable', 'in:'.implode(',', array_column(QuestionDifficulty::cases(), 'value'))],
            'status' => ['nullable', 'in:'.implode(',', array_column(ContentStatus::cases(), 'value'))],
            'standalone_only' => ['nullable', 'boolean'],
        ];
    }
}
