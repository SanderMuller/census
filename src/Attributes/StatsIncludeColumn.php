<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Attributes;

use Attribute;

/**
 * Whitelists one column. One entry makes this model's columns opt-in.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class StatsIncludeColumn
{
    public function __construct(public string $name) {}
}
