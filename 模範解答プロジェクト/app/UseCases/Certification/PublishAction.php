<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationInvalidTransitionException;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 資格マスタを公開（draft → published）するユースケース。
 * 公開済 / アーカイブ済からの遷移は不正で CertificationInvalidTransitionException（409）。
 */
final class PublishAction
{
    /**
     * @throws CertificationInvalidTransitionException 下書き以外からの呼出
     */
    public function __invoke(Certification $certification, User $admin): Certification
    {
        if ($certification->status !== CertificationStatus::Draft) {
            throw CertificationInvalidTransitionException::forPublish();
        }

        return DB::transaction(function () use ($certification, $admin) {
            $certification->update([
                'status' => CertificationStatus::Published->value,
                'published_at' => now(),
                'updated_by_user_id' => $admin->id,
            ]);

            return $certification->fresh();
        });
    }
}
