<?php declare(strict_types=1);

namespace SanderMuller\Census\Attributes;

use Attribute;

/**
 * Whitelists one column. One entry makes this model's columns opt-in.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class StatsIncludeColumn implements NamesTarget
{
    public function __construct(public string $name) {}

    public function target(): string
    {
        return $this->name;
    }
}
