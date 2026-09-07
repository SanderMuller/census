<?php declare(strict_types=1);

namespace SanderMuller\Census\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SanderMuller\Census\Audiences\Audience;
use SanderMuller\Census\Audiences\AudienceResolver;
use SanderMuller\Census\Dashboards\Dashboard;
use SanderMuller\Census\Dashboards\DashboardRegistry;
use SanderMuller\Census\Dashboards\DashboardRenderer;
use SanderMuller\Census\Dashboards\StatReference;
use SanderMuller\Census\Dashboards\UserDashboard;
use SanderMuller\Census\Dashboards\UserDashboards;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Create, edit and delete for user dashboards.
 *
 * Three rules the tests pin:
 *
 * - **Whoever may open a dashboard may mutate it.** Dashboards are shared rather than owned, so a
 *   separate edit right would need a permission concept the package does not have.
 * - **A viewer may only grant audiences they belong to themselves.** Otherwise editing is a route to
 *   handing out access you do not hold.
 * - **A stored reference the editor cannot see is preserved untouched.** Dropping it would let a
 *   support edit silently destroy a developer's cards; showing it would break the gate.
 */
final readonly class ManageUserDashboardController
{
    public function __construct(
        private AudienceResolver $audiences,
        private DashboardRegistry $registry,
        private UserDashboards $store,
        private DashboardRenderer $renderer,
    ) {}

    public function create(): View
    {
        $audience = $this->audience();
        $this->assertAvailable();

        return view('census::dashboards.form', [
            'audience' => $audience,
            'dashboard' => null,
            'audienceChoices' => $this->grantableBy($audience),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $audience = $this->audience();
        $this->assertAvailable();

        $data = $this->validated($request, $audience);
        $slug = $this->uniqueSlug($data['name']);

        UserDashboard::query()->create([
            'slug' => $slug,
            'name' => $data['name'],
            'description' => $data['description'],
            'audiences' => $data['audiences'],
            'stats' => array_map(static fn (StatReference $r): array => $r->toArray(), $data['stats']),
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);

        return redirect()->route($this->routeName() . 'dashboards.show', ['dashboard' => $slug]);
    }

    public function edit(string $dashboard): View
    {
        $audience = $this->audience();
        $found = $this->mutable($dashboard, $audience);

        return view('census::dashboards.form', [
            'audience' => $audience,
            'dashboard' => $found,
            'audienceChoices' => $this->grantableBy($audience),
        ]);
    }

    public function update(Request $request, string $dashboard): RedirectResponse
    {
        $audience = $this->audience();
        $found = $this->mutable($dashboard, $audience);

        $data = $this->validated($request, $audience);

        $found->update([
            'name' => $data['name'],
            'description' => $data['description'],
            // Audiences this editor cannot grant are carried over rather than dropped, for the same
            // reason hidden references are: an edit must not quietly remove someone else's access.
            'audiences' => array_values(array_unique([
                ...$data['audiences'],
                ...array_diff($found->audiences, $this->grantableBy($audience)),
            ])),
            'stats' => $this->mergedStats($found, $data['stats'], $audience),
        ]);

        return redirect()->route($this->routeName() . 'dashboards.show', ['dashboard' => $found->slug]);
    }

    public function destroy(string $dashboard): RedirectResponse
    {
        $audience = $this->audience();
        $this->mutable($dashboard, $audience)->delete();

        return redirect()->route($this->routeName() . 'dashboards.index');
    }

    /**
     * A fixed dashboard is rejected here as well as being absent from the UI — the slug namespace is
     * shared, so a user could otherwise post its slug at these routes.
     */
    private function mutable(string $slug, Audience $audience): UserDashboard
    {
        $this->assertAvailable();

        if ($this->registry->find($slug) instanceof Dashboard) {
            throw new AccessDeniedHttpException("The dashboard [{$slug}] is defined in code and cannot be changed here.");
        }

        $found = $this->store->findOpenableBy($slug, $audience);
        if (! $found instanceof UserDashboard) {
            throw new NotFoundHttpException("No dashboard matches the slug [{$slug}].");
        }

        return $found;
    }

    /**
     * Preserves every stored reference this editor cannot see, in front of what they submitted.
     *
     * @param  list<StatReference>  $submitted
     * @return list<array<string, mixed>>
     */
    private function mergedStats(UserDashboard $found, array $submitted, Audience $audience): array
    {
        $visible = [];
        foreach ($this->grantableStats($found, $audience) as $reference) {
            $visible[$reference->key()] = true;
        }

        $hidden = array_values(array_filter(
            $found->references(),
            static fn (StatReference $r): bool => ! isset($visible[$r->key()]),
        ));

        return array_map(
            static fn (StatReference $r): array => $r->toArray(),
            [...$hidden, ...$submitted],
        );
    }

    /**
     * The stored references this editor can actually see. Anything else is theirs to leave alone.
     *
     * @return list<StatReference>
     */
    private function grantableStats(UserDashboard $found, Audience $audience): array
    {
        return array_values(array_filter(
            $found->references(),
            fn (StatReference $reference): bool => $this->renderer->canRender($reference, $audience),
        ));
    }

    /**
     * @return array{name: string, description: string|null, audiences: list<string>, stats: list<StatReference>}
     */
    private function validated(Request $request, Audience $audience): array
    {
        /** @var array{name: string, description?: string|null, audiences: list<string>, stats: list<array<string, mixed>>} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*' => ['required', 'string'],
            'stats' => ['present', 'array'],
            'stats.*.model' => ['required', 'string'],
            'stats.*.kind' => ['required', 'string'],
            'stats.*.target' => ['nullable', 'string'],
        ]);

        $grantable = $this->grantableBy($audience);
        $audiences = array_values(array_intersect($validated['audiences'], $grantable));

        if ($audiences === []) {
            throw new AccessDeniedHttpException('A dashboard may only be given an audience you belong to.');
        }

        $references = [];
        foreach ($validated['stats'] as $entry) {
            try {
                $references[] = StatReference::fromArray($entry);
            } catch (InvalidArgumentException $exception) {
                throw new AccessDeniedHttpException($exception->getMessage(), $exception);
            }
        }

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'audiences' => $audiences,
            'stats' => $references,
        ];
    }

    /**
     * A viewer may grant their own audience, and a schema-reading one may grant any configured
     * audience — they can already see everything, so they cannot widen their own reach by doing it.
     *
     * @return list<string>
     */
    private function grantableBy(Audience $audience): array
    {
        if (! $audience->readsSchema) {
            return [$audience->key];
        }

        return array_map(
            static fn (Audience $candidate): string => $candidate->key,
            $this->audiences->all(),
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::kebab($name) === '' ? 'dashboard' : Str::kebab($name);
        $slug = $base;
        $suffix = 2;

        // Fixed slugs win: a user dashboard cannot take one, so it gets the next free variant.
        while ($this->registry->find($slug) instanceof Dashboard || $this->store->slugTaken($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function audience(): Audience
    {
        $audience = $this->audiences->resolve();
        if (! $audience instanceof Audience) {
            throw new AccessDeniedHttpException('No census audience covers this user.');
        }

        return $audience;
    }

    private function assertAvailable(): void
    {
        if (! $this->store->isAvailable()) {
            throw new ServiceUnavailableHttpException(
                null,
                'User dashboards need the census migration to have run.',
            );
        }
    }

    private function routeName(): string
    {
        return (string) config('census.route.name', 'census.');
    }
}
