<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_student_sees_all_published_certification_threads(): void
    {
        $student = User::factory()->student()->create();
        $publishedA = Certification::factory()->published()->create();
        $publishedB = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();

        QaThread::factory()->forCertification($publishedA)->count(2)->create();
        QaThread::factory()->forCertification($publishedB)->count(1)->create();
        QaThread::factory()->forCertification($draft)->count(3)->create();

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk();
        $threads = $response->viewData('threads');
        $this->assertSame(3, $threads->total(), 'draft 資格のスレッドは除外され、公開 3 件が表示されること');
    }

    public function test_coach_sees_only_assigned_certification_threads(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $this->attachCoach($assigned, $coach);

        QaThread::factory()->forCertification($assigned)->count(3)->create();
        QaThread::factory()->forCertification($other)->count(5)->create();

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertOk();
        $threads = $response->viewData('threads');
        $this->assertSame(3, $threads->total(), '担当外資格のスレッドは除外されること');
    }

    public function test_coach_specifying_unassigned_certification_returns_403(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $this->attachCoach($assigned, $coach);

        $response = $this->actingAs($coach)->get(route('qa-board.index', ['certification_id' => $other->id]));

        $response->assertForbidden();
    }

    public function test_filter_by_status_unresolved(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        QaThread::factory()->forCertification($cert)->unresolved()->count(2)->create();
        QaThread::factory()->forCertification($cert)->resolved()->count(3)->create();

        $response = $this->actingAs($student)->get(route('qa-board.index', ['status' => 'unresolved']));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('threads')->total());
    }

    public function test_filter_by_status_resolved(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        QaThread::factory()->forCertification($cert)->unresolved()->count(2)->create();
        QaThread::factory()->forCertification($cert)->resolved()->count(3)->create();

        $response = $this->actingAs($student)->get(route('qa-board.index', ['status' => 'resolved']));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('threads')->total());
    }

    public function test_keyword_search_matches_title_body_and_reply_body(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $titleHit = QaThread::factory()->forCertification($cert)->create(['title' => 'プロジェクト管理の章で迷う', 'body' => '通常本文']);
        $bodyHit = QaThread::factory()->forCertification($cert)->create(['title' => '通常タイトル', 'body' => 'PERT 図と CPM の違い']);
        $replyHit = QaThread::factory()->forCertification($cert)->create(['title' => '通常タイトル', 'body' => '通常本文']);
        QaReply::factory()->forThread($replyHit)->create(['body' => 'CPM のクリティカルパスは...']);
        QaThread::factory()->forCertification($cert)->create(['title' => '無関係', 'body' => '別の話題']);

        $response = $this->actingAs($student)->get(route('qa-board.index', ['keyword' => 'CPM']));

        $response->assertOk();
        $ids = $response->viewData('threads')->getCollection()->pluck('id')->all();
        $this->assertCount(2, $ids, 'body と reply の両方が CPM にヒットし、title 一致だけのものはヒットしない');
        $this->assertContains($bodyHit->id, $ids);
        $this->assertContains($replyHit->id, $ids);
    }

    public function test_eager_loading_avoids_n_plus_one(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        QaThread::factory()->forCertification($cert)->count(5)->create();

        $this->actingAs($student)->get(route('qa-board.index'));

        DB::enableQueryLog();
        $this->actingAs($student)->get(route('qa-board.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThan(20, count($queries), 'Eager Loading + withCount でクエリ数を抑制できていること');
    }
}
