<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

/**
 * The settings keys a version renamed or dropped, kept by hand, one row per change:
 * `settings --fix` moves a value to its new key rather than guessing it (decision 120).
 * A row with no new key is an option that was removed.
 */
final readonly class SettingMigrations
{
    /**
     * @var array<string, array{to: ?string, since: string}> by old key
     */
    public const array ALL = [
        'inventory.account' => ['to' => 'global.account', 'since' => 'v3.2.0'],
    ];
}
