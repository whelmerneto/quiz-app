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
     * The Postgres SQLSTATEs that mean "this image is still referenced": 23503
     * foreign_key_violation and 23001 restrict_violation, which server versions
     * disagree about — Postgres 17 raises the first, 18 the second.
     *
     * Since the reference became ON DELETE SET NULL neither should reach an
     * operator, and the pair is kept only for the window where the code is
     * deployed ahead of its migration. That state already produced one
     * unhandled 500 in production, and the alternative to these two lines is
     * the same 500 again.
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
     * An image can be deleted whether or not it has been played. The answer
     * rows that drew it keep their position, verdict and frozen label and lose
     * only the reference, so the round still reads — the review prints
     * "Imagem removida" where the photograph used to be.
     *
     * The failure notification is what an operator sees if the code is running
     * ahead of its migration, when the reference is still ON DELETE RESTRICT.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription('As rodadas que usaram esta imagem continuam com placar e gabarito. Só a foto deixa de aparecer.')
            ->failureNotificationTitle('Não foi possível excluir esta imagem')
            ->failureNotificationBody('O banco ainda a trata como obrigatória em partidas antigas. Rode as migrations pendentes e tente de novo.')
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
