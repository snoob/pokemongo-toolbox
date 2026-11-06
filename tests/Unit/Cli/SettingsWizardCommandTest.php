<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\Domain\Weather\Model\Place;
use App\Infrastructure\Settings\SettingsFile;
use App\Tests\Fake\InMemoryPlaces;
use App\UI\Cli\Command\SettingsWizardCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SettingsWizardCommandTest extends TestCase
{
    private string $file;

    #[\Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/pogo-wizard-' . bin2hex(random_bytes(6)) . '.json';
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testTheAnswersAreSaved(): void
    {
        $tester = $this->wizard(['FR', 'Snoob2555', 'great, ultra', 'Best', 'Villemomble, France']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(
            [
                'global' => ['locale' => 'fr'],
                'inventory' => ['account' => 'Snoob2555'],
                'pvp' => ['league' => ['great', 'ultra'], 'floor' => 'best'],
                'weather' => ['at' => 'Villemomble, France'],
            ],
            new SettingsFile($this->file)->load(),
        );
        self::assertStringContainsString('Found Villemomble, Île-de-France, France.', $tester->getDisplay());
    }

    public function testEnterKeepsTheValueAndADashRemovesIt(): void
    {
        new SettingsFile($this->file)->save([
            'inventory' => ['account' => 'snoob2555', 'floor' => 'best'],
            'weather' => ['at' => 'Villemomble, France'],
        ]);

        $tester = $this->wizard(['', '', '-', '', '-']);

        self::assertMatchesRegularExpression(
            '/Set now:.*inventory\.floor\s+best/s',
            $tester->getDisplay(),
            'the current settings are listed first, those it does not ask about too',
        );

        self::assertSame(
            ['inventory' => ['account' => 'snoob2555', 'floor' => 'best']],
            new SettingsFile($this->file)->load(),
            'the settings it does not ask about stay',
        );
    }

    public function testWithNothingSetEnterTakesTheProposedAnswers(): void
    {
        $this->wizard(['', '', '', '', '']);

        self::assertSame(
            [
                'inventory' => ['account' => 'default'],
                'pvp' => ['league' => ['great', 'ultra', 'master']],
                'weather' => ['at' => 'auto'],
            ],
            new SettingsFile($this->file)->load(),
            'a list, not the text shown; "auto" without a lookup',
        );
    }

    public function testAWrongAnswerIsAskedAgain(): void
    {
        $tester = $this->wizard([
            'de',
            'en',
            '../etc',
            'snoob2555',
            'great,hyper',
            'great',
            'lucky',
            'ultra',
            'Atlantis',
            'Villemomble, France',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('snoob2555', new SettingsFile($this->file)->load()['inventory']['account'] ?? null);
        self::assertStringContainsString('"hyper" is invalid', $tester->getDisplay());
        self::assertStringContainsString('Unknown locale "de"', $tester->getDisplay());
        self::assertStringContainsString('"lucky" is invalid', $tester->getDisplay());
        self::assertSame('ultra', new SettingsFile($this->file)->load()['pvp']['floor'] ?? null);
    }

    public function testNoRerollRemovesTheFloor(): void
    {
        new SettingsFile($this->file)->save(['pvp' => ['floor' => 'best']]);

        $this->wizard(['-', '-', '-', 'no', '-']);

        self::assertSame([], new SettingsFile($this->file)->load());
    }

    public function testAFloorSetByHandIsKeptByEnter(): void
    {
        new SettingsFile($this->file)->save(['pvp' => ['floor' => 'raid']]);

        $tester = $this->wizard(['-', '-', '-', '', '-']);

        self::assertSame(['pvp' => ['floor' => 'raid']], new SettingsFile($this->file)->load());
        self::assertStringContainsString('Keep raid', $tester->getDisplay());
    }

    public function testItNeedsATerminal(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(Command::INVALID, $tester->execute([], ['interactive' => false]));
        self::assertFileDoesNotExist($this->file);
    }

    /**
     * @param list<string> $answers
     */
    private function wizard(array $answers): CommandTester
    {
        $tester = new CommandTester($this->command());
        $tester->setInputs($answers);
        $tester->execute([]);

        return $tester;
    }

    private function command(): SettingsWizardCommand
    {
        return new SettingsWizardCommand(
            new SettingsFile($this->file),
            new InMemoryPlaces(named: [
                'Villemomble, France' => new Place('Villemomble, Île-de-France, France', 48.88, 2.51),
            ]),
        );
    }
}
