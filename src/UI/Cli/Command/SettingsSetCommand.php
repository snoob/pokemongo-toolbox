<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Infrastructure\Json\JsonValue;
use App\Infrastructure\Settings\SettingsFile;
use App\UI\Cli\Settings\ConfigurableOptions;
use App\UI\Cli\Settings\SettingKey;
use App\UI\Cli\Settings\SettingValues;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:settings:set',
    aliases: ['settings:set'],
    // The wizard is the way in for now: this stays for scripts and for an option the
    // wizard does not ask about, but is not listed.
    hidden: true,
    description: 'Set the default of a command option, or unset it with no value',
)]
final class SettingsSetCommand extends Command
{
    public function __construct(
        private readonly SettingsFile $settings,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('setting', InputArgument::REQUIRED, 'Command family and option, e.g. pvp.floor')
            ->addArgument(
                'value',
                InputArgument::IS_ARRAY,
                'Its default; several for a repeatable option; none to unset it',
            )
            ->setHelp(<<<'HELP'
                  <info>%command.full_name% global.account snoob2555</info>
                  <info>%command.full_name% pvp.floor best</info>
                  <info>%command.full_name% pvp.league great ultra master</info>   the Mega Editions left out
                  <info>%command.full_name% weather.at "Villemomble, France"</info>
                  <info>%command.full_name% pvp.floor</info>                       back to the command's own default

                "settings --all" lists every setting. The value is checked as the command would
                read it; every command names the settings it took.
                HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $values = array_values(array_filter(
            array_map(trim(...), JsonValue::asStringList($input->getArgument('value'))),
            static fn(string $value): bool => '' !== $value,
        ));

        try {
            $key = SettingKey::fromString(JsonValue::asString($input->getArgument('setting')) ?? '');
            $sections = $this->settings->load();
            unset($sections[$key->section][$key->option]);

            if ([] !== $values) {
                $sections[$key->section][$key->option] = $this->value($key, $values);
            }

            $this->settings->save($sections);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(
            [] === $values
                ? \sprintf('%s unset: the command\'s own default applies.', $key)
                : \sprintf('%s is now %s.', $key, implode(' ', $values)),
        );

        return Command::SUCCESS;
    }

    /**
     * The key must name an option that takes a value, and only a repeatable one takes
     * several. Unsetting is never checked, so that a setting left behind by an option
     * that no longer exists can still go.
     *
     * @param non-empty-list<string> $values
     *
     * @return string|list<string>
     */
    private function value(SettingKey $key, array $values): string|array
    {
        $application = $this->getApplication() ?? throw new \LogicException('Run outside an application.');
        $options = ConfigurableOptions::require($application, $key);

        SettingValues::check($key->option, $values);

        if (1 === \count($values)) {
            return $values[0];
        }

        if ([] === array_filter($options, static fn(InputOption $option): bool => $option->isArray())) {
            throw new \InvalidArgumentException(\sprintf(
                '--%s takes one value; quote it if it holds spaces.',
                $key->option,
            ));
        }

        return $values;
    }
}
