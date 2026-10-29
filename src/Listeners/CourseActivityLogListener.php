<?php

namespace Modules\Glitter\Learning\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use App\ActivityLog\Traits\ResolvesActivityLogType;
use Modules\Glitter\Learning\Models\Course;

class CourseActivityLogListener implements HookListenerInterface
{
    use ResolvesActivityLogType;

    public static function getSubscribedHooks(): array
    {
        return [
            'glitter-learning.course.after_delete' => ['method' => 'handleCourseAfterDelete', 'priority' => 20],
        ];
    }

    public function handle(...$args): void
    {
    }

    public function handleCourseAfterDelete(Course $course, int $lessonCount): void
    {
        $this->logActivity('course.delete', [
            'loggable_type' => Course::class,
            'loggable_id' => $course->id,
            'description_key' => 'glitter-learning::activity_log.description.course_delete',
            'description_params' => [
                'title' => $course->title ?? '',
                'slug' => $course->slug ?? '',
            ],
            'properties' => [
                'title' => $course->title ?? '',
                'slug' => $course->slug ?? '',
                'lessons_deleted' => $lessonCount,
            ],
        ]);
    }
}
