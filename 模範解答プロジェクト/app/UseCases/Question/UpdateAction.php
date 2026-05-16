<?php

declare(strict_types=1);

namespace App\UseCases\Question;

use App\Exceptions\Content\QuestionCategoryMismatchException;
use App\Exceptions\Content\QuestionCertificationMismatchException;
use App\Exceptions\Content\QuestionInvalidOptionsException;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(Question $question, User $actor, array $validated): Question
    {
        $newSectionId = array_key_exists('section_id', $validated)
            ? ($validated['section_id'] ?: null)
            : $question->section_id;

        if ($newSectionId !== null && $newSectionId !== $question->section_id) {
            $section = Section::with('chapter.part')->findOrFail($newSectionId);
            if ($section->chapter->part->certification_id !== $question->certification_id) {
                throw new QuestionCertificationMismatchException;
            }
        }

        if (array_key_exists('category_id', $validated)
            && $validated['category_id'] !== $question->category_id
        ) {
            $category = QuestionCategory::find($validated['category_id']);
            if ($category === null || $category->certification_id !== $question->certification_id) {
                throw new QuestionCategoryMismatchException;
            }
        }

        if (array_key_exists('options', $validated)) {
            $correctCount = collect($validated['options'])->where('is_correct', true)->count();
            if ($correctCount !== 1) {
                throw new QuestionInvalidOptionsException;
            }
        }

        return DB::transaction(function () use ($question, $validated, $newSectionId) {
            $attributes = Arr::only($validated, ['body', 'explanation', 'category_id', 'difficulty']);
            $attributes['section_id'] = $newSectionId;

            $question->update($attributes);

            if (array_key_exists('options', $validated)) {
                $question->options()->delete();
                foreach ($validated['options'] as $idx => $opt) {
                    $question->options()->create([
                        'body' => $opt['body'],
                        'is_correct' => (bool) ($opt['is_correct'] ?? false),
                        'order' => $idx + 1,
                    ]);
                }
            }

            return $question->fresh(['options', 'category']);
        });
    }
}
