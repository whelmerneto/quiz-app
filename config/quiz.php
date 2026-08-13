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
    | this is "public", behind `php artisan storage:link`. In production it is
    | "quiz_storage", the bucket defined in config/filesystems.php.
    |
    | Anywhere the application container's filesystem is ephemeral — Laravel
    | Cloud included — "public" silently loses every upload on the next deploy.
    | `php artisan quiz:check-storage` is the guard against shipping that way.
    |
    */

    'disk' => env('QUIZ_DISK', 'public'),

];
