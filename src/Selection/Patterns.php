<?php declare(strict_types=1);

namespace SanderMuller\Census\Selection;

use Illuminate\Support\Str;

/**
 * Expands `[modelPattern, targetPattern]` config pairs into the concrete names they match on one model.
 *
 * A pair rather than a delimited string because a fully qualified class name contains backslashes, so
 * no separator character is safe: `App\Models\User.password` cannot be split without ambiguity.
 */
final readonly class Patterns
{
    /**
     * @param  list<array{0: string, 1: string}>  $patterns
     * @param  list<string>  $candidates  Column or relation names on this model.
     * @return list<string>
     */
    public static function match(array $patterns, string $modelClass, array $candidates): array
    {
        $matched = [];
        foreach ($patterns as [$modelPattern, $targetPattern]) {
            if (! Str::is($modelPattern, $modelClass)) {
                continue;
            }

            foreach ($candidates as $candidate) {
                if (Str::is($targetPattern, $candidate)) {
                    $matched[] = $candidate;
                }
            }
        }

        return array_values(array_unique($matched));
    }
}
