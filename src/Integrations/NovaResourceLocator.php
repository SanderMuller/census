<?php declare(strict_types=1);

namespace SanderMuller\Census\Integrations;

use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Resource;
use ReflectionClass;
use Throwable;

/**
 * Links a model to the Nova resource that administers it, so a number on the dashboard leads to
 * the rows behind it.
 *
 * Nova only registers its resources while it is serving a request, so `Nova::resourceForModel()`
 * answers nothing here. Loading all of them to build a map would cost ~30 classes for one link, so
 * the resource is guessed by name and then confirmed against its own `$model` — exact, and one
 * class load.
 */
final readonly class NovaResourceLocator
{
    /**
     * @param  list<string>  $namespaces  Where the host application keeps its Nova resources.
     */
    public function __construct(
        private array $namespaces,
        private string $novaPath,
    ) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function urlFor(string $modelClass): ?string
    {
        if ($this->namespaces === [] || ! class_exists(Resource::class)) {
            return null;
        }

        $uriKey = $this->uriKeyFor($modelClass);

        return $uriKey === null
            ? null
            : rtrim($this->novaPath, '/') . '/resources/' . $uriKey;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function uriKeyFor(string $modelClass): ?string
    {
        $basename = class_basename($modelClass);

        foreach ($this->namespaces as $namespace) {
            $candidate = $namespace . $basename;

            if (! class_exists($candidate) || ! is_subclass_of($candidate, Resource::class)) {
                continue;
            }

            $reflection = new ReflectionClass($candidate);

            try {
                $resourceModel = $reflection->getStaticPropertyValue('model');
                if ($resourceModel !== $modelClass) {
                    continue;
                }

                $uriKey = $reflection->getMethod('uriKey')->invoke(null);
            } catch (Throwable) {
                continue;
            }

            if (is_string($uriKey)) {
                return $uriKey;
            }
        }

        return null;
    }
}
