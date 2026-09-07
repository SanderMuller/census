<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Attributes;

use Attribute;

/**
 * Blacklists one column. Gone for every audience, whatever any whitelist says.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class StatsExcludeColumn
{
    public function __construct(public string $name) {}
}
