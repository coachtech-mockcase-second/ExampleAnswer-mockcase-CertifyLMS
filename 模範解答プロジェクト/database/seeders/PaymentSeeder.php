<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingPackStatus;
use App\Enums\MeetingQuotaTransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 追加面談クォータ購入と admin 付与の開発用シーダー。
 *
 * **設計思想(Seeder 業界標準: status 網羅 + 全 type 補完)**:
 *
 * 1. **Payment status 4 種網羅**: succeeded / pending / failed / refunded を投入。
 *    決済履歴一覧 / status フィルタ / 再決済リンクの動作確認を可能にする。
 * 2. **MeetingQuotaTransaction の type 補完**: Purchased(succeeded Payment 連動) + AdminGrant(admin 手動付与)を投入。
 *    Consumed / Refunded は MentoringSeeder で投入済(面談予約 / キャンセル連動)。
 *    GrantedInitial(初期付与)は UserLifecycleSeeder が投入するため、本 Seeder / MentoringSeeder と合わせて type 5 種すべてが混在し、ユーザー詳細画面の履歴セクションを通しで動作確認できる状態にする。
 * 3. **固定 student に手厚く**: succeeded Payment 1 件 + pending Payment 1 件 + admin_grant 1 件を投入し、PR スクショ参照を安定化。
 *
 * 依存順序: `UserSeeder` → `MeetingPackSeeder` → `MentoringSeeder` → 本 Seeder。
 *   (MentoringSeeder が consumed / refunded を投入するため、本 Seeder はそれの後に置くことで履歴の時系列が現実的になる)
 */
final class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $publishedPacks = MeetingPack::query()
            ->where('status', MeetingPackStatus::Published->value)
            ->ordered()
            ->get();

        if ($publishedPacks->isEmpty()) {
            $this->command?->warn('PaymentSeeder: published MeetingPack がありません。先に MeetingPackSeeder を実行してください。');

            return;
        }

        $admin = User::query()->where('email', 'admin@certify-lms.test')->first();
        if ($admin === null) {
            $admin = User::query()->where('role', UserRole::Admin->value)->orderBy('created_at')->first();
        }

        if ($admin === null) {
            $this->command?->warn('PaymentSeeder: 管理者 User が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($fixedStudent !== null) {
            $this->seedFixedStudentPayments($fixedStudent, $publishedPacks, $admin);
        }

        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'student@certify-lms.test')
            ->orderBy('created_at')
            ->take(6)
            ->get();

        $this->seedDemoStudentPayments($demoStudents, $publishedPacks, $admin);
    }

    /**
     * 固定 student に succeeded + pending + admin_grant を投入。
     *
     * @param Collection<int, MeetingPack> $packs
     */
    private function seedFixedStudentPayments(User $student, Collection $packs, User $admin): void
    {
        $pack5 = $packs->firstWhere('meeting_count', 5) ?? $packs->first();
        $pack1 = $packs->firstWhere('meeting_count', 1) ?? $packs->first();

        $this->createSucceededPaymentWithTransaction($student, $pack5, daysAgo: 14);
        $this->createPendingPayment($student, $pack1, daysAgo: 1);
        $this->createAdminGrantTransaction($student, $admin, amount: 2, daysAgo: 5, reason: '初回コーチ変更に伴う運用補填');
    }

    /**
     * demo student × 6 に対し status / type を散らす。
     *
     * @param Collection<int, User> $students
     * @param Collection<int, MeetingPack> $packs
     */
    private function seedDemoStudentPayments(Collection $students, Collection $packs, User $admin): void
    {
        if ($students->isEmpty()) {
            return;
        }

        $patterns = ['succeeded', 'succeeded', 'pending', 'failed', 'refunded', 'admin_grant'];

        foreach ($students as $i => $student) {
            $pattern = $patterns[$i % count($patterns)];
            $pack = $packs->get($i % $packs->count());

            $daysAgo = ($i + 1) * 3;

            match ($pattern) {
                'succeeded' => $this->createSucceededPaymentWithTransaction($student, $pack, $daysAgo),
                'pending' => $this->createPendingPayment($student, $pack, $daysAgo),
                'failed' => $this->createFailedPayment($student, $pack, $daysAgo),
                'refunded' => $this->createRefundedPayment($student, $pack, $daysAgo),
                'admin_grant' => $this->createAdminGrantTransaction($student, $admin, amount: 1, daysAgo: $daysAgo, reason: 'システム障害補填'),
                default => null,
            };
        }
    }

    private function createSucceededPaymentWithTransaction(User $user, MeetingPack $pack, int $daysAgo): void
    {
        $paidAt = now()->subDays($daysAgo);

        $payment = Payment::factory()
            ->succeeded()
            ->state([
                'user_id' => $user->id,
                'meeting_pack_id' => $pack->id,
                'amount' => $pack->price,
                'quantity' => 1,
                'paid_at' => $paidAt,
            ])
            ->create();

        $payment->forceFill(['created_at' => $paidAt->copy()->subMinutes(2), 'updated_at' => $paidAt])->save();

        MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => $pack->meeting_count,
            'related_meeting_id' => null,
            'related_payment_id' => $payment->id,
            'granted_by_user_id' => null,
            'note' => null,
            'occurred_at' => $paidAt,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
    }

    private function createPendingPayment(User $user, MeetingPack $pack, int $daysAgo): void
    {
        $createdAt = now()->subDays($daysAgo);

        $payment = Payment::factory()
            ->pending()
            ->state([
                'user_id' => $user->id,
                'meeting_pack_id' => $pack->id,
                'amount' => $pack->price,
                'quantity' => 1,
            ])
            ->create();

        $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
    }

    private function createFailedPayment(User $user, MeetingPack $pack, int $daysAgo): void
    {
        $createdAt = now()->subDays($daysAgo);

        $payment = Payment::factory()
            ->failed()
            ->state([
                'user_id' => $user->id,
                'meeting_pack_id' => $pack->id,
                'amount' => $pack->price,
                'quantity' => 1,
            ])
            ->create();

        $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
    }

    private function createRefundedPayment(User $user, MeetingPack $pack, int $daysAgo): void
    {
        $paidAt = now()->subDays($daysAgo + 3);
        $refundedAt = now()->subDays($daysAgo);

        $payment = Payment::factory()
            ->refunded()
            ->state([
                'user_id' => $user->id,
                'meeting_pack_id' => $pack->id,
                'amount' => $pack->price,
                'quantity' => 1,
                'paid_at' => $paidAt,
            ])
            ->create();

        $payment->forceFill(['created_at' => $paidAt->copy()->subMinutes(2), 'updated_at' => $refundedAt])->save();

        MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => $pack->meeting_count,
            'related_meeting_id' => null,
            'related_payment_id' => $payment->id,
            'granted_by_user_id' => null,
            'note' => null,
            'occurred_at' => $paidAt,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);

        MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => -$pack->meeting_count,
            'related_meeting_id' => null,
            'related_payment_id' => $payment->id,
            'granted_by_user_id' => null,
            'note' => '購入分の返金処理',
            'occurred_at' => $refundedAt,
            'created_at' => $refundedAt,
            'updated_at' => $refundedAt,
        ]);
    }

    private function createAdminGrantTransaction(User $user, User $admin, int $amount, int $daysAgo, string $reason): void
    {
        $occurredAt = now()->subDays($daysAgo);

        MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::AdminGrant->value,
            'amount' => $amount,
            'related_meeting_id' => null,
            'related_payment_id' => null,
            'granted_by_user_id' => $admin->id,
            'note' => $reason,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }
}
