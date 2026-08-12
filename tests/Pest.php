<?php

declare(strict_types=1);

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

pest()->extend(TestCase::class)->in('Feature', 'Unit');
