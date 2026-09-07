<?php declare(strict_types=1);

namespace SanderMuller\Census\Selection;

/**
 * The resolution rule, in one place, applied identically to models, columns and relations.
 *
 *     whitelist = config_whitelist ∪ attribute_whitelist
 *     usable    = (whitelist ?: everything) − config_blacklist − attribute_blacklist
 *
 * Two properties are load-bearing and easy to lose:
 *
 * - **The whitelists merge into one list.** Evaluating each source on its own — "no whitelist here
 *   means everything from here" — would make both whitelists inert unless both were set, because the
 *   unlisted source keeps contributing everything.
 * - **Narrowing is last and absolute.** No whitelist re-includes a blacklisted name, so the blacklist
 *   is the single place to look when asking why something is missing.
 */
final readonly class Selector
{
    /**
     * @param  list<string>  $configWhitelist
     * @param  list<string>  $configBlacklist
     * @param  list<string>  $attributeWhitelist
     * @param  list<string>  $attributeBlacklist
     */
    public function __construct(
        private array $configWhitelist = [],
        private array $configBlacklist = [],
        private array $attributeWhitelist = [],
        private array $attributeBlacklist = [],
    ) {}

    /**
     * @param  list<string>  $candidates
     * @return list<string>
     */
    public function apply(array $candidates): array
    {
        $whitelist = [...$this->configWhitelist, ...$this->attributeWhitelist];
        $blacklist = [...$this->configBlacklist, ...$this->attributeBlacklist];

        $allowed = $whitelist === []
            ? $candidates
            : array_values(array_intersect($candidates, $whitelist));

        return array_values(array_diff($allowed, $blacklist));
    }

    public function allows(string $candidate): bool
    {
        return $this->apply([$candidate]) !== [];
    }
}
