# Pokemon go toolbox

A **Pokémon GO** command-line toolbox. It answers the questions you ask yourself before
spending resources on a Pokémon: where it ranks in PvP, what its IVs are worth, which
spread to hunt for, and which moveset to aim at.

Everything is computed **locally** — no scraping, and the only thing fetched at runtime
is the league rankings.

```
$ pogo gengar 1/15/14

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
- **Mega Evolutions**: the CP to stop at before evolving and the exclusive Charged Attack Super Megas has.
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
pogo <pokemon> [ivs]
```

A Pokémon is named by its **English name**, its **French name**, or its **Pokédex
number**. Case and accents are ignored.

```bash
pogo gengar                  # English name
pogo ectoplasma              # French name
pogo 94                      # Pokédex number
pogo gengar 1/15/14          # with your IVs (atk/def/sta)
pogo "Raichu (Alolan)"       # regional form
```

### Options

| Option | Effect |
|---|---|
| `-l, --league` | `great`, `ultra`, `master`, `mega-great`, `mega-ultra`, `mega-master`. Repeatable. Defaults to the three matching the species. |
| `--shadow` | Shadow form |
| `-m, --mega` | Mega Evolution (level 3) |
| `--mega1` … `--mega4` | A specific Mega Level. Only level 4 changes the CP. |
| `--mega-x`, `--mega-y` | Tells Charizard, Raichu and Mewtwo apart — they have two megas each |
| `-b, --best-buddy` | Allow level 51 |
| `--locale` | `fr` or `en` for species and move names. Defaults to the language you typed. |
| `-f, --format` | `text` (default) or `json` |

### Mega Evolutions

```
$ pogo altaria --mega 0/8/15

  League               Species rank                IVs                                       Pre-Mega CP
 -------------------- --------------------------- ----------------------------------------- --------------------
  Mega Great League    #262 / 1200  (score 81.3)   0/8/15  #1 / 4096 — 100.00 % — CP 1500     916 CP (lvl 18)
  Mega Ultra League    #99 / 904  (score 85.9)     0/8/15  #733 / 4096 — 97.00 % — CP 2459    1502 CP (lvl 29.5)
  Mega Master League   #126 / 466  (score 63.4)    0/8/15  #1745 / 4096 — 90.86 % — CP 3299   —

 Pre-Mega CP = what to power the base form up to; Mega Evolving lifts it to the cap.
```

A mega automatically switches to the **Mega Editions**: it is barred from the standard
leagues, so ranking it there would only ever answer "unranked".

The **Pre-Mega CP** column answers "how far do I power this up?". A Mega Edition caps the
CP of the **mega** form, not the base form, so the number the game shows while powering
up sits below the league cap. The percentage beside it is what Mega Evolving adds at that
level — it is given per row because it shifts with the IVs, from +58 % on a 15/15/15
Altaria to +64 % on a 0/8/15 one.

### JSON output

```bash
pogo gengar 1/15/14 --format=json
```

Everything the text output shows, plus what it leaves out for brevity: the best spread is
always present, every move carries its `elite` flag, and CP targets carry their level.

### Cached rankings

League rankings are the only thing fetched at runtime, and they are cached for 24 hours
under `~/.cache/pokemongo-toolbox` (or `$XDG_CACHE_HOME`). Delete that directory to force a
refresh. Everything else — base stats, moves, names — ships inside the archive.

## Data providers

Nothing here is scraped. Four community projects do the heavy lifting, and each is used
for exactly one thing:

### [pvpoke](https://github.com/pvpoke/pvpoke) — MIT

The PvP reference. Provides both the league rankings and the game data they are built on.

| File | Used for |
|---|---|
| `src/data/rankings/{all,mega}/overall/rankings-{1500,2500,10000}.json` | Species rank, score and recommended moveset per league |
| `src/data/gamemaster.json` | Base stats, forms, Elite TM moves, move power and energy, `supermega` tag |

The rankings are the only thing fetched at runtime, behind a 24-hour disk cache.

### [PokeMiners / game_masters](https://github.com/PokeMiners/game_masters) — no declared license

Raw extraction of the game's own files. Read at build time only.

| Template | Used for |
|---|---|
| `PLAYER_LEVEL_SETTINGS` | The CP multiplier table (half levels are interpolated locally) |
| `MEGA_EVOLUTION_LEVEL_*` | Mega Level progression: cooldown, candy, and the level-4 CP boost |

### [PokéAPI](https://github.com/PokeAPI/pokeapi) — BSD-3-Clause

Names only — Pokémon GO has its own stats, moves and formulas, so nothing else from the
main series applies here. Read at build time into XLIFF catalogues.

| File | Used for |
|---|---|
| `data/v2/csv/pokemon_species_names.csv` | Species names in French and English |
| `data/v2/csv/moves.csv` + `move_names.csv` | Move names in French |

### [pokemongo.fandom.com](https://pokemongo.fandom.com/wiki/Mega_Evolution) — CC BY-SA

One value only: the power multiplier the Mega Level applies to the exclusive Charged
Attack. See the caveat below.

---

IV ranks, CP values and power-up targets are **recomputed locally**, not taken from
anywhere. The formulas were checked against an independent implementation, and species
ranks match the published figures exactly.

Game data is © Niantic and The Pokémon Company. This is an unofficial, non-commercial
fan project, and it would not exist without the four projects above.

### Two honest caveats

The project separates what it reads from the data from what it **infers**:

- The CP mechanic for megas in Mega Editions is documented nowhere. It is inferred from
  how pvpoke models them: recomputing their megas under the cap lands within 1.5 % of
  their figures, against 70 % for the competing hypothesis.
- The power multiplier on the exclusive Charged Attack (×1.0 to ×1.3 by Mega Level)
  **appears in no game file**. It comes from the
  [community wiki](https://pokemongo.fandom.com/wiki/Mega_Evolution#Additional_Charged_Attacks).
  It is the only figure in this project that no upstream source confirms.

## Development

### Generate or update cache data (should be done after game update)

```bash 
bin/console pogo:data:build
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