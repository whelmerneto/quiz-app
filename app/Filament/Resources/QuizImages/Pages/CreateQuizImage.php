<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizImages\Pages;

use App\Filament\Resources\QuizImages\QuizImageResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateQuizImage extends CreateRecord
{
    protected static string $resource = QuizImageResource::class;
}
