<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\Infrastructure\Settings\SettingsFile;
use App\UI\Cli\Settings\ApplySettings;
use App\UI\Cli\Settings\ConfigurableOptions;
use App\UI\Cli\Settings\SettingsGuard;
use App\UI\Cli\Settings\SettingsRepair;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplySettingsTest extends TestCase
{
    private string $file;
    private Application $application;

    #[\Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/pogo-settings-' . bin2hex(random_bytes(6)) . '.json';
        $this->application = new Application();
        $this->application->addCommand($this->command('app:pvp:rank'));
        $this->application->addCommand($this->command('app:settings:set'));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testAnOptionLeftOutTakesItsSetting(): void
    {
        $input = $this->applyTo('app:pvp:rank', [], ['pvp' => ['floor' => 'best', 'league' => 'great']]);

        self::assertSame('best', $input->getOption('floor'));
        self::assertSame(['great'], $input->getOption('league'), 'a repeatable option gets a list');
    }

    public function testWhatIsTypedWins(): void
    {
        $input = $this->applyTo(
            'app:pvp:rank',
            ['--floor' => 'raid', '-l' => ['ultra']],
            [
                'pvp' => ['floor' => 'best', 'league' => ['great', 'ultra', 'master']],
            ],
        );

        self::assertSame('raid', $input->getOption('floor'));
        self::assertSame(['ultra'], $input->getOption('league'));
    }

    public function testTheLocaleIsSetOnceForTheWholeApplication(): void
    {
        $input = $this->applyTo('app:pvp:rank', [], ['global' => ['locale' => 'fr']]);

        self::assertSame('fr', $input->getOption('locale'));
        self::assertSame('wild', $input->getOption('floor'), 'only the locale is global');
    }

    public function testAKeyFillingNoOptionStopsTheCommand(): void
    {
        $output = new BufferedOutput();
        $event = $this->eventFor(
            'app:pvp:rank',
            [],
            [
                'inventory' => ['account' => 'snoob2555'],
                'pvp' => ['floor' => 'best', 'locale' => 'en', 'shadow' => 'yes', 'cp' => '1500'],
            ],
            $output,
        );

        self::assertFalse($event->commandShouldRun(), 'it would run on a default the player believes changed');
        self::assertSame('wild', $event->getInput()->getOption('floor'), 'nothing is applied');
        $told = $output->fetch();
        self::assertMatchesRegularExpression('/holds keys this\s+version no longer reads/', $told);
        self::assertMatchesRegularExpression('/pvp\.shadow\s+no option has this name/', $told);
        self::assertStringContainsString('settings --fix will:', $told, 'the plan comes with the error');
        self::assertStringContainsString('settings --fix', $told);
    }

    public function testTheSettingsCommandsRunWhateverTheFileHolds(): void
    {
        $event = $this->eventFor('app:settings:set', [], ['inventory' => ['account' => 'snoob2555']]);

        self::assertTrue($event->commandShouldRun(), 'they are how the file gets put right');
    }

    public function testTheRunNamesTheSettingsItTookAndOnlyThose(): void
    {
        $output = new BufferedOutput();
        $this->applyTo(
            'app:pvp:rank',
            ['-l' => ['ultra']],
            [
                'global' => ['locale' => 'fr'],
                'pvp' => ['floor' => 'best', 'league' => 'great'],
            ],
            $output,
        );

        self::assertSame(' From your settings: --floor=best --locale=fr' . \PHP_EOL, $output->fetch());
    }

    public function testNothingIsSaidWhenNoSettingWasTaken(): void
    {
        $output = new BufferedOutput();
        $this->applyTo('app:pvp:rank', ['--floor' => 'raid'], ['pvp' => ['floor' => 'best']], $output);

        self::assertSame('', $output->fetch());
    }

    public function testAListForAnOptionTakingOneValueIsAnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->applyTo('app:pvp:rank', [], ['pvp' => ['floor' => ['best', 'raid']]]);
    }

    public function testInventoryCheckTakesTheFloorAndLeaguesSetForPvp(): void
    {
        $this->application->addCommand($this->command('app:inventory:check'));

        $input = $this->applyTo(
            'app:inventory:check',
            [],
            [
                'pvp' => ['floor' => 'best', 'league' => ['great', 'ultra']],
            ],
        );

        self::assertSame('best', $input->getOption('floor'));
        self::assertSame(['great', 'ultra'], $input->getOption('league'));
        self::assertSame(
            ['global.locale', 'pvp.floor', 'pvp.league'],
            array_keys(ConfigurableOptions::all($this->application)),
            'no inventory.floor: the key is pvp.floor',
        );
    }

    public function testInventoryCheckTypedFloorWinsOverPvpFloor(): void
    {
        $this->application->addCommand($this->command('app:inventory:check'));

        $input = $this->applyTo('app:inventory:check', ['--floor' => '3'], ['pvp' => ['floor' => 'best']]);

        self::assertSame('3', $input->getOption('floor'));
    }

    public function testTheSettableKeysAreTheValuedOptionsOfEachFamily(): void
    {
        self::assertSame(
            ['global.locale', 'pvp.floor', 'pvp.league'],
            array_keys(ConfigurableOptions::all($this->application)),
        );
    }

    /**
     * @param array<string, mixed>                                 $typed
     * @param array<string, array<string, string|list<string>>> $settings
     */
    private function applyTo(string $name, array $typed, array $settings, ?OutputInterface $output = null): ArrayInput
    {
        $input = $this->eventFor($name, $typed, $settings, $output)->getInput();
        self::assertInstanceOf(ArrayInput::class, $input);

        return $input;
    }

    /**
     * @param array<string, mixed>                                 $typed
     * @param array<string, array<string, string|list<string>>> $settings
     */
    private function eventFor(
        string $name,
        array $typed,
        array $settings,
        ?OutputInterface $output = null,
    ): ConsoleCommandEvent {
        new SettingsFile($this->file)->save($settings);
        $command = $this->application->find($name);
        $command->mergeApplicationDefinition();
        $input = new ArrayInput($typed);
        $input->bind($command->getDefinition());
        $event = new ConsoleCommandEvent($command, $input, $output ?? new NullOutput());

        $settings = new SettingsFile($this->file);
        (new ApplySettings($settings, new SettingsGuard($settings, new SettingsRepair())))($event);

        return $event;
    }

    private function command(string $name): Command
    {
        $command = new Command($name);
        $command
            ->addOption('floor', null, InputOption::VALUE_REQUIRED, 'Floor', 'wild')
            ->addOption('league', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'League')
            ->addOption('shadow', null, InputOption::VALUE_NONE, 'Shadow')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale')
            ->addOption('cp', null, InputOption::VALUE_REQUIRED, 'CP');

        return $command;
    }
}
