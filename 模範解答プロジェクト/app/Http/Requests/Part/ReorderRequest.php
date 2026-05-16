<?php

declare(strict_types=1);

namespace App\Http\Requests\Part;

use App\Models\Certification;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification instanceof Certification
            && ($this->user()?->can('reorder', [Part::class, $certification]) ?? false);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Part ID 配列',
        ];
    }
}
