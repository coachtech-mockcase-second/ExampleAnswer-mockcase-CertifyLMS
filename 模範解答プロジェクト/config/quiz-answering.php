<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 弱点分析サービスの実装クラス
    |--------------------------------------------------------------------------
    |
    | WeaknessAnalysisServiceContract に bind する具象クラス名を指定する。
    | null の場合は NullWeaknessAnalysisService がフォールバック登録され、
    | おすすめバッジは全カテゴリで非表示になる。
    | 模試 Feature が完成した環境では .env で WeaknessAnalysisService::class
    | を指定する。
    */
    'weakness_analysis_service' => env('QUIZ_ANSWERING_WEAKNESS_ANALYSIS_SERVICE'),
];
