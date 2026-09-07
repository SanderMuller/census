<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Dashboards;

/**
 * Which stat a reference points at. `Total` and `Booleans` describe the whole table, so they carry no
 * target; `Column` and `Relation` name one. That split is validated rather than trusted, because a
 * reference arrives from the database as much as from a PHP class.
 */
enum StatKind: string
{
    case Total = 'total';

    case Column = 'column';

    case Relation = 'relation';

    case Booleans = 'booleans';

    public function needsTarget(): bool
    {
        return match ($this) {
            self::Column, self::Relation => true,
            self::Total, self::Booleans => false,
        };
    }
}
