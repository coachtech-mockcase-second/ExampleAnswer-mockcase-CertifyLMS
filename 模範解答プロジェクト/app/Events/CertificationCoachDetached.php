<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 担当コーチが資格から解除された際に発火するイベント。
 * 関連 [[chat]] 等のリスナーが担当変更を受けて Chat メンバーを同期する想定。
 */
final class CertificationCoachDetached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Certification $certification,
        public readonly User $coach,
        public readonly User $admin,
    ) {}
}
