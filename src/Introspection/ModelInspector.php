<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Introspection;

use BackedEnum;
use Deprecated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SanderMuller\ModelStats\Attributes\NamesTarget;
use SanderMuller\ModelStats\Attributes\StatsExcludeColumn;
use SanderMuller\ModelStats\Attributes\StatsExcludeRelation;
use SanderMuller\ModelStats\Attributes\StatsFor;
use SanderMuller\ModelStats\Attributes\StatsForColumn;
use SanderMuller\ModelStats\Attributes\StatsIncludeColumn;
use SanderMuller\ModelStats\Attributes\StatsIncludeRelation;
use SanderMuller\ModelStats\Attributes\StatsOptions;
use SanderMuller\ModelStats\Enums\ColumnStatKind;
use SanderMuller\ModelStats\Selection\Patterns;
use SanderMuller\ModelStats\Selection\Selector;
use Throwable;

/**
 * Turns one Eloquent class into the facts the rule table needs: columns with their casts, and
 * relations with their type and foreign key. Relations are read from return types, so a model whose
 * relation methods are typed is introspected reliably and one whose are not is skipped.
 */
final readonly class ModelInspector
{
    /**
     * Schema types with a value set small enough to chart. `tinyint` covers both booleans and the
     * int-backed enums an application may store, which a cast alone misses whenever a custom inbound
     * cast hides the enum class behind a `CastsInboundAttributes` implementation.
     */
    private const array SMALL_VALUE_SET_TYPES = ['tinyint', 'enum', 'boolean', 'bool', 'set'];

    private const array BOOLEAN_CASTS = ['bool', 'boolean'];

    /**
     * @param  array{whitelist: list<array{0: string, 1: string}>, blacklist: list<array{0: string, 1: string}>}  $columnPatterns
     * @param  array{whitelist: list<array{0: string, 1: string}>, blacklist: list<array{0: string, 1: string}>}  $relationPatterns
     */
    public function __construct(
        private ModelFinder $finder,
        private array $columnPatterns = ['whitelist' => [], 'blacklist' => []],
        private array $relationPatterns = ['whitelist' => [], 'blacklist' => []],
    ) {}

    /**
     * @param  class-string<Model>  $class
     */
    public function inspect(string $class): ModelBlueprint
    {
        $model = new $class();
        $table = $model->getTable();
        $schema = Schema::connection($model->getConnectionName());

        $reflection = new ReflectionClass($class);

        $foreignKeyColumns = $this->foreignKeyColumns($schema->getForeignKeys($table));
        $columns = $this->select(
            $this->columns($model, $schema->getColumns($table), $foreignKeyColumns),
            static fn (ColumnFacts $column): string => $column->name,
            $this->columnPatterns,
            StatsIncludeColumn::class,
            StatsExcludeColumn::class,
            $reflection,
            $class,
        );

        $relations = $this->select(
            $this->relations($model),
            static fn (RelationFacts $relation): string => $relation->name,
            $this->relationPatterns,
            StatsIncludeRelation::class,
            StatsExcludeRelation::class,
            $reflection,
            $class,
        );

        $columnNames = array_map(static fn (ColumnFacts $column): string => $column->name, $columns);

        $published = $this->publishedColumns($reflection);
        $declared = ($reflection->getAttributes(StatsFor::class)[0] ?? null)?->newInstance();

        return new ModelBlueprint(
            class: $class,
            slug: $this->finder->slugFor($class),
            table: $table,
            connection: $model->getConnectionName(),
            createdAtColumn: $this->presentColumn($model->getCreatedAtColumn(), $columnNames),
            deletedAtColumn: $this->presentColumn($this->softDeleteColumn($model), $columnNames),
            columns: $columns,
            relations: $relations,
            label: $declared?->label,
            description: $declared?->description,
            audiences: array_values($declared->audiences ?? []),
            publishedColumns: $published,
            cacheMinutes: ($reflection->getAttributes(StatsOptions::class)[0] ?? null)?->newInstance()->cacheMinutes,
        );
    }

    /**
     * Runs the resolution rule over one model's columns or relations. Config contributes through
     * `[modelPattern, targetPattern]` pairs, attributes name one target each, and both feed the same
     * `Selector` the model list goes through — so the rule is stated once and applied at every level.
     *
     * @template TFact of ColumnFacts|RelationFacts
     *
     * @param  list<TFact>  $facts
     * @param  callable(TFact): string  $name
     * @param  array{whitelist: list<array{0: string, 1: string}>, blacklist: list<array{0: string, 1: string}>}  $patterns
     * @param  class-string<NamesTarget>  $includeAttribute
     * @param  class-string<NamesTarget>  $excludeAttribute
     * @param  ReflectionClass<Model>  $reflection
     * @param  class-string<Model>  $class
     * @return list<TFact>
     */
    private function select(
        array $facts,
        callable $name,
        array $patterns,
        string $includeAttribute,
        string $excludeAttribute,
        ReflectionClass $reflection,
        string $class,
    ): array {
        $candidates = array_map($name, $facts);

        $selector = new Selector(
            configWhitelist: Patterns::match($patterns['whitelist'], $class, $candidates),
            configBlacklist: Patterns::match($patterns['blacklist'], $class, $candidates),
            attributeWhitelist: $this->namedBy($reflection, $includeAttribute),
            attributeBlacklist: $this->namedBy($reflection, $excludeAttribute),
        );

        $usable = $selector->apply($candidates);

        return array_values(array_filter(
            $facts,
            static fn (ColumnFacts|RelationFacts $fact): bool => in_array($name($fact), $usable, strict: true),
        ));
    }

    /**
     * @param  ReflectionClass<Model>  $reflection
     * @param  class-string<NamesTarget>  $attribute
     * @return list<string>
     */
    private function namedBy(ReflectionClass $reflection, string $attribute): array
    {
        $names = [];
        foreach ($reflection->getAttributes($attribute) as $instance) {
            $names[] = $instance->newInstance()->target();
        }

        return $names;
    }

    /**
     * @param  ReflectionClass<Model>  $reflection
     * @return array<string, StatsForColumn>
     */
    private function publishedColumns(ReflectionClass $reflection): array
    {
        $published = [];
        foreach ($reflection->getAttributes(StatsForColumn::class) as $attribute) {
            $instance = $attribute->newInstance();
            $published[$instance->name] = $instance;
        }

        return $published;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawColumns
     * @param  list<string>  $foreignKeyColumns
     * @return list<ColumnFacts>
     */
    private function columns(Model $model, array $rawColumns, array $foreignKeyColumns): array
    {
        $casts = $model->getCasts();

        $columns = [];
        foreach ($rawColumns as $raw) {
            $name = (string) $raw['name'];
            $typeName = (string) $raw['type_name'];
            $cast = is_string($casts[$name] ?? null) ? Str::before((string) $casts[$name], ':') : null;
            $enumClass = $this->enumCastClass($cast) ?? $this->enumAccessorClass($model, $name);
            $isIdentifier = in_array($name, $foreignKeyColumns, strict: true) || str_ends_with($name, '_id');

            $columns[] = new ColumnFacts(
                name: $name,
                typeName: $typeName,
                isNullable: (bool) $raw['nullable'],
                statKind: $this->statKind($typeName, $cast, $enumClass, $isIdentifier),
                enumClass: $enumClass,
            );
        }

        return $columns;
    }

    /**
     * A boolean column costs one column in a shared aggregate; a distribution costs its own
     * `GROUP BY`. Getting that split right is what keeps a wide model to a handful of queries.
     *
     * @param  class-string<BackedEnum>|null  $enumClass
     */
    private function statKind(string $typeName, ?string $cast, ?string $enumClass, bool $isIdentifier): ColumnStatKind
    {
        if ($enumClass !== null) {
            return ColumnStatKind::Distribution;
        }

        if (in_array($cast, self::BOOLEAN_CASTS, strict: true)) {
            return ColumnStatKind::Boolean;
        }

        // An int-backed enum behind a custom inbound cast is invisible in the cast map, so the schema
        // type is the only signal left that the value set is small.
        return ! $isIdentifier && in_array($typeName, self::SMALL_VALUE_SET_TYPES, strict: true)
            ? ColumnStatKind::Distribution
            : ColumnStatKind::None;
    }

    /**
     * A cast may carry arguments (`decimal:2`, `App\Casts\Money:EUR`); only the class matters here.
     *
     * @return class-string<BackedEnum>|null
     */
    private function enumCastClass(?string $cast): ?string
    {
        if ($cast === null) {
            return null;
        }

        return enum_exists($cast) && is_subclass_of($cast, BackedEnum::class) ? $cast : null;
    }

    /**
     * The second place an enum hides. An inbound-only cast keeps the enum class out of the cast map,
     * but a matching `get{Column}Attribute(): SomeEnum` accessor still names it — so a distribution
     * shows case names rather than the raw stored integers.
     *
     * @return class-string<BackedEnum>|null
     */
    private function enumAccessorClass(Model $model, string $column): ?string
    {
        $method = 'get' . Str::studly($column) . 'Attribute';
        if (! method_exists($model, $method)) {
            return null;
        }

        $returnType = new ReflectionMethod($model, $method)->getReturnType();
        if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            return null;
        }

        $type = $returnType->getName();

        return enum_exists($type) && is_subclass_of($type, BackedEnum::class) ? $type : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $foreignKeys
     * @return list<string>
     */
    private function foreignKeyColumns(array $foreignKeys): array
    {
        $columns = [];
        foreach ($foreignKeys as $foreignKey) {
            foreach ((array) $foreignKey['columns'] as $column) {
                $columns[] = (string) $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return list<RelationFacts>
     */
    private function relations(Model $model): array
    {
        $relations = [];
        foreach (new ReflectionClass($model)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->isStatic() || $this->isDeprecated($method)) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
                continue;
            }

            $type = $returnType->getName();
            if (! is_subclass_of($type, Relation::class)) {
                continue;
            }

            $facts = $this->describeRelation($model, $method, $type);
            if ($facts instanceof RelationFacts) {
                $relations[] = $facts;
            }
        }

        usort($relations, static fn (RelationFacts $a, RelationFacts $b): int => $a->name <=> $b->name);

        return $relations;
    }

    /**
     * Building a relation runs no query, but it can read attributes the empty model does not have,
     * so a relation that cannot be built is dropped rather than failing the whole inspection.
     */
    private function describeRelation(Model $model, ReflectionMethod $method, string $type): ?RelationFacts
    {
        try {
            $relation = $method->invoke($model);
        } catch (Throwable) {
            return null;
        }

        if (! $relation instanceof Relation) {
            return null;
        }

        return new RelationFacts(
            name: $method->getName(),
            type: class_basename($type),
            relatedClass: $relation instanceof MorphTo ? null : $relation->getRelated()::class,
            foreignKeyName: method_exists($relation, 'getForeignKeyName') ? $relation->getForeignKeyName() : null,
            morphTypeColumn: $relation instanceof MorphTo ? $relation->getMorphType() : null,
            pivotTable: $relation instanceof BelongsToMany ? $relation->getTable() : null,
        );
    }

    /**
     * Reflection calls the method, which fires the deprecation notice a normal reader never sees.
     * A relation on its way out is not worth a stat, so it is skipped rather than silenced.
     */
    private function isDeprecated(ReflectionMethod $method): bool
    {
        if ($method->getAttributes(Deprecated::class) !== []) {
            return true;
        }

        $docComment = $method->getDocComment();

        return $docComment !== false && str_contains($docComment, '@deprecated');
    }

    private function softDeleteColumn(Model $model): ?string
    {
        return method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : null;
    }

    /**
     * @param  list<string>  $columnNames
     */
    private function presentColumn(?string $column, array $columnNames): ?string
    {
        return $column !== null && in_array($column, $columnNames, strict: true) ? $column : null;
    }
}
