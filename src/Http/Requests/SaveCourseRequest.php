<?php

namespace Modules\Glitter\Learning\Http\Requests;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Learning\Enums\CourseCategory;
use Modules\Glitter\Learning\Enums\CourseDescriptionMode;
use Modules\Glitter\Learning\Enums\CourseDifficulty;
use Modules\Glitter\Learning\Models\Course;

class SaveCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $course = $this->route('course');
        $course = $course instanceof Course ? $course : null;
        $userId = $this->user()?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Course::class, 'slug')->ignore($course?->id)],
            'introduction' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:200000'],
            'description_mode' => ['required', Rule::enum(CourseDescriptionMode::class)],
            'category' => ['nullable', Rule::enum(CourseCategory::class)],
            'difficulty' => ['nullable', Rule::enum(CourseDifficulty::class)],
            'learning_objectives' => ['nullable', 'array', 'max:20'],
            'learning_objectives.*' => ['required', 'string', 'max:500'],
            'prerequisites' => ['nullable', 'string', 'max:5000'],
            'target_audience' => ['nullable', 'string', 'max:5000'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'thumbnail_attachment_id' => [
                'nullable', 'integer',
                Rule::exists(Attachment::class, 'id')->where(function ($query) use ($course, $userId): void {
                    $query->where('source_type', 'module')
                        ->where('source_identifier', 'glitter-learning')
                        ->where('collection', 'course_thumbnail')
                        ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->where('size', '<=', 10 * 1024 * 1024)
                        ->whereNull('deleted_at')
                        ->where(function ($owner) use ($course, $userId): void {
                            $owner->where(function ($pending) use ($userId): void {
                                $pending->where('created_by', $userId)
                                    ->whereNull('attachmentable_type')
                                    ->whereNull('attachmentable_id');
                            });
                            if ($course?->thumbnail_attachment_id) {
                                $owner->orWhere(function ($existing) use ($course): void {
                                    $existing->where('id', $course->thumbnail_attachment_id)
                                        ->where('attachmentable_type', Course::class)
                                        ->where('attachmentable_id', $course->id);
                                });
                            }
                        });
                }),
            ],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $course = $this->route('course');
        $defaults = [];
        if (! $this->exists('description_mode')) {
            $defaults['description_mode'] = $course instanceof Course ? $course->description_mode : CourseDescriptionMode::Text->value;
        }
        if (is_array($this->input('learning_objectives'))) {
            $defaults['learning_objectives'] = array_values(array_filter(
                array_map(static fn ($goal) => is_string($goal) ? trim($goal) : $goal, $this->input('learning_objectives')),
                static fn ($goal) => $goal !== '' && $goal !== null,
            ));
        }
        if ($defaults !== []) {
            $this->merge($defaults);
        }
    }
}
