<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;

/**
 * メモの削除 Action(物理削除)。コーチは自身が作成したノートのみ、admin は越境可(Policy 側で判定済)。
 */
final class DestroyAction
{
    public function __invoke(EnrollmentNote $note): void
    {
        $note->delete();
    }
}
