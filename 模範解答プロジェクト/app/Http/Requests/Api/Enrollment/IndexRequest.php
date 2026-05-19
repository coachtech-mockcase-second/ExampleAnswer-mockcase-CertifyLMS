<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
use App\Http\Controllers\Api\EnrollmentController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 運用エクスポート API `/api/v1/admin/enrollments` の入力検証。
 *
 * 状態 (Learning / Passed / Failed の 3 値、paused は撤回) / 資格 ID / 学習ターム / Eager Loading 対象を受ける。
 * 担当コーチ別フィルタは廃止 (担当紐づきは certification_coach_assignments 経由になったため)。
 * Eager Loading で許容するのは `user` / `certification` のみで、その他キーは寛容に無視する。
 *
 * @see EnrollmentController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(EnrollmentStatus::class)],
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'current_term' => ['nullable', Rule::enum(TermType::class)],
            'include' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.max' => '1 回のリクエストで取得できる件数は 500 件までです。',
            'certification_id.exists' => '指定された資格が存在しません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'ステータス',
            'certification_id' => '資格 ID',
            'current_term' => '学習ターム',
            'include' => 'Eager Load 対象',
            'per_page' => '1 ページあたり件数',
            'page' => 'ページ番号',
        ];
    }

    /**
     * `?include=user,certification` を寛容にパースする。許容外キーは無視する。
     *
     * @return array<int, string>
     */
    public function resolveIncludes(): array
    {
        $raw = (string) $this->validated('include', '');

        if ($raw === '') {
            return [];
        }

        $allowed = ['user', 'certification'];
        $requested = array_filter(array_map('trim', explode(',', $raw)));

        return array_values(array_intersect($requested, $allowed));
    }
}
