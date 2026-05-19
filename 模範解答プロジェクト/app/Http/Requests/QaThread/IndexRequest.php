<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Enums\UserRole;
use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 質問掲示板一覧のフィルタ / 検索パラメータ検証用 FormRequest。
 *
 * 列挙攻撃防止のため、coach が担当外資格 `certification_id` を指定した場合は
 * Controller 側で QaThreadPolicy::view() を介して 403 に変換する (本 FormRequest では入力形式のみ検証)。
 *
 * @see QaThreadController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', QaThread::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['nullable', 'ulid'],
            'status' => ['nullable', Rule::in(['resolved', 'unresolved'])],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * coach が担当外資格 ID をクエリ指定したかを Controller 側で判定するためのヘルパ。
     */
    public function isUnassignedCertificationForCoach(): bool
    {
        $user = $this->user();

        if ($user === null || $user->role !== UserRole::Coach) {
            return false;
        }

        $certificationId = $this->input('certification_id');

        if ($certificationId === null || $certificationId === '') {
            return false;
        }

        return ! in_array($certificationId, $user->coachingCertificationIds(), true);
    }

    /**
     * @return array{certification_id: ?string, status: ?string, keyword: ?string}
     */
    public function filters(): array
    {
        return [
            'certification_id' => $this->input('certification_id'),
            'status' => $this->input('status'),
            'keyword' => $this->input('keyword'),
        ];
    }
}
