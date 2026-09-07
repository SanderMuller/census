<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Introspection;

use Illuminate\Database\Eloquent\Model;

final readonly class ModelReference
{
    /**
     * @param  class-string<Model>  $class
     */
    public function __construct(
        public string $class,
        public string $slug,
        public string $table,
    ) {}
}
