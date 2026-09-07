<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Introspection;

use Illuminate\Database\Eloquent\Model;
use SanderMuller\ModelStats\Attributes\PublishedColumn;
use SanderMuller\ModelStats\Audiences\Audience;
use SanderMuller\ModelStats\Enums\ColumnStatKind;

final readonly class ModelBlueprint
{
    /**
     * @param  class-string<Model>  $class
     * @param  list<ColumnFacts>  $columns
     * @param  list<RelationFacts>  $relations
     * @param  list<string>  $audiences  Audience keys a `#[ModelStats]` attribute published this to.
     * @param  array<string, PublishedColumn>  $publishedColumns  Column name => its attribute.
     */
    public function __construct(
        public string $class,
        public string $slug,
        public string $table,
        public ?string $connection,
        public ?string $createdAtColumn,
        public ?string $deletedAtColumn,
        public array $columns,
        public array $relations,
        public ?string $label = null,
        public ?string $description = null,
        public array $audiences = [],
        public array $publishedColumns = [],
    ) {}

    public function displayName(): string
    {
        return $this->label ?? class_basename($this->class);
    }

    public function isPublishedTo(Audience $audience): bool
    {
        return $audience->readsSchema || in_array($audience->key, $this->audiences, strict: true);
    }

    /**
     * The label an audience reads for a column. A developer reads the column name, because that is
     * the thing they will go and grep for; everyone else reads what the attribute published.
     */
    public function columnLabel(string $column, Audience $audience): string
    {
        if ($audience->readsSchema) {
            return $column;
        }

        return $this->publishedColumns[$column]->label ?? $column;
    }

    /**
     * @return list<ColumnFacts>
     */
    public function columnsOfKind(ColumnStatKind $kind): array
    {
        return array_values(array_filter(
            $this->columns,
            static fn (ColumnFacts $column): bool => $column->statKind === $kind,
        ));
    }

    /**
     * @return list<RelationFacts>
     */
    public function relationsOfType(string ...$types): array
    {
        return array_values(array_filter(
            $this->relations,
            static fn (RelationFacts $relation): bool => in_array($relation->type, $types, strict: true),
        ));
    }
}
