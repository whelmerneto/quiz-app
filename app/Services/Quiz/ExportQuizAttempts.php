<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the attempts table as CSV.
 *
 * Streamed rather than assembled: `lazyById()` keeps one page of rows in memory
 * at a time, so the response costs the same whether the table holds fifty rows
 * or fifty thousand. Filament ships an ExportAction that would do this too, but
 * it runs through a queued job and needs an `exports` table and database
 * notifications — infrastructure this application deliberately does not have,
 * since it has no asynchronous work at all.
 */
final readonly class ExportQuizAttempts
{
    /**
     * Semicolon, not comma. The operator opens this in Excel under a pt_BR
     * locale, where the list separator is `;` and a comma-separated file lands
     * entirely in column A. The BOM is the other half of the same problem:
     * without it Excel reads the file as Latin-1 and every accented name in it
     * arrives mangled.
     */
    private const string DELIMITER = ';';

    private const string BOM = "\u{FEFF}";

    /** @var list<string> */
    private const array HEADINGS = [
        'Jogador',
        'E-mail',
        'Acertos',
        'Total de perguntas',
        'Prêmio',
        'Iniciada em',
        'Concluída em',
    ];

    /**
     * @param  Builder<QuizAttempt>  $query  the table's own filtered query, so the
     *                                       file matches what the operator sees
     */
    public function handle(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($query): void {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    return;
                }

                fwrite($handle, self::BOM);
                // `escape: ''` disables PHP's backslash escaping, which is not
                // part of RFC 4180 and which no spreadsheet reads back. With it
                // off, a quote inside a value is doubled, which every consumer
                // understands. PHP 8.4 deprecates leaving the argument implicit,
                // so it is passed on every call.
                fputcsv($handle, self::HEADINGS, self::DELIMITER, escape: '');

                // `prize` is read for every row and lazy loading is blocked
                // outside production, so it is loaded with the chunk. lazyById
                // paginates on the primary key rather than by OFFSET, which is
                // what keeps the cursor stable while rows are being written.
                $query->with('prize')
                    ->lazyById()
                    ->each(function (QuizAttempt $attempt) use ($handle): void {
                        fputcsv($handle, $this->row($attempt), self::DELIMITER, escape: '');
                    });

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function row(QuizAttempt $attempt): array
    {
        $timezone = Config::string('app.timezone');

        return [
            $attempt->player_name,
            $attempt->player_email,
            (string) $attempt->correct_count,
            (string) $attempt->question_count,
            // Keyed off the foreign key rather than the relation. `prize_id` is
            // nullable and the analyser knows it, while the BelongsTo generic
            // cannot express that the relation is absent. It is also exact: the
            // FK is ON DELETE SET NULL, so a non-null id always has a row.
            $attempt->prize_id === null ? '' : $attempt->prize->name,
            $attempt->created_at?->timezone($timezone)->format('d/m/Y H:i') ?? '',
            $attempt->completed_at?->timezone($timezone)->format('d/m/Y H:i') ?? '',
        ];
    }
}
