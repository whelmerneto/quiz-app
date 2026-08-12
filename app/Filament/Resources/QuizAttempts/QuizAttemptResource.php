<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizAttempts;

use App\Filament\Concerns\ResolvesNumericRecordKey;
use App\Filament\Resources\QuizAttempts\Pages\ListQuizAttempts;
use App\Filament\Resources\QuizAttempts\Pages\ViewQuizAttempt;
use App\Filament\Resources\QuizAttempts\Schemas\QuizAttemptInfolist;
use App\Filament\Resources\QuizAttempts\Tables\QuizAttemptsTable;
use App\Models\QuizAttempt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Attempts are written by the public quiz and read here. Every write operation
 * is denied at the resource level, so the create and edit pages do not exist and
 * the actions that would reach them are hidden wherever Filament offers them.
 */
final class QuizAttemptResource extends Resource
{
    use ResolvesNumericRecordKey;

    protected static ?string $model = QuizAttempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'player_name';

    protected static ?int $navigationSort = 3;

    #[\Override]
    public static function getModelLabel(): string
    {
        return 'partida';
    }

    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return 'partidas';
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    #[\Override]
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    #[\Override]
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * The table and the infolist both read `prize.name`, and lazy loading is
     * blocked outside production, so the relation is loaded up front.
     */
    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('prize');
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return QuizAttemptInfolist::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return QuizAttemptsTable::configure($table);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListQuizAttempts::route('/'),
            'view' => ViewQuizAttempt::route('/{record}'),
        ];
    }
}
