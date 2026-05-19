<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\MockExam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 運用エクスポート API で MockExamSession.mock_exam (whenLoaded) を入れ子返却する際の薄い Resource。
 *
 * @mixin MockExam
 */
class MockExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certification_id' => $this->certification_id,
            'title' => $this->title,
            'order' => $this->order,
            'is_published' => $this->is_published,
            'passing_score' => $this->passing_score,
        ];
    }
}
