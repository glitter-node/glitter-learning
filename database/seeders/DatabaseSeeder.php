<?php

namespace Modules\Glitter\Learning\Database\Seeders;

use App\Traits\HasSampleSeeders;
use App\Traits\HasSeederCounts;
use Illuminate\Database\Seeder;

/**
 * Glitter Learning 모듈 메인 시더.
 *
 * 초기 공개 강좌는 샘플 데이터로 분류하며 --sample 옵션에서만 생성한다.
 */
final class DatabaseSeeder extends Seeder
{
    use HasSampleSeeders;
    use HasSeederCounts;

    public function run(): void
    {
        if (! $this->shouldIncludeSample()) {
            $this->command?->info('Glitter Learning install seeders: none. Use --sample for starter courses.');

            return;
        }

        $this->callWithCounts(Sample\AcademyStarterCoursesSeeder::class);
    }

    private function callWithCounts(string $class): void
    {
        $seeder = $this->resolve($class);
        if (method_exists($seeder, 'setSeederCounts')) {
            $seeder->setSeederCounts($this->getSeederCounts());
        }

        $seeder->__invoke();
    }
}
