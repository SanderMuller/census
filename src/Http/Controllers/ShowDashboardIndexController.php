<?php declare(strict_types=1);

namespace SanderMuller\Census\Http\Controllers;

use Illuminate\Contracts\View\View;
use SanderMuller\Census\Audiences\Audience;
use SanderMuller\Census\Audiences\AudienceResolver;
use SanderMuller\Census\Dashboards\DashboardRegistry;
use SanderMuller\Census\Dashboards\UserDashboards;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ShowDashboardIndexController
{
    public function __construct(
        private AudienceResolver $audiences,
        private DashboardRegistry $registry,
        private UserDashboards $store,
    ) {}

    public function __invoke(): View
    {
        $audience = $this->audiences->resolve();
        if (! $audience instanceof Audience) {
            throw new AccessDeniedHttpException('No census audience covers this user.');
        }

        $fixed = [];
        foreach ($this->registry->all() as $slug => $dashboard) {
            // An empty `audiences()` means nobody, so a dashboard that forgot to declare them is
            // hidden rather than exposed. An audience name config does not define never matches.
            if (! in_array($audience->key, $dashboard->audiences(), strict: true)) {
                continue;
            }

            $fixed[] = [
                'slug' => $slug,
                'name' => $dashboard->name(),
                'description' => $dashboard->description(),
            ];
        }

        // Fixed dashboards first; the user ones join this list once they exist.
        return view('census::dashboards.index', [
            'audience' => $audience,
            'fixed' => $fixed,
            'userDashboards' => $this->store->openableBy($audience),
            'canCreate' => $this->store->isAvailable(),
        ]);
    }
}
