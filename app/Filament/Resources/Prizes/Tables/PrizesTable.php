<?php

declare(strict_types=1);

namespace App\Filament\Resources\Prizes\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;

final class PrizesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Foto')
                    ->disk(Config::string('quiz.disk'))
                    ->height(48)
                    ->square(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('required_correct')
                    ->label('Acertos necessários')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ativo' : 'Inativo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('required_correct', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Situação'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
