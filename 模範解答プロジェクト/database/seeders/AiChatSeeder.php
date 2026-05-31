<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * AI 相談履歴の開発用シーダー。
 *
 * **設計思想(Seeder 業界標準: モード網羅 + 固定アカウント)**:
 *
 * 1. **2 モード網羅**: general(全般相談、section_id・enrollment_id とも null) / withSection(教材相談、
 *    section_id + 所属資格の enrollment_id) を混在し、AI チャット起動位置別の動線・履歴一覧フィルタが
 *    効くことを実機確認できるようにする。全般相談の資格コンテキストは受講生の default_enrollment_id から解決される。
 * 2. **固定 student に両モード**: student@certify-lms.test には両モードの会話を確実に作り、
 *    画面確認・PR スクショで安定参照できる「決まった会話」を用意する。
 * 3. **メッセージ status 網羅**: assistantPending(処理中)で終わる会話と assistantError(生成失敗)で終わる会話を
 *    それぞれ 1 件用意し、「処理中」表示と「エラー表示」の動作確認を可能にする。
 * 4. **last_message_at の更新**: 各会話末尾の message 時刻を last_message_at に反映し、
 *    一覧の降順並び順が現実的に見える状態にする。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → `ContentSeeder`(Section 必要) → 本 Seeder。
 */
final class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'student@certify-lms.test')
            ->orderBy('created_at')
            ->take(6)
            ->get();

        if ($fixedStudent === null && $demoStudents->isEmpty()) {
            $this->command?->warn('AiChatSeeder: 受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        if ($fixedStudent !== null) {
            $this->seedConversationsForFixedStudent($fixedStudent);
        }

        foreach ($demoStudents as $i => $student) {
            $modeIndex = $i % 2;
            $this->seedSingleConversation($student, mode: $modeIndex, daysAgo: 3 + $i);
        }
    }

    /**
     * 固定 student に両モード(general / withSection)の会話 + 末尾が処理中 / エラーで終わる会話を投入する。
     */
    private function seedConversationsForFixedStudent(User $student): void
    {
        $this->seedSingleConversation($student, mode: 0, daysAgo: 2);
        $this->seedSingleConversation($student, mode: 1, daysAgo: 5);
        $this->seedSingleConversation($student, mode: 1, daysAgo: 9, pendingTail: true);
        // 応答が error で終わる会話: エラー表示の動作確認用
        $this->seedSingleConversation($student, mode: 0, daysAgo: 4, errorTail: true);
    }

    /**
     * 指定モードで 1 会話を投入する。mode: 0 = general / 1 = withSection。
     */
    private function seedSingleConversation(User $student, int $mode, int $daysAgo, bool $pendingTail = false, bool $errorTail = false): void
    {
        [$enrollment, $section] = $this->resolveContextForMode($student, $mode);

        $title = $this->titleForMode($mode);
        $script = $this->scriptForMode($mode, $section);

        $messageCount = count($script);
        $startedAt = now()->subDays($daysAgo);
        $lastMessageAt = $startedAt->copy()->addMinutes(($messageCount - 1) * 3);

        $conversation = AiChatConversation::factory()
            ->state([
                'user_id' => $student->id,
                'enrollment_id' => $enrollment?->id,
                'section_id' => $section?->id,
                'title' => $title,
                'last_message_at' => $lastMessageAt,
            ])
            ->create();

        $conversation->forceFill(['created_at' => $startedAt, 'updated_at' => $lastMessageAt])->save();

        $this->seedMessages($conversation, $script, $startedAt, $pendingTail, $errorTail);
    }

    /**
     * mode に応じて enrollment / section を解決する。
     *
     * mode=0 (general): 共に null (資格コンテキストは default_enrollment_id から解決)
     * mode=1 (withSection): student の learning Enrollment + その資格の Section を 1 件解決。
     *   Enrollment / Section いずれかが見つからなければ general (共に null) にフォールバックする
     *   (section_id 無しで enrollment_id だけ紐付く会話は作らない)。
     *
     * @return array{0: ?Enrollment, 1: ?Section}
     */
    private function resolveContextForMode(User $student, int $mode): array
    {
        if ($mode === 0) {
            return [null, null];
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Learning->value)
            ->orderBy('created_at')
            ->first();

        if ($enrollment === null) {
            return [null, null];
        }

        $section = Section::query()
            ->whereHas('chapter.part', fn ($q) => $q->where('certification_id', $enrollment->certification_id))
            ->orderBy('created_at')
            ->first();

        if ($section === null) {
            return [null, null];
        }

        return [$enrollment, $section];
    }

    private function titleForMode(int $mode): string
    {
        return match ($mode) {
            0 => '学習計画の立て方を相談',
            1 => '教材の補足説明をリクエスト',
            default => 'AI 相談',
        };
    }

    /**
     * 会話シナリオ(user / assistant 交互、6 件)。
     *
     * @return list<array{role: AiChatMessageRole, content: string}>
     */
    private function scriptForMode(int $mode, ?Section $section): array
    {
        $sectionTitle = $section?->title ?? '教材';

        return match ($mode) {
            0 => [
                ['role' => AiChatMessageRole::User, 'content' => "資格試験の合格に向けて、効率的な学習計画の立て方を教えてください。\n仕事と両立しながら 3 ヶ月で合格を目指しています。"],
                ['role' => AiChatMessageRole::Assistant, 'content' => "3 ヶ月で合格を目指す前提で、以下の 3 段階に分けるのが現実的です。\n\n1. 1 ヶ月目: 出題範囲全体を 1 周(教材 + 章末問題)\n2. 2 ヶ月目: 過去問演習 + 弱点分野の重点学習\n3. 3 ヶ月目: 模試 + 復習 + 仕上げ\n\n仕事との両立を考えると平日 60-90 分 / 休日 3-4 時間が無理のないペースになります。"],
                ['role' => AiChatMessageRole::User, 'content' => "弱点分野はどう特定するのがよいですか?\n章末問題の正答率で判断してよいでしょうか?"],
                ['role' => AiChatMessageRole::Assistant, 'content' => "章末問題の正答率は 1 つの指標になりますが、それだけでは不十分です。\n\nおすすめは以下の 3 指標を組み合わせる方法です:\n- 章末問題の正答率(60% 未満を弱点候補に)\n- 解答スピード(時間がかかる単元は理解が浅い兆候)\n- 過去問の分野別正答率(本番形式での実力)\n\n3 つを総合して優先順位を決めると、効率よく弱点克服が進みます。"],
                ['role' => AiChatMessageRole::User, 'content' => 'ありがとうございます。早速、章末問題を進めながら指標を集めてみます。'],
                ['role' => AiChatMessageRole::Assistant, 'content' => '良いスタートです。1 週間試してみて、データが揃ったら再度学習計画を一緒に見直しましょう。'],
            ],
            1 => [
                ['role' => AiChatMessageRole::User, 'content' => "教材『{$sectionTitle}』の例題 3 番、解説を読んでも納得できませんでした。別の角度から説明してください。"],
                ['role' => AiChatMessageRole::Assistant, 'content' => "例題 3 番は、「条件分岐 + ループの組み合わせ」を問う典型問題です。\n以下のステップで読み解くと整理しやすくなります:\n1. 条件分岐の境界値を先に書き出す\n2. ループの初回 / 中間 / 最終の 3 ケースで挙動を追う\n3. 期待出力と実際の動きを比較\n\nこの読み方で例題 3 番を再度解いてみてください。"],
                ['role' => AiChatMessageRole::User, 'content' => 'ループの中間ケースを追うときに変数の状態が複雑になります。何かよい記法はありますか?'],
                ['role' => AiChatMessageRole::Assistant, 'content' => "「ループ展開」と呼ばれる紙ベースの方法がおすすめです。\n各反復ステップで変数の値を行に書き連ねていく方法で、複雑な処理でも誤りが発見しやすくなります。\n\n例: i=1 のとき x=5, i=2 のとき x=8 ... のように紙に書く。"],
                ['role' => AiChatMessageRole::User, 'content' => '別の例題も同じ方法で解いてみます。ありがとうございました!'],
                ['role' => AiChatMessageRole::Assistant, 'content' => 'ぜひ試してみてください。詰まったら同じセクションから再度質問できます。'],
            ],
            default => [],
        };
    }

    /**
     * 会話に対し script の順序でメッセージを投入する。pendingTail / errorTail=true の場合、最後の assistant メッセージを
     * それぞれ Pending / Error 状態で残す(本文は空にし、生成途中 / 生成失敗を表現する)。
     *
     * @param list<array{role: AiChatMessageRole, content: string}> $script
     */
    private function seedMessages(AiChatConversation $conversation, array $script, Carbon $startedAt, bool $pendingTail, bool $errorTail = false): void
    {
        foreach ($script as $i => $line) {
            $createdAt = $startedAt->copy()->addMinutes($i * 3);
            $isLastAssistant = $i === count($script) - 1
                && $line['role'] === AiChatMessageRole::Assistant;
            $isLastAssistantPending = $pendingTail && $isLastAssistant;
            $isLastAssistantError = $errorTail && $isLastAssistant;

            $factory = AiChatMessage::factory()->state([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => $line['role']->value,
            ]);

            $factory = match (true) {
                $line['role'] === AiChatMessageRole::User => $factory->userMessage(),
                $isLastAssistantPending => $factory->assistantPending(),
                $isLastAssistantError => $factory->assistantError(),
                default => $factory->assistantCompleted(),
            };

            $content = ($isLastAssistantPending || $isLastAssistantError) ? '' : $line['content'];
            $message = $factory->state(['content' => $content])->create();
            $message->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }
    }
}
