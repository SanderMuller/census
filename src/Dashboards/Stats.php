<?php declare(strict_types=1);

namespace SanderMuller\Census\Dashboards;

use Illuminate\Database\Eloquent\Model;

/**
 * Reads as a sentence in a dashboard class: `Stats::model(Video::class)->column('status')`.
 */
final readonly class Stats
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    private function __construct(private string $modelClass) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function model(string $modelClass): self
    {
        return new self($modelClass);
    }

    public function total(): StatReference
    {
        return new StatReference($this->modelClass, StatKind::Total);
    }

    public function booleans(): StatReference
    {
        return new StatReference($this->modelClass, StatKind::Booleans);
    }

    public function column(string $name): StatReference
    {
        return new StatReference($this->modelClass, StatKind::Column, $name);
    }

    public function relation(string $name): StatReference
    {
        return new StatReference($this->modelClass, StatKind::Relation, $name);
    }
}
