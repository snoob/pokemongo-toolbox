<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

use App\Infrastructure\Settings\SettingsFile;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Makes each setting the default of its option, before the command runs: the commands
 * read their options as usual and never know a setting was used, and what is typed wins
 * as over any default.
 *
 * The default is changed in the definition rather than the value set on the input:
 * `Command::run()` binds the input again after this event, which would wipe it.
 *
 * The settings a run actually took — those of options left out — are named on one line,
 * so that a result never depends on a default the player forgot. The line goes to the
 * error output: it stays on screen without getting into a JSON output or a pipe.
 */
#[AsEventListener(event: ConsoleEvents::COMMAND)]
final readonly class ApplySettings
{
    public function __construct(
        private SettingsFile $settings,
    ) {}

    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $section = null === $command ? null : ConfigurableOptions::section($command);

        if (null === $command || null === $section) {
            return;
        }

        $used = [];

        foreach (self::applicable($section, $this->settings->load()) as $name => $value) {
            $option = ConfigurableOptions::of($command, $name, $command->getApplication());

            if (null === $option) {
                continue;
            }

            if (\is_array($value) && !$option->isArray()) {
                throw new \InvalidArgumentException(\sprintf(
                    'The setting "%s.%s" is a list, but --%s of %s takes one value.',
                    $section,
                    $name,
                    $name,
                    (string) $command->getName(),
                ));
            }

            $option->setDefault($option->isArray() ? (array) $value : $value);

            if (!self::typed($event->getInput(), $option)) {
                $used[] = \sprintf('--%s=%s', $name, implode(',', (array) $value));
            }
        }

        self::mention($event->getOutput(), $used);
    }

    /**
     * @param list<string> $used
     */
    private static function mention(OutputInterface $output, array $used): void
    {
        if ([] === $used) {
            return;
        }

        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $errors->writeln(\sprintf(' <fg=gray>From your settings: %s</>', implode(' ', $used)));
    }

    /**
     * Whether the option was typed, as `--floor`, `--floor=…` or by its shortcut (`-l`).
     */
    private static function typed(InputInterface $input, InputOption $option): bool
    {
        $shortcuts = array_filter(
            explode('|', $option->getShortcut() ?? ''),
            static fn(string $short): bool => '' !== $short,
        );
        $spellings = [
            '--' . $option->getName(),
            ...array_values(array_map(static fn(string $short): string => '-' . $short, $shortcuts)),
        ];

        return $input->hasParameterOption($spellings, true);
    }

    /**
     * The settings that apply to a command of this family: its own, and the global ones.
     *
     * @param array<string, array<string, string|list<string>>> $sections as the settings file holds them
     *
     * @return array<string, string|list<string>> by option name
     */
    private static function applicable(string $section, array $sections): array
    {
        $global = array_flip(ConfigurableOptions::APPLICATION_WIDE);

        return [
            ...array_diff_key($sections[$section] ?? [], $global),
            ...array_intersect_key($sections[ConfigurableOptions::GLOBAL] ?? [], $global),
        ];
    }
}
