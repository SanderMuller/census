<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Audiences;

use Illuminate\Support\Facades\Gate;

/**
 * Decides which audience the current viewer belongs to. Audiences are declared in configuration and
 * checked in order, so the host application controls both who exists and who ranks above whom.
 */
final readonly class AudienceResolver
{
    /**
     * @param  array<string, array<string, mixed>>  $configured
     */
    public function __construct(private array $configured) {}

    /**
     * The first audience whose ability the viewer passes. Null means this viewer reads nothing, which
     * callers must treat as a denial rather than as "show the default".
     */
    public function resolve(): ?Audience
    {
        foreach ($this->all() as $audience) {
            if ($audience->ability === null || Gate::allows($audience->ability)) {
                return $audience;
            }
        }

        return null;
    }

    public function find(string $key): ?Audience
    {
        foreach ($this->all() as $audience) {
            if ($audience->key === $key) {
                return $audience;
            }
        }

        return null;
    }

    /**
     * @return list<Audience>
     */
    public function all(): array
    {
        $audiences = [];
        foreach ($this->configured as $key => $settings) {
            $audiences[] = new Audience(
                key: (string) $key,
                label: (string) ($settings['label'] ?? $key),
                readsSchema: (bool) ($settings['reads_schema'] ?? false),
                ability: isset($settings['ability']) ? (string) $settings['ability'] : null,
            );
        }

        return $audiences;
    }
}
