<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Dashboards;

use LogicException;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Discovers the code-defined dashboards, the way `ModelFinder` discovers models.
 *
 * Fixed and user dashboards share one slug namespace because they share one listing and one show
 * route. Two fixed dashboards on one slug is a configuration error raised here rather than resolved
 * silently — whichever won would depend on filesystem order.
 */
final class DashboardRegistry
{
    /** @var array<string, Dashboard>|null */
    private ?array $dashboards = null;

    /**
     * @param  array<string, string>  $sourceRoots  Namespace prefix => directory holding those classes.
     */
    public function __construct(private readonly array $sourceRoots) {}

    /**
     * Slug => dashboard.
     *
     * @return array<string, Dashboard>
     */
    public function all(): array
    {
        if ($this->dashboards !== null) {
            return $this->dashboards;
        }

        $dashboards = [];
        foreach ($this->candidateClasses() as $class) {
            if (! is_subclass_of($class, Dashboard::class) || new ReflectionClass($class)->isAbstract()) {
                continue;
            }

            $dashboard = new $class();
            $slug = $dashboard->slug();

            if (isset($dashboards[$slug])) {
                throw new LogicException(
                    "Two dashboards declare the slug [{$slug}]: [" . $dashboards[$slug]::class . "] and [{$class}]."
                );
            }

            $dashboards[$slug] = $dashboard;
        }

        return $this->dashboards = $dashboards;
    }

    public function find(string $slug): ?Dashboard
    {
        return $this->all()[$slug] ?? null;
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
                $class = $prefix . str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }
}
