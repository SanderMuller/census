<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Stats;

final readonly class Stat
{
    /**
     * @param  list<array{label: string, count: int}>  $breakdown
     */
    public function __construct(
        public string $group,
        public string $label,
        public ?int $value = null,
        public array $breakdown = [],
        public ?string $note = null,
    ) {}

    public static function unavailable(string $group, string $label, string $note): self
    {
        return new self(group: $group, label: $label, note: $note);
    }

    public function isUnavailable(): bool
    {
        return $this->value === null && $this->breakdown === [];
    }

    /**
     * How much this card has to say. Every breakdown puts its telling number first — orphans for a
     * `belongsTo`, rows that actually have children for a `hasMany` — so that entry is the sort key.
     * The largest number would be the wrong one: "rows with none" is highest precisely when the
     * relation is empty and least worth reading.
     */
    public function signal(): int
    {
        return $this->breakdown === []
            ? $this->value ?? 0
            : $this->breakdown[0]['count'];
    }
}
