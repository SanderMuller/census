<?php declare(strict_types=1);

namespace SanderMuller\Census\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use SanderMuller\Census\Audiences\Audience;
use SanderMuller\Census\Audiences\AudienceGate;
use SanderMuller\Census\Audiences\AudienceResolver;
use SanderMuller\Census\Integrations\NovaResourceLocator;
use SanderMuller\Census\Introspection\ModelFinder;
use SanderMuller\Census\Introspection\ModelInspector;
use SanderMuller\Census\Introspection\ModelReference;
use SanderMuller\Census\Stats\Stat;
use SanderMuller\Census\Stats\StatCalculator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ShowModelController
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
            throw new AccessDeniedHttpException('No census audience covers this user.');
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

        $cached = $this->calculator->cached($blueprint, $audience->key, $request->query('fresh') !== null);

        return view('census::show', [
            'blueprint' => $blueprint,
            'audience' => $audience,
            'groups' => collect($cached->stats)->groupBy(static fn (Stat $stat): string => $stat->group),
            'calculatedAt' => $cached->calculatedAt,
            'novaUrl' => $audience->readsSchema ? $this->novaLocator->urlFor($reference->class) : null,
        ]);
    }
}
