<?php

namespace App\Http\Requests\Question;

use App\Enums\QuestionDifficulty;
use App\Models\Certification;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification instanceof Certification
            && ($this->user()?->can('create', [Question::class, $certification]) ?? false);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'ulid', 'exists:question_categories,id,deleted_at,NULL'],
            'difficulty' => ['required', 'in:'.implode(',', array_column(QuestionDifficulty::cases(), 'value'))],
            'section_id' => ['nullable', 'ulid', 'exists:sections,id,deleted_at,NULL'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*.body' => ['required', 'string', 'max:1000'],
            'options.*.is_correct' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => '問題文',
            'explanation' => '解説',
            'category_id' => '出題分野',
            'difficulty' => '難易度',
            'section_id' => '紐づき Section',
            'options' => '選択肢',
            'options.*.body' => '選択肢本文',
            'options.*.is_correct' => '正答フラグ',
        ];
    }
}
