<?php

namespace Modules\Glitter\Learning\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Glitter\Learning\Models\Course;
use Modules\Glitter\Learning\Models\Lesson;
use Modules\Glitter\Learning\Services\CourseContentPolicy;
use Modules\Glitter\Learning\Services\CourseDescriptionSanitizer;

class LessonController extends Controller
{
    public function __construct(
        private readonly CourseContentPolicy $courseContentPolicy,
        private readonly CourseDescriptionSanitizer $htmlSanitizer
    ) {}

    public function store(Request $request, Course $course): JsonResponse
    {
        $data = $this->validated($request);
        $lesson = DB::transaction(function () use ($course, $data): Lesson {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
            $this->courseContentPolicy->assertLessonStateAllowed(
                $lockedCourse,
                (bool) $data['is_required'],
                (bool) $data['is_published'],
            );
            $data['sort_order'] ??= ((int) $lockedCourse->lessons()->max('sort_order')) + 1;

            return $lockedCourse->lessons()->create($data);
        });

        return response()->json(['data' => $lesson], 201);
    }

    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $data = $this->validated($request);
        $lesson = DB::transaction(function () use ($course, $lesson, $data): Lesson {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
            $lockedLesson = $lockedCourse->lessons()->whereKey($lesson->id)->firstOrFail();
            $this->courseContentPolicy->assertLessonStateAllowed(
                $lockedCourse,
                (bool) $data['is_required'],
                (bool) $data['is_published'],
            );
            $lockedLesson->update($data);

            return $lockedLesson->fresh();
        });

        return response()->json(['data' => $lesson->fresh()]);
    }

    public function destroy(Course $course, Lesson $lesson): JsonResponse
    {
        $this->assertCourse($course, $lesson);
        if ($lesson->progress()->exists()) {
            throw ValidationException::withMessages(['lesson' => __('glitter-learning::validation.lesson_has_progress')]);
        }
        $lesson->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function reorder(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate([
            'lesson_ids' => ['required', 'array'],
            'lesson_ids.*' => ['integer', 'distinct'],
        ]);
        $currentIds = $course->lessons()->pluck('id')->sort()->values()->all();
        $requestedIds = collect($data['lesson_ids'])->sort()->values()->all();
        if ($currentIds !== $requestedIds) {
            throw ValidationException::withMessages(['lesson_ids' => __('glitter-learning::validation.invalid_lesson_order')]);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['lesson_ids'] as $order => $id) {
                Lesson::query()->whereKey($id)->update(['sort_order' => $order + 1]);
            }
        });

        return response()->json(['data' => $course->lessons()->get()]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'body' => ['nullable', 'string'],
            'media_reference' => ['nullable', 'url:http,https', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_required' => ['required', 'boolean'],
            'is_published' => ['required', 'boolean'],
        ]);

        if (isset($data['body'])) {
            $data['body'] = $this->htmlSanitizer->sanitize($data['body']);
        }

        return $data;
    }

}
