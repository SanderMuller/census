<?php declare(strict_types=1);

namespace SanderMuller\Census\Audiences;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use SanderMuller\Census\Attributes\StatsFor;
use SanderMuller\Census\Introspection\ColumnFacts;
use SanderMuller\Census\Introspection\ModelBlueprint;
use SanderMuller\Census\Introspection\ModelFinder;
use SanderMuller\Census\Introspection\ModelReference;

/**
 * Narrows what an audience may read, before a single stat is calculated.
 *
 * Filtering here rather than in the view is deliberate: a stat that never gets computed cannot leak
 * through a cache key, an export, or a template someone edits later. A non-developer audience sees
 * only what an attribute published to it, so a new model or column is invisible to them by default.
 */
final readonly class AudienceGate
{
    public function __construct(private ModelFinder $finder) {}

    /**
     * @return array<string, ModelReference>
     */
    public function models(Audience $audience): array
    {
        if ($audience->readsSchema) {
            return $this->finder->all();
        }

        return array_filter(
            $this->finder->all(),
            fn (ModelReference $reference): bool => $this->publishesTo($reference->class, $audience),
        );
    }

    public function canSee(ModelBlueprint $blueprint, Audience $audience): bool
    {
        return $blueprint->isPublishedTo($audience);
    }

    /**
     * Relations expose data-integrity questions — orphans, rows without children — and read across
     * into another table. They stay with the audience that reads schema.
     */
    public function restrict(ModelBlueprint $blueprint, Audience $audience): ModelBlueprint
    {
        if ($audience->readsSchema) {
            return $blueprint;
        }

        $columns = array_values(array_filter(
            $blueprint->columns,
            static fn (ColumnFacts $column): bool => in_array(
                $audience->key,
                $blueprint->publishedColumns[$column->name]->audiences ?? [],
                strict: true,
            ),
        ));

        // The creation trend is a stat over `created_at`, so it needs `created_at` published like any
        // other column. Passing it through would hand this audience a stat nobody opted in.
        $publishedCreatedAt = $blueprint->createdAtColumn !== null
            && in_array($audience->key, $blueprint->publishedColumns[$blueprint->createdAtColumn]->audiences ?? [], strict: true);

        return new ModelBlueprint(
            class: $blueprint->class,
            slug: $blueprint->slug,
            table: $blueprint->table,
            connection: $blueprint->connection,
            createdAtColumn: $publishedCreatedAt ? $blueprint->createdAtColumn : null,
            deletedAtColumn: null,
            columns: $columns,
            relations: [],
            label: $blueprint->label,
            description: $blueprint->description,
            audiences: $blueprint->audiences,
            publishedColumns: $blueprint->publishedColumns,
        );
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function publishesTo(string $class, Audience $audience): bool
    {
        $declared = $this->declaration($class);

        return $declared instanceof StatsFor
            && in_array($audience->key, $declared->audiences, strict: true);
    }

    /**
     * The name an audience reads in the model list. A developer reads the class, because that is what
     * they will open; everyone else reads what the attribute published.
     *
     * @param  class-string<Model>  $class
     */
    public function nameFor(string $class, Audience $audience): string
    {
        if ($audience->readsSchema) {
            return $class;
        }

        $declared = $this->declaration($class);

        return $declared instanceof StatsFor && $declared->label !== null
            ? $declared->label
            : class_basename($class);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function declaration(string $class): ?StatsFor
    {
        $attributes = new ReflectionClass($class)->getAttributes(StatsFor::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
