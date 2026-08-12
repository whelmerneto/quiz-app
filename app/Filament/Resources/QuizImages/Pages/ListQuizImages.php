<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizImages\Pages;

use App\Filament\Resources\QuizImages\QuizImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListQuizImages extends ListRecords
{
    protected static string $resource = QuizImageResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
