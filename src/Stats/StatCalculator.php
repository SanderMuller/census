<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Stats;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use SanderMuller\ModelStats\Enums\ColumnStatKind;
use SanderMuller\ModelStats\Introspection\ColumnFacts;
use SanderMuller\ModelStats\Introspection\ModelBlueprint;
use SanderMuller\ModelStats\Introspection\RelationFacts;
use Throwable;

/**
 * The rule table: which stat each schema signal earns. Nothing here is configured per model —
 * a column's cast or a relation's type decides the stat, the same way for all 141 models.
 *
 * Global scopes stay on so a count matches what the application itself would read; only the
 * soft-delete scope is lifted, because "how many rows are trashed" is one of the stats.
 */
final readonly class StatCalculator
{
    public const string GROUP_TABLE = 'Table';

    public const string GROUP_COLUMNS = 'Columns';

    public const string GROUP_RELATIONS = 'Relations';

    public function __construct(
        private int $queryTimeoutMs = 3000,
        private int $breakdownLimit = 25,
    ) {}

    /**
     * @return list<Stat>
     */
    public function calculate(ModelBlueprint $blueprint): array
    {
        return $this->withQueryTimeout($blueprint, function () use ($blueprint): array {
            $total = $this->count($blueprint, static fn (EloquentQueryBuilder $query): EloquentQueryBuilder => $query);

            return [
                ...$this->tableStats($blueprint, $total),
                ...$this->columnStats($blueprint),
                ...$this->relationStats($blueprint, $total->value),
            ];
        });
    }

    /**
     * @return list<Stat>
     */
    private function tableStats(ModelBlueprint $blueprint, Stat $total): array
    {
        $stats = [new Stat(self::GROUP_TABLE, 'Total rows', $total->value, note: $total->note)];

        if ($blueprint->createdAtColumn !== null) {
            foreach ([7, 30] as $days) {
                $column = $blueprint->createdAtColumn;
                $stats[] = $this->count(
                    $blueprint,
                    static fn (EloquentQueryBuilder $query): EloquentQueryBuilder => $query->where($column, '>=', now()->subDays($days)),
                    label: "Created in the last {$days} days",
                );
            }
        }

        if ($blueprint->deletedAtColumn !== null) {
            $column = $blueprint->deletedAtColumn;
            $stats[] = $this->count(
                $blueprint,
                static fn (EloquentQueryBuilder $query): EloquentQueryBuilder => $query->whereNotNull($column),
                label: 'Soft deleted',
            );
        }

        return $stats;
    }

    /**
     * @return list<Stat>
     */
    private function columnStats(ModelBlueprint $blueprint): array
    {
        $stats = [];
        foreach ($blueprint->columnsOfKind(ColumnStatKind::Distribution) as $column) {
            $stats[] = $this->distribution(
                $blueprint,
                $column->name,
                self::GROUP_COLUMNS,
                $column->name,
                $column->enumClass,
            );
        }

        // A column holding one value everywhere says nothing; sorting by how many distinct values
        // it actually carries puts the columns worth reading at the top of the grid.
        usort($stats, static fn (Stat $a, Stat $b): int => count($b->breakdown) <=> count($a->breakdown));

        $booleans = $blueprint->columnsOfKind(ColumnStatKind::Boolean);
        if ($booleans !== []) {
            $stats[] = $this->booleanRollup($blueprint, $booleans);
        }

        return $stats;
    }

    /**
     * One aggregate over every boolean column. `Video` has 55 of them — a `GROUP BY` each would
     * be 55 scans of the same table for information a single row of SUMs already carries.
     *
     * @param  list<ColumnFacts>  $columns
     */
    private function booleanRollup(ModelBlueprint $blueprint, array $columns): Stat
    {
        $label = 'Boolean columns set to true';

        $selects = array_map(
            static fn (ColumnFacts $column): string => 'sum(case when `' . $column->name . '` = 1 then 1 else 0 end) as `' . $column->name . '`',
            $columns,
        );

        try {
            $row = $this->baseQuery($blueprint)->selectRaw(implode(', ', $selects))->first();
        } catch (Throwable $throwable) {
            return Stat::unavailable(self::GROUP_COLUMNS, $label, $this->reason($throwable));
        }

        if ($row === null) {
            return Stat::unavailable(self::GROUP_COLUMNS, $label, 'No rows.');
        }

        $values = (array) $row;

        $breakdown = [];
        foreach ($columns as $column) {
            $breakdown[] = ['label' => $column->name, 'count' => (int) ($values[$column->name] ?? 0)];
        }

        usort($breakdown, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return new Stat(self::GROUP_COLUMNS, $label, breakdown: $breakdown);
    }

    /**
     * @return list<Stat>
     */
    private function relationStats(ModelBlueprint $blueprint, ?int $total): array
    {
        $stats = [];
        foreach ($blueprint->relations as $relation) {
            $stat = match ($relation->type) {
                'BelongsTo' => $this->belongsToStat($blueprint, $relation),
                'HasMany', 'HasOne', 'MorphMany', 'MorphOne' => $this->hasManyStat($blueprint, $relation, $total),
                'BelongsToMany', 'MorphToMany' => $this->belongsToManyStat($blueprint, $relation),
                'MorphTo' => $this->morphToStat($blueprint, $relation),
                default => null,
            };

            if ($stat instanceof Stat) {
                $stats[] = $stat;
            }
        }

        // A model like `Video` has 29 relations and most read zero on a given database. Sorting by
        // the largest number in the card floats the ones carrying data to the top of the grid.
        usort($stats, static fn (Stat $a, Stat $b): int => $b->signal() <=> $a->signal());

        return $stats;
    }

    private function belongsToStat(ModelBlueprint $blueprint, RelationFacts $relation): ?Stat
    {
        if ($relation->foreignKeyName === null) {
            return null;
        }

        $foreignKey = $relation->foreignKeyName;
        $name = $relation->name;

        $missing = $this->count(
            $blueprint,
            static fn (EloquentQueryBuilder $query): EloquentQueryBuilder => $query->whereNull($foreignKey),
        );

        $orphaned = $this->count(
            $blueprint,
            static fn (EloquentQueryBuilder $query): EloquentQueryBuilder => $query
                ->whereNotNull($foreignKey)
                ->whereDoesntHave($name),
        );

        return new Stat(
            group: self::GROUP_RELATIONS,
            label: "{$name} — belongs to",
            breakdown: [
                ['label' => "orphaned ({$foreignKey} points at nothing)", 'count' => $orphaned->value ?? 0],
                ['label' => "no {$foreignKey}", 'count' => $missing->value ?? 0],
            ],
            note: $orphaned->note ?? $missing->note,
        );
    }

    private function hasManyStat(ModelBlueprint $blueprint, RelationFacts $relation, ?int $total): Stat
    {
        $name = $relation->name;

        $childless = $this->count(
            $blueprint,
            static fn (EloquentQueryBuilder $query): EloquentQueryBuilder => $query->whereDoesntHave($name),
        );

        if ($childless->value === null) {
            return Stat::unavailable(self::GROUP_RELATIONS, "{$name} — has many", (string) $childless->note);
        }

        return new Stat(
            group: self::GROUP_RELATIONS,
            label: "{$name} — has many",
            breakdown: [
                ['label' => 'with at least one', 'count' => max(0, ($total ?? 0) - $childless->value)],
                ['label' => 'with none', 'count' => $childless->value],
            ],
        );
    }

    private function belongsToManyStat(ModelBlueprint $blueprint, RelationFacts $relation): ?Stat
    {
        if ($relation->pivotTable === null) {
            return null;
        }

        $label = "{$relation->name} — pivot rows in {$relation->pivotTable}";

        try {
            $value = DB::connection($blueprint->connection)->table($relation->pivotTable)->count();
        } catch (Throwable $throwable) {
            return Stat::unavailable(self::GROUP_RELATIONS, $label, $this->reason($throwable));
        }

        return new Stat(self::GROUP_RELATIONS, $label, $value);
    }

    private function morphToStat(ModelBlueprint $blueprint, RelationFacts $relation): ?Stat
    {
        if ($relation->morphTypeColumn === null) {
            return null;
        }

        return $this->distribution(
            $blueprint,
            $relation->morphTypeColumn,
            self::GROUP_RELATIONS,
            "{$relation->name} — by {$relation->morphTypeColumn}",
        );
    }

    /**
     * @param callable(EloquentQueryBuilder<Model>):EloquentQueryBuilder<Model> $constrain
     */
    private function count(
        ModelBlueprint $blueprint,
        callable $constrain,
        string $group = self::GROUP_TABLE,
        string $label = 'Total rows',
    ): Stat {
        try {
            return new Stat($group, $label, $constrain($this->query($blueprint))->count());
        } catch (Throwable $throwable) {
            return Stat::unavailable($group, $label, $this->reason($throwable));
        }
    }

    /**
     * @param  class-string<BackedEnum>|null  $enumClass
     */
    private function distribution(
        ModelBlueprint $blueprint,
        string $column,
        string $group,
        string $label,
        ?string $enumClass = null,
    ): Stat {
        try {
            $rows = $this->baseQuery($blueprint)
                ->select($column, DB::raw('count(*) as aggregate'))
                ->groupBy($column)
                ->orderByDesc('aggregate')
                ->limit($this->breakdownLimit + 1)
                ->get();
        } catch (Throwable $throwable) {
            return Stat::unavailable($group, $label, $this->reason($throwable));
        }

        $truncated = $rows->count() > $this->breakdownLimit;

        $breakdown = $rows
            ->take($this->breakdownLimit)
            ->map(function (object $row) use ($column, $enumClass): array {
                $values = (array) $row;

                return [
                    'label' => $this->valueLabel($values[$column] ?? null, $enumClass),
                    'count' => (int) ($values['aggregate'] ?? 0),
                ];
            })
            ->all();

        return new Stat(
            group: $group,
            label: $label,
            breakdown: array_values($breakdown),
            note: $truncated ? 'More than ' . $this->breakdownLimit . ' distinct values — showing the largest.' : null,
        );
    }

    /**
     * @param  class-string<BackedEnum>|null  $enumClass
     */
    private function valueLabel(mixed $value, ?string $enumClass): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($value instanceof BackedEnum) {
            return $value->name;
        }

        if ($enumClass !== null && (is_int($value) || is_string($value))) {
            $case = $enumClass::tryFrom($value);

            return $case instanceof BackedEnum ? $case->name : (string) $value;
        }

        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }

    /**
     * @return EloquentQueryBuilder<Model>
     */
    private function query(ModelBlueprint $blueprint): EloquentQueryBuilder
    {
        return $blueprint->class::query()->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Aggregates read through the base query on purpose. Hydrating a model from a partial select
     * hands the accessors a row without the columns they read, and an application may have models whose
     * accessors then fall back to a relation — which `preventLazyLoading` turns into an exception.
     */
    private function baseQuery(ModelBlueprint $blueprint): QueryBuilder
    {
        return $this->query($blueprint)->toBase();
    }

    /**
     * Caps every read at three seconds so one unindexed `whereDoesntHave` against a table with
     * tens of millions of rows degrades to a single skipped stat instead of a dead request.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $calculate
     * @return TReturn
     */
    private function withQueryTimeout(ModelBlueprint $blueprint, callable $calculate): mixed
    {
        $connection = DB::connection($blueprint->connection);

        if ($connection->getDriverName() !== 'mysql') {
            return $calculate();
        }

        $connection->statement('SET SESSION max_execution_time = ' . $this->queryTimeoutMs);

        try {
            return $calculate();
        } finally {
            $connection->statement('SET SESSION max_execution_time = 0');
        }
    }

    private function reason(Throwable $e): string
    {
        return str_contains($e->getMessage(), 'maximum statement execution time')
            ? 'Skipped — slower than ' . $this->queryTimeoutMs . 'ms.'
            : class_basename($e) . ': ' . $e->getMessage();
    }
}
