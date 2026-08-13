<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizImages\Schemas;

use App\Enums\ImageLabel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;

final class QuizImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Imagem')
                    ->required()
                    ->disk(Config::string('quiz.disk'))
                    ->directory('quiz-images')
                    ->visibility('public')
                    // Neither call is only a browser hint. `acceptedFileTypes()`
                    // registers a `mimetypes:image/png` validation rule and
                    // `maxSize()` registers `max:10240`, both evaluated on the
                    // server against the uploaded file. A request that skips the
                    // form UI therefore still fails before anything is stored.
                    ->acceptedFileTypes(['image/png'])
                    ->maxSize(10240)
                    // Without this, a plain string submitted as form state is
                    // persisted into `path` verbatim: the mimetypes and max rules
                    // only apply to entries that are actually uploaded files.
                    ->preventFilePathTampering()
                    ->imagePreviewHeight('180')
                    ->helperText('PNG apenas, no máximo 10 MB.'),
                Radio::make('label')
                    ->label('Classificação')
                    ->required()
                    ->inline()
                    ->options(ImageLabel::options()),
                Toggle::make('is_active')
                    ->label('Ativa')
                    ->helperText('Somente imagens ativas entram no sorteio de novas rodadas.')
                    ->default(true),
            ]);
    }
}
