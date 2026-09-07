<?php declare(strict_types=1);

namespace SanderMuller\ModelStats;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SanderMuller\ModelStats\Audiences\AudienceGate;
use SanderMuller\ModelStats\Audiences\AudienceResolver;
use SanderMuller\ModelStats\Dashboards\DashboardRegistry;
use SanderMuller\ModelStats\Dashboards\DashboardRenderer;
use SanderMuller\ModelStats\Http\Controllers\ManageUserDashboardController;
use SanderMuller\ModelStats\Http\Controllers\ShowDashboardController;
use SanderMuller\ModelStats\Http\Controllers\ShowDashboardIndexController;
use SanderMuller\ModelStats\Http\Controllers\ShowModelStatsController;
use SanderMuller\ModelStats\Http\Controllers\ShowModelStatsIndexController;
use SanderMuller\ModelStats\Integrations\NovaResourceLocator;
use SanderMuller\ModelStats\Introspection\ModelFinder;
use SanderMuller\ModelStats\Introspection\ModelInspector;
use SanderMuller\ModelStats\Stats\StatCalculator;

final class ModelStatsServiceProvider extends ServiceProvider
{
    private const string CONFIG_KEY = 'model-stats';

    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), self::CONFIG_KEY);

        $this->app->singleton(
            ModelFinder::class,
            fn (): ModelFinder => new ModelFinder(
                $this->settingMap('source_roots'),
                $this->settingList('models.whitelist'),
                $this->settingList('models.blacklist'),
            ),
        );

        // Bound, not shared. The inspector holds no cache of its own, so a singleton would buy
        // nothing and would freeze the selection config at whatever it was on first resolution.
        // `ModelFinder` stays shared on purpose — its per-request model list is the point.
        $this->app->bind(
            ModelInspector::class,
            fn (): ModelInspector => new ModelInspector(
                $this->app->make(ModelFinder::class),
                $this->patterns('columns'),
                $this->patterns('relations'),
            ),
        );

        $this->app->singleton(
            NovaResourceLocator::class,
            fn (): NovaResourceLocator => new NovaResourceLocator(
                $this->settingList('nova.namespaces'),
                (string) config('nova.path', '/nova'),
            ),
        );

        $this->app->singleton(
            DashboardRegistry::class,
            fn (): DashboardRegistry => new DashboardRegistry($this->settingMap('dashboard_roots')),
        );

        $this->app->bind(
            DashboardRenderer::class,
            fn (): DashboardRenderer => new DashboardRenderer(
                $this->app->make(ModelFinder::class),
                $this->app->make(ModelInspector::class),
                $this->app->make(StatCalculator::class),
                $this->app->make(AudienceGate::class),
                (int) (is_numeric($p = $this->setting('page_budget_ms', 15000)) ? $p : 15000),
            ),
        );

        $this->app->singleton(
            AudienceResolver::class,
            fn (): AudienceResolver => new AudienceResolver($this->settingArray('audiences')),
        );

        $this->app->singleton(
            StatCalculator::class,
            fn (): StatCalculator => new StatCalculator(
                (int) (is_numeric($t = $this->setting('timeout_ms', 3000)) ? $t : 3000),
                (int) (is_numeric($b = $this->setting('breakdown_limit', 25)) ? $b : 25),
                (int) (is_numeric($c = $this->setting('cache_minutes', 15)) ? $c : 15),
            ),
        );
    }

    /**
     * @return array{whitelist: list<array{0: string, 1: string}>, blacklist: list<array{0: string, 1: string}>}
     */
    private function patterns(string $level): array
    {
        /** @var array{whitelist: list<array{0: string, 1: string}>, blacklist: list<array{0: string, 1: string}>} $patterns */
        $patterns = [
            'whitelist' => $this->settingArray("{$level}.whitelist"),
            'blacklist' => $this->settingArray("{$level}.blacklist"),
        ];

        return $patterns;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function settingArray(string $key): array
    {
        $value = $this->setting($key, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Namespace prefix => directory, with anything malformed dropped rather than reaching a `Finder`
     * that would fail on it.
     *
     * @return array<string, string>
     */
    private function settingMap(string $key): array
    {
        $map = [];
        foreach ($this->settingArray($key) as $prefix => $directory) {
            if (is_string($prefix) && is_string($directory)) {
                $map[$prefix] = $directory;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function settingList(string $key): array
    {
        return array_values(array_map(
            static fn (mixed $entry): string => (string) (is_scalar($entry) ? $entry : ''),
            $this->settingArray($key),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom($this->viewPath(), self::CONFIG_KEY);
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([$this->configPath() => config_path('model-stats.php')], 'model-stats-config');
            $this->publishes([$this->viewPath() => resource_path('views/vendor/model-stats')], 'model-stats-views');
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'model-stats-migrations');
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

                // Ahead of the `{model}` catch-all below, which matches any single segment and would
                // otherwise resolve `/dashboards` as a model slug. This order is load-bearing;
                // `DashboardRoutingTest` pins it.
                Route::get('dashboards', ShowDashboardIndexController::class)->name('dashboards.index');
                Route::get('dashboards/create', [ManageUserDashboardController::class, 'create'])->name('dashboards.create');
                Route::post('dashboards', [ManageUserDashboardController::class, 'store'])->name('dashboards.store');
                Route::get('dashboards/{dashboard}/edit', [ManageUserDashboardController::class, 'edit'])->name('dashboards.edit');
                Route::put('dashboards/{dashboard}', [ManageUserDashboardController::class, 'update'])->name('dashboards.update');
                Route::delete('dashboards/{dashboard}', [ManageUserDashboardController::class, 'destroy'])->name('dashboards.destroy');
                Route::get('dashboards/{dashboard}', ShowDashboardController::class)->name('dashboards.show');

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
