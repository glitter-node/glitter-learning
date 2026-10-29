<?php

namespace Modules\Glitter\Learning\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Glitter\Learning\Models\Completion;
use Modules\Glitter\Learning\Models\Course;
use Modules\Glitter\Learning\Models\Enrollment;
use Modules\Glitter\Learning\Models\Lesson;
use Modules\Glitter\Learning\Models\LessonProgress;
use Modules\Glitter\Learning\Support\CompletionPolicy;

class LearningService
{
    public function enroll(Course $course, User $user): Enrollment
    {
        return DB::transaction(function () use ($course, $user) {
            $enrollment = Enrollment::query()
                ->where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($enrollment) {
                if ($enrollment->status === 'cancelled') {
                    $enrollment->update([
                        'status' => 'active',
                        'enrolled_at' => now(),
                        'cancelled_at' => null,
                    ]);
                }

                return $enrollment->fresh(['course']);
            }

            try {
                return Enrollment::query()->create([
                    'course_id' => $course->id,
                    'user_id' => $user->id,
                    'status' => 'active',
                    'enrolled_at' => now(),
                ])->load('course');
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }

                return Enrollment::query()
                    ->where('course_id', $course->id)
                    ->where('user_id', $user->id)
                    ->firstOrFail()
                    ->load('course');
            }
        });
    }

    public function completeLesson(Enrollment $enrollment, Lesson $lesson): Enrollment
    {
        if ((int) $lesson->course_id !== (int) $enrollment->course_id || ! $lesson->is_published) {
            throw ValidationException::withMessages([
                'lesson' => __('glitter-learning::validation.lesson_unavailable'),
            ]);
        }

        if (! in_array($enrollment->status, ['active', 'completed'], true)) {
            throw ValidationException::withMessages([
                'enrollment' => __('glitter-learning::validation.enrollment_inactive'),
            ]);
        }

        return DB::transaction(function () use ($enrollment, $lesson) {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);

            LessonProgress::query()->firstOrCreate(
                ['enrollment_id' => $locked->id, 'lesson_id' => $lesson->id],
                ['completed_at' => now()]
            );

            $this->completeEnrollmentWhenEligible($locked);

            return $locked->fresh(['course', 'progress', 'completion']);
        });
    }

    public function progressSummary(Enrollment $enrollment): array
    {
        $enrollment->loadMissing('completion');
        if ($enrollment->completion !== null) {
            $requiredCount = $enrollment->completion->required_lessons_count;

            return [
                'required_lessons' => $requiredCount,
                'completed_required_lessons' => $requiredCount,
                'progress_percent' => 100,
                'is_completed' => true,
            ];
        }

        $requiredIds = Lesson::query()
            ->where('course_id', $enrollment->course_id)
            ->where('is_required', true)
            ->pluck('id');
        $completedRequired = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $requiredIds)
            ->count();
        $requiredCount = $requiredIds->count();

        return [
            'required_lessons' => $requiredCount,
            'completed_required_lessons' => $completedRequired,
            'progress_percent' => CompletionPolicy::progressPercent($requiredCount, $completedRequired),
            'is_completed' => $enrollment->status === 'completed',
        ];
    }

    private function completeEnrollmentWhenEligible(Enrollment $enrollment): void
    {
        if (Completion::query()->where('enrollment_id', $enrollment->id)->exists()) {
            return;
        }

        $requiredLessonIds = Lesson::query()
            ->where('course_id', $enrollment->course_id)
            ->where('is_required', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');

        $completedCount = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $requiredLessonIds)
            ->distinct()
            ->count('lesson_id');

        if (! CompletionPolicy::isEligible($requiredLessonIds->count(), $completedCount)) {
            return;
        }

        $completedAt = now();
        Completion::query()->firstOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'course_id' => $enrollment->course_id,
                'user_id' => $enrollment->user_id,
                'completed_at' => $completedAt,
                'required_lessons_count' => $requiredLessonIds->count(),
                'criteria_version' => 'required-lessons-v1',
                'criteria_snapshot' => [
                    'required_lesson_ids' => $requiredLessonIds->values()->all(),
                    'rule' => 'all_required_lessons_completed',
                ],
            ]
        );
        $enrollment->update(['status' => 'completed', 'completed_at' => $completedAt]);
    }
}
