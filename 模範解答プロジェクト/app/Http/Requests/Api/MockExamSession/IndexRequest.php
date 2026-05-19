<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\MockExamSession;

use App\Enums\MockExamSessionStatus;
use App\Http\Controllers\Api\MockExamSessionController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 運用エクスポート API `/api/v1/admin/mock-exam-sessions` の入力検証。
 *
 * 模試 ID / 合否 / ステータス / 提出日範囲 (`from` / `to`) / Eager Loading 対象を受ける。
 * 提出日 `from > to` は 422、`pass` は boolean 受けで `0/1` / `true/false` どちらも許容。
 *
 * @see MockExamSessionController::index()
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
            'mock_exam_id' => ['nullable', 'ulid', 'exists:mock_exams,id'],
            // GAS / curl から `?pass=true|false` の文字列受けも受け付けるため `in` で拡張する。
            'pass' => ['nullable', 'in:0,1,true,false,True,False'],
            'status' => ['nullable', Rule::enum(MockExamSessionStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
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
            'mock_exam_id.exists' => '指定された模試が存在しません。',
            'from.date_format' => '提出日 (開始) は YYYY-MM-DD 形式で指定してください。',
            'to.date_format' => '提出日 (終了) は YYYY-MM-DD 形式で指定してください。',
            'to.after_or_equal' => '提出日 (終了) は提出日 (開始) 以降の日付を指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mock_exam_id' => '模試 ID',
            'pass' => '合否',
            'status' => 'ステータス',
            'from' => '提出日 (開始)',
            'to' => '提出日 (終了)',
            'include' => 'Eager Load 対象',
            'per_page' => '1 ページあたり件数',
            'page' => 'ページ番号',
        ];
    }

    /**
     * `?include=user,mock_exam,enrollment` を寛容にパースする。許容外キーは無視する。
     *
     * @return array<int, string>
     */
    public function resolveIncludes(): array
    {
        $raw = (string) $this->validated('include', '');

        if ($raw === '') {
            return [];
        }

        $allowed = ['user', 'mock_exam', 'enrollment'];
        $requested = array_filter(array_map('trim', explode(',', $raw)));

        return array_values(array_intersect($requested, $allowed));
    }
}
