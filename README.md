# Pokemon go toolbox

A **Pokémon GO** command-line toolbox. It answers the questions you ask yourself before
spending resources on a Pokémon: where it — and everything it evolves into — ranks in
PvP, what its IVs are worth, which spread to hunt for, which moveset to aim at, and how
far to power it up before evolving — and when the weather is right to light the Daily
Adventure Incense.

Everything is computed **locally** — no scraping. The only things fetched at runtime are
the league rankings and, for the weather command, the forecast.

```
$ pogo pvp gengar 1/15/14

Gengar — #94
============

  League          Species rank                                IVs
 --------------- ------------------------------------------- ---------------------------------------------
  Great League    #615 / 1146  (score 73.0)                   1/15/14   #131 / 4096 — 98.02 % — CP 1478 at level 19
                  Shadow Claw → Shadow Punch* / Dark Pulse*   0/13/13   best — CP 1498 at level 19.5
  Ultra League    #479 / 844  (score 73.4)                    1/15/14   #23 / 4096 — 98.83 % — CP 2490 at level 34
                  Shadow Claw → Shadow Punch* / Dark Pulse*   0/15/14   best — CP 2500 at level 34.5
  Master League   unranked                                    1/15/14   #304 / 4096 — 94.93 % — CP 3079
                                                              15/15/15  best — CP 3254

 * = exclusive move, unlocked with an Elite TM.
```

## What it does

- **Species rank** in every league, with its score.
- **IV rank within the species** — where your spread sits among all 4096 combinations,
  as a percentage of the best, with the CP and level reachable under the league cap.
- **The spread to hunt for**, shown even without IVs: that is the question you ask
  yourself in front of a wild Pokémon.
- **Recommended moveset**, with moves that cost an **Elite TM** marked `*`.
- **Evolutions and Mega Evolutions**: give the form you hold and every form it can become
  is ranked too, megas included, with the CP to stop at before evolving and the exclusive
  Charged Attack a Super Mega has.
- **Shadow, regional, Origin/Altered forms**…
- **Bilingual data**: type the name in French or English, and species and move names
  come back in the language you asked in. The interface itself is always English.

## Install

Grab `pogo.phar` from the [latest release](../../releases/latest), make it executable,
and put it somewhere on your `PATH`:

```bash
curl -fsSLO https://github.com/snoob/pokemongo-toolbox/releases/latest/download/pogo.phar
chmod +x pogo.phar
sudo mv pogo.phar /usr/local/bin/pogo
```

PHP 8.5 with the `zlib` extension is the only requirement. Game data ships inside the
archive, so it works offline apart from refreshing the league rankings.

## Usage

```bash
pogo pvp <pokemon> [ivs]       # PvP ranking
pogo weather [--at=place]      # in-game weather, for the Daily Adventure Incense
```

`pvp` and `weather` are short aliases of `app:pvp:rank` and `app:weather:forecast`;
`pogo list` shows every command.

A Pokémon is named by its **English name**, its **French name**, or its **Pokédex
number**. Case and accents are ignored, and a typo is forgiven: `flozizarre` is read
as Florizarre, and the answer says so before the tables.

```bash
pogo pvp gengar              # English name
pogo pvp ectoplasma          # French name
pogo pvp 94                  # Pokédex number
pogo pvp gengar 1/15/14      # with your IVs (atk/def/sta)
pogo pvp "Raichu (Alolan)"   # regional form
```

### Options

| Option | Effect |
|---|---|
| `-l, --league` | `great`, `ultra`, `master`, `mega-great`, `mega-ultra`, `mega-master`. Repeatable. Defaults to the standard leagues, and the Mega Editions for a mega. A mega is never ranked in a standard league. |
| `--shadow` | Shadow form — its evolutions are shadows too, and it has no mega |
| `--mega1` … `--mega4` | The Mega Level megas are ranked at (default 3). Only level 4 changes the CP. |
| `--floor` | Lowest IV the Pokémon can have: a number, or where it comes from — `wild` (default), `weather`, `good`, `great`, `ultra`, `best` (friend trades), `shadow`, `giovanni`, `raid`, `egg`, `research`, `lucky` |
| `-b, --best-buddy` | Allow level 51 |
| `--locale` | `fr` or `en` for species and move names. Defaults to the language you typed. |
| `-f, --format` | `text` (default) or `json` |

### Evolutions and Mega Evolutions

Name the form you hold: every form it can become is ranked as well, each followed by its
mega. A form that still evolves and that no league ranks is left out to save room —
the one you typed included — so Ralts gives Gallade, Mega Gallade, Gardevoir and Mega
Gardevoir, with Ralts's own CP in the power-up column. Final forms always show. A mega's title gives
the Mega Level it is ranked at — 3 unless `--mega1` … `--mega4` says otherwise.

```
$ pogo pvp ralts 1/15/14 --league=great --league=mega-great
…
Gallade — #475
==============

  League              Species rank                             IVs                                                  Ralts CP
 ------------------- ---------------------------------------- ---------------------------------------------------- -----------------
  Great League        #377 / 1153  (score 79.0)                1/15/14  #6 / 4096 — 99.46 % — CP 1498 at level 18   234 CP (lvl 18)
                      Psycho Cut → Leaf Blade / Sacred Sword   0/15/15  best — CP 1496 at level 18                  233 CP (lvl 18)
  Mega Great League   #439 / 1210  (score 77.4)                1/15/14  #6 / 4096 — 99.46 % — CP 1498 at level 18   234 CP (lvl 18)
                      Psycho Cut → Leaf Blade / Sacred Sword   0/15/15  best — CP 1496 at level 18                  233 CP (lvl 18)

Gallade (Mega, level 3) — #475
==============================

  League              Species rank                           IVs                                                    Ralts CP
 ------------------- -------------------------------------- ------------------------------------------------------ -----------------
  Mega Great League   #636 / 1210  (score 72.7)              1/15/14  #145 / 4096 — 97.93 % — CP 1482 at level 12   156 CP (lvl 12)
                      Low Kick → Sacred Sword / Leaf Blade   4/15/15  best — CP 1500 at level 12                    163 CP (lvl 12)
…
 Ralts CP = what to power Ralts up to before evolving.
```

The **Ralts CP** column — named after the form you typed — answers "how far do I power
this up?". Evolving and Mega Evolving keep the IVs and the level, so the number the game
shows while powering up is the CP of the form in hand at the level the evolved one is
ranked at.

A mega is barred from the standard leagues, so it is ranked in the **Mega Editions**,
which the rest of the family may enter too. A shadow evolves into shadows and has no mega:
it would have to be purified first, which rerolls its IVs.

Typing a final form still brings its mega:

```
$ pogo pvp altaria 0/8/15
…
  League               Species rank                                IVs                                                     Altaria CP
 -------------------- ------------------------------------------- ------------------------------------------------------- --------------------------
  Mega Great League    #261 / 1210  (score 81.2)                   0/8/15  #1 / 4096 — 100.00 % — CP 1500 at level 18      916 CP (lvl 18) +64 %
                       Dragon Breath → Moonblast* / Flamethrower
  Mega Ultra League    #101 / 912  (score 85.9)                    0/8/15  #733 / 4096 — 97.00 % — CP 2459 at level 29.5   1502 CP (lvl 29.5) +64 %
                       Dragon Breath → Moonblast* / Flamethrower   0/15/15  best — CP 2497 at level 29.5                   1527 CP (lvl 29.5) +64 %
  Mega Master League   #128 / 470  (score 63.4)                    0/8/15  #1745 / 4096 — 90.86 % — CP 3299                —
                       Dragon Breath → Moonblast* / Sky Attack     15/15/15  best — CP 3576

 Altaria CP = what to power Altaria up to before evolving, and what Mega Evolving adds at that level.
```

One step from its mega, the percentage beside the CP is what Mega Evolving adds at that
level — it is given per row because it shifts with the IVs, from +58 % on a 15/15/15
Altaria to +64 % on a 0/8/15 one.

### IV floor: the best you can actually get

Where a Pokémon comes from sets the lowest IV it can roll: a trade rolls from 1 to 5
depending on friendship, a raid, egg or research reward never goes under 10, a lucky
trade under 12. `--floor` points the spread to hunt at the best one that source can
give, and still says where it stands among all 4096 — that is what the floor costs.

| Source | Floor |
|---|---|
| `wild` | 0 |
| `good` friend trade | 1 |
| `great` friend trade | 2 |
| `ultra` friend trade | 3 |
| `weather`-boosted catch | 4 |
| `best` friend trade | 5 |
| `shadow` (shadow raid), `giovanni` | 6 |
| `raid`, `egg`, `research` | 10 |
| `lucky` trade | 12 |

Any other floor is a number: `--floor=3`.

```
$ pogo pvp azurill 5/15/14 --floor=best --league=great
…
  League         Species rank                     IVs                                                                              Azurill CP
  Great League   #29 / 1154  (score 90.0)         5/15/14  #341 / 4096 · #4 / 1331 above floor 5 — 97.49 % — CP 1497 at level 42   299 CP (lvl 42)
                 Bubble → Ice Beam / Play Rough   5/15/15  best above floor 5, #180 — 98.00 % — CP 1500 at level 42                300 CP (lvl 42)
…
 IV floor considered: best (5).
```

IVs under the floor are refused: they cannot come from that source.

### Weather and the Daily Adventure Incense

```bash
pogo weather                           # where your IP address says you are
pogo weather --at="Villemomble, France"
pogo weather --at="Paris, United States"   # the country picks among namesakes
```

The in-game weather for the next 12 hours, and for each hour which of the incense's rare
targets — the three Galarian birds — it boosts. A boosted wild encounter rolls every IV at
4 or more, but the capped-league spreads need a 0–3 attack: only an **unboosted**
encounter can roll them. Each bird gets a ✔ when it is boosted and a – when it is not;
an hour that boosts at least one bird is highlighted in green.

The forecast comes from **[Open-Meteo](https://open-meteo.com)**: free, no key, nothing to
configure. The game itself reads AccuWeather, which no longer offers a free plan, so an
hour can disagree with what the game shows — treat the table as a forecast, not a promise.

Without `--at`, the place is guessed from your public IP address. That is good to the town
at best, and far off behind a VPN or a mobile connection — the place found is printed
first, so check it. Name the place where you will actually play, since the weather is
computed per area some 10 to 20 kilometres across.

### JSON output

```bash
pogo pvp gengar 1/15/14 --format=json
```

Everything the text output shows, plus what it leaves out for brevity: the best spread is
always present, every move carries its `elite` flag, and CP targets carry their level.
Each form of the family is an entry of `evolutions`, with the `path` of species ids that
leads to it from the one you typed.

### Cached downloads

League rankings are cached for 24 hours under `~/.cache/pokemongo-toolbox` (or
`$XDG_CACHE_HOME`; `var/cache/pogo/` from a checkout). The weather forecast is cached for 30
minutes, the IP location for an hour and the places you name for 30 days. Delete that directory to force a refresh. Everything else
— base stats, types, moves, names — ships inside the archive.

## Data providers

Nothing here is scraped. Community projects do the heavy lifting, and each is used for
exactly one thing:

### [pvpoke](https://github.com/pvpoke/pvpoke) — MIT

The PvP reference. Provides both the league rankings and the game data they are built on.

| File | Used for |
|---|---|
| `src/data/rankings/{all,mega}/overall/rankings-{1500,2500,10000}.json` | Species rank, score and recommended moveset per league |
| `src/data/gamemaster.json` | Base stats, types, forms, evolutions, Elite TM moves, move power and energy, `supermega` tag |

The rankings are the only thing fetched at runtime, behind a 24-hour disk cache.

### [PokeMiners / game_masters](https://github.com/PokeMiners/game_masters) — no declared license

Raw extraction of the game's own files. Read at build time only.

| Template | Used for |
|---|---|
| `PLAYER_LEVEL_SETTINGS` | The CP multiplier table (half levels are interpolated locally) |
| `MEGA_EVOLUTION_LEVEL_*` | Mega Level progression: cooldown, candy, and the level-4 CP boost |
| `WEATHER_AFFINITY_*` | Which types each weather boosts |

### [PokéAPI](https://github.com/PokeAPI/pokeapi) — BSD-3-Clause

Names only — Pokémon GO has its own stats, moves and formulas, so nothing else from the
main series applies here. Read at build time into XLIFF catalogues.

| File | Used for |
|---|---|
| `data/v2/csv/pokemon_species_names.csv` | Species names in French and English |
| `data/v2/csv/moves.csv` + `move_names.csv` | Move names in French |

### [Open-Meteo](https://open-meteo.com) — CC BY 4.0, no key

The weather forecast (hourly weather code, wind and gusts) and the geocoder behind `--at`.
Fetched at runtime, cached. Free for non-commercial use.

### [ipinfo.io](https://ipinfo.io) — no key

Only when `--at` is not given: the town your public IP address points to.

### [pokemongo.fandom.com](https://pokemongo.fandom.com/wiki/Mega_Evolution) — CC BY-SA

One value only: the power multiplier the Mega Level applies to the exclusive Charged
Attack. See the caveat below.

---

IV ranks, CP values and power-up targets are **recomputed locally**, not taken from
anywhere. The formulas were checked against an independent implementation, and species
ranks match the published figures exactly.

Game data is © Niantic and The Pokémon Company. This is an unofficial, non-commercial
fan project, and it would not exist without the projects above.

### Honest caveats

The project separates what it reads from the data from what it **infers**:

- The CP mechanic for megas in Mega Editions is documented nowhere. It is inferred from
  how pvpoke models them: recomputing their megas under the cap lands within 1.5 % of
  their figures, against 70 % for the competing hypothesis.
- The power multiplier on the exclusive Charged Attack (×1.0 to ×1.3 by Mega Level)
  **appears in no game file**. It comes from the
  [community wiki](https://pokemongo.fandom.com/wiki/Mega_Evolution#Additional_Charged_Attacks).
  No upstream source confirms it.
- The translation from forecast to in-game weather is **ours, and unchecked**. Niantic
  publishes none, and the community's table
  ([pgo-weatherbot](https://github.com/thornleaf/pgo-weatherbot)) is for AccuWeather, the
  source the game reads. Ours maps Open-Meteo's WMO codes the same way — precipitation
  wins over cloud, and a dry sky turns Windy past 24 km/h of wind or 35 km/h of gusts —
  but nobody has compared it with the game yet, and the two forecasts come from different
  models.
- That the Galarian birds are the incense's rare encounters is Niantic's announcement:
  the game files describe the item but carry no spawn table.

## Development

### Generate or update cache data (should be done after game update)

```bash 
bin/console app:data:build
```

### Running quality scripts

```bash 
vendor/bin/mago format && vendor/bin/mago lint && vendor/bin/mago analyze && vendor/bin/phpunit
```

### Building the PHAR manually

```bash
composer install --no-dev
curl -fsSLO https://github.com/box-project/box/releases/download/4.7.0/box.phar
php -d phar.readonly=0 box.phar compile
```