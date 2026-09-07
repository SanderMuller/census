<?php declare(strict_types=1);

namespace SanderMuller\Census\Attributes;

use Attribute;

/**
 * Publishes a model to audiences beyond the developer one, under a name they recognise.
 *
 * Publication is opt-in on purpose. An unannotated model stays developer-only, so adding a model —
 * or a column to one — can never quietly widen who reads it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StatsFor
{
    /**
     * @param  list<string>  $audiences  Audience keys from `census.audiences`.
     */
    public function __construct(
        public ?string $label = null,
        public ?string $description = null,
        public array $audiences = [],
    ) {}
}
