<?php declare(strict_types=1);

namespace SanderMuller\Census\Attributes;

use Attribute;

/**
 * Per-model behaviour. An expensive model can hold its numbers far longer than a trivial one, without
 * moving the application-wide default.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StatsOptions
{
    public function __construct(public ?int $cacheMinutes = null) {}
}
