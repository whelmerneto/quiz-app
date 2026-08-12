<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizImages\Pages;

use App\Filament\Resources\QuizImages\QuizImageResource;
use Filament\Resources\Pages\EditRecord;

final class EditQuizImage extends EditRecord
{
    protected static string $resource = QuizImageResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            QuizImageResource::deleteAction(),
        ];
    }
}
