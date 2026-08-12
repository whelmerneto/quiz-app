<?php

declare(strict_types=1);

use App\Http\Controllers\Quiz\AnswerController;
use App\Http\Controllers\Quiz\LandingController;
use App\Http\Controllers\Quiz\QuizAttemptController;
use App\Http\Controllers\Quiz\ResultController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public quiz routes
|--------------------------------------------------------------------------
|
| `/admin/*` belongs to the Filament panel provider and is not declared here.
|
| Every attempt segment is constrained with whereUuid(). `quiz_attempts.uuid`
| is a native Postgres uuid column, so without the constraint a typo in the URL
| reaches the driver and comes back as 22P02 — a 500 on a route that should
| simply answer 404.
|
*/

Route::get('/', LandingController::class)->name('quiz.landing');

Route::post('/quiz', [QuizAttemptController::class, 'store'])
    ->middleware('throttle:quiz-start')
    ->name('quiz.start');

Route::get('/quiz/{attempt:uuid}', [QuizAttemptController::class, 'show'])
    ->whereUuid('attempt')
    ->name('quiz.play');

Route::post('/quiz/{attempt:uuid}/answer', [AnswerController::class, 'store'])
    ->middleware('throttle:quiz-answer')
    ->whereUuid('attempt')
    ->name('quiz.answer');

Route::get('/quiz/{attempt:uuid}/resultado', ResultController::class)
    ->whereUuid('attempt')
    ->name('quiz.result');
