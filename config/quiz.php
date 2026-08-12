<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Questions Per Round
    |--------------------------------------------------------------------------
    |
    | How many images a single quiz round draws. The value is frozen onto the
    | attempt when the round starts, so changing it never alters a round that
    | is already in flight.
    |
    */

    'questions_per_round' => (int) env('QUIZ_QUESTIONS_PER_ROUND', 10),

    /*
    |--------------------------------------------------------------------------
    | Image Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk backing quiz image and prize photo uploads. Locally
    | this is the "public" disk behind `php artisan storage:link`. On Laravel
    | Cloud an attached bucket sets FILESYSTEM_DISK=s3 and the same code
    | writes to object storage.
    |
    */

    'disk' => env('QUIZ_DISK', env('FILESYSTEM_DISK', 'public')),

];
