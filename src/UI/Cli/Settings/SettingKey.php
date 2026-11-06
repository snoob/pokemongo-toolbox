<?php

declare(strict_types=1);

namespace App\UI\Cli\Settings;

/**
 * A setting's name: the command family, then the option's long name — `pvp.floor`.
 */
final readonly class SettingKey implements \Stringable
{
    public function __construct(
        public string $section,
        public string $option,
    ) {}

    /**
     * @throws \InvalidArgumentException when it is not "family.option"
     */
    public static function fromString(string $key): self
    {
        $parts = [];

        if (1 !== preg_match('/^([a-z0-9-]+)\.([a-z0-9-]+)$/', strtolower(trim($key)), $parts)) {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot read a setting from "%s"; expected the command family and the option, e.g. pvp.floor.',
                $key,
            ));
        }

        return new self($parts[1] ?? '', $parts[2] ?? '');
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->section . '.' . $this->option;
    }
}
