<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * コーチ / admin による Enrollment 配下メモ追加 Action。coach_user_id には操作者(coach or admin) の ID を記録。
 * 認可は Controller 側の Policy で完結。
 */
final class StoreAction
{
    /**
     * @param  array{body: string}  $validated
     */
    public function __invoke(Enrollment $enrollment, User $author, array $validated): EnrollmentNote
    {
        return $enrollment->notes()->create([
            'coach_user_id' => $author->id,
            'body' => $validated['body'],
        ]);
    }
}
