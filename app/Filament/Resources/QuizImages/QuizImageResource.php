<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuizImages;

use App\Filament\Concerns\ResolvesNumericRecordKey;
use App\Filament\Resources\QuizImages\Pages\CreateQuizImage;
use App\Filament\Resources\QuizImages\Pages\EditQuizImage;
use App\Filament\Resources\QuizImages\Pages\ListQuizImages;
use App\Filament\Resources\QuizImages\Schemas\QuizImageForm;
use App\Filament\Resources\QuizImages\Tables\QuizImagesTable;
use App\Models\QuizImage;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class QuizImageResource extends Resource
{
    use ResolvesNumericRecordKey;

    /**
     * The Postgres SQLSTATEs that mean "this image is still referenced".
     * `quiz_attempt_answers` references `quiz_images` with ON DELETE RESTRICT,
     * and which of the two codes comes back depends on the server version:
     * Postgres 17 answers the blocked delete with 23503 foreign_key_violation,
     * Postgres 18 with 23001 restrict_violation. Matching only one of them
     * turns a blocked delete into an unhandled 500 on whichever server
     * disagrees, which is exactly what production (Neon, Postgres 18) did while
     * the container (Postgres 17) stayed green.
     *
     * @var list<string>
     */
    private const array STILL_REFERENCED = ['23503', '23001'];

    protected static ?string $model = QuizImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'path';

    protected static ?int $navigationSort = 1;

    #[\Override]
    public static function getModelLabel(): string
    {
        return 'imagem';
    }

    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return 'imagens';
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return QuizImageForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return QuizImagesTable::configure($table);
    }

    /**
     * Deleting an image that appears in a past attempt is blocked by the
     * foreign key, which surfaces as a QueryException in the middle of the
     * action. Swallowing only that one SQLSTATE turns it into the notification
     * the operator can act on; anything else still bubbles up as a real error.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->failureNotificationTitle('Não foi possível excluir esta imagem')
            ->failureNotificationBody('Ela já foi usada em pelo menos uma partida. Desative a imagem para tirá-la dos próximos sorteios sem apagar o histórico.')
            ->using(static function (QuizImage $record): bool {
                $path = $record->path;

                try {
                    // The transaction is here for the analyser, not for Postgres:
                    // `Model::delete()` declares only `@throws \LogicException`,
                    // so PHPStan reports the catch below as dead. `DB::transaction()`
                    // declares `@throws \Throwable` and makes it live again.
                    // Outside a transaction Postgres would abort just the failing
                    // statement and leave the connection usable either way.
                    $deleted = (bool) DB::transaction(static fn (): ?bool => $record->delete());
                } catch (QueryException $exception) {
                    if (! in_array((string) $exception->getCode(), self::STILL_REFERENCED, true)) {
                        throw $exception;
                    }

                    return false;
                }

                // Filament only removes a stored file when the operator clears
                // the field in the form, never on record deletion. Without this
                // every deleted image leaves an orphan on the disk, which on R2
                // is billed storage nobody can reach. It runs after the commit:
                // a rolled-back delete must not take the file with it.
                // rescue() so the action's outcome can never depend on the disk.
                // Today every disk sets `throw => false`, but if phase 6 turns
                // that on for s3 an UnableToDeleteFile would escape from outside
                // the try above and report a failure for a delete that committed.
                if ($deleted) {
                    rescue(static fn (): bool => Storage::disk(Config::string('quiz.disk'))->delete($path), report: true);
                }

                return $deleted;
            });
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListQuizImages::route('/'),
            'create' => CreateQuizImage::route('/create'),
            'edit' => EditQuizImage::route('/{record}/edit'),
        ];
    }
}
