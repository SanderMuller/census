<?php declare(strict_types=1);

namespace SanderMuller\Census\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\Census\Dashboards\StatKind;
use SanderMuller\Census\Dashboards\StatReference;

/**
 * A reference arrives from a PHP class and from database JSON, so the same validation has to hold on
 * both paths. Malformed means it could never be valid; that is rejected here rather than at render.
 */
final class StatReferenceTest extends TestCase
{
    #[Test]
    public function it_round_trips_through_an_array(): void
    {
        $reference = new StatReference(FakeStatsModel::class, StatKind::Column, 'status');

        self::assertSame(
            $reference->toArray(),
            StatReference::fromArray($reference->toArray())->toArray(),
        );
    }

    #[Test]
    public function a_column_reference_without_a_target_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StatReference(FakeStatsModel::class, StatKind::Column);
    }

    #[Test]
    public function a_total_reference_carrying_a_target_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StatReference(FakeStatsModel::class, StatKind::Total, 'status');
    }

    #[Test]
    public function an_unknown_kind_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StatReference::fromArray(['model' => FakeStatsModel::class, 'kind' => 'histogram']);
    }

    #[Test]
    public function a_class_that_is_not_a_model_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StatReference::fromArray(['model' => self::class, 'kind' => 'total']);
    }

    #[Test]
    public function the_key_distinguishes_references_on_the_same_model(): void
    {
        $a = new StatReference(FakeStatsModel::class, StatKind::Column, 'status');
        $b = new StatReference(FakeStatsModel::class, StatKind::Relation, 'status');

        self::assertNotSame($a->key(), $b->key());
    }
}
