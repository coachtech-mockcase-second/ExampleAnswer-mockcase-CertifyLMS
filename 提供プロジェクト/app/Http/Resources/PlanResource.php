<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plan の select 表示 / モーダル組込用 Resource。
 *
 * 用途: 招待モーダル(user-management 所有)・プラン延長モーダルで Plan select を描画する際に利用。
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_days' => $this->duration_days,
            'default_meeting_quota' => $this->default_meeting_quota,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'sort_order' => $this->sort_order,
        ];
    }
}
