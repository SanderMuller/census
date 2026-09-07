<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Enums;

/**
 * Which stat a column earns, and — just as important — how many queries it costs.
 * `Video` alone has 55 boolean columns, so booleans roll up into one aggregate query
 * instead of one `GROUP BY` each.
 */
enum ColumnStatKind
{
    case Distribution;

    case Boolean;

    case None;
}
