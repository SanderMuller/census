<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Dashboards;

use Illuminate\Database\Eloquent\Model;
use SanderMuller\ModelStats\Audiences\Audience;
use SanderMuller\ModelStats\Audiences\AudienceGate;
use SanderMuller\ModelStats\Enums\ColumnStatKind;
use SanderMuller\ModelStats\Introspection\ColumnFacts;
use SanderMuller\ModelStats\Introspection\ModelBlueprint;
use SanderMuller\ModelStats\Introspection\ModelFinder;
use SanderMuller\ModelStats\Introspection\ModelInspector;
use SanderMuller\ModelStats\Introspection\RelationFacts;
use SanderMuller\ModelStats\Stats\Stat;
use SanderMuller\ModelStats\Stats\StatCalculator;
use Throwable;

/**
 * Turns a dashboard's references into stats for one viewer.
 *
 * Three rules are load-bearing:
 *
 * - **A reference outside the viewer's audience is dropped before anything is computed.** A withheld
 *   stat therefore costs no query and cannot leak through a cache key.
 * - **A stale reference is omitted, not raised.** A model, column or relation can stop being usable
 *   after a dashboard names it — a blacklist entry, a dropped column — and that must not break a page.
 * - **The page carries one budget.** Several models on one page would otherwise multiply the per-stat
 *   cap. When the budget is spent the remaining cards render as skipped, and a partial result is never
 *   cached, so a slow page retries rather than freezing its own failure.
 */
final readonly class DashboardRenderer
{
    public function __construct(
        private ModelFinder $finder,
        private ModelInspector $inspector,
        private StatCalculator $calculator,
        private AudienceGate $gate,
        private int $budgetMs = 15000,
    ) {}

    /**
     * @param  list<StatReference>  $references
     */
    public function render(array $references, Audience $audience): RenderedDashboard
    {
        $cards = [];
        $complete = true;
        $withheld = false;
        $startedAt = microtime(true);

        foreach ($references as $reference) {
            $blueprint = $this->blueprintFor($reference, $audience);
            if (! $blueprint instanceof ModelBlueprint) {
                $withheld = $withheld || $this->isWithheldFrom($reference, $audience);

                continue;
            }

            if ($this->spentMs($startedAt) >= $this->budgetMs) {
                $complete = false;
                $cards[] = Stat::unavailable(
                    $this->group($reference),
                    $this->label($reference),
                    'Skipped — the page ran out of its query budget.',
                );

                continue;
            }

            $stat = $this->statFor($reference, $blueprint);
            if ($stat instanceof Stat) {
                $cards[] = $stat;
            }
        }

        return new RenderedDashboard($cards, $complete, $withheld);
    }

    /**
     * Whether this reference would produce a card for this viewer. Callers that need the decision
     * without paying for the stat — the edit screen, deciding which references to preserve — ask here
     * rather than reimplementing it.
     */
    public function canRender(StatReference $reference, Audience $audience): bool
    {
        return $this->blueprintFor($reference, $audience) instanceof ModelBlueprint;
    }

    /**
     * Null means this reference contributes nothing: the model is no longer usable, this audience
     * cannot see it, or the named column or relation has gone.
     */
    private function blueprintFor(StatReference $reference, Audience $audience): ?ModelBlueprint
    {
        // Selection first. Inspecting the class directly would route straight around the model
        // blacklist, and "named there, gone everywhere" is the one guarantee the rule makes.
        if (! $this->isUsable($reference->modelClass)) {
            return null;
        }

        try {
            $blueprint = $this->inspector->inspect($reference->modelClass);
        } catch (Throwable) {
            return null;
        }

        if (! $this->gate->canSee($blueprint, $audience)) {
            return null;
        }

        $blueprint = $this->gate->restrict($blueprint, $audience);

        return $this->targetSurvives($reference, $blueprint) ? $blueprint : null;
    }

    /**
     * Distinguishes the two reasons a reference produced nothing. Only an audience refusal is an
     * access signal; a globally excluded model leaves the dashboard genuinely empty instead.
     */
    private function isWithheldFrom(StatReference $reference, Audience $audience): bool
    {
        if (! $this->isUsable($reference->modelClass)) {
            return false;
        }

        try {
            $blueprint = $this->inspector->inspect($reference->modelClass);
        } catch (Throwable) {
            return false;
        }

        return ! $this->gate->canSee($blueprint, $audience)
            || ! $this->targetSurvives($reference, $this->gate->restrict($blueprint, $audience));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function isUsable(string $modelClass): bool
    {
        foreach ($this->finder->all() as $reference) {
            if ($reference->class === $modelClass) {
                return true;
            }
        }

        return false;
    }

    private function targetSurvives(StatReference $reference, ModelBlueprint $blueprint): bool
    {
        return match ($reference->kind) {
            StatKind::Total => true,
            StatKind::Booleans => $blueprint->columnsOfKind(ColumnStatKind::Boolean) !== [],
            StatKind::Column => $this->hasColumn($blueprint, (string) $reference->target),
            StatKind::Relation => $this->hasRelation($blueprint, (string) $reference->target),
        };
    }

    private function hasColumn(ModelBlueprint $blueprint, string $name): bool
    {
        foreach ($blueprint->columns as $column) {
            if ($column->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function hasRelation(ModelBlueprint $blueprint, string $name): bool
    {
        foreach ($blueprint->relations as $relation) {
            if ($relation->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reuses the per-model calculation, then picks the one card this reference asked for. That keeps
     * one rule table rather than a second one for dashboards, and it means a model's own
     * `cacheMinutes` applies here too, because caching lives in the calculator.
     */
    private function statFor(StatReference $reference, ModelBlueprint $blueprint): ?Stat
    {
        $stats = $this->calculator->calculate($this->narrow($reference, $blueprint));

        $wanted = $this->label($reference);
        foreach ($stats as $stat) {
            if ($stat->label === $wanted || str_starts_with($stat->label, $wanted)) {
                return $stat;
            }
        }

        return $stats[0] ?? null;
    }

    /**
     * Narrows the blueprint to just what this reference needs, so asking for one column does not
     * compute every stat the model has.
     */
    private function narrow(StatReference $reference, ModelBlueprint $blueprint): ModelBlueprint
    {
        $columns = match ($reference->kind) {
            StatKind::Column => array_values(array_filter(
                $blueprint->columns,
                static fn (ColumnFacts $column): bool => $column->name === $reference->target,
            )),
            StatKind::Booleans => $blueprint->columnsOfKind(ColumnStatKind::Boolean),
            StatKind::Total, StatKind::Relation => [],
        };

        $relations = $reference->kind === StatKind::Relation
            ? array_values(array_filter(
                $blueprint->relations,
                static fn (RelationFacts $relation): bool => $relation->name === $reference->target,
            ))
            : [];

        return new ModelBlueprint(
            class: $blueprint->class,
            slug: $blueprint->slug,
            table: $blueprint->table,
            connection: $blueprint->connection,
            createdAtColumn: null,
            deletedAtColumn: null,
            columns: $columns,
            relations: $relations,
            label: $blueprint->label,
            description: $blueprint->description,
            audiences: $blueprint->audiences,
            publishedColumns: $blueprint->publishedColumns,
            cacheMinutes: $blueprint->cacheMinutes,
        );
    }

    private function label(StatReference $reference): string
    {
        return match ($reference->kind) {
            StatKind::Total => 'Total rows',
            StatKind::Booleans => 'Boolean columns set to true',
            StatKind::Column, StatKind::Relation => (string) $reference->target,
        };
    }

    private function group(StatReference $reference): string
    {
        return match ($reference->kind) {
            StatKind::Total => StatCalculator::GROUP_TABLE,
            StatKind::Column, StatKind::Booleans => StatCalculator::GROUP_COLUMNS,
            StatKind::Relation => StatCalculator::GROUP_RELATIONS,
        };
    }

    private function spentMs(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }
}
