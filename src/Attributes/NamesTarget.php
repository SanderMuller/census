<?php declare(strict_types=1);

namespace SanderMuller\Census\Attributes;

/**
 * Implemented by every attribute that names one column or relation. Reflection hands back
 * `object`, so without this contract reading `->name` is duck-typing that no analyser can check.
 */
interface NamesTarget
{
    public function target(): string;
}
