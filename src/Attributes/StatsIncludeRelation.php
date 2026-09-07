<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Attributes;

use Attribute;

/**
 * Whitelists one relation. One entry makes this model's relations opt-in.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class StatsIncludeRelation implements NamesTarget
{
    public function __construct(public string $name) {}

    public function target(): string
    {
        return $this->name;
    }
}
