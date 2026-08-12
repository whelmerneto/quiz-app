<?php

declare(strict_types=1);

namespace App\Filament\Resources\Prizes\Pages;

use App\Filament\Resources\Prizes\PrizeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditPrize extends EditRecord
{
    protected static string $resource = PrizeResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
