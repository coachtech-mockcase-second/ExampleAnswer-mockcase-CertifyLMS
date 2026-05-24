<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * 資格 × 担当コーチの割当を表す Pivot Model。
 * 解除時は `unassigned_at` を now() に設定 + SoftDelete することで、過去の担当履歴を残しつつ active 行だけ参照可能にする。
 *
 * Active 行の取得は `Certification::coaches()` の `wherePivot('unassigned_at', null)` 経由。
 */
class CertificationCoachAssignment extends Pivot
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'certification_coach_assignments';

    protected $fillable = [
        'certification_id',
        'user_id',
        'assigned_by_user_id',
        'assigned_at',
        'unassigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];
}
