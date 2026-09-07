<?php declare(strict_types=1);

namespace SanderMuller\ModelStats\Audiences;

final readonly class Audience
{
    public function __construct(
        public string $key,
        public string $label,
        /**
         * A developer audience reads schema truth: every model, every column, every relation.
         * Every other audience reads only what an attribute published to it.
         */
        public bool $readsSchema = false,
        /**
         * Gate ability the viewer must pass. Null means the route's own middleware is the only gate,
         * which is the right setting for an audience that is already behind a role check.
         */
        public ?string $ability = null,
    ) {}
}
