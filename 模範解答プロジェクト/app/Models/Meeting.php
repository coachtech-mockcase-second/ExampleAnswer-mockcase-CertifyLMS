<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingStatus;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 1on1 面談予約を表す Model。
 *
 * 1 件 = 60 分固定の単発予約で、受講生 / 担当コーチ / 紐づく受講登録の 3 者と、面談回数履歴の
 * 消費トランザクションを参照する。コーチは予約時に自動割当されるため、受講生フローから coach_id を
 * 直接指定することはない(`Meeting\StoreAction` で決定)。
 *
 * 関連: Enrollment(受講登録) / User(coach / student / canceledBy) / MeetingMemo(1:1) / MeetingQuotaTransaction(消費トランザクション)
 * scope: upcoming / past / forCoach(coach) / forStudent(student)
 */
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'enrollment_id',
        'coach_id',
        'student_id',
        'scheduled_at',
        'status',
        'topic',
        'canceled_by_user_id',
        'canceled_at',
        'meeting_url_snapshot',
        'completed_at',
        'meeting_quota_transaction_id',
        'google_event_id',
    ];

    protected $casts = [
        'status' => MeetingStatus::class,
        'scheduled_at' => 'datetime',
        'canceled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * キャンセル操作の実行者(受講生 or コーチ)。退会等で User が SoftDelete された後も
     * 「誰がキャンセルしたか」を履歴で示せるよう withTrashed で参照する。
     *
     * @return BelongsTo<User, $this>
     */
    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by_user_id')->withTrashed();
    }

    /**
     * @return HasOne<MeetingMemo, $this>
     */
    public function meetingMemo(): HasOne
    {
        return $this->hasOne(MeetingMemo::class);
    }

    /**
     * @return BelongsTo<MeetingQuotaTransaction, $this>
     */
    public function quotaTransaction(): BelongsTo
    {
        return $this->belongsTo(MeetingQuotaTransaction::class, 'meeting_quota_transaction_id');
    }

    /**
     * 今後の予約(予約済 かつ 開始時刻が未来)に絞る。dashboard / 履歴 upcoming タブで利用。
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->where('status', MeetingStatus::Reserved->value)
            ->where('scheduled_at', '>=', now());
    }

    /**
     * 過去の予約(キャンセル済 or 完了済)に絞る。履歴 past タブで利用。
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->whereIn('status', [
            MeetingStatus::Canceled->value,
            MeetingStatus::Completed->value,
        ]);
    }

    public function scopeForCoach(Builder $query, User $coach): Builder
    {
        return $query->where('coach_id', $coach->id);
    }

    public function scopeForStudent(Builder $query, User $student): Builder
    {
        return $query->where('student_id', $student->id);
    }
}
