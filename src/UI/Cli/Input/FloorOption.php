<?php

declare(strict_types=1);

namespace App\UI\Cli\Input;

use App\Domain\Pokemon\Model\IvFloor;
use App\Infrastructure\Json\JsonValue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * `--floor`, shared by the commands that read IVs.
 */
final readonly class FloorOption
{
    private const string NAME = 'floor';

    public static function addTo(Command $command): void
    {
        $command->addOption(
            self::NAME,
            null,
            InputOption::VALUE_REQUIRED,
            'Lowest IV: 0 to 15, or its source (wild, best, raid, lucky…; "pvp --help" lists them)',
            'wild',
        );
    }

    /**
     * @throws \InvalidArgumentException when the floor cannot be read
     */
    public static function parse(InputInterface $input): IvFloor
    {
        return IvFloor::fromString(JsonValue::asString($input->getOption(self::NAME)) ?? 'wild');
    }
}
