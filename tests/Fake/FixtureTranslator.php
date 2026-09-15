<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Infrastructure\Naming\MoveNameResolver;
use App\Infrastructure\Naming\SpeciesNameResolver;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * A real Symfony translator wired to the frozen fixture catalogues — a fake in the
 * sense that the data is ours, not in the sense that the behaviour is simulated.
 */
final class FixtureTranslator
{
    /**
     * @param list<string> $locales
     */
    public static function create(string $defaultLocale = 'fr', array $locales = ['fr', 'en']): Translator
    {
        $translator = new Translator($defaultLocale);
        $translator->addLoader('xlf', new XliffFileLoader());
        $translator->setFallbackLocales(['en']);

        foreach ($locales as $locale) {
            foreach ([
                SpeciesNameResolver::SPECIES_DOMAIN,
                SpeciesNameResolver::FORM_DOMAIN,
                MoveNameResolver::DOMAIN,
            ] as $domain) {
                $translator->addResource(
                    'xlf',
                    \sprintf('%s/../Fixtures/translations/%s.%s.xlf', __DIR__, $domain, $locale),
                    $locale,
                    $domain,
                );
            }
        }

        return $translator;
    }
}
