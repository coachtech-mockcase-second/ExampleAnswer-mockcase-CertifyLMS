<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Enrollment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 運用エクスポート API `/api/v1/admin/enrollments` のレスポンス整形。
 *
 * `progress_rate` (Section 単位完了率) と `last_activity_at` (教材セッション + 演習解答の MAX) は
 * `EnrollmentController` が `->additional(['_batch' => [...]])` で渡したバッチ算出結果を参照する
 * (Resource 自身が DB を叩かないことで N+1 を回避する)。担当コーチ別カラムは Enrollment から
 * 撤回済 (担当紐づきは certification_coach_assignments 経由) のため、本 Resource にも含めない。
 *
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lastActivityAt = $this->analytics_last_activity_at ?? null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'certification_id' => $this->certification_id,
            'status' => $this->status->value,
            'current_term' => $this->current_term->value,
            'exam_date' => $this->exam_date?->format('Y-m-d'),
            'passed_at' => $this->passed_at?->toIso8601String(),
            'progress_rate' => $this->analytics_progress_rate ?? null,
            'last_activity_at' => $lastActivityAt instanceof Carbon
                ? $lastActivityAt->toIso8601String()
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => UserResource::make($this->whenLoaded('user')),
            'certification' => CertificationResource::make($this->whenLoaded('certification')),
        ];
    }
}
