<?php

declare(strict_types=1);

namespace App\Http\Requests\Part;

use App\Models\Certification;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Part 新規作成リクエスト。資格マスタ配下に紐付け、title / description を受け取る。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification instanceof Certification
            && ($this->user()?->can('create', [Part::class, $certification]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'description' => '説明',
        ];
    }
}
