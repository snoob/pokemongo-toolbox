<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\Infrastructure\Settings\SettingsFile;
use App\UI\Cli\Command\SettingsShowCommand;
use App\UI\Cli\Settings\SettingsGuard;
use App\UI\Cli\Settings\SettingsRepair;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class SettingsShowCommandTest extends TestCase
{
    private string $file;
    private SettingsFile $settings;
    private Application $application;

    #[\Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/pogo-settings-show-' . bin2hex(random_bytes(6)) . '.json';
        $this->settings = new SettingsFile($this->file);
        $this->application = SettingsRepairTest::application();
        $this->application->addCommand(new SettingsShowCommand($this->settings, new SettingsRepair()));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testTheBlockingErrorSettingsAndFixTellTheSamePlan(): void
    {
        $this->settings->save(['inventory' => ['account' => 'snoob2555'], 'pvp' => ['flor' => 'best']]);
        $planned = [
            'set global.account to snoob2555, the value of inventory.account',
            'remove inventory.account',
            'remove pvp.flor — its value "best" is lost; set it again under its right name',
        ];

        self::assertSame($planned, self::bullets($this->blockingError()), 'the error that stops a command');

        $shown = $this->settings();
        self::assertSame(Command::FAILURE, $shown->getStatusCode());
        self::assertSame($planned, self::bullets($shown->getDisplay()), 'settings, without --fix');
        self::assertStringContainsString('Run: ', $shown->getDisplay());

        $fixed = $this->settings(['--fix' => true]);
        self::assertSame(Command::SUCCESS, $fixed->getStatusCode());
        self::assertSame(
            [
                'set global.account to snoob2555, the value of inventory.account',
                'removed inventory.account',
                'removed pvp.flor — its value "best" is lost; set it again under its right name',
            ],
            self::bullets($fixed->getDisplay()),
            'the same actions, done',
        );
        self::assertSame(['global' => ['account' => 'snoob2555']], new SettingsFile($this->file)->load());
        self::assertMatchesRegularExpression(
            '/global\.account\s+snoob2555/',
            $fixed->getDisplay(),
            'then the settings',
        );
    }

    public function testOnceFixedNoCommandIsStopped(): void
    {
        $this->settings->save(['inventory' => ['account' => 'snoob2555']]);
        $this->settings(['--fix' => true]);

        self::assertSame('', $this->blockingError());
    }

    public function testAHealthyFileHasNothingToFixAndIsNotWritten(): void
    {
        file_put_contents($this->file, '{"global":{"account":"main"}}');

        $tester = $this->settings(['--fix' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Nothing to fix', $tester->getDisplay());
        self::assertSame('{"global":{"account":"main"}}', file_get_contents($this->file));
    }

    /**
     * @param array<string, bool> $input
     */
    private function settings(array $input = []): CommandTester
    {
        $tester = new CommandTester($this->application->find('app:settings:show'));
        $tester->execute($input, ['decorated' => false]);

        return $tester;
    }

    /**
     * What the guard tells when `inventory:list` is run, '' when it lets it run.
     */
    private function blockingError(): string
    {
        $settings = new SettingsFile($this->file);
        $command = $this->application->find('app:inventory:list');
        $output = new BufferedOutput();
        $event = new ConsoleCommandEvent($command, new ArrayInput([]), $output);
        $stopped = new SettingsGuard($settings, new SettingsRepair())->stops(
            $event,
            $this->application,
            $settings->load(),
        );

        self::assertSame($stopped, !$event->commandShouldRun());

        return $output->fetch();
    }

    /**
     * @return list<string> the items of the listings, joined back when wrapped
     */
    private static function bullets(string $display): array
    {
        $matches = [];
        preg_match_all('/^ \* (.+(?:\n   .+)*)$/m', $display, $matches);

        return array_values(array_map(static fn(string $item): string => trim((string) preg_replace(
            '/\s+/',
            ' ',
            $item,
        )), $matches[1] ?? []));
    }
}
