<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CoachGoogleCredentialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * コーチの Google Calendar OAuth 認証情報を表す Model。1 コーチ : 1 認証情報。
 *
 * 短寿命の `access_token` は `refresh_token` で自動更新する(`GoogleCalendarTokenService`)。
 * `calendar_id` は OAuth 同意時に取得した primary カレンダー識別子で、将来コーチが任意のカレンダーを
 * 切り替える余地を残す。連携解除時は SoftDelete で履歴を保持する。
 *
 * 関連: User(coach)
 */
class CoachGoogleCredential extends Model
{
    /** @use HasFactory<CoachGoogleCredentialFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'coach_id',
        'access_token',
        'refresh_token',
        'calendar_id',
        'connected_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
