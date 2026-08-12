<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The active image pool is smaller than a round needs. The round is never
 * created, so the visitor sees the landing page again with an explanation
 * instead of a half-built attempt.
 */
final class NotEnoughQuizImagesException extends RuntimeException
{
    private function __construct(
        public readonly int $required,
        public readonly int $available,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function needing(int $required, int $available): self
    {
        return new self(
            $required,
            $available,
            sprintf('A round needs %d active images, %d are available.', $required, $available),
        );
    }
}
