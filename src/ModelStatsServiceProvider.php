<?php declare(strict_types=1);

namespace SanderMuller\ModelStats;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SanderMuller\ModelStats\Audiences\AudienceResolver;
use SanderMuller\ModelStats\Http\Controllers\ShowModelStatsController;
use SanderMuller\ModelStats\Http\Controllers\ShowModelStatsIndexController;
use SanderMuller\ModelStats\Integrations\NovaResourceLocator;
use SanderMuller\ModelStats\Introspection\ModelFinder;
use SanderMuller\ModelStats\Stats\StatCalculator;

final class ModelStatsServiceProvider extends ServiceProvider
{
    private const string CONFIG_KEY = 'model-stats';

    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), self::CONFIG_KEY);

        $this->app->singleton(
            ModelFinder::class,
            fn (): ModelFinder => new ModelFinder($this->setting('source_roots', [])),
        );

        $this->app->singleton(
            NovaResourceLocator::class,
            fn (): NovaResourceLocator => new NovaResourceLocator(
                array_values($this->setting('nova.namespaces', [])),
                (string) config('nova.path', '/nova'),
            ),
        );

        $this->app->singleton(
            AudienceResolver::class,
            fn (): AudienceResolver => new AudienceResolver($this->setting('audiences', [])),
        );

        $this->app->singleton(
            StatCalculator::class,
            fn (): StatCalculator => new StatCalculator(
                (int) $this->setting('timeout_ms', 3000),
                (int) $this->setting('breakdown_limit', 25),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom($this->viewPath(), self::CONFIG_KEY);

        if ($this->app->runningInConsole()) {
            $this->publishes([$this->configPath() => config_path('model-stats.php')], 'model-stats-config');
            $this->publishes([$this->viewPath() => resource_path('views/vendor/model-stats')], 'model-stats-views');
        }

        if ($this->setting('route.enabled', true) === true) {
            $this->registerRoutes();
        }
    }

    /**
     * The package registers its own routes so a host application gets the dashboard by installing it,
     * but every gate is the host's: `route.middleware` is the only thing standing in front of them.
     */
    private function registerRoutes(): void
    {
        Route::domain($this->setting('route.domain'))
            ->prefix((string) $this->setting('route.prefix', 'model-stats'))
            ->name((string) $this->setting('route.name', 'model-stats.'))
            ->middleware($this->setting('route.middleware', ['web']))
            ->group(function (): void {
                Route::get('/', ShowModelStatsIndexController::class)->name('index');
                Route::get('{model}', ShowModelStatsController::class)->name('show');
            });
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return config(self::CONFIG_KEY . '.' . $key, $default);
    }

    private function configPath(): string
    {
        return __DIR__ . '/../config/model-stats.php';
    }

    private function viewPath(): string
    {
        return __DIR__ . '/../resources/views';
    }
}
