<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [[enrollment]] Feature の正式実装に先立つ stub。
 * [[certification-management]] が必要とする最小カラム + リレーションのみ定義する。
 */
class Enrollment extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'certification_id',
        'status',
        'exam_date',
        'current_term',
        'completion_requested_at',
        'passed_at',
    ];

    protected $casts = [
        'status' => EnrollmentStatus::class,
        'exam_date' => 'date',
        'completion_requested_at' => 'datetime',
        'passed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
