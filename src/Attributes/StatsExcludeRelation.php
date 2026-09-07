<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Attributes;

use Attribute;

/**
 * Blacklists one relation. Gone for every audience, whatever any whitelist says.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class StatsExcludeRelation
{
    public function __construct(public string $name) {}
}
