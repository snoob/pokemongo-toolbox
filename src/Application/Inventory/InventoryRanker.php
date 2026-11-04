<?php

declare(strict_types=1);

namespace App\Application\Inventory;

use App\Domain\Pokemon\Model\IvSpread;
use App\Domain\Pokemon\Model\Species;
use App\Domain\Pokemon\Service\EvolutionTree;
use App\Domain\Pvp\Exception\SpeciesIneligibleForLeague;
use App\Domain\Pvp\Model\IvRanking;
use App\Domain\Pvp\Model\League;
use App\Domain\Pvp\Model\PokemonLevel;
use App\Domain\Pvp\Model\RankedSpread;
use App\Domain\Pvp\Port\PvpRankingCatalog;
use App\Domain\Pvp\Service\IvRankCalculator;

/**
 * Places a kept Pokémon in its league as the best form its family offers there, not as
 * its last evolution: Dragonair outranks Dragonite in the Great League.
 *
 * Ranking 4096 spreads is the costly part, so each form's ranking is computed once per
 * league and reused for every entry of the family.
 */
final class InventoryRanker
{
    /** @var array<string, IvRanking|false> false when the form busts the CP cap */
    private array $rankings = [];

    public function __construct(
        private readonly EvolutionTree $evolutions,
        private readonly PvpRankingCatalog $catalog,
        private readonly IvRankCalculator $ivRanker,
    ) {}

    public function standing(Species $inHand, IvSpread $iv, League $league): Standing
    {
        $form = $inHand;
        $best = null;

        foreach ($this->formsIn($inHand, $league) as $candidate) {
            $rank = $this->catalog->rankOf($candidate->id, $league);

            if (null !== $rank && (null === $best || $rank->position < $best->position)) {
                $form = $candidate;
                $best = $rank;
            }
        }

        return new Standing($form, $best, $this->spreadAs($form, $iv, $league));
    }

    /**
     * Where the IVs place as that form; null when the form cannot get under the CP cap.
     */
    public function spreadAs(Species $form, IvSpread $iv, League $league): ?RankedSpread
    {
        return $this->ranking($form, $league)?->for($iv);
    }

    /**
     * Whether a Pokémon kept as `inHand` can become `form`, or already is it.
     */
    public function canBecome(Species $inHand, Species $form): bool
    {
        foreach ($this->evolutions->from($inHand) as $path) {
            if ($path->last()->id->equals($form->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every form of the family from the one in hand that may enter the league: a mega
     * only in a Mega Edition.
     *
     * @return list<Species>
     */
    private function formsIn(Species $inHand, League $league): array
    {
        $forms = [];

        foreach ($this->evolutions->from($inHand) as $path) {
            $form = $path->last();

            if (!$form->isMega() || $league->allowsMega()) {
                $forms[$form->id->value] = $form;
            }
        }

        return array_values($forms);
    }

    private function ranking(Species $form, League $league): ?IvRanking
    {
        $key = $league->value . '|' . $form->id->value;

        if (!\array_key_exists($key, $this->rankings)) {
            try {
                $this->rankings[$key] = $this->ivRanker->rank($form->baseStats, $league, PokemonLevel::regularCap());
            } catch (SpeciesIneligibleForLeague) {
                $this->rankings[$key] = false;
            }
        }

        $ranking = $this->rankings[$key];

        return false === $ranking ? null : $ranking;
    }
}
