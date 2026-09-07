<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Introspection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use SanderMuller\ModelStats\Attributes\StatsExclude;
use SanderMuller\ModelStats\Attributes\StatsInclude;
use SanderMuller\ModelStats\Selection\Selector;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Discovers every concrete Eloquent model in the configured source roots that is backed by a real
 * table. The roots are configuration rather than a constant so a host application can keep its models
 * wherever it likes — `app/Models` is only the Laravel default.
 */
final class ModelFinder
{
    /** @var array<string, ModelReference>|null */
    private ?array $models = null;

    /**
     * @param  array<string, string>  $sourceRoots  Namespace prefix => directory holding those classes.
     * @param  list<string>  $whitelist  Model classes config whitelists.
     * @param  list<string>  $blacklist  Model classes config blacklists.
     */
    public function __construct(
        private readonly array $sourceRoots,
        private readonly array $whitelist = [],
        private readonly array $blacklist = [],
    ) {}

    /**
     * Slug => reference, sorted by slug.
     *
     * @return array<string, ModelReference>
     */
    public function all(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }

        // One listing for all of them. Asking `Schema::hasTable()` per model was 99 queries.
        $tables = array_flip(Schema::getTableListing(schemaQualified: false));

        $candidates = array_values(array_filter(
            $this->candidateClasses(),
            static fn (string $class): bool => is_subclass_of($class, Model::class),
        ));

        $models = [];
        foreach ($this->selectorFor($candidates)->apply($candidates) as $class) {
            /** @var class-string<Model> $class */
            $reference = $this->reference($class, $tables);
            if ($reference instanceof ModelReference) {
                $models[$reference->slug] = $reference;
            }
        }

        ksort($models);

        return $this->models = $models;
    }

    public function findBySlug(string $slug): ?ModelReference
    {
        return $this->all()[$slug] ?? null;
    }

    /**
     * @param  class-string<Model>  $class
     */
    public function slugFor(string $class): string
    {
        $relative = $class;
        foreach (array_keys($this->sourceRoots) as $prefix) {
            if (str_starts_with($class, $prefix)) {
                $relative = Str::after($class, $prefix);

                break;
            }
        }

        return collect(explode('\\', $relative))
            ->map(static fn (string $segment): string => Str::kebab($segment))
            ->implode('.');
    }

    /**
     * Config and attributes both feed one `Selector`. The attribute side is read here rather than in
     * `ModelInspector` because selection decides which models get inspected at all.
     *
     * @param  list<class-string<Model>>  $candidates
     */
    private function selectorFor(array $candidates): Selector
    {
        $whitelist = [];
        $blacklist = [];

        foreach ($candidates as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->getAttributes(StatsInclude::class) !== []) {
                $whitelist[] = $class;
            }

            if ($reflection->getAttributes(StatsExclude::class) !== []) {
                $blacklist[] = $class;
            }
        }

        return new Selector(
            configWhitelist: array_values($this->whitelist),
            configBlacklist: array_values($this->blacklist),
            attributeWhitelist: $whitelist,
            attributeBlacklist: $blacklist,
        );
    }

    /**
     * @return list<class-string>
     */
    private function candidateClasses(): array
    {
        $classes = [];
        foreach ($this->sourceRoots as $prefix => $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
                $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
                $class = $prefix . $relative;

                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * A model earns a place only when it can be instantiated and its table exists. Sushi models
     * and base classes without a table would otherwise blow up every stat query on the page.
     *
     * @param  class-string<Model>  $class
     * @param  array<string, int>  $tables
     */
    private function reference(string $class, array $tables): ?ModelReference
    {
        if (new ReflectionClass($class)->isAbstract()) {
            return null;
        }

        try {
            $model = new $class();
            $table = $model->getTable();
            $connection = $model->getConnectionName();

            $exists = $connection === null
                ? isset($tables[$table])
                : Schema::connection($connection)->hasTable($table);
        } catch (Throwable) {
            return null;
        }

        return $exists ? new ModelReference($class, $this->slugFor($class), $table) : null;
    }
}
