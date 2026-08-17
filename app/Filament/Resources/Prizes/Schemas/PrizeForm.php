<?php

declare(strict_types=1);

namespace App\Filament\Resources\Prizes\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;

final class PrizeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('required_correct')
                    ->label('Acertos necessários')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    // `required_correct` is a smallint. Rejecting anything above
                    // its ceiling in the form keeps an out-of-range value from
                    // reaching the driver as a 500.
                    ->maxValue(32767)
                    // The column is unique so prize resolution stays
                    // deterministic. Without ignoreRecord an edit that leaves the
                    // threshold untouched would fail against the record itself.
                    ->unique(ignoreRecord: true)
                    ->helperText('Pontuação mínima que libera este prêmio. Dois prêmios não podem exigir o mesmo número de acertos.'),
                FileUpload::make('image_path')
                    ->label('Foto')
                    ->disk(Config::string('quiz.disk'))
                    ->directory('prize-images')
                    ->visibility('public')
                    // Same server-side rules as the quiz image upload:
                    // `mimetypes:` and `max:10240` are registered by these two
                    // calls, not just applied in the browser.
                    ->acceptedFileTypes(['image/png', 'image/jpeg'])
                    ->maxSize(10240)
                    // See QuizImageForm: the upload rules only reach entries that
                    // are real uploads, so a hand-crafted string would otherwise
                    // land in `image_path` untouched.
                    ->preventFilePathTampering()
                    // See QuizImageForm: the field preview is a cross-origin
                    // fetch against a bucket that sends no CORS headers, which
                    // fails and takes the form's submit with it.
                    ->previewable(false)
                    ->helperText('Opcional. PNG, JPG ou JPEG, no máximo 10 MB.'),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->helperText('Somente prêmios ativos são considerados no resultado de uma rodada.')
                    ->default(true),
            ]);
    }
}
