<?php declare(strict_types=1);

namespace SanderMuller\Census\Tests\Unit;

use Illuminate\Database\Eloquent\Model;

/**
 * Stands in for a host application's model. Needs no table: these tests only validate reference
 * shape, never touch the database.
 */
final class FakeStatsModel extends Model {}
