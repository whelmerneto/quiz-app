<?php

declare(strict_types=1);

use App\Enums\ImageLabel;
use App\Filament\Resources\QuizImages\Pages\CreateQuizImage;
use App\Filament\Resources\QuizImages\Pages\EditQuizImage;
use App\Filament\Resources\QuizImages\Pages\ListQuizImages;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizImage;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake(Config::string('quiz.disk'));

    $this->actingAs(User::factory()->create());
});

function pngBytes(int $paddingBytes = 0): string
{
    $image = imagecreatetruecolor(4, 4);

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    // Trailing bytes after IEND leave the PNG magic number at the head of the
    // file untouched, so the result still reads as a PNG at any size.
    return $png.str_repeat("\0", $paddingBytes);
}

/**
 * Puts a real file behind a factory-made record. Filament drops a stored path
 * from the form state when the file is missing from the disk, which would make
 * an edit fail on a required field that the operator never touched.
 */
function storeImageFile(QuizImage $image): void
{
    Storage::disk(Config::string('quiz.disk'))->put($image->path, pngBytes());
}

it('creates a quiz image through the panel', function (): void {
    $file = UploadedFile::fake()->image('render.png', 640, 480);

    Livewire::test(CreateQuizImage::class)
        ->fillForm([
            'path' => $file,
            'label' => ImageLabel::ThreeD->value,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $image = QuizImage::query()->sole();

    expect($image->label)->toBe(ImageLabel::ThreeD)
        ->and($image->is_active)->toBeTrue()
        ->and($image->path)->toStartWith('quiz-images/');

    Storage::disk(Config::string('quiz.disk'))->assertExists($image->path);
});

it('edits a quiz image through the panel', function (): void {
    $image = QuizImage::factory()->create(['label' => ImageLabel::Real]);
    storeImageFile($image);

    Livewire::test(EditQuizImage::class, ['record' => $image->getRouteKey()])
        ->fillForm(['label' => ImageLabel::ThreeD->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($image->refresh()->label)->toBe(ImageLabel::ThreeD);
});

it('deactivates a quiz image through the panel', function (): void {
    $image = QuizImage::factory()->create(['is_active' => true]);
    storeImageFile($image);

    Livewire::test(EditQuizImage::class, ['record' => $image->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($image->refresh()->is_active)->toBeFalse();
});

it('lists quiz images in the grid', function (): void {
    $images = QuizImage::factory()->count(3)->create();

    Livewire::test(ListQuizImages::class)
        ->assertCanSeeTableRecords($images);
});

it('deletes an unused quiz image', function (): void {
    $image = QuizImage::factory()->create();

    Livewire::test(ListQuizImages::class)
        ->callAction(TestAction::make('delete')->table($image));

    expect(QuizImage::query()->count())->toBe(0);
});

it('removes the stored file when an image is deleted', function (): void {
    // Filament clears a file only when the operator empties the field in a
    // form, never on record deletion. Without the explicit delete, every
    // removed image leaves an orphan the panel can no longer reach.
    $disk = Storage::disk(Config::string('quiz.disk'));
    $disk->put('quiz-images/orphan-check.png', 'png-bytes');

    $image = QuizImage::factory()->create(['path' => 'quiz-images/orphan-check.png']);

    Livewire::test(ListQuizImages::class)
        ->callAction(TestAction::make('delete')->table($image));

    expect(QuizImage::query()->count())->toBe(0)
        ->and($disk->exists('quiz-images/orphan-check.png'))->toBeFalse();
});

it('keeps the stored file when the delete is refused', function (): void {
    $disk = Storage::disk(Config::string('quiz.disk'));
    $disk->put('quiz-images/kept.png', 'png-bytes');

    $image = QuizImage::factory()->create(['path' => 'quiz-images/kept.png']);
    $attempt = QuizAttempt::factory()->create();

    QuizAttemptAnswer::query()->create([
        'quiz_attempt_id' => $attempt->id,
        'quiz_image_id' => $image->id,
        'position' => 1,
    ]);

    Livewire::test(ListQuizImages::class)
        ->callAction(TestAction::make('delete')->table($image));

    expect($disk->exists('quiz-images/kept.png'))->toBeTrue();
});

it('refuses to delete an image that appears in a past attempt', function (): void {
    $image = QuizImage::factory()->create();
    $attempt = QuizAttempt::factory()->create();

    QuizAttemptAnswer::query()->create([
        'quiz_attempt_id' => $attempt->id,
        'quiz_image_id' => $image->id,
        'position' => 1,
    ]);

    Livewire::test(ListQuizImages::class)
        ->callAction(TestAction::make('delete')->table($image))
        ->assertNotified('Não foi possível excluir esta imagem');

    expect(QuizImage::query()->whereKey($image->id)->exists())->toBeTrue();
});

it('rejects a jpeg upload', function (): void {
    Livewire::test(CreateQuizImage::class)
        ->fillForm([
            'path' => UploadedFile::fake()->image('foto.jpg', 640, 480),
            'label' => ImageLabel::Real->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['path']);

    expect(QuizImage::query()->count())->toBe(0);
});

it('rejects a webp upload', function (): void {
    // A hand-built RIFF/WEBP container: enough for content sniffing to report
    // something other than image/png, which is all the rule needs to reject it.
    $webp = 'RIFF'.pack('V', 44).'WEBPVP8 '.str_repeat("\0", 36);

    Livewire::test(CreateQuizImage::class)
        ->fillForm([
            'path' => UploadedFile::fake()->createWithContent('foto.webp', $webp),
            'label' => ImageLabel::Real->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['path']);

    expect(QuizImage::query()->count())->toBe(0);
});

it('rejects a png larger than 10 mb', function (): void {
    Livewire::test(CreateQuizImage::class)
        ->fillForm([
            'path' => UploadedFile::fake()->createWithContent('grande.png', pngBytes(11 * 1024 * 1024)),
            'label' => ImageLabel::Real->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['path']);

    expect(QuizImage::query()->count())->toBe(0);
});

it('accepts a png just under the limit', function (): void {
    // The counterpart to the oversized case: same construction, same MIME type,
    // only the size differs. Without it the rejection above could be passing on
    // a MIME failure instead of the size rule. 9 MB also pins the boundary that
    // moved: it was rejected under the old 8 MB cap and must pass under 10.
    Livewire::test(CreateQuizImage::class)
        ->fillForm([
            'path' => UploadedFile::fake()->createWithContent('quase.png', pngBytes(9 * 1024 * 1024)),
            'label' => ImageLabel::Real->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(QuizImage::query()->count())->toBe(1);
});
