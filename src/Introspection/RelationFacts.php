<?php declare(strict_types=1);

namespace SanderMuller\Census\Introspection;

use Illuminate\Database\Eloquent\Model;

final readonly class RelationFacts
{
    /**
     * @param  class-string<Model>|null  $relatedClass
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?string $relatedClass,
        public ?string $foreignKeyName = null,
        public ?string $morphTypeColumn = null,
        public ?string $pivotTable = null,
    ) {}
}
