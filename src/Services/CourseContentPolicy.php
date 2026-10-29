<?php

namespace Modules\Glitter\Learning\Services;

use Illuminate\Validation\ValidationException;
use Modules\Glitter\Learning\Models\Course;

class CourseContentPolicy
{
    public function assertCourseCanBePublished(Course $course): void
    {
        if ($course->lessons()
            ->where('is_required', true)
            ->where('is_published', false)
            ->exists()) {
            throw ValidationException::withMessages([
                'status' => __('glitter-learning::validation.course_has_unpublished_required_lessons'),
            ]);
        }
    }

    public function assertLessonStateAllowed(Course $course, bool $isRequired, bool $isPublished): void
    {
        if ($course->status === 'published' && $isRequired && ! $isPublished) {
            throw ValidationException::withMessages([
                'is_published' => __('glitter-learning::validation.required_lesson_must_be_published'),
            ]);
        }
    }
}
