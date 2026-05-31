<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Q&A 掲示板の開発用シーダー。
 *
 * **設計思想(Seeder 業界標準: 状態網羅 + 固定アカウント)**:
 *
 * 1. **資格ごとにスレッド散布**: published 資格に対し 5-10 件のスレッドを資格別の現実味あるトピックで散布。
 *    受講生カタログ → 資格詳細 → 質問掲示板の動線で「すでに議論されている」状態を見せる。
 * 2. **状態網羅**: open / resolved 両方を混在させ、「未解決のみ」「解決済のみ」フィルタの動作確認を可能にする。
 *    resolved スレッドは reply 1 件以上 + resolved_at を最終 reply 時刻に揃え、「解決を導いた回答」が文脈上必ず存在する状態にする。
 * 3. **固定 student のスレッド**: student@certify-lms.test を投稿者にしたスレッドを最低 2 件用意し、
 *    「自分の質問」一覧 + 「解決マーク」ボタンの実機確認を可能にする。
 * 4. **複数 reply 混在**: 0 / 1 / 2-3 reply のスレッドを混ぜ、reply 件数バッジ・並び順の確認に対応する。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder` → 本 Seeder。
 */
final class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        $publishedCertifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->orderBy('created_at')
            ->get();

        if ($publishedCertifications->isEmpty()) {
            $this->command?->warn('QaBoardSeeder: 公開済資格がありません。先に CertificationSeeder を実行してください。');

            return;
        }

        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $fixedCoach = User::query()->where('email', 'coach@certify-lms.test')->first();

        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'student@certify-lms.test')
            ->get();

        $coaches = User::query()
            ->where('role', UserRole::Coach->value)
            ->where('status', UserStatus::InProgress->value)
            ->get();

        if ($demoStudents->isEmpty() && $fixedStudent === null) {
            $this->command?->warn('QaBoardSeeder: 受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        foreach ($publishedCertifications as $certification) {
            $this->seedThreadsForCertification($certification, $fixedStudent, $fixedCoach, $demoStudents, $coaches);
        }
    }

    /**
     * 1 資格に対し 5-10 件のスレッドを投入する。固定 student の投稿を最低 1 件混ぜる(基本情報 / 応用情報 限定)。
     *
     * @param  Collection<int, User>  $demoStudents
     * @param  Collection<int, User>  $coaches
     */
    private function seedThreadsForCertification(
        Certification $certification,
        ?User $fixedStudent,
        ?User $fixedCoach,
        Collection $demoStudents,
        Collection $coaches,
    ): void {
        $templates = $this->threadTemplatesFor($certification->name);
        $assignedCoaches = $certification->coaches()->get();
        $threadCoach = $assignedCoaches->first() ?? $fixedCoach ?? $coaches->first();

        foreach ($templates as $i => $template) {
            $useFixedStudent = $fixedStudent !== null && in_array($certification->name, ['基本情報技術者試験', '応用情報技術者試験'], true) && $i < 2;
            $poster = $useFixedStudent
                ? $fixedStudent
                : ($demoStudents->get($i % max($demoStudents->count(), 1)) ?? $fixedStudent);

            if ($poster === null) {
                continue;
            }

            $createdAt = now()->subDays((int) (30 - $i * 3))->subHours($i);

            $thread = QaThread::factory()
                ->state([
                    'certification_id' => $certification->id,
                    'user_id' => $poster->id,
                    'title' => $template['title'],
                    'body' => $template['body'],
                    'status' => QaThreadStatus::Open->value,
                    'resolved_at' => null,
                ])
                ->create();

            $thread->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            $replyCount = $template['replies'];
            $lastReplyAt = $this->seedReplies($thread, $poster, $threadCoach, $demoStudents, $createdAt, $replyCount);

            if ($template['resolved'] && $replyCount > 0) {
                $thread->update([
                    'status' => QaThreadStatus::Resolved->value,
                    'resolved_at' => $lastReplyAt ?? $createdAt,
                ]);
            }
        }
    }

    /**
     * スレッドに対し reply を生成し、最終 reply の時刻を返す(resolved_at に流用する)。
     *
     * @param  Collection<int, User>  $demoStudents
     */
    private function seedReplies(
        QaThread $thread,
        User $threadPoster,
        ?User $threadCoach,
        Collection $demoStudents,
        Carbon $threadCreatedAt,
        int $replyCount,
    ): ?Carbon {
        if ($replyCount <= 0) {
            return null;
        }

        $replyBodies = [
            'コーチからの回答: 教材の該当 Section をもう一度通して読んでみてください。' .
                "そのうえで分からない点を具体例として挙げてもらえると、より深掘りした補足ができます。",
            '受講生からの共有: 私も同じところで詰まりました。' .
                '過去問の解説を 3 周してから本文に戻ったら腑に落ちました。同じ流れで試してみる価値はあると思います。',
            'コーチ補足: 試験本番では時間配分が重要なので、' .
                'まずは標準時間より 20% 速く解く意識で過去問を回してみるとよいです。',
        ];

        $lastReplyAt = null;
        for ($i = 0; $i < $replyCount; $i++) {
            $isCoachReply = ($i === 0 && $threadCoach !== null) || $i === $replyCount - 1 && $threadCoach !== null;
            $replyUser = $isCoachReply
                ? $threadCoach
                : ($demoStudents->filter(fn (User $u) => $u->id !== $threadPoster->id)->skip($i)->first()
                    ?? $threadPoster);

            $replyAt = $threadCreatedAt->copy()->addHours(($i + 1) * 6);
            if ($replyAt->greaterThan(now())) {
                $replyAt = now()->subMinutes(($replyCount - $i) * 15);
            }

            $reply = QaReply::factory()
                ->state([
                    'qa_thread_id' => $thread->id,
                    'user_id' => $replyUser->id,
                    'body' => $replyBodies[$i % count($replyBodies)],
                ])
                ->create();
            $reply->forceFill(['created_at' => $replyAt, 'updated_at' => $replyAt])->save();

            $lastReplyAt = $replyAt;
        }

        return $lastReplyAt;
    }

    /**
     * 資格名ごとの現実味のあるスレッドテンプレ。
     *
     * @return list<array{title: string, body: string, replies: int, resolved: bool}>
     */
    private function threadTemplatesFor(string $certificationName): array
    {
        return match ($certificationName) {
            '基本情報技術者試験' => [
                ['title' => 'ハフマン符号化の問題でつまずいています', 'body' => "ハフマン木の構築手順は理解できているのですが、各文字のビット数を求める計算で時間がかかってしまいます。\n\n効率的に解くコツがあれば教えてください。", 'replies' => 3, 'resolved' => true],
                ['title' => '2 進数の補数表現がいまいち腑に落ちません', 'body' => "1 の補数 / 2 の補数 / 符号付絶対値の使い分けと、それぞれが対応するビット範囲の覚え方を整理したいです。\n\n模試で毎回どちらか迷ってしまいます。", 'replies' => 2, 'resolved' => true],
                ['title' => 'ネットワーク分野が苦手で点が伸びません', 'body' => "OSI 参照モデルと TCP/IP の対応関係、各プロトコルの役割が散発的にしか覚えられません。\n\n体系的な暗記の進め方を共有いただけると助かります。", 'replies' => 2, 'resolved' => false],
                ['title' => 'SQL の結合 (INNER / LEFT) の使い分け', 'body' => '過去問で結合の問題は半々の正答率です。INNER と LEFT の使い分けの判断基準を、典型問題ごとに整理したいです。', 'replies' => 1, 'resolved' => true],
                ['title' => '擬似言語の読み解きで時間切れになります', 'body' => "アルゴリズム問題の擬似言語パートを読み切れず、毎回最後の数問を捨てています。\n\nどこに着目しながら読むと効率的でしょうか?", 'replies' => 0, 'resolved' => false],
                ['title' => 'セキュリティ分野の暗号方式の整理方法', 'body' => '共通鍵暗号 / 公開鍵暗号 / ハッシュ関数 / 電子署名の整理がぼんやりとしか頭に入っていません。', 'replies' => 1, 'resolved' => false],
            ],
            '応用情報技術者試験' => [
                ['title' => '午後試験の論述、時間配分の組み方', 'body' => "選択問題 5 問を 2 時間半で解く配分が安定しません。\n\n回答順序や見直し時間の取り方の定石を知りたいです。", 'replies' => 3, 'resolved' => true],
                ['title' => 'プロジェクトマネジメント分野の苦手意識', 'body' => "PMBOK 由来の用語が定着せず、似たような問題で毎回点数を落とします。\n\nおすすめの整理表や問題集を教えてください。", 'replies' => 2, 'resolved' => true],
                ['title' => 'ER 図設計問題で点を落とします', 'body' => '正規化と関連の多重度がうまく書けません。典型パターンを掴むには何を解けばよいでしょうか?', 'replies' => 1, 'resolved' => false],
                ['title' => '情報セキュリティの過去問の深堀り範囲', 'body' => '解説まで読み込むと 1 問 30 分かかります。どこまで深堀すべきか境界線が知りたいです。', 'replies' => 2, 'resolved' => true],
                ['title' => '経営戦略分野は捨ててもよい?', 'body' => '配点と学習コストのバランスが気になっています。捨て選択肢として現実的かどうか相談したいです。', 'replies' => 0, 'resolved' => false],
            ],
            'TOEIC L&R 800 点コース' => [
                ['title' => 'Part 5 で時間を使いすぎてしまいます', 'body' => "Part 5 だけで 15 分以上使ってしまい、Part 7 に皺寄せが来ています。\n\n短縮するための解き順や捨て問の判断基準を教えてください。", 'replies' => 2, 'resolved' => true],
                ['title' => 'Part 7 が時間内に終わりません', 'body' => "ダブルパッセージから先が毎回時間切れです。\n\nスピード向上のための読み方や練習メニューを相談したいです。", 'replies' => 3, 'resolved' => true],
                ['title' => 'リスニングの先読みのコツ', 'body' => 'Part 3 / Part 4 の設問先読みが間に合わず、結果として答えを聞き取り損ねます。', 'replies' => 1, 'resolved' => false],
                ['title' => '単語帳のおすすめがあれば教えてください', 'body' => '金フレを 1 周しましたが伸び悩んでいます。次の 1 冊として何が良いでしょうか?', 'replies' => 2, 'resolved' => true],
                ['title' => '公式問題集の効果的な復習方法', 'body' => '1 周終えたのですが、復習で何を中心にやればよいか迷っています。', 'replies' => 0, 'resolved' => false],
            ],
            '日商簿記 2 級' => [
                ['title' => '工業簿記の原価計算で毎回詰まります', 'body' => "個別原価計算と総合原価計算の使い分けが定着しません。\n\n判定基準を例題ベースで整理したいです。", 'replies' => 2, 'resolved' => true],
                ['title' => '精算表の整理仕訳で必ず 1 ヶ所間違える', 'body' => '主に減価償却 / 貸倒引当金 / 売上原価あたりで取りこぼします。チェックリスト的な確認手順を相談したいです。', 'replies' => 1, 'resolved' => false],
                ['title' => '連結会計の処理が複雑で覚えきれません', 'body' => '投資と資本の相殺消去から開始仕訳まで、流れが頭で繋がりません。', 'replies' => 3, 'resolved' => true],
                ['title' => '商業簿記の決算整理仕訳をまとめたい', 'body' => '頻出パターンを 1 枚の表にまとめたいのですが、項目立てに迷っています。おすすめのまとめ方を教えてください。', 'replies' => 0, 'resolved' => false],
            ],
            'PMP' => [
                ['title' => 'PMBOK の知識エリアの覚え方', 'body' => "10 知識エリア × 5 プロセス群のマトリクスがどうしても頭に入りません。\n\n暗記用のコツや覚え順序があれば共有してほしいです。", 'replies' => 2, 'resolved' => true],
                ['title' => 'Agile 関連の問題が解けません', 'body' => "Predictive 寄りの問題は解けるのですが、Agile / Hybrid の選択肢が来ると失点します。\n\nおすすめの補強教材を教えてください。", 'replies' => 1, 'resolved' => false],
                ['title' => 'ITTO の理解で時間がかかっています', 'body' => '個別の Tool & Technique を覚えるのが苦痛です。実務想起ベースで覚える方法はありますか?', 'replies' => 2, 'resolved' => true],
                ['title' => 'リスクマネジメント計画の具体例が欲しい', 'body' => '計画書のサンプルがイメージできず、設問の文脈を取り違えてしまいます。', 'replies' => 0, 'resolved' => false],
            ],
            default => [],
        };
    }
}
