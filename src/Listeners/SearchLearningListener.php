<?php

namespace Modules\Glitter\Learning\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use App\Enums\TotalRelation;
use App\Search\SearchCategoryPayload;
use App\Search\SearchHighlighter;
use App\Support\Query\BoundedPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Modules\Glitter\Learning\Models\Course;
use Modules\Glitter\Learning\Models\Lesson;

class SearchLearningListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.search.results' => ['method' => 'searchLearning', 'priority' => 10, 'type' => 'filter'],
            'core.search.build_response' => ['method' => 'buildLearningResponse', 'priority' => 10, 'type' => 'filter'],
            'core.search.index_validation_rules' => ['method' => 'addValidationRules', 'priority' => 10, 'type' => 'filter'],
        ];
    }

    public function handle(...$args): void {}

    public function addValidationRules(array $rules): array
    {
        $rules['sort'] = ['nullable', 'string', 'in:relevance,latest,oldest'];

        return $rules;
    }

    public function searchLearning(array $results, array $context): array
    {
        $keyword = trim((string) ($context['q'] ?? ''));
        if ($keyword === '') {
            return $results;
        }

        try {
            $type = (string) ($context['type'] ?? 'all');
            if ($type === 'all' || $type === 'courses') {
                $results['courses'] = $this->searchCourses($keyword, $context);
            } elseif (($context['include_inactive_counts'] ?? true) && $type !== 'courses') {
                $results['courses'] = $this->countCourses($keyword, $context);
            }

            if ($type === 'all' || $type === 'lessons') {
                $results['lessons'] = $this->searchLessons($keyword, $context);
            } elseif (($context['include_inactive_counts'] ?? true) && $type !== 'lessons') {
                $results['lessons'] = $this->countLessons($keyword, $context);
            }
        } catch (\Throwable $exception) {
            Log::error('Glitter Learning search failed', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
            $results['courses'] = SearchCategoryPayload::failed();
            $results['lessons'] = SearchCategoryPayload::failed();
        }

        return $results;
    }

    public function buildLearningResponse(array $response, array $results, array $context): array
    {
        foreach (['courses', 'lessons'] as $key) {
            if (! isset($results[$key])) {
                continue;
            }

            $payload = $results[$key];
            $response[$key.'_count'] = (int) ($payload['total'] ?? 0);
            $response[$key] = [
                'total' => $payload['total'] ?? 0,
                'total_relation' => $payload['total_relation'] ?? TotalRelation::Exact->value,
                'total_is_exact' => $payload['total_is_exact'] ?? true,
                'result_cap' => $payload['result_cap'] ?? null,
                'items' => $payload['items'] ?? [],
            ];

            if (($context['type'] ?? 'all') === $key) {
                $response['current_page'] = (int) ($context['page'] ?? 1);
                $response['per_page'] = (int) ($context['per_page'] ?? 10);
                $response['last_page'] = $payload['last_page'] ?? null;
                $response['has_more_pages'] = $payload['has_more_pages'] ?? false;
                $response['next_cursor'] = $payload['next_cursor'] ?? null;
                $response['prev_cursor'] = $payload['prev_cursor'] ?? null;
            }
        }

        return $response;
    }

    private function searchCourses(string $keyword, array $context): array
    {
        $query = Course::query()->publiclyAvailable()
            ->where(function (Builder $builder) use ($keyword): void {
                $like = '%'.$keyword.'%';
                $builder->where('title', 'like', $like)
                    ->orWhere('introduction', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });

        $this->applyOrder($query, $context['sort'] ?? 'relevance', 'published_at');

        return $this->paginateResult($query, $context, fn (Course $course): array => [
            'provider_key' => 'courses',
            'content_type' => 'course',
            'id' => $course->id,
            'title' => $course->title,
            'summary' => $this->summary($course->introduction ?: $course->description),
            'url' => '/learning/courses/'.$course->slug,
        ]);
    }

    private function searchLessons(string $keyword, array $context): array
    {
        $query = Lesson::query()
            ->where('is_published', true)
            ->whereHas('course', function (Builder $courseQuery): void {
                $courseQuery->publiclyAvailable();
            })
            ->where(function (Builder $builder) use ($keyword): void {
                $like = '%'.$keyword.'%';
                $builder->where('title', 'like', $like)->orWhere('body', 'like', $like);
            });

        $user = $context['user'] ?? null;
        if ($user === null) {
            return SearchCategoryPayload::fromCountOnly(
                new \App\Support\Query\BoundedCount(0, TotalRelation::Exact, null)
            );
        }

        $query->whereHas('course.enrollments', function (Builder $enrollmentQuery) use ($user): void {
            $enrollmentQuery->where('user_id', $user->id)->whereIn('status', ['active', 'completed']);
        });
        $query->with('course:id,title,slug');
        $this->applyOrder($query, $context['sort'] ?? 'relevance', 'created_at');

        return $this->paginateResult($query, $context, function (Lesson $lesson): array {
            $course = $lesson->course;

            return [
                'provider_key' => 'lessons',
                'content_type' => 'lesson',
                'id' => $lesson->id,
                'course_id' => $course->id,
                'course_title' => $course->title,
                'title' => $lesson->title,
                'summary' => $this->summary($lesson->body),
                'url' => '/learning/courses/'.$course->slug.'/lessons/'.$lesson->id,
            ];
        });
    }

    private function countCourses(string $keyword, array $context): array
    {
        $query = Course::query()->publiclyAvailable()
            ->where(fn (Builder $builder) => $builder->where('title', 'like', '%'.$keyword.'%')->orWhere('introduction', 'like', '%'.$keyword.'%')->orWhere('description', 'like', '%'.$keyword.'%'));

        return SearchCategoryPayload::fromCountOnly(BoundedPaginator::count($query, 10000));
    }

    private function countLessons(string $keyword, array $context): array
    {
        $query = Lesson::query()->where('is_published', true)
            ->whereHas('course', fn (Builder $builder) => $builder->publiclyAvailable())
            ->where(fn (Builder $builder) => $builder->where('title', 'like', '%'.$keyword.'%')->orWhere('body', 'like', '%'.$keyword.'%'));
        $user = $context['user'] ?? null;
        if ($user === null) {
            return SearchCategoryPayload::fromCountOnly(new \App\Support\Query\BoundedCount(0, TotalRelation::Exact, null));
        }
        $query->whereHas('course.enrollments', fn (Builder $builder) => $builder->where('user_id', $user->id)->whereIn('status', ['active', 'completed']));

        return SearchCategoryPayload::fromCountOnly(BoundedPaginator::count($query, 10000));
    }

    private function paginateResult(Builder $query, array $context, callable $format): array
    {
        $type = $context['type'] ?? 'all';
        $perPage = $type === 'all' ? (int) ($context['all_tab_limit'] ?? 5) : (int) ($context['per_page'] ?? 10);
        $page = $type === 'all' ? 1 : (int) ($context['page'] ?? 1);
        $paged = BoundedPaginator::paginate($query, min(max($perPage, 1), 100), $page, 10000);

        return SearchCategoryPayload::fromBounded($paged, collect($paged->items())->map($format)->all());
    }

    private function applyOrder(Builder $query, string $sort, string $column): void
    {
        $query->orderBy($column, $sort === 'oldest' ? 'asc' : 'desc')->orderBy('id');
    }

    private function summary(?string $value): string
    {
        return mb_substr(SearchHighlighter::toPlainText($value), 0, 180);
    }
}
