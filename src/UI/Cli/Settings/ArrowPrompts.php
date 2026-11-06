<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;

/**
 * Whether the wizard can draw a list to pick from with the arrows (`laravel/prompts`):
 * only in a real terminal, outside Windows where that needs `stty`, and not when the
 * input is piped or given by a test — those get the same choices typed.
 */
final readonly class ArrowPrompts
{
    public static function available(InputInterface $input): bool
    {
        $given = $input instanceof StreamableInputInterface && null !== $input->getStream();

        return !$given && $input->isInteractive() && '\\' !== \DIRECTORY_SEPARATOR && stream_isatty(\STDIN);
    }
}
