<?php

namespace Modules\Glitter\Learning\Http\Controllers\Admin;

use App\Helpers\TimezoneHelper;
use App\Extension\HookManager;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Glitter\Learning\Enums\CourseDescriptionMode;
use Modules\Glitter\Learning\Http\Requests\SaveCourseRequest;
use Modules\Glitter\Learning\Models\Completion;
use Modules\Glitter\Learning\Models\Course;
use Modules\Glitter\Learning\Models\LessonProgress;
use Modules\Glitter\Learning\Services\CourseContentPolicy;
use Modules\Glitter\Learning\Services\CourseDescriptionSanitizer;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseContentPolicy $courseContentPolicy,
        private readonly CourseDescriptionSanitizer $descriptionSanitizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $courses = Course::query()
            ->with('thumbnail:id,hash,original_filename,mime_type,size')
            ->withCount(['lessons', 'enrollments', 'completions'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100))
            ->through(fn (Course $course): array => $this->courseData($course));

        return response()->json(['data' => $courses]);
    }

    public function store(SaveCourseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['author_id'] = $request->user()->id;
        $this->sanitizeDescription($data);
        $this->normalizePublication($data);
        $course = DB::transaction(function () use ($data, $request): Course {
            $course = Course::query()->create($data);
            $this->attachThumbnail($course, $data['thumbnail_attachment_id'] ?? null, $request->user()->id);

            return $course->fresh('thumbnail');
        });

        return response()->json(['data' => $this->courseData($course)], 201);
    }

    public function show(Course $course): JsonResponse
    {
        return response()->json(['data' => $this->courseData($course->load(['lessons', 'author:id,uuid,name,nickname', 'thumbnail:id,hash,original_filename,mime_type,size']))]);
    }

    public function preview(Request $request): JsonResponse
    {
        $input = $request->validate([
            'description' => ['nullable', 'string', 'max:200000'],
            'description_mode' => ['required', \Illuminate\Validation\Rule::enum(CourseDescriptionMode::class)],
        ]);
        $description = $input['description'] ?? '';
        if ($input['description_mode'] === CourseDescriptionMode::Html->value) {
            $description = $this->descriptionSanitizer->sanitize($description);
        }

        return response()->json(['data' => [
            'description' => $description,
            'description_mode' => $input['description_mode'],
        ]]);
    }

    public function update(SaveCourseRequest $request, Course $course): JsonResponse
    {
        $data = $request->validated();
        $this->sanitizeDescription($data);
        $this->normalizePublication($data);
        $course = DB::transaction(function () use ($course, $data, $request): Course {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);

            if ($data['status'] === 'published') {
                $this->courseContentPolicy->assertCourseCanBePublished($lockedCourse);
            }

            if (array_key_exists('thumbnail_attachment_id', $data) && $data['thumbnail_attachment_id'] !== $lockedCourse->thumbnail_attachment_id) {
                $this->attachThumbnail($lockedCourse, $data['thumbnail_attachment_id'], $request->user()->id);
            }
            $lockedCourse->update($data);

            return $lockedCourse->fresh(['lessons', 'thumbnail']);
        });

        return response()->json(['data' => $this->courseData($course)]);
    }

    public function archive(Course $course): JsonResponse
    {
        $course->update(['status' => 'archived']);

        return response()->json(['data' => $course->fresh()]);
    }

    public function destroy(Course $course): JsonResponse
    {
        [$deletedCourse, $lessonCount] = DB::transaction(function () use ($course): array {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
            $lessonIds = $lockedCourse->lessons()->lockForUpdate()->pluck('id')->all();
            $enrollmentIds = $lockedCourse->enrollments()->lockForUpdate()->pluck('id')->all();

            if ($enrollmentIds !== []) {
                throw ValidationException::withMessages([
                    'course' => __('glitter-learning::validation.course_has_enrollments'),
                ]);
            }

            $hasProgress = $lessonIds !== []
                && LessonProgress::query()->whereIn('lesson_id', $lessonIds)->lockForUpdate()->exists();
            $hasCompletion = Completion::query()
                ->where('course_id', $lockedCourse->id)
                ->lockForUpdate()
                ->exists();

            if ($hasProgress || $hasCompletion) {
                throw ValidationException::withMessages([
                    'course' => __('glitter-learning::validation.course_has_learning_records'),
                ]);
            }

            $lessonCount = count($lessonIds);
            $deletedCourse = $lockedCourse->replicate();
            $deletedCourse->id = $lockedCourse->id;
            $deletedCourse->slug = $lockedCourse->slug;
            $deletedCourse->title = $lockedCourse->title;
            $lockedCourse->delete();

            return [$deletedCourse, $lessonCount];
        });

        HookManager::doAction('glitter-learning.course.after_delete', $deletedCourse, $lessonCount);

        return response()->json(['data' => [
            'deleted' => true,
            'lessons_deleted' => $lessonCount,
        ]]);
    }

    private function sanitizeDescription(array &$data): void
    {
        if (($data['description_mode'] ?? null) === CourseDescriptionMode::Html->value && isset($data['description'])) {
            $data['description'] = $this->descriptionSanitizer->sanitize($data['description']);
        }
    }

    private function attachThumbnail(Course $course, ?int $attachmentId, int $userId): void
    {
        if ($attachmentId === null) {
            return;
        }

        $attachment = Attachment::query()->whereKey($attachmentId)->lockForUpdate()->first();
        if (! $attachment || $attachment->source_type?->value !== 'module'
            || $attachment->source_identifier !== 'glitter-learning'
            || $attachment->collection !== 'course_thumbnail'
            || ! in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
            || $attachment->size > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['thumbnail_attachment_id' => __('glitter-learning::validation.invalid_thumbnail')]);
        }

        if ($attachment->attachmentable_type === Course::class && (int) $attachment->attachmentable_id === (int) $course->id) {
            return;
        }
        if ($attachment->attachmentable_type !== null || $attachment->attachmentable_id !== null || (int) $attachment->created_by !== $userId) {
            throw ValidationException::withMessages(['thumbnail_attachment_id' => __('glitter-learning::validation.invalid_thumbnail')]);
        }

        $attachment->update(['attachmentable_type' => Course::class, 'attachmentable_id' => $course->id]);
    }

    private function courseData(Course $course): array
    {
        $course->loadMissing('thumbnail:id,hash,original_filename,mime_type,size');
        $payload = $course->toArray();
        $payload['published_at'] = TimezoneHelper::toSiteDateTimeLocalString($course->published_at);
        unset($payload['thumbnail']);
        $thumbnail = $course->thumbnail;
        $payload['thumbnail_url'] = $thumbnail?->download_url;
        $payload['thumbnail_file'] = $thumbnail ? [
            'id' => $thumbnail->id,
            'hash' => $thumbnail->hash,
            'download_url' => $thumbnail->download_url,
            'original_filename' => $thumbnail->original_filename,
            'mime_type' => $thumbnail->mime_type,
            'size' => $thumbnail->size,
            'is_image' => $thumbnail->is_image,
        ] : null;

        return $payload;
    }

    private function normalizePublication(array &$data): void
    {
        if (! empty($data['published_at'])) {
            $data['published_at'] = TimezoneHelper::fromSiteDateTime((string) $data['published_at']);
        }
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        if ($data['status'] === 'draft') {
            $data['published_at'] = null;
        }
    }
}
