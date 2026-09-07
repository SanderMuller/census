<?php declare(strict_types=1);

namespace SanderMuller\Census\Dashboards;

use SanderMuller\Census\Stats\Stat;

/**
 * Cards plus whether the page finished inside its budget. `$complete` is what decides cacheability:
 * a partial result is never stored, so a slow page retries rather than freezing its own failure.
 */
final readonly class RenderedDashboard
{
    /**
     * @param  list<Stat>  $cards
     */
    public function __construct(
        public array $cards,
        public bool $complete,
        /**
         * Whether any reference was dropped because *this viewer's* audience cannot read it, as
         * opposed to being globally unusable.
         *
         * The two are different answers. A blacklisted model is gone for everyone, so a dashboard
         * left empty by one reads as genuinely empty. A stat withheld from this audience is an
         * access signal, and a page holding nothing else must read as missing rather than confirm
         * the dashboard exists.
         */
        public bool $withheldFromAudience = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->cards === [];
    }
}
