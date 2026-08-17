<?php

declare(strict_types=1);

use App\Enums\ImageLabel;
use App\Models\QuizAttempt;
use App\Models\QuizImage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every test in the suite runs against the application TestCase, which stops
| stray outbound HTTP requests. Database access is opt-in per file through
| `uses(RefreshDatabase::class)`.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Browser');

/*
|--------------------------------------------------------------------------
| Browser binary
|--------------------------------------------------------------------------
|
| pest-plugin-browser serves the application from inside the app container and
| drives it through a Playwright server it starts there, so the browser has to
| be in the container too. Playwright's own Chromium download is a glibc build
| and cannot run on musl; Alpine's package can. Playwright has no environment
| variable for a custom executable, so the binary is linked into the layout its
| registry looks for. The revision comes from the installed playwright-core,
| which is why this runs here and not in the Dockerfile: node_modules is a bind
| mount and does not exist at image build time.
|
| Everything is guarded. On a host with a normal Playwright install none of it
| applies and the block is a no-op.
|
*/
(static function (): void {
    $manifestPath = __DIR__.'/../node_modules/playwright-core/browsers.json';
    $systemChromium = '/usr/bin/chromium-browser';

    if (! is_file($manifestPath) || ! is_file($systemChromium)) {
        return;
    }

    /** @var array{browsers: list<array{name: string, revision: string}>} $manifest */
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    $root = __DIR__.'/../storage/framework/playwright';

    $layout = [
        'chromium' => 'chrome-linux/chrome',
        'chromium-headless-shell' => 'chrome-headless-shell-linux64/chrome-headless-shell',
    ];

    foreach ($manifest['browsers'] as $browser) {
        if (! array_key_exists($browser['name'], $layout)) {
            continue;
        }

        $directory = $root.'/'.str_replace('-', '_', $browser['name']).'-'.$browser['revision'];
        $executable = $directory.'/'.$layout[$browser['name']];

        if (! is_dir(dirname($executable))) {
            mkdir(dirname($executable), 0755, true);
        }

        if (! is_link($executable)) {
            symlink($systemChromium, $executable);
        }

        touch($directory.'/INSTALLATION_COMPLETE');
    }

    $environment = [
        'PLAYWRIGHT_BROWSERS_PATH' => $root,
        // Alpine is not on Playwright's supported list, so its host check fails
        // on package names that do not exist here. The launch itself works.
        'PLAYWRIGHT_SKIP_VALIDATE_HOST_REQUIREMENTS' => '1',
    ];

    // All three, and $_ENV is the one that matters. Symfony's Process builds a
    // child environment from $_ENV plus the intersection of getenv() and
    // $_SERVER, so a variable set with putenv() alone is filtered out before
    // the Playwright server ever sees it.
    foreach ($environment as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
})();

/*
|--------------------------------------------------------------------------
| Quiz helpers
|--------------------------------------------------------------------------
|
| A round is only ever started over HTTP, because the session ownership the
| answer endpoint checks is part of what the tests are asserting.
|
*/

/**
 * Seeds an active image pool. Passing a label makes every image agree, which is
 * what lets a test know the truth of a position without querying for it.
 */
function seedQuizImages(int $count, ?ImageLabel $label = null): void
{
    $factory = QuizImage::factory()->count($count);

    if ($label instanceof ImageLabel) {
        $factory = $factory->state(['label' => $label]);
    }

    $factory->create();
}

/**
 * Starts a round the way a player does: a POST that fills the session. Each
 * call uses its own address, because an address plays exactly one round — a
 * test that needs two rounds is two players.
 */
function startRound(int $poolSize = 10, ?ImageLabel $label = null): QuizAttempt
{
    static $player = 0;

    seedQuizImages($poolSize, $label);

    $player++;

    test()->post(route('quiz.start'), [
        'name' => 'Ana Souza',
        'email' => "ana+{$player}@example.com",
    ])->assertRedirect();

    return QuizAttempt::query()->latest('id')->firstOrFail();
}

/**
 * The label a position is actually carrying. Server-side only: the client is
 * never told this.
 */
function correctLabelFor(QuizAttempt $attempt, int $position): ImageLabel
{
    return $attempt->answers()
        ->with('image')
        ->where('position', $position)
        ->sole()
        ->image
        ->label;
}

function wrongLabelFor(QuizAttempt $attempt, int $position): ImageLabel
{
    return correctLabelFor($attempt, $position) === ImageLabel::Real
        ? ImageLabel::ThreeD
        : ImageLabel::Real;
}

function answerPosition(QuizAttempt $attempt, int $position, ?ImageLabel $answer = null): TestResponse
{
    return test()->postJson(route('quiz.answer', ['attempt' => $attempt]), [
        'position' => $position,
        'answer' => ($answer ?? correctLabelFor($attempt, $position))->value,
    ]);
}

/**
 * Plays every position, getting the first `$correctAnswers` of them right.
 */
function playRound(QuizAttempt $attempt, int $correctAnswers): void
{
    for ($position = 1; $position <= $attempt->question_count; $position++) {
        $answer = $position <= $correctAnswers
            ? correctLabelFor($attempt, $position)
            : wrongLabelFor($attempt, $position);

        answerPosition($attempt, $position, $answer)->assertOk();
    }
}
