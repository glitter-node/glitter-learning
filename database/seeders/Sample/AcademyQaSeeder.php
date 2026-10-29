<?php

namespace Modules\Glitter\Learning\Database\Seeders\Sample;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\Learning\Models\Course;
use Modules\Glitter\Learning\Models\Lesson;
use RuntimeException;

final class AcademyQaSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @param array<string, int> $counts */
    public function setSeederCounts(array $counts): void
    {
        $this->counts = $counts;
    }

    public function run(): void
    {
        $authorId = $this->counts['author_id'] ?? 0;
        if ($authorId < 1 || ! User::query()->whereKey($authorId)->exists()) {
            throw new RuntimeException('A valid --count=author_id=<user-id> is required for the Academy QA course.');
        }

        DB::transaction(function () use ($authorId): void {
            $slug = 'qa-glitter-learning-academy-v1';
            $title = '[QA] Glitter Learning Academy 1.0.0';
            $course = Course::query()->where('slug', $slug)->lockForUpdate()->first();

            if ($course !== null) {
                if ($course->title !== $title || (int) $course->author_id !== $authorId) {
                    throw new RuntimeException('The QA slug already belongs to different course data; refusing to overwrite it.');
                }
                if ($course->status === 'archived') {
                    throw new RuntimeException('The QA course is already archived; preserve its records and use a new QA course for another run.');
                }
            } else {
                $course = Course::query()->create([
                    'title' => $title,
                    'slug' => $slug,
                    'introduction' => '[QA] Academy 템플릿의 공개 강좌, 수강, 진도 및 수료 흐름을 확인합니다.',
                    'description' => '[QA] 이 강좌는 Glitter Learning Academy 1.0.0 운영 검증용입니다. 검증 종료 후 강좌만 보관하며 수강·진도·수료 이력은 보존합니다.',
                    'author_id' => $authorId,
                    'status' => 'published',
                    'published_at' => now(),
                ]);
            }

            $lessons = [
                ['title' => '[QA] 필수 차시 1', 'body' => '[QA] 첫 번째 필수 차시입니다. 완료 후 필수 진도율은 50%여야 합니다.', 'sort_order' => 1, 'is_required' => true],
                ['title' => '[QA] 필수 차시 2', 'body' => '[QA] 두 번째 필수 차시입니다. 완료 후 필수 진도율은 100%이며 수료 이력은 한 건이어야 합니다.', 'sort_order' => 2, 'is_required' => true],
                ['title' => '[QA] 선택 차시 1', 'body' => '[QA] 선택 차시는 수료 판정에 영향을 주지 않아야 합니다.', 'sort_order' => 3, 'is_required' => false],
            ];

            $existing = $course->lessons()->lockForUpdate()->get()->keyBy('title');
            $expectedTitles = collect($lessons)->pluck('title');
            if ($existing->keys()->diff($expectedTitles)->isNotEmpty()) {
                throw new RuntimeException('The QA course contains unexpected lessons; refusing to overwrite them.');
            }

            foreach ($lessons as $lessonData) {
                $lesson = $existing->get($lessonData['title']);
                if ($lesson !== null) {
                    $matches = (int) $lesson->sort_order === $lessonData['sort_order']
                        && (bool) $lesson->is_required === $lessonData['is_required']
                        && (bool) $lesson->is_published;
                    if (! $matches) {
                        throw new RuntimeException("Existing QA lesson configuration differs: {$lessonData['title']}");
                    }
                    continue;
                }

                Lesson::query()->create($lessonData + [
                    'course_id' => $course->id,
                    'media_reference' => null,
                    'is_published' => true,
                ]);
            }

            $this->command?->info("Academy QA course ready: course_id={$course->id}, status={$course->status}");
        });
    }
}
