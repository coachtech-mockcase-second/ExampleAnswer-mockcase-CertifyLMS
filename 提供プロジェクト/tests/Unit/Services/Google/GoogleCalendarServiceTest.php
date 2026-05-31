<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Google;

use App\Models\CoachGoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Google\GoogleCalendarService;
use App\Services\Google\GoogleOAuthService;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Google Calendar 操作 Service `GoogleCalendarService` の単体テスト。
 * 空き時刻取得(freebusy)の正常系 / トークン期限切れ → リフレッシュ成功 → リトライ / リフレッシュ失敗 → 空配列フォールバック /
 * busy なし → 空配列、予定作成(insertEvent)の event_id 返却・失敗時 null、予定削除(deleteEvent)の 410 Gone 成功扱い・
 * 他例外の握りつぶしを、Mockery でスタブした `Google\Client` 上で検証する。
 *
 * `GoogleCalendarService` は内部で `GoogleOAuthService::buildClient()` から `Google\Client` を取得するため、
 * `GoogleOAuthService` を Mockery でモックして `buildClient()` がスタブ済 `Google\Client` を返すよう差し替える。
 * Calendar の Resource 呼出(`freebusy->query` / `events->insert` / `events->delete`)は最終的に
 * `Google\Client::execute()` に到達するため、`execute()` の戻り値 / 例外でケースを構成する。
 *
 * モック手法に Mockery を採用する理由 + `#[Group('external')]` / `Http::preventStrayRequests()` の意図は
 * `GoogleOAuthServiceTest` のクラス DocBlock と同様(`google/apiclient` の独自 HTTP クライアントは `Http::fake` 不可)。
 */
#[Group('external')]
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 未モックの外部通信が発生したらテストを失敗させる最終ライン(API キー漏洩 / レート制限消費の防止)
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Calendar の Resource 呼出が `execute()` まで到達できるよう、HTTP 詳細以外のインフラメソッドを
     * 既定スタブした `Google\Client` の Mockery モックを返す。各テストは `execute()` / トークン系の expectation を追加する。
     *
     * @param bool $expired `isAccessTokenExpired()` の戻り値(期限切れシナリオで true)
     */
    private function clientMock(bool $expired = false): MockInterface
    {
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setAccessToken');
        $client->shouldReceive('isAccessTokenExpired')->andReturn($expired);
        // google/apiclient の Service\Resource::call() が execute() 到達前に参照するインフラメソッド群
        $client->shouldReceive('getLogger')->andReturn(new NullLogger);
        $client->shouldReceive('getUniverseDomain')->andReturn('googleapis.com');
        $client->shouldReceive('shouldDefer')->andReturn(false);

        return $client;
    }

    /**
     * スタブ済 `Google\Client` を `buildClient()` から返す `GoogleOAuthService` を DI した
     * 実物の `GoogleCalendarService` を組み立てる。
     */
    private function serviceWithClient(MockInterface $client): GoogleCalendarService
    {
        $oauth = Mockery::mock(GoogleOAuthService::class);
        $oauth->shouldReceive('buildClient')->andReturn($client);

        return new GoogleCalendarService($oauth);
    }

    private function credential(): CoachGoogleCredential
    {
        $coach = User::factory()->coach()->create(['meeting_url' => 'https://meet.example.com/coach-room']);

        return CoachGoogleCredential::factory()->forCoach($coach)->create([
            'access_token' => 'ya29.valid_access',
            'refresh_token' => '1//valid_refresh',
            'calendar_id' => 'primary',
        ]);
    }

    public function test_freebusy_returns_busy_periods_as_carbon_pairs(): void
    {
        // Arrange
        $credential = $this->credential();
        $client = $this->clientMock();
        $client->shouldReceive('execute')->once()->andReturn([
            'calendars' => [
                'primary' => [
                    'busy' => [
                        ['start' => '2026-06-01T10:00:00+09:00', 'end' => '2026-06-01T11:00:00+09:00'],
                        ['start' => '2026-06-01T14:00:00+09:00', 'end' => '2026-06-01T15:00:00+09:00'],
                    ],
                ],
            ],
        ]);
        $service = $this->serviceWithClient($client);

        // Act
        $busy = $service->freebusy(
            $credential,
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 18:00:00'),
        );

        // Assert
        $this->assertCount(2, $busy);
        $this->assertInstanceOf(Carbon::class, $busy[0]['start']);
        $this->assertInstanceOf(Carbon::class, $busy[0]['end']);
        $this->assertTrue($busy[0]['start']->equalTo(Carbon::parse('2026-06-01T10:00:00+09:00')));
        $this->assertTrue($busy[1]['end']->equalTo(Carbon::parse('2026-06-01T15:00:00+09:00')));
    }

    public function test_freebusy_returns_empty_array_when_no_busy_periods(): void
    {
        // Arrange
        $credential = $this->credential();
        $client = $this->clientMock();
        $client->shouldReceive('execute')->once()->andReturn(['calendars' => ['primary' => ['busy' => []]]]);
        $service = $this->serviceWithClient($client);

        // Act
        $busy = $service->freebusy(
            $credential,
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 18:00:00'),
        );

        // Assert
        $this->assertSame([], $busy);
    }

    public function test_freebusy_refreshes_expired_token_then_retries_and_persists_new_access_token(): void
    {
        // Arrange: access_token が期限切れ → refresh_token で更新成功 → freebusy 続行
        $credential = $this->credential();
        $client = $this->clientMock(expired: true);
        $client->shouldReceive('fetchAccessTokenWithRefreshToken')
            ->once()
            ->with('1//valid_refresh')
            ->andReturn(['access_token' => 'ya29.refreshed_access', 'expires_in' => 3599]);
        $client->shouldReceive('execute')->once()->andReturn([
            'calendars' => ['primary' => ['busy' => [
                ['start' => '2026-06-01T10:00:00+09:00', 'end' => '2026-06-01T11:00:00+09:00'],
            ]]],
        ]);
        $service = $this->serviceWithClient($client);

        // Act
        $busy = $service->freebusy(
            $credential,
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 18:00:00'),
        );

        // Assert
        $this->assertCount(1, $busy, 'refresh 成功後に freebusy がリトライされ busy が取得できるはず');
        $this->assertSame(
            'ya29.refreshed_access',
            $credential->fresh()->access_token,
            'refresh 成功時は新 access_token が credential に永続化されるはず',
        );
    }

    public function test_freebusy_returns_empty_array_when_token_refresh_fails(): void
    {
        // Arrange: 期限切れ + refresh_token も無効化されたケース → 空配列フォールバック
        $credential = $this->credential();
        $client = $this->clientMock(expired: true);
        $client->shouldReceive('fetchAccessTokenWithRefreshToken')
            ->once()
            ->andReturn(['error' => 'invalid_grant']);
        // refresh 失敗時は freebusy.query を実行しない
        $client->shouldNotReceive('execute');
        $service = $this->serviceWithClient($client);

        // Act
        $busy = $service->freebusy(
            $credential,
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 18:00:00'),
        );

        // Assert
        $this->assertSame([], $busy, 'refresh 失敗時は空配列でフォールバックするはず');
        $this->assertSame(
            'ya29.valid_access',
            $credential->fresh()->access_token,
            'refresh 失敗時は access_token を書き換えないはず',
        );
    }

    public function test_freebusy_returns_empty_array_when_api_throws(): void
    {
        // Arrange: API 呼出が例外 → Throwable catch で空配列にフォールバック
        $credential = $this->credential();
        $client = $this->clientMock();
        $client->shouldReceive('execute')->once()->andThrow(new GoogleServiceException('Backend Error', 500));
        $service = $this->serviceWithClient($client);

        // Act
        $busy = $service->freebusy(
            $credential,
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 18:00:00'),
        );

        // Assert
        $this->assertSame([], $busy, 'API 例外時は空配列でフォールバックするはず(面談予約の根幹機能を壊さない)');
    }

    public function test_insert_event_returns_event_id_on_success(): void
    {
        // Arrange
        $credential = $this->credential();
        $meeting = Meeting::factory()->reserved()->forCoach($credential->coach)->create([
            'scheduled_at' => Carbon::parse('2026-06-01 10:00:00'),
            'topic' => 'アルゴリズム分野の相談',
        ]);

        $createdEvent = new GoogleEvent;
        $createdEvent->setId('gcal-event-abc');

        $client = $this->clientMock();
        $client->shouldReceive('execute')->once()->andReturn($createdEvent);
        $service = $this->serviceWithClient($client);

        // Act
        $eventId = $service->insertEvent($credential, $meeting);

        // Assert
        $this->assertSame('gcal-event-abc', $eventId);
    }

    public function test_insert_event_returns_null_when_api_throws(): void
    {
        // Arrange: 予定作成失敗 → null(予約自体は成功扱い、GCal は付加機能)
        $credential = $this->credential();
        $meeting = Meeting::factory()->reserved()->forCoach($credential->coach)->create([
            'scheduled_at' => Carbon::parse('2026-06-01 10:00:00'),
            'topic' => '相談',
        ]);

        $client = $this->clientMock();
        $client->shouldReceive('execute')->once()->andThrow(new GoogleServiceException('Forbidden', 403));
        $service = $this->serviceWithClient($client);

        // Act
        $eventId = $service->insertEvent($credential, $meeting);

        // Assert
        $this->assertNull($eventId, '予定作成失敗時は null を返し meetings.google_event_id を NULL のままにするはず');
    }

    public function test_insert_event_returns_null_when_token_refresh_fails(): void
    {
        // Arrange: 期限切れ + refresh 失敗 → events.insert を呼ばず null
        $credential = $this->credential();
        $meeting = Meeting::factory()->reserved()->forCoach($credential->coach)->create([
            'scheduled_at' => Carbon::parse('2026-06-01 10:00:00'),
            'topic' => '相談',
        ]);

        $client = $this->clientMock(expired: true);
        $client->shouldReceive('fetchAccessTokenWithRefreshToken')->once()->andReturn(['error' => 'invalid_grant']);
        $client->shouldNotReceive('execute');
        $service = $this->serviceWithClient($client);

        // Act
        $eventId = $service->insertEvent($credential, $meeting);

        // Assert
        $this->assertNull($eventId);
    }

    public function test_delete_event_treats_410_gone_as_success(): void
    {
        // Arrange: 既に削除済(410 Gone)は成功扱い → 例外を投げず正常終了
        $credential = $this->credential();
        $client = $this->clientMock();
        $client->shouldReceive('execute')->once()->andThrow(new GoogleServiceException('Resource has been deleted', 410));
        $service = $this->serviceWithClient($client);

        // Act
        $service->deleteEvent($credential, 'gcal-event-gone');

        // Assert
        // 410 を握りつぶし例外が伝播しないことが成功扱いの証明
        $this->addToAssertionCount(1);
    }

    public function test_delete_event_swallows_other_api_errors(): void
    {
        // Arrange: 410 以外の API エラーもログのみで継続(例外を伝播しない)
        $credential = $this->credential();
        $client = $this->clientMock();
        $client->shouldReceive('execute')->once()->andThrow(new GoogleServiceException('Server Error', 500));
        $service = $this->serviceWithClient($client);

        // Act
        $service->deleteEvent($credential, 'gcal-event-x');

        // Assert
        $this->addToAssertionCount(1);
    }

    public function test_delete_event_succeeds_on_normal_deletion(): void
    {
        // Arrange
        $credential = $this->credential();
        $client = $this->clientMock();
        // 正常削除時、Calendar の events.delete は空ボディを返す(execute は null 相当)
        $client->shouldReceive('execute')->once()->andReturnNull();
        $service = $this->serviceWithClient($client);

        // Act
        $service->deleteEvent($credential, 'gcal-event-ok');

        // Assert
        $this->addToAssertionCount(1);
    }
}
