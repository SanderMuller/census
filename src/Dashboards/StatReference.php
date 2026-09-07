<?php declare(strict_types=1);

namespace SanderMuller\Census\Dashboards;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Names one stat without holding it. Three plain fields, so the PHP path and the database path
 * serialise identically — a dashboard defined in a class and one stored as JSON are the same shape.
 *
 * A reference is *malformed* when it could never be valid (unknown kind, a target on `total`), and
 * *stale* when it was valid once and its target has since gone. The two are handled differently:
 * malformed is rejected at save, stale is omitted at render.
 */
final readonly class StatReference
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public string $modelClass,
        public StatKind $kind,
        public ?string $target = null,
    ) {
        if ($kind->needsTarget() && ($target === null || $target === '')) {
            throw new InvalidArgumentException("A [{$kind->value}] stat needs a target.");
        }

        if (! $kind->needsTarget() && $target !== null) {
            throw new InvalidArgumentException("A [{$kind->value}] stat takes no target, got [{$target}].");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $kind = StatKind::tryFrom(is_string($data['kind'] ?? null) ? $data['kind'] : '');
        if ($kind === null) {
            throw new InvalidArgumentException('A stat reference needs a known kind.');
        }

        $model = is_string($data['model'] ?? null) ? $data['model'] : '';
        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException("[{$model}] is not an Eloquent model.");
        }

        $target = $data['target'] ?? null;

        return new self($model, $kind, is_string($target) && $target !== '' ? $target : null);
    }

    /**
     * @return array{model: string, kind: string, target: string|null}
     */
    public function toArray(): array
    {
        return ['model' => $this->modelClass, 'kind' => $this->kind->value, 'target' => $this->target];
    }

    public function key(): string
    {
        return $this->modelClass . '|' . $this->kind->value . '|' . ($this->target ?? '');
    }
}
