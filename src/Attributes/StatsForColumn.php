<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Attributes;

use Attribute;

/**
 * Publishes one column to one or more audiences, under a name they recognise.
 *
 * This is what keeps a distribution over an email address, a name, or free text out of reach of a
 * non-developer audience: a column nobody published is a column nobody outside development sees.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class StatsForColumn
{
    /**
     * @param  list<string>  $audiences  Audience keys from `model-stats.audiences`.
     */
    public function __construct(
        public string $name,
        public ?string $label = null,
        public array $audiences = [],
    ) {}
}
