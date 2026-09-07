<?php declare(strict_types=1);

namespace SanderMuller\Census\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use SanderMuller\Census\Audiences\Audience;
use SanderMuller\Census\Audiences\AudienceGate;
use SanderMuller\Census\Audiences\AudienceResolver;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

final readonly class ShowModelIndexController
{
    public function __construct(
        private AudienceResolver $audiences,
        private AudienceGate $gate,
    ) {}

    public function __invoke(): View
    {
        $audience = $this->audiences->resolve();
        if (! $audience instanceof Audience) {
            throw new AccessDeniedHttpException('No census audience covers this user.');
        }

        // A developer reads table names and row estimates; nobody else needs either, and the
        // estimate is a schema fact rather than a business one.
        $approximateRows = $audience->readsSchema ? $this->approximateRowCounts() : [];

        $models = [];
        foreach ($this->gate->models($audience) as $reference) {
            $models[] = [
                'slug' => $reference->slug,
                'name' => $this->gate->nameFor($reference->class, $audience),
                'table' => $audience->readsSchema ? $reference->table : '',
                'approximate_rows' => $approximateRows[$reference->table] ?? null,
            ];
        }

        // Biggest tables first — that is the order a developer scans in, far more than alphabetical.
        usort($models, static fn (array $a, array $b): int => ($b['approximate_rows'] ?? -1) <=> ($a['approximate_rows'] ?? -1));

        return view('census::index', ['models' => $models, 'audience' => $audience]);
    }

    /**
     * The index deliberately runs no `COUNT(*)`. `information_schema` holds the optimiser's own
     * row estimate, which is free to read and accurate enough to decide which model to open.
     *
     * @return array<string, int>
     */
    private function approximateRowCounts(): array
    {
        try {
            $rows = DB::select(
                'select TABLE_NAME as table_name, TABLE_ROWS as table_rows
                 from information_schema.TABLES
                 where TABLE_SCHEMA = database()',
            );
        } catch (Throwable) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->table_name] = (int) $row->table_rows;
        }

        return $counts;
    }
}
