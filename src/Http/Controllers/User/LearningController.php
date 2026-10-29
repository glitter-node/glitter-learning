<?php

namespace Modules\Glitter\Learning\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Glitter\Learning\Models\Completion;
use Modules\Glitter\Learning\Models\Course;
use Modules\Glitter\Learning\Models\Enrollment;
use Modules\Glitter\Learning\Models\Lesson;
use Modules\Glitter\Learning\Services\LearningService;

class LearningController extends Controller
{
    public function __construct(private readonly LearningService $learningService) {}

    public function courses(Request $request): JsonResponse
    {
        $courses = Course::query()
            ->publiclyAvailable()
            ->with('thumbnail:id,hash')
            ->withCount(['lessons' => fn ($query) => $query->where('is_published', true)])
            ->latest('published_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100))
            ->through(fn (Course $course): array => $this->courseData($course));

        return response()->json(['data' => $courses]);
    }

    public function course(Request $request, Course $course): JsonResponse
    {
        $this->assertPublicCourse($course);
        $enrollment = $request->user()
            ? Enrollment::query()->where('course_id', $course->id)->where('user_id', $request->user()->id)->first()
            : null;
        $lessons = $course->lessons()->where('is_published', true)->get([
            'id', 'course_id', 'title', 'sort_order', 'is_required', 'is_published',
        ]);

        return response()->json(['data' => [
            'course' => $this->courseData($course),
            'lessons' => $lessons,
            'enrollment' => $enrollment,
            'progress' => $enrollment ? $this->learningService->progressSummary($enrollment) : null,
        ]]);
    }

    public function enroll(Request $request, Course $course): JsonResponse
    {
        $this->assertPublicCourse($course);
        $enrollment = $this->learningService->enroll($course, $request->user());

        return response()->json(['data' => $this->enrollmentData($enrollment)], 201);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->assertPublicCourse($course);
        abort_unless((int) $lesson->course_id === (int) $course->id && $lesson->is_published, 404);
        $enrollment = $this->ownedEnrollment($request, $course);

        return response()->json(['data' => [
            'course' => $course->only(['id', 'title', 'slug']),
            'lesson' => $lesson,
            'completed' => $enrollment->progress()->where('lesson_id', $lesson->id)->exists(),
            'progress' => $this->learningService->progressSummary($enrollment),
        ]]);
    }

    public function completeLesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->assertPublicCourse($course);
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $enrollment = $this->ownedEnrollment($request, $course);
        $enrollment = $this->learningService->completeLesson($enrollment, $lesson);

        return response()->json(['data' => $this->enrollmentData($enrollment)]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $items = Enrollment::query()
            ->where('user_id', $request->user()->id)
            ->with(['course', 'completion'])
            ->latest('enrolled_at')
            ->get()
            ->map(fn (Enrollment $enrollment) => $this->enrollmentData($enrollment));

        return response()->json(['data' => $items]);
    }

    public function completions(Request $request): JsonResponse
    {
        $items = Completion::query()
            ->where('user_id', $request->user()->id)
            ->with('course:id,title,slug')
            ->latest('completed_at')
            ->get();

        return response()->json(['data' => $items]);
    }

    private function assertPublicCourse(Course $course): void
    {
        abort_unless(Course::query()->publiclyAvailable()->whereKey($course->id)->exists(), 404);
    }

    private function ownedEnrollment(Request $request, Course $course): Enrollment
    {
        return Enrollment::query()
            ->where('course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'completed'])
            ->firstOrFail();
    }

    private function enrollmentData(Enrollment $enrollment): array
    {
        $enrollment->loadMissing(['course', 'completion']);

        return [
            'id' => $enrollment->id,
            'status' => $enrollment->status,
            'enrolled_at' => $enrollment->enrolled_at,
            'completed_at' => $enrollment->completed_at,
            'course' => $enrollment->course,
            'completion' => $enrollment->completion,
            'progress' => $this->learningService->progressSummary($enrollment),
        ];
    }

    private function courseData(Course $course): array
    {
        $course->loadMissing('thumbnail:id,hash');
        $data = $course->toArray();
        unset($data['thumbnail']);
        $data['thumbnail_url'] = $course->thumbnail?->download_url;

        return $data;
    }
}
