<?php

declare(strict_types=1);

/**
 * 学習セッション関連の設定。Schedule Command (learning:close-stale-sessions) の閾値と、
 * LearningSession\StartAction での duration_seconds clamp 上限を同期させる目的で集中管理する。
 */
return [
    'max_session_seconds' => env('LEARNING_MAX_SESSION_SECONDS', 3600),
    'close_stale_schedule' => env('LEARNING_CLOSE_STALE_SCHEDULE', '01:00'),

    /*
     * 受講生ダッシュボードの学習カレンダー (GitHub 風の日別学習時間ヒートマップ) の表示設定。
     * months: 草グリッドに表示する「今月を含む直近 N ヶ月」。
     * 濃淡レベルの閾値はフロント (resources/js/dashboard/learning-calendar.js) が
     * 分単位 (20分 / 45分 / 90分) で持つ。
     */
    'calendar' => [
        'months' => 4,
    ],
];
