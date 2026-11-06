<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Infrastructure\Settings\SettingsFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SettingsFileTest extends TestCase
{
    private string $directory;
    private string $file;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pogo-settings-' . bin2hex(random_bytes(6));
        $this->file = $this->directory . '/settings.json';
    }

    #[\Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testNoFileYetIsNoSettings(): void
    {
        self::assertSame([], new SettingsFile($this->file)->load());
    }

    public function testTheFileReadsAsTheCommandLineWould(): void
    {
        mkdir($this->directory);
        file_put_contents($this->file, <<<'JSON'
            {
                "inventory": {"account": "Snoob2555", "league": ""},
                "pvp": {"floor": 5, "league": ["great", "ultra", "master"]},
                "weather": {"at": "Villemomble, France"}
            }
            JSON);

        self::assertSame(
            [
                'inventory' => ['account' => 'Snoob2555'],
                'pvp' => ['floor' => '5', 'league' => ['great', 'ultra', 'master']],
                'weather' => ['at' => 'Villemomble, France'],
            ],
            new SettingsFile($this->file)->load(),
            'an empty value is unset',
        );
    }

    public function testWhatIsSavedIsLoadedBackAndEmptyFamiliesDropped(): void
    {
        new SettingsFile($this->file)->save(['pvp' => ['floor' => 'best'], 'weather' => []]);

        self::assertSame(['pvp' => ['floor' => 'best']], new SettingsFile($this->file)->load());
    }

    public function testAMalformedValueIsAnErrorNamingTheSetting(): void
    {
        mkdir($this->directory);
        file_put_contents($this->file, '{"pvp": {"floor": {"min": 5}}}');

        try {
            new SettingsFile($this->file)->load();
            self::fail('a malformed file must not read as no settings');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('"pvp.floor"', $e->getMessage());
        }
    }
}
