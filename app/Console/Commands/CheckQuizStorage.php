<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Proves that an uploaded image will survive and be readable, before an operator
 * uploads one.
 *
 * This exists because the failure it catches is silent. Writing to the `public`
 * disk on an ephemeral container succeeds: the file lands, the row is created,
 * the panel reports success. The image only disappears on the next deploy, and
 * the symptom — a 404 in someone's browser — surfaces far from the action that
 * caused it. Configuration alone is not enough to check either, since a bucket
 * can be named correctly and still be unreachable or private.
 *
 * So the command does the round trip: write an object, fetch it back over the
 * public URL exactly as a visitor's browser would, then delete it.
 */
final class CheckQuizStorage extends Command
{
    protected $signature = 'quiz:check-storage';

    protected $description = 'Verify that quiz images can be written, served publicly, and deleted';

    private const string PROBE_PATH = 'quiz-images/.storage-probe.txt';

    public function handle(): int
    {
        $disk = Config::string('quiz.disk');

        $this->line('Disk in use: <options=bold>'.$disk.'</>');

        if ($disk === 'public' || $disk === 'local') {
            $this->components->warn(
                "Uploads go to the `{$disk}` disk, which is the container's own "
                .'filesystem. Where that filesystem is ephemeral every image is lost '
                .'on the next deploy. Set QUIZ_DISK=quiz_storage and redeploy — '
                .'config:cache freezes the value at build time, so adding the variable '
                .'is not enough on its own.'
            );
        }

        foreach (['bucket', 'endpoint', 'url'] as $key) {
            $value = Config::get("filesystems.disks.{$disk}.{$key}");

            $this->line(sprintf(
                '  %-9s %s',
                $key,
                $value === null ? '<fg=red>not set</>' : (string) $value
            ));
        }

        return $this->probe($disk);
    }

    /**
     * Write, read back over HTTP, delete. The HTTP leg is the point: a bucket
     * that accepts the write can still be private, and QUIZ_STORAGE_URL can
     * point somewhere no browser resolves.
     */
    private function probe(string $disk): int
    {
        $body = 'probe '.now()->toIso8601String();

        try {
            Storage::disk($disk)->put(self::PROBE_PATH, $body);
        } catch (Throwable $e) {
            $this->components->error('Write failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $url = Storage::disk($disk)->url(self::PROBE_PATH);

        $this->line('Probe URL: '.$url);

        // A local URL points at a port published on the host, which this
        // container cannot reach. Fetching it would fail for a reason that has
        // nothing to do with storage, so say so rather than report a problem
        // that is not there.
        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            Storage::disk($disk)->delete(self::PROBE_PATH);

            $this->components->warn(
                'The URL is local, so the round trip is skipped: this container cannot '
                .'reach a port published on the host. Run this command where the disk '
                .'is the one production uses.'
            );

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(15)->get($url);
            $readable = $response->successful() && $response->body() === $body;
            $status = $response->status();
        } catch (Throwable $e) {
            $readable = false;
            $status = $e->getMessage();
        }

        Storage::disk($disk)->delete(self::PROBE_PATH);

        if (! $readable) {
            $this->components->error(
                'The object was written but is not readable at its public URL ('.$status.'). '
                .'Either public access is off on the bucket, or QUIZ_STORAGE_URL is wrong.'
            );

            return self::FAILURE;
        }

        $this->components->info('Wrote, fetched and deleted a probe object. Storage is ready.');

        // A green round trip on the local disk still means production would
        // lose its uploads, so only a real bucket counts as success.
        return ($disk === 'public' || $disk === 'local') ? self::FAILURE : self::SUCCESS;
    }
}
