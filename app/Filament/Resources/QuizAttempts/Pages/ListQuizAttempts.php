<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizAttempts\Pages;

use App\Filament\Resources\QuizAttempts\QuizAttemptResource;
use App\Models\QuizAttempt;
use App\Services\Quiz\ExportQuizAttempts;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ListQuizAttempts extends ListRecords
{
    protected static string $resource = QuizAttemptResource::class;

    /**
     * Read-only resource, so the header carries an export and nothing that
     * writes.
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exportar CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->export()),
        ];
    }

    private function export(): StreamedResponse
    {
        // The table's own filtered query, not the resource's: the file has to
        // match what the operator is looking at, including the "somente
        // concluídas" filter. Sorting is left off — a CSV is re-sorted by
        // whatever opens it, and lazyById() needs the primary key order anyway.
        $query = $this->getFilteredTableQuery() ?? QuizAttempt::query();

        return app(ExportQuizAttempts::class)->handle(
            $query,
            'partidas-'.now()->format('Y-m-d-Hi').'.csv',
        );
    }
}
