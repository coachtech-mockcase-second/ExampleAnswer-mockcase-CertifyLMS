<?php

declare(strict_types=1);

namespace App\Http\Requests\Part;

use App\Models\Certification;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Part 並び替えリクエスト。配下 Part の ID 配列を表示順に受け取る。
 */
class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification instanceof Certification
            && ($this->user()?->can('reorder', [Part::class, $certification]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'Part ID 配列',
        ];
    }
}
