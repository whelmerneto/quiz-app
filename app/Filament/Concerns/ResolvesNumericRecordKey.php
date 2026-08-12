<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Every resource route here carries an unconstrained `{record}` parameter, so
 * any stray segment reaches route-model binding. The primary keys are bigints
 * and Postgres answers a bad key with an error rather than an empty result:
 * 22P02 for a non-numeric one, 22003 for a numeric one past the bigint ceiling.
 * Both surface as a 500 on a URL an operator can reach by typing.
 *
 * `FILTER_VALIDATE_INT` is the single predicate that rejects both, because
 * PHP_INT_MAX equals the Postgres bigint maximum on a 64-bit build. `ctype_digit`
 * is not enough: it accepts a twenty-digit string and hands it straight to the
 * driver. Returning null here is what turns the request into a 404 —
 * `InteractsWithRecord::resolveRecord()` converts it into a ModelNotFoundException.
 *
 * A global `Route::pattern('record', '[0-9]+')` would be the wrong instrument:
 * it reaches every resource in the application including any later keyed on a
 * uuid, it depends on being registered before the panel registers its routes,
 * and it still matches the overflow case.
 */
trait ResolvesNumericRecordKey
{
    #[\Override]
    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        if (filter_var($key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return null;
        }

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }
}
