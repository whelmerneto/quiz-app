<?php

declare(strict_types=1);

use App\Filament\Resources\QuizAttempts\Pages\ListQuizAttempts;
use App\Models\Prize;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Quiz\ExportQuizAttempts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Runs the streamed response and returns the bytes a browser would receive.
 *
 * @param  Builder<QuizAttempt>|null  $query
 */
function exportedCsv(?Builder $query = null): string
{
    $response = app(ExportQuizAttempts::class)->handle(
        $query ?? QuizAttempt::query(),
        'partidas.csv',
    );

    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/**
 * Reads the file back the way a spreadsheet would, BOM stripped.
 *
 * @return list<list<string>>
 */
function parsedCsv(string $csv): array
{
    $lines = preg_split('/\R/', trim(str_replace("\u{FEFF}", '', $csv))) ?: [];

    return array_map(
        static fn (string $line): array => str_getcsv($line, ';', '"', ''),
        array_filter($lines, static fn (string $line): bool => $line !== ''),
    );
}

it('writes a row per attempt, with the prize when there is one', function (): void {
    $prize = Prize::factory()->create(['name' => 'Caneca', 'required_correct' => 5]);

    QuizAttempt::factory()->create([
        'player_name' => 'Ana Souza',
        'player_email' => 'ana@example.com',
        'correct_count' => 7,
        'question_count' => 10,
        'prize_id' => $prize->id,
        'completed_at' => now(),
    ]);

    QuizAttempt::factory()->create([
        'player_name' => 'Bruno Lima',
        'player_email' => 'bruno@example.com',
        'correct_count' => 2,
        'question_count' => 10,
        'prize_id' => null,
        'completed_at' => null,
    ]);

    // Parsed back rather than string-matched: this asserts the file is readable
    // as CSV, which is the actual contract. PHP quotes any field containing a
    // space, so a raw-string assertion would be testing its quoting rules.
    $rows = parsedCsv(exportedCsv());

    expect($rows[0])->toBe(['Jogador', 'E-mail', 'Acertos', 'Total de perguntas', 'Prêmio', 'Iniciada em', 'Concluída em']);

    $byPlayer = collect($rows)->skip(1)->keyBy(0);

    expect($byPlayer['Ana Souza'][1])->toBe('ana@example.com')
        ->and($byPlayer['Ana Souza'][2])->toBe('7')
        ->and($byPlayer['Ana Souza'][3])->toBe('10')
        ->and($byPlayer['Ana Souza'][4])->toBe('Caneca')
        // No prize and no completion: empty fields, not the string "null" and
        // not missing columns.
        ->and($byPlayer['Bruno Lima'][4])->toBe('')
        ->and($byPlayer['Bruno Lima'][6])->toBe('')
        ->and($byPlayer['Bruno Lima'])->toHaveCount(7);
});

it('starts with a utf-8 bom so excel reads the accents', function (): void {
    // Without the BOM, Excel under a pt_BR locale decodes the file as Latin-1
    // and every accented name arrives mangled.
    QuizAttempt::factory()->create(['player_name' => 'João Conceição']);

    $csv = exportedCsv();

    expect($csv)->toStartWith("\u{FEFF}")
        ->and($csv)->toContain('João Conceição')
        ->and($csv)->toContain('Prêmio');
});

it('quotes a value containing the delimiter instead of splitting it', function (): void {
    QuizAttempt::factory()->create(['player_name' => 'Souza; Ana']);

    expect(exportedCsv())->toContain('"Souza; Ana"');
});

it('doubles a quote rather than escaping it with a backslash', function (): void {
    // `escape: ''` turns off PHP's non-standard backslash escaping. A consumer
    // reading RFC 4180 expects the doubled form; a backslash would survive into
    // the cell as a literal character.
    QuizAttempt::factory()->create(['player_name' => 'Ana "Aninha" Souza']);

    $csv = exportedCsv();

    expect($csv)->toContain('"Ana ""Aninha"" Souza"')
        ->and($csv)->not->toContain('\\"');
});

it('exports only the rows the given query selects', function (): void {
    QuizAttempt::factory()->create(['player_name' => 'Concluida', 'completed_at' => now()]);
    QuizAttempt::factory()->create(['player_name' => 'EmAndamento', 'completed_at' => null]);

    $csv = exportedCsv(QuizAttempt::query()->whereNotNull('completed_at'));

    expect($csv)->toContain('Concluida')
        ->and($csv)->not->toContain('EmAndamento');
});

it('reads the prize without a lazy load', function (): void {
    // preventLazyLoading() is on outside production. The export touches
    // `prize` on every row, so a missing eager load throws here rather than in
    // an operator's browser.
    $prize = Prize::factory()->create(['name' => 'Chaveiro', 'required_correct' => 5]);
    QuizAttempt::factory()->count(3)->create(['prize_id' => $prize->id]);

    expect(exportedCsv())->toContain('Chaveiro');
});

it('offers the export from the attempts page', function (): void {
    Prize::factory()->create(['name' => 'Caneca', 'required_correct' => 5]);
    QuizAttempt::factory()->create(['player_name' => 'Ana Souza']);

    Livewire::actingAs(User::factory()->create())
        ->test(ListQuizAttempts::class)
        ->callAction('export')
        ->assertFileDownloaded();
});

it('exports what the active filter shows, not the whole table', function (): void {
    // The reason the page hands the table's filtered query to the service
    // instead of the model: the file has to match what the operator sees.
    QuizAttempt::factory()->create(['player_name' => 'Concluida', 'completed_at' => now()]);
    QuizAttempt::factory()->create(['player_name' => 'EmAndamento', 'completed_at' => null]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(ListQuizAttempts::class)
        ->filterTable('completed');

    $rows = $component->instance()->getFilteredTableQuery()?->pluck('player_name')->all();

    expect($rows)->toBe(['Concluida']);
});
