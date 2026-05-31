<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 管理者ダッシュボード集計キャッシュ
    |--------------------------------------------------------------------------
    |
    | 全体 KPI(learning / passed / failed)と資格別修了率は全 enrollment を走査する
    | 重い集計のため、一定時間 Cache::remember でキャッシュする。受講状態の遷移時には
    | EnrollmentStatusChangeService がキャッシュを無効化して最新値を再計算させ、それ以外の
    | 変化(新規受講登録 / 資格公開)は TTL 失効で取り込む。集計は全件対象でロール差が無いため
    | ロール横断の単一キーで共有する。
    |
    */

    'admin_stats_cache_ttl' => 300,

    'admin_kpi_cache_key' => 'dashboard:admin:kpi',

    'admin_completion_rate_cache_key' => 'dashboard:admin:completion-rate',

];
