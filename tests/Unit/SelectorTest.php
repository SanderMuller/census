<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\ModelStats\Selection\Selector;

/**
 * The resolution rule decides what the package can see at all, so each property it promises gets its
 * own test. The two that cost the most if wrong: a whitelist in one source alone must narrow, and a
 * blacklist must beat every whitelist.
 */
final class SelectorTest extends TestCase
{
    private const array CANDIDATES = ['Video', 'User', 'Audit'];

    #[Test]
    public function it_allows_everything_when_neither_source_whitelists(): void
    {
        self::assertSame(self::CANDIDATES, new Selector()->apply(self::CANDIDATES));
    }

    #[Test]
    public function a_config_whitelist_alone_narrows(): void
    {
        $selector = new Selector(configWhitelist: ['Video']);

        self::assertSame(['Video'], $selector->apply(self::CANDIDATES));
    }

    /**
     * The case that exposed the original rule as broken: evaluating each source separately left an
     * attribute whitelist inert, because the config source kept contributing everything.
     */
    #[Test]
    public function an_attribute_whitelist_alone_narrows(): void
    {
        $selector = new Selector(attributeWhitelist: ['User']);

        self::assertSame(['User'], $selector->apply(self::CANDIDATES));
    }

    #[Test]
    public function the_two_whitelists_merge_rather_than_intersect(): void
    {
        $selector = new Selector(configWhitelist: ['Video'], attributeWhitelist: ['User']);

        self::assertSame(['Video', 'User'], $selector->apply(self::CANDIDATES));
    }

    #[Test]
    public function a_config_blacklist_beats_an_attribute_whitelist(): void
    {
        $selector = new Selector(configBlacklist: ['Video'], attributeWhitelist: ['Video']);

        self::assertSame([], $selector->apply(self::CANDIDATES));
    }

    #[Test]
    public function an_attribute_blacklist_beats_a_config_whitelist(): void
    {
        $selector = new Selector(configWhitelist: ['Video'], attributeBlacklist: ['Video']);

        self::assertSame([], $selector->apply(self::CANDIDATES));
    }

    #[Test]
    public function a_whitelist_entry_that_matches_no_candidate_adds_nothing(): void
    {
        $selector = new Selector(configWhitelist: ['Video', 'NotDiscovered']);

        self::assertSame(['Video'], $selector->apply(self::CANDIDATES));
    }

    #[Test]
    public function it_answers_for_a_single_candidate(): void
    {
        $selector = new Selector(configBlacklist: ['Audit']);

        self::assertTrue($selector->allows('Video'));
        self::assertFalse($selector->allows('Audit'));
    }
}
