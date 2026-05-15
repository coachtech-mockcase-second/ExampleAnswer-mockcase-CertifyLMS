<?php

namespace App\UseCases\Question;

use App\Enums\ContentStatus;
use App\Exceptions\Content\QuestionCategoryMismatchException;
use App\Exceptions\Content\QuestionCertificationMismatchException;
use App\Exceptions\Content\QuestionInvalidOptionsException;
use App\Models\Certification;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(Certification $certification, User $actor, array $validated): Question
    {
        $sectionId = $validated['section_id'] ?? null;
        if ($sectionId !== null && $sectionId !== '') {
            $section = Section::with('chapter.part')->findOrFail($sectionId);
            if ($section->chapter->part->certification_id !== $certification->id) {
                throw new QuestionCertificationMismatchException();
            }
        } else {
            $sectionId = null;
        }

        $category = QuestionCategory::find($validated['category_id'] ?? null);
        if ($category === null || $category->certification_id !== $certification->id) {
            throw new QuestionCategoryMismatchException();
        }

        $options = $validated['options'] ?? [];
        $correctCount = collect($options)->where('is_correct', true)->count();
        if ($correctCount !== 1) {
            throw new QuestionInvalidOptionsException();
        }

        return DB::transaction(function () use ($certification, $validated, $options, $sectionId) {
            $question = $certification->questions()->create([
                ...Arr::only($validated, ['body', 'explanation', 'category_id', 'difficulty']),
                'section_id' => $sectionId,
                'status' => ContentStatus::Draft->value,
                'order' => 0,
                'published_at' => null,
            ]);

            foreach ($options as $idx => $opt) {
                $question->options()->create([
                    'body' => $opt['body'],
                    'is_correct' => (bool) ($opt['is_correct'] ?? false),
                    'order' => $idx + 1,
                ]);
            }

            return $question->fresh(['options', 'category']);
        });
    }
}
