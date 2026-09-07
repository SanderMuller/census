<?php declare(strict_types=1);

namespace SanderMuller\Census\Attributes;

use Attribute;

/**
 * Blacklists this model. Narrowing runs last and binds every audience, developers included, so a model
 * named here is gone everywhere and no whitelist anywhere pulls it back.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StatsExclude {}
