<?php declare(strict_types=1);

namespace SanderMuller\Census\Dashboards;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A dashboard created in the application. Same shape as a code-defined one — it answers `stats()`,
 * `audiences()`, `name()` and `slug()` — so the renderer and the views cannot tell them apart.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property array<int, string> $audiences
 * @property array<int, array<string, mixed>> $stats
 * @property int|null $created_by
 */
final class UserDashboard extends Model
{
    protected $table = 'census_dashboards';

    protected $guarded = [];

    /**
     * @return list<StatReference>
     */
    public function references(): array
    {
        $references = [];
        foreach ($this->stats as $entry) {
            try {
                $references[] = StatReference::fromArray($entry);
            } catch (InvalidArgumentException) {
                // Stored before a rename, or hand-edited. Omitted rather than breaking the page.
                continue;
            }
        }

        return $references;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['audiences' => 'array', 'stats' => 'array'];
    }
}
