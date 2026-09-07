<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Dashboards;

use Illuminate\Support\Str;

/**
 * A dashboard defined in code. It ships with a deploy and cannot be edited or deleted in the
 * application, which is the whole difference between it and a user dashboard.
 */
abstract class Dashboard
{
    /**
     * @return list<StatReference>
     */
    abstract public function stats(): array;

    /**
     * Which audiences may open this. An empty list means **nobody** — the dashboard fails closed, so
     * forgetting to declare audiences hides it rather than exposing it.
     *
     * @return list<string>
     */
    public function audiences(): array
    {
        return [];
    }

    public function name(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public function slug(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    public function description(): ?string
    {
        return null;
    }
}
