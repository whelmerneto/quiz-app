<?php

declare(strict_types=1);

namespace App\Filament\Resources\Prizes\Pages;

use App\Filament\Resources\Prizes\PrizeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPrizes extends ListRecords
{
    protected static string $resource = PrizeResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
