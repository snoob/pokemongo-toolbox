<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\Domain\Inventory\Model\Account;
use App\Domain\Inventory\Model\Inventory;
use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\SpeciesId;
use App\Domain\Pvp\Model\League;
use App\Infrastructure\Settings\SettingsFile;
use App\Tests\Fake\InMemoryInventoryRepository;
use App\UI\Cli\Command\AccountSwitchCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AccountSwitchCommandTest extends TestCase
{
    private string $file;
    private InMemoryInventoryRepository $inventories;

    #[\Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/pogo-switch-' . bin2hex(random_bytes(6)) . '.json';
        $this->inventories = new InMemoryInventoryRepository();
        $inventory = new Inventory();
        $inventory->add(new SpeciesId('gengar'), new IvSpread(1, 15, 14), League::Great, new \DateTimeImmutable());
        $this->inventories->save(new Account('snoob2'), $inventory);
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testTheNameGivenBecomesTheGlobalAccountAndTheOtherSettingsStay(): void
    {
        new SettingsFile($this->file)->save(['global' => ['locale' => 'fr'], 'pvp' => ['floor' => 'best']]);

        $tester = $this->switch(['account' => 'Snoob2']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(
            ['global' => ['locale' => 'fr', 'account' => 'snoob2'], 'pvp' => ['floor' => 'best']],
            new SettingsFile($this->file)->load(),
        );
        self::assertStringContainsString('Now using "snoob2": 1 Pokémon kept.', $tester->getDisplay());
    }

    public function testAnAccountWithNoInventoryIsTakenWithAWarning(): void
    {
        $tester = $this->switch(['account' => 'snoob3']);

        self::assertSame('snoob3', new SettingsFile($this->file)->load()['global']['account'] ?? null);
        self::assertStringContainsString('has no inventory yet', $tester->getDisplay());
    }

    public function testANameThatCannotBeAnAccountIsRefused(): void
    {
        $tester = $this->switch(['account' => '../etc']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertFileDoesNotExist($this->file);
    }

    public function testWithoutANameOrATerminalTheAccountsAreListed(): void
    {
        $tester = $this->switch([], interactive: false);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Current account: default', $tester->getDisplay());
        self::assertStringContainsString('snoob2', $tester->getDisplay());
        self::assertFileDoesNotExist($this->file);
    }

    public function testAKnownAccountCanBePicked(): void
    {
        $tester = new CommandTester(new AccountSwitchCommand(new SettingsFile($this->file), $this->inventories));
        $tester->setInputs(['snoob2']);
        $tester->execute([]);

        self::assertSame('snoob2', new SettingsFile($this->file)->load()['global']['account'] ?? null);
    }

    /**
     * @param array<string, string> $input
     */
    private function switch(array $input, bool $interactive = true): CommandTester
    {
        $tester = new CommandTester(new AccountSwitchCommand(new SettingsFile($this->file), $this->inventories));
        $tester->execute($input, ['interactive' => $interactive]);

        return $tester;
    }
}
