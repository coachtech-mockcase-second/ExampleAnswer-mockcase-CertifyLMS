<?php

declare(strict_types=1);

return [
    // Google Calendar イベント文言(GoogleCalendarService::insertEvent で使用)。
    // マジック文字列を避け、面談イベントの summary / description をここに集約する。
    'gcal' => [
        'event_summary' => ':student と :certification の面談',
        'event_description_template' => "資格: :certification\n\n受講生: :student\n\n相談内容: :topic",
        'event_description_meeting_url' => '面談 URL: :url',
    ],
];
