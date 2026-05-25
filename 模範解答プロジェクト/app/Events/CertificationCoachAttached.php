<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 担当コーチが資格に新規割当された際に発火するイベント。
 * Chat メンバー同期リスナーが本イベントを受けて、該当資格に紐づく全 ChatRoom の参加者を同期する。
 */
final class CertificationCoachAttached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Certification $certification,
        public readonly User $coach,
        public readonly User $admin,
    ) {}
}
