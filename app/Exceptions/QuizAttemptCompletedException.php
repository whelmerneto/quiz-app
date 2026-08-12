<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\QuizAttempt;
use RuntimeException;

/**
 * A write reached an attempt that already carries `completed_at`. The score and
 * the prize are frozen at completion, so a late answer is refused rather than
 * silently dropped.
 */
final class QuizAttemptCompletedException extends RuntimeException
{
    public static function forAttempt(QuizAttempt $attempt): self
    {
        return new self(sprintf('Quiz attempt %s is already completed.', $attempt->uuid));
    }
}
