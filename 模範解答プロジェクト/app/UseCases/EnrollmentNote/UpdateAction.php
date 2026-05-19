<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;

/**
 * メモの本文編集 Action。コーチは自身が作成したノートのみ、admin は越境可(Policy 側で判定済)。
 * coach_user_id(作成者) は UPDATE しない(履歴的に意味があるため不変)。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(EnrollmentNote $note, array $validated): EnrollmentNote
    {
        $note->update(['body' => $validated['body']]);

        return $note->refresh();
    }
}
