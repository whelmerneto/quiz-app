<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Quiz\StartQuizRequest;

/**
 * The player behind a round. Carrying this instead of the request is what lets
 * StartQuizAttempt run from a test or a console command as well as from HTTP.
 */
final readonly class StartQuizData
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    public static function fromRequest(StartQuizRequest $request): self
    {
        return new self(
            name: (string) $request->validated('name'),
            email: (string) $request->validated('email'),
        );
    }
}
