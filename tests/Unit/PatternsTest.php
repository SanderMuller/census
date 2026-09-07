<?php declare(strict_types=1);

namespace SanderMuller\Census\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\Census\Selection\Patterns;

final class PatternsTest extends TestCase
{
    private const string MODEL = 'App\\Models\\User';

    private const array COLUMNS = ['id', 'email', 'password', 'remember_token', 'api_token'];

    #[Test]
    public function a_wildcard_model_matches_every_model(): void
    {
        $matched = Patterns::match([['*', 'password']], self::MODEL, self::COLUMNS);

        self::assertSame(['password'], $matched);
    }

    /**
     * The reason a pattern is a pair and not a delimited string: a fully qualified class name is full
     * of backslashes, so any separator character would make this ambiguous to split.
     */
    #[Test]
    public function a_fully_qualified_class_name_matches_without_escaping(): void
    {
        $matched = Patterns::match([[self::MODEL, 'email']], self::MODEL, self::COLUMNS);

        self::assertSame(['email'], $matched);
    }

    #[Test]
    public function a_model_pattern_that_does_not_match_contributes_nothing(): void
    {
        $matched = Patterns::match([['App\\Models\\Video', 'password']], self::MODEL, self::COLUMNS);

        self::assertSame([], $matched);
    }

    #[Test]
    public function a_wildcard_target_matches_every_column_it_covers(): void
    {
        $matched = Patterns::match([['*', '*_token']], self::MODEL, self::COLUMNS);

        self::assertSame(['remember_token', 'api_token'], $matched);
    }

    #[Test]
    public function overlapping_patterns_do_not_produce_duplicates(): void
    {
        $matched = Patterns::match([['*', 'password'], [self::MODEL, 'passw*']], self::MODEL, self::COLUMNS);

        self::assertSame(['password'], $matched);
    }
}
