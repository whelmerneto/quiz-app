<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizAttempts\Pages;

use App\Filament\Resources\QuizAttempts\QuizAttemptResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewQuizAttempt extends ViewRecord
{
    protected static string $resource = QuizAttemptResource::class;

    /**
     * Read-only resource: no edit or delete action in the header.
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
