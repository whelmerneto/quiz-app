<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Models\Prize;

/**
 * The highest active tier a score unlocks, or null when the score clears none.
 * `prizes.required_correct` is unique at the database level, so the ordering
 * below has exactly one answer.
 */
final readonly class ResolvePrize
{
    public function handle(int $correctCount): ?Prize
    {
        return Prize::query()
            ->active()
            ->where('required_correct', '<=', $correctCount)
            ->orderByDesc('required_correct')
            ->first();
    }
}
