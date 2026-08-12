<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizImages\Tables;

use App\Enums\ImageLabel;
use App\Filament\Resources\QuizImages\QuizImageResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;

final class QuizImagesTable
{
    public static function configure(Table $table): Table
    {
        // There is no bulk delete on purpose. `quiz_attempt_answers` references
        // this table with ON DELETE RESTRICT, so a selection mixing used and
        // unused images fails halfway with nothing useful to report. Images are
        // removed one at a time, where a failure maps to a single record.
        return $table
            ->columns([
                Stack::make([
                    ImageColumn::make('path')
                        ->label('Imagem')
                        ->disk(Config::string('quiz.disk'))
                        ->height(200)
                        ->extraImgAttributes(['class' => 'w-full rounded-lg object-cover']),
                    TextColumn::make('label')
                        ->label('Classificação')
                        ->badge()
                        ->formatStateUsing(fn (ImageLabel $state): string => $state->label())
                        ->color(fn (ImageLabel $state): string => $state === ImageLabel::Real ? 'success' : 'info'),
                    TextColumn::make('is_active')
                        ->label('Situação')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Ativa' : 'Inativa')
                        ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('label')
                    ->label('Classificação')
                    ->options(ImageLabel::options()),
                TernaryFilter::make('is_active')
                    ->label('Situação'),
            ])
            ->recordActions([
                EditAction::make(),
                QuizImageResource::deleteAction(),
            ]);
    }
}
