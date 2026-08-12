<?php

declare(strict_types=1);

namespace App\Filament\Resources\Prizes;

use App\Filament\Concerns\ResolvesNumericRecordKey;
use App\Filament\Resources\Prizes\Pages\CreatePrize;
use App\Filament\Resources\Prizes\Pages\EditPrize;
use App\Filament\Resources\Prizes\Pages\ListPrizes;
use App\Filament\Resources\Prizes\Schemas\PrizeForm;
use App\Filament\Resources\Prizes\Tables\PrizesTable;
use App\Models\Prize;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class PrizeResource extends Resource
{
    use ResolvesNumericRecordKey;

    protected static ?string $model = Prize::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    #[\Override]
    public static function getModelLabel(): string
    {
        return 'prêmio';
    }

    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return 'prêmios';
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return PrizeForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return PrizesTable::configure($table);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPrizes::route('/'),
            'create' => CreatePrize::route('/create'),
            'edit' => EditPrize::route('/{record}/edit'),
        ];
    }
}
