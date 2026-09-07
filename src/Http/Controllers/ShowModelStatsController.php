<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use SanderMuller\ModelStats\Audiences\Audience;
use SanderMuller\ModelStats\Audiences\AudienceGate;
use SanderMuller\ModelStats\Audiences\AudienceResolver;
use SanderMuller\ModelStats\Integrations\NovaResourceLocator;
use SanderMuller\ModelStats\Introspection\ModelBlueprint;
use SanderMuller\ModelStats\Introspection\ModelFinder;
use SanderMuller\ModelStats\Introspection\ModelInspector;
use SanderMuller\ModelStats\Introspection\ModelReference;
use SanderMuller\ModelStats\Stats\Stat;
use SanderMuller\ModelStats\Stats\StatCalculator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ShowModelStatsController
{
    public function __construct(
        private ModelFinder $finder,
        private ModelInspector $inspector,
        private StatCalculator $calculator,
        private NovaResourceLocator $novaLocator,
        private AudienceResolver $audiences,
        private AudienceGate $gate,
    ) {}

    public function __invoke(Request $request, string $model): View
    {
        $audience = $this->audiences->resolve();
        if (! $audience instanceof Audience) {
            throw new AccessDeniedHttpException('No model-stats audience covers this user.');
        }

        $reference = $this->finder->findBySlug($model);
        if (! $reference instanceof ModelReference) {
            throw new NotFoundHttpException("No model matches the slug [{$model}].");
        }

        $blueprint = $this->inspector->inspect($reference->class);

        // A model this audience cannot see is reported as missing, not as forbidden. "Forbidden"
        // would confirm the model exists to someone who was never meant to learn that.
        if (! $this->gate->canSee($blueprint, $audience)) {
            throw new NotFoundHttpException("No model matches the slug [{$model}].");
        }

        $blueprint = $this->gate->restrict($blueprint, $audience);

        if ($request->query('fresh') !== null) {
            Cache::forget($this->cacheKey($blueprint, $audience));
        }

        $cached = Cache::remember(
            $this->cacheKey($blueprint, $audience),
            now()->addMinutes((int) config('model-stats.cache_minutes', 15)),
            fn (): array => [
                'calculated_at' => now()->toIso8601String(),
                'stats' => $this->calculator->calculate($blueprint),
            ],
        );

        return view('model-stats::show', [
            'blueprint' => $blueprint,
            'audience' => $audience,
            'groups' => collect($cached['stats'])->groupBy(static fn (Stat $stat): string => $stat->group),
            'calculatedAt' => Carbon::parse($cached['calculated_at']),
            'novaUrl' => $audience->readsSchema ? $this->novaLocator->urlFor($reference->class) : null,
        ]);
    }

    /**
     * The audience is part of the key. Two audiences see different stats for the same model, so one
     * cache entry serving both would hand a support user a developer's numbers.
     */
    private function cacheKey(ModelBlueprint $blueprint, Audience $audience): string
    {
        return "model-stats.{$audience->key}.{$blueprint->slug}";
    }
}
