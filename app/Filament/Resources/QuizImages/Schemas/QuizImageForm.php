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
                    // registers a `mimetypes:` validation rule and `maxSize()`
                    // registers `max:10240`, both evaluated on the server
                    // against the uploaded file. A request that skips the form
                    // UI therefore still fails before anything is stored.
                    // One MIME type covers both extensions: .jpg and .jpeg are
                    // the same format and both arrive as image/jpeg.
                    ->acceptedFileTypes(['image/png', 'image/jpeg'])
                    ->maxSize(10240)
                    // Without this, a plain string submitted as form state is
                    // persisted into `path` verbatim: the mimetypes and max rules
                    // only apply to entries that are actually uploaded files.
                    ->preventFilePathTampering()
                    // No preview inside the field, and it is not a cosmetic
                    // choice. FilePond builds one by fetching the stored file
                    // with `fetch()`, and the quiz disk serves from R2's
                    // pub-*.r2.dev host, which answers with no
                    // Access-Control-Allow-Origin header. The fetch is blocked,
                    // FilePond leaves the item in a failed state, and the form
                    // hangs on submit — the reported "Failed to fetch". The
                    // thumbnail an operator actually browses by is the one in
                    // the table, which is a plain <img> and immune to this.
                    // Once the bucket carries a CORS policy for the panel's
                    // origin (docs/deploy.md), this can go back to
                    // `imagePreviewHeight('180')`.
                    ->previewable(false)
                    ->helperText('PNG, JPG ou JPEG, no máximo 10 MB.'),
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
