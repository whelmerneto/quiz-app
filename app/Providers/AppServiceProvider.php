<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\QuizAttempt;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('quiz-start', static fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->ip() ?? 'unknown'));

        // Keyed on the round in the session so one player answering fast cannot
        // spend the budget of everyone else behind the same NAT. A caller
        // without a session falls back to the address, which is the only thing
        // left to count.
        RateLimiter::for('quiz-answer', static function (Request $request): Limit {
            $attemptUuid = $request->hasSession()
                ? $request->session()->get(QuizAttempt::SESSION_KEY)
                : null;

            return Limit::perMinute(120)->by(
                is_string($attemptUuid) ? $attemptUuid : ($request->ip() ?? 'unknown')
            );
        });
    }
}
