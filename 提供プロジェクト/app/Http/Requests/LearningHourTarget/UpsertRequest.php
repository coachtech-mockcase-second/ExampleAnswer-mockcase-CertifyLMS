<?php

declare(strict_types=1);

namespace App\Http\Requests\LearningHourTarget;

use App\Models\LearningHourTarget;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 学習時間目標の upsert(新規 / 更新)バリデーション。受講生本人のみ通過し、合計目標時間は 1..9999h の整数。
 */
class UpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', [LearningHourTarget::class, $this->route('enrollment')]) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_total_hours' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target_total_hours' => '合計目標時間',
        ];
    }
}
