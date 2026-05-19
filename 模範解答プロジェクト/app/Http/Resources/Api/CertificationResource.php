<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Certification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 運用エクスポート API で Enrollment.certification (whenLoaded) を入れ子返却する際の薄い Resource。
 *
 * GAS 側の Sheet 表示で必要十分な属性のみ返し、出題仕様 (passing_score / total_questions /
 * exam_duration_minutes) は MockExam 側の Resource にまとめる。
 *
 * @mixin Certification
 */
class CertificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'difficulty' => $this->difficulty?->value,
            'description' => $this->description,
        ];
    }
}
