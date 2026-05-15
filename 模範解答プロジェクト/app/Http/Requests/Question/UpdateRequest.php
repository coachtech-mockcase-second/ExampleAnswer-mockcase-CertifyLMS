<?php

namespace App\Http\Requests\Question;

use App\Enums\QuestionDifficulty;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = $this->route('question');

        return $question instanceof Question
            && ($this->user()?->can('update', $question) ?? false);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'ulid', 'exists:question_categories,id,deleted_at,NULL'],
            'difficulty' => ['required', 'in:'.implode(',', array_column(QuestionDifficulty::cases(), 'value'))],
            'section_id' => ['nullable', 'ulid', 'exists:sections,id,deleted_at,NULL'],
            'options' => ['sometimes', 'required', 'array', 'min:2', 'max:6'],
            'options.*.body' => ['required_with:options', 'string', 'max:1000'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],
        ];
    }
}
