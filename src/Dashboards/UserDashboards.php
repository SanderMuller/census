<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Dashboards;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Schema;
use SanderMuller\ModelStats\Audiences\Audience;
use Throwable;

/**
 * The user-dashboard store, and the one place that knows the table may not exist.
 *
 * Publishing a migration does not mean it ran. A host that installs the package and never migrates
 * still gets the fixed dashboards and the index; only creating a user dashboard is unavailable. That
 * degradation lives here rather than in every caller.
 */
final class UserDashboards
{
    private ?bool $available = null;

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            return $this->available = Schema::hasTable((new UserDashboard())->getTable());
        } catch (Throwable) {
            return $this->available = false;
        }
    }

    /**
     * @return list<UserDashboard>
     */
    public function openableBy(Audience $audience): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        return array_values(array_filter(
            UserDashboard::query()->orderBy('name')->get()->all(),
            static fn (UserDashboard $dashboard): bool => in_array($audience->key, $dashboard->audiences, strict: true),
        ));
    }

    public function findOpenableBy(string $slug, Audience $audience): ?UserDashboard
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $dashboard = UserDashboard::query()->where('slug', $slug)->first();

        if (! $dashboard instanceof UserDashboard) {
            return null;
        }

        return in_array($audience->key, $dashboard->audiences, strict: true) ? $dashboard : null;
    }

    public function slugTaken(string $slug, ?int $ignoreId = null): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return UserDashboard::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, static fn (EloquentBuilder $query): EloquentBuilder => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
