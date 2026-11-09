<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cli;

use App\UI\Cli\Settings\SettingsRepair;
use App\UI\Cli\Settings\SettingsRepairAction;
use App\UI\Cli\Settings\SettingsRepairPlan;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

final class SettingsRepairTest extends TestCase
{
    private const array MIGRATIONS = [
        'inventory.account' => ['to' => 'global.account', 'since' => 'v3.2.0'],
        'pvp.leagues' => ['to' => 'pvp.league', 'since' => 'v9.0.0'],
        'pvp.reroll' => ['to' => 'pvp.floor', 'since' => 'v9.0.0'],
        'pvp.ranks' => ['to' => 'pvp.floor', 'since' => 'v9.0.0'],
        'pvp.mode' => ['to' => null, 'since' => 'v9.1.0'],
    ];

    public function testARenamedKeyMovesItsValue(): void
    {
        $plan = $this->plan(['inventory' => ['account' => 'snoob2555'], 'pvp' => ['floor' => 'best']]);

        self::assertSame(['inventory.account' => 'renamed global.account in v3.2.0'], $plan->problems);
        self::assertSame(
            ['set global.account to snoob2555, the value of inventory.account', 'remove inventory.account'],
            self::planned($plan),
        );
        self::assertSame(['pvp' => ['floor' => 'best'], 'global' => ['account' => 'snoob2555']], $plan->fixed);
    }

    public function testAListMovesIntact(): void
    {
        $plan = $this->plan(['pvp' => ['leagues' => ['great', 'ultra']]]);

        self::assertSame(['pvp' => ['league' => ['great', 'ultra']]], $plan->fixed);
    }

    public function testANewKeyAlreadySetIsKept(): void
    {
        $plan = $this->plan(['global' => ['account' => 'main'], 'inventory' => ['account' => 'snoob2555']]);

        self::assertSame(['keep global.account = main, drop inventory.account = snoob2555'], self::planned($plan));
        self::assertSame(['global' => ['account' => 'main']], $plan->fixed);
    }

    public function testAValueTheNewOptionRefusesIsLostAndSaysWhy(): void
    {
        $plan = $this->plan(['pvp' => ['reroll' => 'lots', 'ranks' => ['best', 'raid']]]);

        self::assertStringStartsWith(
            'remove pvp.reroll — its value "lots" is lost; pvp.floor refuses it: ',
            self::planned($plan)[0] ?? '',
        );
        self::assertSame(
            'remove pvp.ranks — its value "best raid" is lost; pvp.floor refuses it: --floor takes one value',
            self::planned($plan)[1] ?? null,
        );
        self::assertSame([], $plan->fixed);
    }

    public function testARemovedOptionAndAnUnknownKeyAreDroppedWithTheirValueQuoted(): void
    {
        $plan = $this->plan(['pvp' => ['mode' => 'fast', 'flor' => 'best']]);

        self::assertSame(['pvp.mode' => 'removed in v9.1.0', 'pvp.flor' => 'no option has this name'], $plan->problems);
        self::assertSame(
            [
                'remove pvp.mode — its value "fast" is lost; the option no longer exists',
                'remove pvp.flor — its value "best" is lost; set it again under its right name',
            ],
            self::planned($plan),
        );
        self::assertSame(
            [
                'removed pvp.mode — its value "fast" is lost; the option no longer exists',
                'removed pvp.flor — its value "best" is lost; set it again under its right name',
            ],
            array_map(static fn(SettingsRepairAction $action): string => $action->done, $plan->actions),
            'told again once done, in the past',
        );
    }

    public function testAHealthyFileHasNothingToFix(): void
    {
        self::assertTrue($this->plan(['global' => ['account' => 'main'], 'pvp' => ['floor' => 'best']])->isEmpty());
    }

    /**
     * @param array<string, array<string, string|list<string>>> $sections
     */
    private function plan(array $sections): SettingsRepairPlan
    {
        return new SettingsRepair(self::MIGRATIONS)->plan(self::application(), $sections);
    }

    /**
     * @return list<string>
     */
    private static function planned(SettingsRepairPlan $plan): array
    {
        return array_map(static fn(SettingsRepairAction $action): string => $action->planned, $plan->actions);
    }

    public static function application(): Application
    {
        $application = new Application();
        $pvp = new Command('app:pvp:rank');
        $pvp->addOption('floor', null, InputOption::VALUE_REQUIRED, 'Floor', 'wild')->addOption(
            'league',
            'l',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'League',
        );
        $inventory = new Command('app:inventory:list');
        $inventory->addOption('account', 'a', InputOption::VALUE_REQUIRED, 'Account');
        $application->addCommand($pvp);
        $application->addCommand($inventory);

        return $application;
    }
}
