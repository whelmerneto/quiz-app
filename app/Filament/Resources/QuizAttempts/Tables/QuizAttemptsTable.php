<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizAttempts\Tables;

use App\Models\QuizAttempt;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class QuizAttemptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('player_name')
                    ->label('Jogador')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('player_email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('correct_count')
                    ->label('Pontuação')
                    ->sortable()
                    ->formatStateUsing(fn (QuizAttempt $record): string => $record->correct_count.'/'.$record->question_count),
                TextColumn::make('prize.name')
                    ->label('Prêmio')
                    ->placeholder('Nenhum'),
                TextColumn::make('completed_at')
                    ->label('Concluída em')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Em andamento'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('completed')
                    ->label('Somente concluídas')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('completed_at')),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
