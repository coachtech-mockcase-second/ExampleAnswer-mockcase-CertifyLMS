<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 受講生の修了証を表す Model。受講生が修了達成後に自己発火で発行される。
 * 1 Enrollment につき 1 Certificate（`enrollment_id` UNIQUE）。SoftDelete 不採用（修了証は永続データ）。
 *
 * 関連: User(受講生) / Enrollment(発行元の受講登録) / Certification(資格)
 * scope: issuedThisMonth(当月発行分のみ)
 */
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'certification_id',
        'serial_no',
        'pdf_path',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    public function scopeIssuedThisMonth(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereYear('issued_at', $now->year)
            ->whereMonth('issued_at', $now->month);
    }
}
