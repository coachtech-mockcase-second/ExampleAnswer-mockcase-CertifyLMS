<?php

declare(strict_types=1);

namespace App\Http\Requests\Chapter;

use App\Models\Chapter;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Chapter 並び替えリクエスト。親 Part 配下の Chapter ID 配列を表示順に受け取る。
 */
class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $part = $this->route('part');

        return $part instanceof Part
            && ($this->user()?->can('reorder', [Chapter::class, $part]) ?? false);
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
            'ids' => 'Chapter ID 配列',
        ];
    }
}
