<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\CoachGoogleCredential;
use App\Models\Meeting;
use Carbon\Carbon;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime as GoogleEventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Illuminate\Support\Facades\Log;

/**
 * Google Calendar API のラッパー Service。freebusy 取得 / event 作成 / event 削除を提供し、
 * トークン期限切れ時の自動リフレッシュを内包する。
 *
 * 1 リクエスト 1 コーチで freebusy.query を呼ぶ運用(複数 calendar を同時 query しないことで OAuth scope を最小化)。
 * エラー時は空配列 / null を返してフォールバックし、面談予約画面の根幹機能(CoachAvailability ベース)が壊れないようにする。
 */
// final は外している(Mockery で Action テスト時にスタブするため、backend-services.md 「Mockery で
// テストする Service は final 不採用可」方針)
class GoogleCalendarService
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
    ) {}

    /**
     * 指定コーチのカレンダーから [$start, $end] の busy 時間帯を取得する。
     * トークン期限切れ時は refresh_token で自動更新し、refresh も失敗したら空配列を返す。
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function freebusy(CoachGoogleCredential $credential, Carbon $start, Carbon $end): array
    {
        try {
            $client = $this->oauthService->buildClient();
            $client->setAccessToken($credential->access_token);

            if ($client->isAccessTokenExpired()) {
                if (! $this->refresh($credential, $client)) {
                    return [];
                }
            }

            $request = new FreeBusyRequest;
            $request->setTimeMin($start->toRfc3339String());
            $request->setTimeMax($end->toRfc3339String());
            $request->setTimeZone('Asia/Tokyo');

            $item = new FreeBusyRequestItem;
            $item->setId($credential->calendar_id);
            $request->setItems([$item]);

            $calendar = new GoogleCalendar($client);
            $response = $calendar->freebusy->query($request);
            $busy = $response['calendars'][$credential->calendar_id]['busy'] ?? [];

            return array_map(fn (array $period) => [
                'start' => Carbon::parse($period['start']),
                'end' => Carbon::parse($period['end']),
            ], $busy);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarService::freebusy failed', [
                'coach_id' => $credential->coach_id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Meeting に紐づく event を作成し、成功時に event_id を返す。失敗時は null を返して呼出側で
     * `meetings.google_event_id` を NULL のまま放置できるようにする(GCal は付加機能扱い)。
     */
    public function insertEvent(CoachGoogleCredential $credential, Meeting $meeting): ?string
    {
        try {
            $client = $this->oauthService->buildClient();
            $client->setAccessToken($credential->access_token);

            if ($client->isAccessTokenExpired() && ! $this->refresh($credential, $client)) {
                return null;
            }

            $meeting->loadMissing(['student', 'enrollment.certification']);
            $studentName = $meeting->student?->name ?? '受講生';
            $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';
            $meetingUrl = $meeting->meeting_url_snapshot ?? '';

            $event = new GoogleEvent;
            $event->setSummary("{$studentName} と {$certificationName} の面談");
            $event->setDescription(implode("\n\n", array_filter([
                "資格: {$certificationName}",
                "受講生: {$studentName}",
                '相談内容: '.$meeting->topic,
                $meetingUrl !== '' ? "面談 URL: {$meetingUrl}" : null,
            ])));
            if ($meetingUrl !== '') {
                $event->setLocation($meetingUrl);
            }

            $startDateTime = new GoogleEventDateTime;
            $startDateTime->setDateTime($meeting->scheduled_at->toRfc3339String());
            $startDateTime->setTimeZone('Asia/Tokyo');
            $event->setStart($startDateTime);

            $endDateTime = new GoogleEventDateTime;
            $endDateTime->setDateTime($meeting->scheduled_at->copy()->addHour()->toRfc3339String());
            $endDateTime->setTimeZone('Asia/Tokyo');
            $event->setEnd($endDateTime);

            $calendar = new GoogleCalendar($client);
            $created = $calendar->events->insert($credential->calendar_id, $event);

            return $created->getId();
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarService::insertEvent failed', [
                'coach_id' => $credential->coach_id,
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 既存 event を削除する。410 Gone(既に削除済)は成功扱い、他例外は warning ログを残す。
     */
    public function deleteEvent(CoachGoogleCredential $credential, string $eventId): void
    {
        try {
            $client = $this->oauthService->buildClient();
            $client->setAccessToken($credential->access_token);

            if ($client->isAccessTokenExpired() && ! $this->refresh($credential, $client)) {
                return;
            }

            $calendar = new GoogleCalendar($client);
            $calendar->events->delete($credential->calendar_id, $eventId);
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 410) {
                // 既に削除済 = 成功扱い
                return;
            }
            Log::warning('GoogleCalendarService::deleteEvent failed', [
                'coach_id' => $credential->coach_id,
                'event_id' => $eventId,
                'http_code' => $e->getCode(),
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarService::deleteEvent unexpected error', [
                'coach_id' => $credential->coach_id,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 期限切れトークンを refresh_token で更新する。成功時 true を返し、credential の DB 行を UPDATE する。
     * refresh_token も無効化された場合は false を返し、呼出側はリクエストをスキップする。
     */
    private function refresh(CoachGoogleCredential $credential, \Google\Client $client): bool
    {
        try {
            $token = $client->fetchAccessTokenWithRefreshToken($credential->refresh_token);

            if (isset($token['error']) || ! isset($token['access_token'])) {
                Log::warning('GoogleCalendarService::refresh: refresh_token invalid', [
                    'coach_id' => $credential->coach_id,
                    'error' => $token['error'] ?? 'no access_token returned',
                ]);

                return false;
            }

            $credential->update(['access_token' => $token['access_token']]);
            $client->setAccessToken($token['access_token']);

            return true;
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarService::refresh failed', [
                'coach_id' => $credential->coach_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
