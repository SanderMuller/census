<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Stats;

use Carbon\CarbonInterface;

/**
 * Stats plus when they were measured. A viewer reading a number that may be a quarter of an hour old
 * needs to know that, so the timestamp travels with the stats rather than being re-derived.
 */
final readonly class CachedStats
{
    /**
     * @param  list<Stat>  $stats
     */
    public function __construct(
        public array $stats,
        public CarbonInterface $calculatedAt,
    ) {}
}
