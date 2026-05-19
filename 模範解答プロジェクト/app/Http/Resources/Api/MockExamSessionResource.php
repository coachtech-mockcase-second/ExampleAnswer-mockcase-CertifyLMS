<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\MockExamSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 運用エクスポート API `/api/v1/admin/mock-exam-sessions` のレスポンス整形。
 *
 * `category_breakdown` は `MockExamSessionController` が `->additional(['_batch' => [...]])` で
 * 渡したバッチ集計を参照する (graded セッションのみ非空、それ以外は空配列)。配点判定基準のスナップショット
 * `passing_score_snapshot` は受験時点の合格点を保持し、後で `passing_score` 改修があっても合否の事実が変わらない
 * ようにする運用上の正規データ。
 *
 * @mixin MockExamSession
 */
class MockExamSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'mock_exam_id' => $this->mock_exam_id,
            'enrollment_id' => $this->enrollment_id,
            'status' => $this->status->value,
            'total_correct' => $this->total_correct,
            'passing_score_snapshot' => $this->passing_score_snapshot,
            'pass' => $this->pass,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'graded_at' => $this->graded_at?->toIso8601String(),
            'category_breakdown' => $this->analytics_category_breakdown ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => UserResource::make($this->whenLoaded('user')),
            'mock_exam' => MockExamResource::make($this->whenLoaded('mockExam')),
            'enrollment' => EnrollmentResource::make($this->whenLoaded('enrollment')),
        ];
    }
}
