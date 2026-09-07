<?php declare(strict_types=1);

namespace SanderMuller\Census\Attributes;

use Attribute;

/**
 * Whitelists this model. One entry anywhere — config or attribute — makes the whole system opt-in, so
 * only listed models survive. Held separately from `StatsExclude` so the contradictory
 * both-at-once state cannot be written on one attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StatsInclude {}
