<?php

namespace Modules\Glitter\Learning\Support;

final class CompletionPolicy
{
    public static function progressPercent(int $requiredCount, int $completedRequiredCount): int
    {
        if ($requiredCount < 1) {
            return 0;
        }

        return (int) floor((min($completedRequiredCount, $requiredCount) / $requiredCount) * 100);
    }

    public static function isEligible(int $requiredCount, int $completedRequiredCount): bool
    {
        return $requiredCount > 0 && $completedRequiredCount >= $requiredCount;
    }
}
