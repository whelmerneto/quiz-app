<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizAttempts\Pages;

use App\Filament\Resources\QuizAttempts\QuizAttemptResource;
use Filament\Resources\Pages\ListRecords;

final class ListQuizAttempts extends ListRecords
{
    protected static string $resource = QuizAttemptResource::class;

    /**
     * Read-only resource: no create action in the header.
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
