<?php declare(strict_types=1);

namespace SanderMuller\Census\Introspection;

use BackedEnum;
use SanderMuller\Census\Enums\ColumnStatKind;

final readonly class ColumnFacts
{
    /**
     * @param  class-string<BackedEnum>|null  $enumClass
     */
    public function __construct(
        public string $name,
        public string $typeName,
        public bool $isNullable,
        public ColumnStatKind $statKind,
        public ?string $enumClass = null,
    ) {}
}
