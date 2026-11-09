<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

/**
 * What is wrong with a settings file and what `settings --fix` does about it, with the
 * file it writes: shown and applied from this one value, so what is announced is what is
 * done.
 */
final readonly class SettingsRepairPlan
{
    /**
     * @param array<string, string>                              $problems why, by key this version no longer reads
     * @param list<SettingsRepairAction>                         $actions
     * @param array<string, array<string, string|list<string>>> $fixed    the settings once repaired
     */
    public function __construct(
        public array $problems,
        public array $actions,
        public array $fixed,
    ) {}

    public function isEmpty(): bool
    {
        return [] === $this->problems;
    }
}
