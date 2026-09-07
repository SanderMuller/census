<?php declare(strict_types=1);

namespace SanderMuller\Census\Http\Controllers;

use Illuminate\Contracts\View\View;
use SanderMuller\Census\Audiences\Audience;
use SanderMuller\Census\Audiences\AudienceResolver;
use SanderMuller\Census\Dashboards\Dashboard;
use SanderMuller\Census\Dashboards\DashboardRegistry;
use SanderMuller\Census\Dashboards\DashboardRenderer;
use SanderMuller\Census\Dashboards\UserDashboard;
use SanderMuller\Census\Dashboards\UserDashboards;
use SanderMuller\Census\Stats\Stat;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ShowDashboardController
{
    public function __construct(
        private AudienceResolver $audiences,
        private DashboardRegistry $registry,
        private DashboardRenderer $renderer,
        private UserDashboards $store,
    ) {}

    public function __invoke(string $dashboard): View
    {
        $audience = $this->audiences->resolve();
        if (! $audience instanceof Audience) {
            throw new AccessDeniedHttpException('No census audience covers this user.');
        }

        // Fixed slugs win, so a fixed dashboard is looked up first even though the two share one
        // namespace. Denied as missing, not as forbidden, and before a single reference is resolved:
        // "forbidden" would confirm the dashboard exists to someone never meant to learn that, and
        // resolving first would cost queries for stats this viewer may not read.
        $found = $this->registry->find($dashboard);

        if ($found instanceof Dashboard) {
            if (! in_array($audience->key, $found->audiences(), strict: true)) {
                throw new NotFoundHttpException("No dashboard matches the slug [{$dashboard}].");
            }

            $references = $found->stats();
            $name = $found->name();
            $description = $found->description();
            $editable = false;
        } else {
            $user = $this->store->findOpenableBy($dashboard, $audience);
            if (! $user instanceof UserDashboard) {
                throw new NotFoundHttpException("No dashboard matches the slug [{$dashboard}].");
            }

            $references = $user->references();
            $name = $user->name;
            $description = $user->description;
            $editable = true;
        }

        $rendered = $this->renderer->render($references, $audience);

        // A dashboard whose every stat is withheld *from this viewer* has nothing for them, so it
        // reads as missing — matching its absence from their list. A dashboard left empty by a
        // global exclusion is a different thing: empty for everyone, and it still renders.
        if ($rendered->isEmpty() && $rendered->withheldFromAudience) {
            throw new NotFoundHttpException("No dashboard matches the slug [{$dashboard}].");
        }

        return view('census::dashboards.show', [
            'audience' => $audience,
            'slug' => $dashboard,
            'name' => $name,
            'description' => $description,
            'groups' => collect($rendered->cards)->groupBy(static fn (Stat $stat): string => $stat->group),
            'complete' => $rendered->complete,
            'editable' => $editable,
        ]);
    }
}
