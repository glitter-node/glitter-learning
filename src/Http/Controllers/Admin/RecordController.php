<?php

namespace Modules\Glitter\Learning\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Glitter\Learning\Models\Completion;
use Modules\Glitter\Learning\Models\Course;
use Modules\Glitter\Learning\Models\Enrollment;
use Modules\Glitter\Learning\Services\LearningService;

class RecordController extends Controller
{
    public function __construct(private readonly LearningService $learningService) {}

    public function enrollments(Request $request, Course $course): JsonResponse
    {
        $items = Enrollment::query()
            ->where('course_id', $course->id)
            ->with(['user:id,uuid,name,nickname,email', 'completion'])
            ->withCount('progress')
            ->latest('enrolled_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        $items->getCollection()->transform(function (Enrollment $enrollment) {
            $enrollment->setAttribute('progress_summary', $this->learningService->progressSummary($enrollment));

            return $enrollment;
        });

        return response()->json(['data' => $items]);
    }

    public function completions(Request $request): JsonResponse
    {
        $items = Completion::query()
            ->with(['course:id,title,slug', 'enrollment.user:id,uuid,name,nickname,email'])
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->latest('completed_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json(['data' => $items]);
    }
}
