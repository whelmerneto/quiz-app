<?php

declare(strict_types=1);

namespace App\Filament\Resources\Prizes\Pages;

use App\Filament\Resources\Prizes\PrizeResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePrize extends CreateRecord
{
    protected static string $resource = PrizeResource::class;
}
