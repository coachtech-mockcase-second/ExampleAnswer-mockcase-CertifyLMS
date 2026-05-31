<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MockExamAnswer\UpdateRequest;
use App\Models\MockExamSession;
use App\UseCases\MockExamAnswer\UpdateAction;
use Illuminate\Http\JsonResponse;

/**
 * 受験中の解答を逐次保存(PATCH JSON) する Controller。
 *
 * resources/js/mock-exam/answer-autosave.js が選択肢の change イベントで PATCH 呼出する。
 */
class MockExamAnswerController extends Controller
{
    public function update(MockExamSession $session, UpdateRequest $request, UpdateAction $action): JsonResponse
    {
        $answer = $action($session, $request->validated());

        return response()->json([
            'mock_exam_question_id' => $answer->mock_exam_question_id,
            'selected_option_id' => $answer->selected_option_id,
            'answered_at' => $answer->answered_at?->toIso8601String(),
        ]);
    }
}
