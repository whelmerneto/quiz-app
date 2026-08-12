<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Closes a round and freezes its prize. Editing or deactivating a prize later
 * never rewrites an attempt that already won it, because the decision is stored
 * as `prize_id` rather than recomputed on every read.
 */
final readonly class CompleteQuizAttempt
{
    public function __construct(private ResolvePrize $resolvePrize) {}

    /**
     * Idempotent: a second visit to the result page returns the stored decision.
     *
     * Returns false when the round still has unanswered positions. That
     * invariant lives here rather than in the controller so no other caller — a
     * command, a job, a later endpoint — can freeze a half-played attempt.
     */
    public function handle(QuizAttempt $attempt): bool
    {
        if ($attempt->isComplete()) {
            return true;
        }

        return DB::transaction(function () use ($attempt): bool {
            // Re-read under a row lock. Two concurrent result requests would
            // otherwise both pass the guard above and both resolve a prize, and
            // an operator flipping `is_active` between the two resolutions would
            // decide which one wins.
            $locked = QuizAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isComplete()) {
                // syncOriginal() so the caller's instance is updated but not left
                // dirty. Without it a later save() or touch() on that instance
                // writes these columns back and bumps updated_at for nothing.
                $attempt->forceFill($locked->only(['prize_id', 'completed_at']))->syncOriginal();

                return true;
            }

            $unanswered = $locked->answers()->whereNull('answered_at')->exists();

            if ($unanswered) {
                return false;
            }

            $prize = $this->resolvePrize->handle($locked->correct_count);

            $locked->update([
                'prize_id' => $prize?->id,
                'completed_at' => now(),
            ]);

            $attempt->forceFill($locked->only(['prize_id', 'completed_at', 'correct_count']))->syncOriginal();

            return true;
        });
    }
}
