<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizAttempts\Schemas;

use App\Models\QuizAttempt;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class QuizAttemptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('player_name')
                    ->label('Jogador'),
                TextEntry::make('player_email')
                    ->label('E-mail'),
                TextEntry::make('correct_count')
                    ->label('Pontuação')
                    ->formatStateUsing(fn (QuizAttempt $record): string => $record->correct_count.'/'.$record->question_count),
                TextEntry::make('prize.name')
                    ->label('Prêmio')
                    ->placeholder('Nenhum'),
                TextEntry::make('created_at')
                    ->label('Iniciada em')
                    ->dateTime(),
                TextEntry::make('completed_at')
                    ->label('Concluída em')
                    ->dateTime()
                    ->placeholder('Em andamento'),
            ]);
    }
}
