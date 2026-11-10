# GUIDELINES — pokemongo-toolbox

Référentiel des décisions et conventions du projet. À lire avant d'écrire du code ;
toute décision structurante nouvelle s'ajoute au §12. Les features sont décrites
chacune dans sa spec, sous `specs/` (§4).

---

## 1. Produit

**Toolbox Pokémon GO en ligne de commande**, à usage personnel.

Pas un Pokédex généraliste : les données du jeu principal (PokéAPI stats, types, etc.)
ne s'appliquent **pas** à Pokémon GO, qui a ses propres stats de base, ses propres
attaques et ses propres formules. Seule exception tolérée : PokéAPI comme source de
**traduction des noms** (voir §3).

Périmètre à date : classements PvP, et météo du jeu pour choisir l'heure de l'Encens
d'aventure du jour. Les raids, events et dex perso viendront après, chacun avec sa propre
source dédiée.

---

## 2. Invocation

Le cœur métier vit dans des **commandes Symfony**, préfixées `app:` :

```bash
php bin/console app:pvp:rank <pokemon> [ivs]   # alias : pvp
pogo pvp <pokemon> [ivs]                        # même chose depuis le PHAR
php bin/console app:inventory:add <pokemon> <ivs> -l <ligue>   # alias : inventory:add
```

Une commande à plusieurs actions s'écrit en commandes séparées sous un même namespace
(`app:inventory:add`, `:list`, `:remove`) plutôt qu'en `inventory add` : le shell coupe
sur l'espace et Symfony ne prend que le premier mot pour nom. Les abréviations de Symfony
font le reste (`inv:add`).

Règle : **la logique n'est jamais dans un agent**. Les commandes doivent tourner seules,
dans un terminal, sans Claude ni réseau (hors rafraîchissement du cache). Si on veut un
confort d'usage via Claude Code, ce sera une slash-command `.claude/commands/*.md` qui
ne fait qu'**appeler** `bin/console` — jamais réimplémenter le calcul.

Corollaires :
- toute commande a une sortie lisible par un humain **et** un `--format=json` ;
- pas d'interaction bloquante : tout argument manquant a un défaut ou provoque une erreur claire ;
- code de sortie 0/1 correct (une commande qui échoue ne renvoie pas 0).

---

## 3. Sources de données

Une règle : **on tape la source la plus en amont qui soit stable et machine-readable.**
Scraper du HTML est un dernier recours, à justifier au §9.

| Donnée | Source retenue | URL |
|---|---|---|
| Classements PvP par ligue | pvpoke (JSON, GitHub) | `.../src/data/rankings/{all,mega}/overall/rankings-{1500,2500,10000}.json` |
| Stats de base, attaques, tags, `eliteMoves`, évolutions | pvpoke gamemaster | `.../src/data/gamemaster.json` (`family.evolutions` pour l'arbre) |
| Moveset recommandé par ligue | déjà dans les fichiers de classement (clé `moveset`) | — |
| Noms d'attaques (fr) | PokéAPI, **au build** | `.../data/v2/csv/moves.csv` + `move_names.csv` |
| Table des CP Multipliers | PokeMiners game_master | `raw.githubusercontent.com/PokeMiners/game_masters/master/latest/latest.json` → `PLAYER_LEVEL_SETTINGS` |
| Noms d'espèces (fr, en) | PokéAPI, **au build** | `raw.githubusercontent.com/PokeAPI/pokeapi/master/data/v2/csv/pokemon_species_names.csv` (un seul fichier, 12 langues) |
| Types des espèces | pvpoke gamemaster, **au build** | `.../src/data/gamemaster.json` → `types` (`"none"` en second pour un mono-type) |
| Types boostés par météo | PokeMiners game_master, **au build** | `WEATHER_AFFINITY_*` |
| Prévision météo horaire | Open-Meteo, **au runtime**, sans clé | `api.open-meteo.com/v1/forecast` → `weather_code`, `wind_speed_10m`, `wind_gusts_10m` |
| Lieu nommé → coordonnées | Open-Meteo geocoding, **au runtime** | `geocoding-api.open-meteo.com/v1/search` |
| Position devinée | ipinfo.io, **au runtime**, sans clé | `ipinfo.io/json` → `loc` |
| Code WMO → météo du jeu | **à nous**, par analogie avec la table AccuWeather de thornleaf/pgo-weatherbot | `WmoConditions` — voir [specs/weather-forecast.md](specs/weather-forecast.md) |

### Pourquoi pas pokemongohub / stadiumgaming

- **pokemongohub** (`db.pokemongohub.net/pokemon/65`) affiche Alakazam en Great League
  `#919`. `rankings-1500.json` de pvpoke place Alakazam en position **919**. Hub est un
  *affichage* des données pvpoke : scraper le HTML apporterait zéro information
  supplémentaire, pour une fragilité maximale.
- **stadiumgaming rank-checker** ne détient aucune donnée propriétaire : le rang IV
  interne à l'espèce est un calcul déterministe ([specs/pvp-rank.md](specs/pvp-rank.md)) qu'on fait nous-mêmes, plus vite,
  hors-ligne, et avec les cas que le site ne couvre pas (ombres, best buddy, niveau 51).

### Cache

Le projet a **deux étages de données**, et un seul se rafraîchit tout seul.

**Étage build — manuel.** `app:data:build` va chercher les trois sources amont et écrit
les fichiers commités listés plus bas. Il n'est **jamais** déclenché automatiquement et
il n'existe **aucune détection de péremption** : on le relance après une mise à jour du
jeu. C'est un outil de mainteneur : masqué de `list` et absent du PHAR, avec ses
builders (décision 100). Au runtime ces fichiers sont lus sur disque, sans réseau — c'est ce qui rend la
toolbox utilisable hors-ligne et reproductible d'une machine à l'autre.

**Étage runtime — automatique.** Les **classements** et, pour la commande météo, la
**prévision** sortent sur le réseau à l'exécution, derrière un cache disque
(`var/cache/pogo/` depuis un checkout, `~/.cache/pokemongo-toolbox/` depuis le PHAR). Un TTL par
rythme de la source : 24 h pour les classements, 30 min pour la prévision, 1 h pour la
position devinée par IP, 30 jours pour les lieux nommés. Aucune
commande métier ne fait d'appel HTTP direct : elle demande la donnée à un *port* (§6),
dont l'implémentation décide de servir le cache ou de rafraîchir. Si la source est
injoignable, la copie périmée est servie plutôt que d'échouer.

**Données du joueur — à part.** L'inventaire ([specs/inventory.md](specs/inventory.md)) n'est ni généré ni un cache : c'est
ce que le joueur a saisi, sans autre copie. Il vit dans
`$XDG_DATA_HOME/pokemongo-toolbox/inventory/`, un fichier par compte (défaut
`~/.local/share/…`), le même depuis le PHAR et depuis un checkout, sans TTL ;
`POGO_INVENTORY` nomme un autre répertoire. Les réglages ([specs/settings.md](specs/settings.md)) suivent la même
règle sous `$XDG_CONFIG_HOME/pokemongo-toolbox/settings.json` (`POGO_SETTINGS`).

Pour forcer une mise à jour : supprimer le répertoire de cache (classements) ou relancer
`app:data:build` (données de jeu).

Deux fichiers sont **générés puis commités** par `app:data:build`, et lus sans réseau :

- `data/species.json` — 1751 formes : identifiant, n° de dex, stats de base, types, slugs
  de forme, formes vers lesquelles elle évolue. **Aucun nom** : ils vivent dans les
  catalogues de traduction ;
- `data/cpm.json` — 101 niveaux (1 → 51 par pas de 0,5), demi-niveaux déjà interpolés ;
- `translations/pokemon.{fr,en}.xlf` — 1025 noms par langue, clé = n° de dex ;
- `translations/pokemon_form.en.xlf` — 119 libellés de forme, clé = slug (`shadow`, `mega_x`) ;
- `data/moves.json` — 351 attaques : puissance, énergie, drapeau `mega` (15 exclusives) ;
- `data/mega-levels.json` — progression des niveaux de méga ;
- `data/weather.json` — types boostés par chaque météo du jeu ;
- `translations/pokemon_move.{fr,en}.xlf` — 351 attaques en anglais, 346 en français.

Une exception : **`translations/pokemon_form.fr.xlf` est tenu à la main** et n'est jamais
réécrit par `app:data:build`. « Shadow », « Mega » ou « Alolan » n'existent pas dans le
Pokédex, donc aucune source ne les traduit. Ce qui n'y figure pas retombe sur l'anglais
via `framework.translator.fallbacks`.

Ce sont des données quasi-immuables : elles ne bougent qu'avec une mise à jour du jeu.
Seuls les **classements** sont téléchargés à l'exécution, derrière le cache.

---

## 4. Features — `specs/`

Chaque feature a sa spec dans [`specs/`](specs/README.md), un fichier par feature : la
question du joueur, l'interface, les règles, leur provenance et les tests attendus. Ce
document-ci garde ce qui vaut pour toutes : principes, architecture, conventions et
journal des décisions (§12).

| Feature | Commande | Spec |
|---|---|---|
| Classement PvP | `app:pvp:rank` | [specs/pvp-rank.md](specs/pvp-rank.md) |
| Météo du jeu | `app:weather:forecast` | [specs/weather-forecast.md](specs/weather-forecast.md) |
| Inventaire | `app:inventory:*` | [specs/inventory.md](specs/inventory.md) |
| Réglages | `app:settings:*` | [specs/settings.md](specs/settings.md) |
| Poussière par source | `app:stardust:info` | [specs/stardust.md](specs/stardust.md) |

---

## 5. Architecture — DDD

Découpage DDD en quatre couches. Le modèle métier est le centre ; tout le reste gravite
autour et lui est remplaçable.

```
src/
  Domain/                 # modèle métier pur — zéro dépendance externe
    Pokemon/
      Model/              # Species, PokemonName, DexNumber, IvSpread, EvolutionPath
      Port/               # SpeciesCatalog (interface)
      Service/            # SpeciesResolver, EvolutionTree
      Exception/          # SpeciesNotFound, AmbiguousSpecies, InvalidIv
    Weather/
      Model/              # GameWeather, Place, HourlyWeather
      Port/               # WeatherForecast, PlaceSearch, PlaceLocator (interfaces)
    Encounter/
      Model/              # DailyAdventureIncense
      Port/               # WeatherBoostTable (interface)
      Service/            # WeatherBoost
    Inventory/
      Model/              # Inventory, InventoryEntry, Account
      Port/               # InventoryRepository (interface)
      Exception/          # DuplicateEntry, EntryNotFound
    Stardust/
      Model/              # Stardust, CatchReward, BattleSetReward, SeasonReward, GymFeeding, FedDefender
      Service/            # CatchStardust, BattleLeagueStardust, BerryStardust
    Pvp/
      Model/              # League, Cp, StatProduct, SpeciesRank, RankedSpread
      Service/            # CpCalculator, IvRankCalculator — calcul pur, sans état
      Port/               # PvpRankingCatalog, CpMultiplierTable (interfaces)
  Application/            # cas d'usage, un par intention
    Pvp/RankPokemon/      # RankPokemonQuery, RankPokemonHandler, EvolutionRanker, RankPokemonResult
    Weather/ForecastIncense/  # ForecastIncenseQuery, ForecastIncenseHandler, ForecastIncenseResult
    Inventory/            # InventoryRanker, Standing ; AddToInventory/, ListInventory/, RemoveFromInventory/
    Stardust/StardustInfo/ # StardustInfoHandler, StardustInfoResult
  Infrastructure/         # adapters — seule couche qui connaît le réseau et le disque
    Pvpoke/               # PvpokeRankingCatalog
    OpenMeteo/            # OpenMeteoForecast, OpenMeteoPlaceSearch, WmoConditions
    Ipinfo/               # IpinfoPlaceLocator
    Inventory/            # JsonFileInventoryRepository, InventoryDocument, InventoryFile
    Settings/             # SettingsFile, SettingsDocument, SettingValue
    Json/                 # … JsonDocumentFile (fichiers du joueur : inventaire, réglages)
    Environment/          # UserDirectory (répertoires XDG)
    Encounter/            # JsonFileWeatherBoostTable (data/weather.json)
    PokeMiners/           # PokeMinersCpMultiplierTable
    Catalog/              # JsonFileSpeciesCatalog (data/names.json)
    Cache/                # décorateurs de cache disque
  UI/Cli/Command/         # #[AsCommand] : parse, délègue, affiche
  UI/Cli/Settings/        # ApplySettings (écouteur console.command), ConfigurableOptions, SettingKey,
                          # SettingValues, SettingQuestion, LeaguePicker, SettingsTable, AccountHint
```

**Règle de dépendance, non négociable :** `UI → Application → Domain` et
`Infrastructure → Domain`. Le **Domain ne dépend de rien** : ni Symfony, ni HTTP, ni
filesystem, ni `$_ENV`, ni `date()`. Un `use Symfony\...` dans `src/Domain/` est un bug,
pas un raccourci.

- **Langage ubiquitaire** : le code parle le langage du jeu — `League`, `IvSpread`,
  `StatProduct`, `RankedSpread`, `Species`. Bannis : `Data`, `Manager`, `Helper`,
  `Util`, `Service` tout court.
- **Objets valeur immuables et auto-validants** : `new IvSpread(16, 0, 0)` lève une
  exception. Un IV hors de [0,15] ne doit pas pouvoir exister en mémoire — la validation
  vit dans le constructeur, pas dans la commande.
- **Agrégat** : `Species` est la racine (dex, noms, stats de base, moves éligibles).
  On ne manipule pas ses stats de base sans passer par elle.
- **Services de domaine sans état** : les calculs (CP, stat product, rang IV) sont
  entrée → sortie, sans effet de bord, sans I/O, testables sans conteneur.
- **Un cas d'usage = une classe** dans `Application/`, avec sa Query et son Result. La
  commande CLI instancie la Query et formate le Result : elle ne calcule rien et ne
  décide rien.

---

## 6. Ports & adapters — substituabilité

**Tout service externe est derrière une interface** définie par le domaine (le *port*),
implémentée dans `Infrastructure/` (l'*adapter*). Le domaine déclare ce dont il a besoin ;
il ignore comment on l'obtient.

| Port | Adapter (Infrastructure) | Substitut de test |
|---|---|---|
| `Pvp\Port\PvpRankingCatalog` | `PvpokeRankingCatalog` (HTTP + cache) | `InMemoryPvpRankingCatalog` |
| `Pokemon\Port\SpeciesCatalog` | `JsonFileSpeciesCatalog` (+ `SpeciesFileReader`, `SpeciesIndex`) | `InMemorySpeciesCatalog` |
| `Pvp\Port\CpMultiplierTable` | `JsonFileCpMultiplierTable` | `RealCpMultiplierTable` (valeurs réelles, en dur) |
| `Json\JsonFetcher` (port d'infra) | `HttpJsonFetcher`, décoré par `CachedJsonFetcher` | `RecordingJsonFetcher` |
| `Psr\Clock\ClockInterface` | `symfony/clock` (service `clock`) | `MockClock` |
| `Weather\Port\WeatherForecast` | `OpenMeteoForecast` (HTTP + cache 30 min) | `InMemoryWeatherForecast` |
| `Weather\Port\PlaceSearch` | `OpenMeteoPlaceSearch` (HTTP + cache 30 j) | `InMemoryPlaces` |
| `Weather\Port\PlaceLocator` | `IpinfoPlaceLocator` (HTTP + cache 1 h) | `InMemoryPlaces` |
| `Encounter\Port\WeatherBoostTable` | `JsonFileWeatherBoostTable` | `RealWeatherBoostTable` (valeurs réelles, en dur) |
| `Inventory\Port\InventoryRepository` | `JsonFileInventoryRepository` (+ `InventoryFile`, `InventoryDocument`) | `InMemoryInventoryRepository` |

Le nommage n'est pas un port mais un service de présentation : `SpeciesNameResolver`
compose un nom depuis les catalogues (`pokemon` pour l'espèce, `pokemon_form` pour les
segments), et `LocalisedSpeciesNames` en donne toutes les variantes pour alimenter
l'index de recherche. `Species` ne porte aucun nom : un nom dépend d'une locale, donc
c'est de la présentation, pas de l'identité.

Conséquences pratiques :

- **Le nom du fournisseur ne fuit jamais.** `Pvpoke` ne doit apparaître dans aucun
  fichier de `Domain/` ni `Application/` — ni en nom de classe, ni en commentaire, ni en
  clé de tableau. Changer de source de classements = une classe nouvelle et une ligne de
  config, zéro ligne de métier touchée.
- **Le cache est un décorateur du port**, pas une responsabilité de l'adapter :
  `CachedPvpRankingCatalog implements PvpRankingCatalog` enveloppe l'adapter HTTP. On
  peut ainsi tester le métier sans cache, et le cache sans réseau.
- **L'injection se fait par le type de l'interface.** Aliasing explicite dans
  `services.yaml` quand l'autowiring ne peut pas trancher (plusieurs implémentations).
- **Ce qui franchit un port est un objet du domaine**, jamais un `array` brut issu du
  JSON. Mapper JSON → modèle est le travail de l'adapter ; si le domaine reçoit un
  tableau associatif, le port est mal conçu.
- Le temps est un service comme un autre : pas de `time()` ni de `new \DateTime()` en
  dur, sinon l'expiration du cache n'est pas testable.

---

## 7. Tests — PHPUnit

**PHPUnit, tests unitaires d'abord.** La pyramide penche volontairement vers le bas : la
valeur de ce projet, ce sont des formules, et une formule se teste en microsecondes.

```
tests/
  Unit/           # Domain + Application — aucun réseau, aucun disque, aucun conteneur
  Integration/    # adapters réels contre fixtures figées
  Fixtures/       # extraits JSON pvpoke / PokeMiners, réduits et commités
```

- **Domain** : testé directement, en instanciant les classes. Pas de `KernelTestCase`
  pour vérifier un calcul de CP.
- **Application** : testé avec les substituts en mémoire du §6. Un handler de cas d'usage
  doit être testable sans qu'aucune socket ne s'ouvre.
- **Préférer un fake à un mock.** Un fake (implémentation réelle, en mémoire) respecte le
  contrat du port et reste honnête quand le contrat évolue ; un mock réécrit le
  comportement et rend le test aveugle. `createMock()` reste légitime pour vérifier
  qu'une interaction a bien eu lieu (ex. : le cache n'a pas rappelé le réseau).
- **Aucun test n'appelle le réseau.** Les adapters HTTP se testent avec `MockHttpClient`
  et fixtures. Un test rouge parce que pvpoke a publié une mise à jour est un test cassé,
  pas une régression détectée.
- Les fixtures sont des **extraits** (quelques espèces), pas des dumps d'1 Mo.
- Tout bug corrigé arrive avec le test qui échouait avant le correctif.

---

## 8. Ne pas réinventer la roue

**Avant d'écrire une brique technique, chercher le composant Symfony qui la couvre.**
Le projet tourne déjà sur Symfony : un composant maintenu, testé et documenté vaut
mieux qu'une implémentation maison, même courte.

Le réflexe vaut pour tout ce qui n'est pas du métier Pokémon GO : traduction, cache,
horloge, sérialisation, HTTP, validation, système de fichiers, process, verrous,
limitation de débit. Ce qui reste légitimement maison, c'est le domaine — les formules
du jeu, les classements, les rangs d'IV : personne n'a de composant pour ça.

Critères pour préférer le composant :

- il est **populaire et maintenu** (composant Symfony officiel, ou dépendance déjà
  présente), pas une bibliothèque abandonnée ;
- il ne force pas le domaine à le connaître : on le branche derrière un port (§6), de
  sorte qu'en dépendre reste réversible ;
- il ne tire pas une grappe de dépendances hors de proportion avec le besoin.

Quand on écrit quand même la brique à la main, la raison s'inscrit au §12 — et la règle
vaut rétroactivement : `Clock`, `SystemClock` et `FrozenClock` étaient maison « parce que
ça ne fait que deux lignes », ils ont été remplacés par `symfony/clock` et `MockClock`.
Deux lignes maison restent deux lignes à maintenir, et `Psr\Clock\ClockInterface` est un
type que le reste de l'écosystème comprend.

Le réflexe a aussi une limite : un composant ne remplace une brique que s'il en couvre le
comportement **exact**. `AsciiSlugger` écrase par défaut ♀ et ♂ — sur un projet Pokémon
c'est une perte de sens, pas un détail. On l'utilise donc, mais avec une table de
symboles, et le test qui distingue les deux Nidoran est ce qui garantit qu'on ne l'a pas
adopté à l'aveugle.

---

## 9. Conventions de code

- `declare(strict_types=1);` dans **tous** les fichiers PHP.
- PHP 8.4+ assumé : promotion de constructeur, `readonly`, enums, types de retour partout.
  Pas de type `mixed` ni de `array` non documenté qui traverse une frontière de couche.
- Enums pour tout ensemble fermé (`League`, `OutputFormat`), jamais de string magique.
- Exceptions métier dédiées, définies dans la couche qui les lève. Pas de `\Exception`
  nu, pas de `return null` pour signaler une erreur.
- Nommage : anglais pour le code, les identifiants **et les messages CLI**. L'interface
  (en-têtes, libellés, aide, erreurs) est en anglais ; seules les **données de jeu** —
  noms d'espèces, d'attaques et de formes — suivent `--locale`. C'est aussi la langue du
  méta PvP, donc celle dans laquelle on lit et partage les analyses.
- Le `README.md` est en anglais, pour un dépôt public ; `GUIDELINES.md` reste en français,
  ce sont les notes de travail.
- Pas de commentaire qui paraphrase le code ; on commente **les formules du jeu** et les
  constantes non évidentes (d'où sort ce `/10`, pourquoi ce `floor`).
- Jamais de secret dans `.env` (commité) — `.env.local` uniquement.

---

## 10. Outillage qualité — Mago

Un seul binaire pour le formatage, le lint et l'analyse statique :
[Mago](https://github.com/carthage-software/mago) (écrit en Rust), en remplacement du
couple PHP-CS-Fixer + PHPStan.

```bash
composer require --dev carthage-software/mago
vendor/bin/mago init      # détecte le layout du projet et écrit mago.toml
```

Version de référence : **1.48.x** (septembre 2026). Contrainte Composer amont
`~8.1 || … || ~8.5 || ~8.6` — notre PHP 8.5 est supporté. L'analyseur comprend les
annotations **PHPStan et Psalm** : les docblocks restent portables si on doit faire
marche arrière.

Commandes (celles employées par la CI du projet amont) :

```bash
vendor/bin/mago format                        # formate
vendor/bin/mago format --dry-run              # vérifie sans écrire — CI / pre-commit
vendor/bin/mago lint --reporting-format=github
vendor/bin/mago analyze --reporting-format=github
```

`mago.toml` à la racine :

- `php-version = "8.5"` — la version d'exécution du projet. Elle doit rester **alignée
  avec `require.php` de `composer.json`** (`>=8.5`) : si les deux divergent, Mago valide
  du code qu'un `composer install` sur une machine plus ancienne accepterait et qui
  casserait à l'exécution.
- `[source]` : `paths = ["src", "tests"]`, `includes = ["vendor"]`.
- `[analyzer]` : sévérité maximale dès maintenant, tant que la base est petite —
  `check-missing-type-hints`, `find-unused-definitions`, `find-unused-parameters`,
  `analyze-dead-code`, `trust-existence-checks = false`,
  `allow-possibly-undefined-array-keys = false`.
- `[linter.rules]` : les règles par défaut, plus celles désactivées par défaut qui
  servent le §9 (`no-redundant-use`, `sensitive-parameter`, `no-redundant-readonly`).

Règles d'usage :

- **Une erreur se corrige, elle ne se désactive pas.** Baisser un seuil ou couper une
  règle est une décision, qui s'inscrit au §12 avec sa raison.
- Mago est jeune et publie vite. Si un faux positif bloque, on l'isole **au cas précis**,
  jamais en désactivant la règle globalement — et on reste capable de revenir à PHPStan,
  d'où les annotations compatibles.
- La règle de dépendance du §5 n'est tenue que si une machine la vérifie : dès que le
  domaine grossit, ajouter deptrac ou un simple test PHPUnit qui échoue si `src/Domain/`
  contient un `use Symfony\`.
- Avant tout commit :
  `vendor/bin/mago format && vendor/bin/mago lint && vendor/bin/mago analyze && vendor/bin/phpunit`

---

## 11. Workflow

1. Le squelette est incomplet (voir `CLAUDE.md`) : `src/Kernel.php`, `config/services.yaml`
   et `config/packages/framework.yaml` doivent exister avant toute feature.
2. **`.gitignore` d'abord** : `vendor/` est actuellement commité. `git rm -r --cached vendor`
   avant d'ajouter la moindre dépendance, sinon chaque `composer require` produit un diff
   de plusieurs milliers de fichiers.
3. Une feature = spec + domaine + cas d'usage + adapters + tests, dans le même commit.
   La spec (`specs/<feature>.md`) s'écrit ou se met à jour **avant** le code ; un
   changement de comportement la modifie dans le même commit.
4. **Aligner la version de PHP au bootstrap** : `composer.json` déclare encore
   `"php": ">=8.4"` alors que le projet tourne en 8.5. Passer à `">=8.5"` (et régénérer
   le `platform` du `composer.lock`) en même temps que l'ajout des dépendances, pour que
   la contrainte, le `mago.toml` et le runtime disent la même chose.
5. Dépendances : toujours `composer require`, jamais d'édition manuelle de `composer.json`
   (Flex écrit les recettes dans `config/packages/`).
6. Les caches d'exécution ne sont **jamais** commités ; les données générées stables
   (`data/*.json`, `translations/*.xlf`) le sont.
7. **Commit direct sur `main`, sans pull request** (décision 122) : une release publie
   `main` tel quel, donc on n'y commite que du travail fini. Un commit qui règle une issue
   le dit dans son corps : `Closes #N`.

---

## 12. Journal des décisions

| # | Décision | Raison |
|---|---|---|
| 1 | Commandes Symfony comme cœur, Claude en enveloppe optionnelle | L'outil doit vivre sans agent |
| 2 | pvpoke JSON plutôt que scraping pokemongohub | Hub affiche les rangs pvpoke à l'identique (Alakazam #919 dans les deux) |
| 3 | Rang IV calculé en local plutôt que scrapé sur stadiumgaming | Calcul déterministe, hors-ligne, couvre ombre/best buddy/niv. 51 |
| 4 | PokéAPI utilisé au build uniquement, pour les noms FR | Données GO ≠ données jeu principal ; le mapping de noms, lui, est stable |
| 5 | Cache fichier, pas de base de données | Volume faible, données re-téléchargeables ; Doctrine viendra avec le dex perso |
| 6 | DDD, domaine isolé du framework | Les formules du jeu sont le cœur de valeur : elles doivent survivre à un changement de Symfony ou de source |
| 7 | Tout service externe derrière un port | pvpoke et PokeMiners peuvent disparaître ou changer de format ; on remplace alors un adapter, pas le métier |
| 8 | Fakes en mémoire plutôt que mocks générés | Un fake respecte le contrat du port et reste vrai quand ce contrat évolue |
| 9 | Mago à la place de PHP-CS-Fixer + PHPStan | Un seul binaire pour format/lint/analyse, rapide, supporte PHP 8.5 et lit les annotations PHPStan — donc réversible |
| 10 | `excessive-parameter-list` relevé de 5 à 8 | Les objets valeur (`Species`, `RankedSpread`) ont 7 champs nommés ; les regrouper en tableau de forme perdrait le typage |
| 11 | `too-many-methods` exclu de `tests/` | Une classe de test a une méthode par cas ; le seuil vise le code de production |
| 12 | `literal-named-argument` désactivée | Désactivée en amont dans le `mago.toml` de Mago lui-même ; alourdit chaque appel de la bibliothèque standard |
| 13 | `readable-literal` exclue de `RealCpMultiplierTable` | Fichier généré : 101 constantes issues du jeu, pas du code écrit à la main |
| 14 | `JsonValue` / `JsonPath` pour décoder | L'analyseur interdit toute variable `mixed` ; centraliser le rétrécissement de type évite de parsemer des `is_*` dans chaque adapter |
| 15 | Rang IV affiché aussi en Master League, avec un avertissement | Le calculer coûte le même prix ; le masquer ferait croire à un bug, l'avertissement dit pourquoi il n'arbitre rien |
| 16 | Noms portés par `symfony/translation`, plus par `Species` | Sort le nom de l'agrégat — un nom dépend d'une locale, c'est de la présentation ; au passage les formes deviennent traduisibles, ce qu'un champ `frenchName` ne permettait pas |
| 17 | `pokemon_form.fr.xlf` tenu à la main, jamais généré | « Shadow », « Mega », « Alolan » n'ont pas d'équivalent Pokédex ; le reste retombe sur l'anglais |
| 18 | XLIFF écrit par `XliffFileDumper`, pas par DOMDocument | §8 : échappement, structure et nommage des fichiers sont le travail du composant, et le loader qui relit vient avec |
| 19 | `symfony/clock` remplace `Clock`/`SystemClock`/`FrozenClock` | Annule la décision inverse prise plus tôt : `MockClock` fait le travail, et `Psr\Clock\ClockInterface` est un type partagé plutôt qu'une interface maison |
| 20 | `AsciiSlugger` remplace le repli d'accents maison | Couvre bien plus que notre table de 25 caractères (Flabébé, Ho-Oh, « Type: Null »), pour une classe qui se réduit à sa politique de comparaison |
| 21 | Table de symboles passée en **closure** à `AsciiSlugger` | Sous forme de tableau elle s'applique après la translittération, donc trop tard : ♀ et ♂ ont déjà disparu et les deux Nidoran deviennent le même terme |
| 22 | Égalités de `statProduct` départagées par le total d'IVs | Sans ça le « meilleur » de Master sortait à 15/15/14, exact mais méconnaissable ; effet de bord assumé : les rangs bougent de quelques positions (1/15/14 d'Ectoplasma passe de 130 à 131 en Great) |
| 23 | Meilleur spread affiché par ligue, calculé même sans IVs | C'est la question qu'on se pose devant un Pokémon (« qu'est-ce que je dois chercher ? ») ; remplace le paragraphe d'avertissement sur Master par une donnée |
| 24 | Moveset pris dans la clé `moveset` du classement | Déjà téléchargée, et c'est la recommandation de la même source que le rang — donc cohérente avec lui, contrairement à un tri maison sur les `uses` |
| 25 | Noms d'attaques traduits par repli sur le préfixe le plus long | Le Pokédex ignore les variantes du jeu (`_PLUS`, `HIDDEN_POWER_FIRE`) : le repli fait passer la couverture FR de 85 % à 98,6 %, le reste retombant sur l'anglais |
| 26 | Langue par défaut passée de `fr` à `en` | C'est la langue du méta PvP et des noms d'attaques, celle dans laquelle on lit et partage les analyses |
| 27 | Langue déduite du nom saisi plutôt qu'un drapeau `--fr` | Mesuré à coût nul (deux lectures d'un catalogue déjà en mémoire) ; `--locale` couvre déjà le cas explicite, un `--fr` ferait doublon et se taperait à chaque appel |
| 28 | Mega Editions en ligues à part entière, pas en annotations | pvpoke publie un cup `mega` séparé ; une méga est réellement interdite en ligue standard, donc « non classé » y est la bonne réponse |
| 29 | Ligues choisies d'après l'espèce quand `--league` est absent | Classer une méga en ligue standard ne produit que des « non classé » ; l'inverse vaut pour une non-méga |
| 30 | Mécanique du CP méga déduite des données, pas d'une doc | Aucune source ne la documente ; recalculer les mégas de pvpoke sous plafond donne 1,5 % d'écart, contre 70 % pour l'hypothèse concurrente — à reconfirmer en jeu |
| 31 | `--mega` plutôt que le nom de forme | Symétrique de `--shadow`, et évite d'avoir à connaître l'orthographe exacte du suffixe dans la langue courante |
| 32 | Colonne « Hors méga » affichée sous condition | Une 4ᵉ colonne coûte de la largeur à tout le monde ; elle n'apparaît que quand une méga est classée, donc quand elle a quelque chose à dire |
| 33 | Résolution d'espèce extraite en `SpeciesResolver` (domaine) | Le handler dépassait le seuil de complexité ; résoudre « un identifiant + une forme voulue » vers une espèce est de la logique de domaine sur le port, pas de l'orchestration |
| 34 | Les segments `mega` et `shadow` sortent de l'index de recherche | Une seule façon de demander une forme ; les segments **sans** drapeau (régionales, Origine…) y restent, sinon elles deviendraient injoignables |
| 35 | L'index utilise `searchName()`, l'affichage `name()` | Le nom affiché garde ses segments (« Ectoplasma (Obscur) ») ; seul l'index les retire. La détection de langue lit `searchName()`, sinon `ectoplasma --mega` ne serait plus reconnu comme français |
| 36 | Préférence sur le nombre de segments à drapeau, plus sur `isBaseForm()` | « Raichu (d'Alola) » couvre la forme régionale et son obscure : aucune n'est une forme de base, mais l'une a un segment à drapeau de moins |
| 37 | Ni niveau ni « hors méga » dans une ligue sans plafond | Deux informations qui n'en sont pas : le niveau y est toujours le plafond, et aucun CP n'est requis. Gagne deux colonnes de largeur là où c'est le plus dense |
| 38 | Un formateur par colonne (`IvCell`, `Moveset`, `BaseFormCell`) | Le présentateur repassait le seuil de complexité à chaque colonne ajoutée ; une colonne = une classe testable isolément |
| 39 | Niveaux de méga 1-4, mais un seul change le CP | Relevé sur les 139 templates : `selfCpBoostAdditionalLevel` n'existe qu'au niveau 4. Deux niveaux qui donnent la même cible ne sont pas un bug, et un test le verrouille |
| 40 | Effets du niveau énoncés en pied plutôt qu'en colonne | Le niveau n'a aucun effet par ligue ; le mettre dans le tableau le répéterait à l'identique. Et le « bonus de dégâts par niveau » attendu n'existe pas dans les données |
| 41 | Bonbons retirés de l'affichage, gardés dans le modèle | Outil PvP : le gain de capture ne dit rien d'un combat. La donnée reste générée pour une future commande orientée farm |
| 42 | `--mega-x` / `--mega-y` pour Dracaufeu, Raichu et Mewtwo | La décision 34 avait fermé l'accès par nom de forme sans laisser d'alternative à ces trois espèces : il ne restait que l'identifiant interne |
| 43 | Parsing des options extrait en `RankPokemonQueryFactory` | La commande repassait le seuil de complexité à chaque option ; elle garde le flux et le rendu d'erreur, la fabrique garde la lecture de la ligne de commande |
| 44 | Multiplicateur de puissance de l'attaque exclusive en constante | Absent du game master (vérifié sur la liste exhaustive des clés) ; source communautaire, donc isolé et commenté plutôt que présenté comme une donnée de jeu |
| 45 | Pieds de tableau extraits en `RankFooterNotes` | Le présentateur redépassait le seuil ; et les deux drapeaux booléens que la méthode prenait au départ ont été remplacés par les formateurs eux-mêmes, qui savent déjà répondre |
| 46 | Puissance affichée en « base + bonus », pas en multiplicateur | « 60 + 12 » se lit directement ; « ×1,2 » demande une multiplication et cache la valeur de base |
| 47 | `data/moves.json` avec puissance et énergie | Nécessaire au calcul du bonus, et déjà ce qu'il faut pour comparer le DPE d'une attaque exclusive à sa version de base |
| 48 | Distribution en PHAR via Box, publiée par tag | Un binaire à poser dans le `PATH` ; Box est la référence et publie son propre `box.phar` signé, donc pas de conflit de dépendances avec le projet empaqueté |
| 49 | `resource:` sans slash final dans `services.yaml` | Sous `phar://`, `GlobResource` ne renvoie **rien** quand le préfixe finit par `/` — l'application enregistrait zéro service sans le moindre avertissement. Comportement vérifié : 0 fichier avec le slash, 74 sans |
| 50 | Config importée par `scandir` plutôt que par glob | `glob()` ne fonctionne pas sur le flux `phar://` ; l'import `config/packages/*.yaml` par défaut ne chargeait rien |
| 51 | `getProjectDir()` surchargé en mode PHAR | Symfony remonte jusqu'à un `composer.json`, absent de l'archive : il s'arrêtait sur `src/` et cherchait la config dans `src/config/` |
| 52 | PHAR en `prod` uniquement, mono-commande | Le conteneur `dev` écrit une référence de config dans `config/`, impossible en lecture seule ; et `pogo gengar 1/15/14` se lit mieux que `pogo pogo:pvp:rank …` |
| 53 | Gain de CP de la méga affiché **par ligne**, pas par espèce | Il est stable selon le niveau (±0,2 %) mais **varie avec les IVs** : Altaria gagne +64 % en 0/8/15 et +58 % en 15/15/15, parce que les mêmes IVs ne pèsent pas pareil sur deux jeux de stats de base. Un chiffre unique par espèce serait faux de six points |
| 54 | Checks qualité dupliqués entre `ci.yml` et `release.yml` | Un tag ne rejoue pas le CI de la branche : sans cette duplication, on pourrait publier un binaire issu d'un commit jamais vérifié |
| 55 | `mago format --dry-run` en CI, pas `format` | Un job qui réécrit des fichiers ne rapporte rien : soit il les modifie sans committer, soit il passe toujours |
| 56 | AccuWeather comme source météo, malgré la clé API | C'est la source que lit le jeu ; toute autre prédirait une météo voisine, pas celle du jeu |
| 57 | Traduction AccuWeather → météo du jeu en constante, sourcée communauté | Niantic ne la publie pas ; isolée dans `AccuWeatherConditions` avec sa provenance, comme la décision 44 |
| 58 | *Hot* / *Cold* laissés non traduits | Jamais observés en jeu par la source ; une heure inconnue sans verdict vaut mieux qu'une supposition |
| 59 | Position devinée par IP (ipinfo), `--at` prioritaire | Zéro saisie dans le cas courant ; le lieu deviné est affiché parce qu'il peut tomber une ville à côté. ipinfo plutôt que la recherche IP d'AccuWeather : pas de clé, et un appel de moins sur le quota |
| 60 | Types dans `species.json`, affinités dans `data/weather.json`, générés au build | Données de jeu stables (§3) : le runtime reste hors-ligne pour tout sauf la prévision |
| 61 | Coût du boost calculé (`bestWithFloor`) plutôt qu'un conseil figé | « Éviter le boost » est vrai mais coûte 0,1 à 1,4 % de stat product selon l'oiseau : un chiffre laisse le joueur trancher |
| 62 | Un `CachedJsonFetcher` par rythme de source | Le décorateur existe déjà ; trois TTL valent mieux qu'un compromis qui serait trop long pour la météo ou trop court pour le quota |
| 63 | Clé AccuWeather masquée dans les erreurs, exception d'origine non chaînée | La clé voyage dans l'URL, que l'erreur HTTP recopie |
| 64 | `pogo:weather:forecast` hors du PHAR pour l'instant | Le PHAR est mono-commande (décision 52) ; y ajouter une commande impose de revoir `pogo gengar` — décision laissée ouverte |
| 65 | Pas d'option `--goal` (PvP / 100 %) | Retirée avant la première version : le besoin est le PvP ; l'inverse se lit dans la note sur le 15/15/15, et `HourOutcome::$boosted` suffira à le réintroduire |
| 66 | Open-Meteo remplace AccuWeather | AccuWeather n'a plus d'offre gratuite. Annule les décisions 56 et 63 (plus de clé ni de masquage). Le domaine n'a pas bougé : seuls les adapters ont changé, ce que les ports devaient permettre |
| 67 | Traduction WMO → météo du jeu écrite par nous, non validée | Aucune table n'existe pour cette source ; tracée par analogie avec celle d'AccuWeather (décision 57) et isolée dans `WmoConditions`, à recaler sur des relevés en jeu |
| 68 | PHAR multi-commande, alias `pvp` et `weather` | Annule le mode mono-commande de la décision 52 (le `prod` forcé reste) et clôt la 64 : le PHAR expose les commandes de `bin/console`, et les alias gardent la saisie courte — `pogo pvp gengar` |
| 69 | Coût du boost, notes et colonne « Unboosted » retirés de `app:weather:forecast` | La commande répond à « quelle heure ? » : le tableau horaire et le surlignage suffisent. Annule la décision 61 ; `bestWithFloor` et la lecture du plancher d'IVs partent avec, puisque plus rien ne les lit |
| 70 | Surlignage des heures qui boostent au moins un oiseau, plus du « meilleur créneau » | Préférence de l'utilisateur : l'heure se repère par le boost, le PvP se lit dans les coches. `bestHours` et le compte de non-boostés disparaissent, comme le champ `best` du JSON |
| 71 | Conteneur du PHAR mis en cache par build de l'archive (`prod-<id>`) | En `prod`, Symfony ne revalide jamais son conteneur compilé : après une mise à jour, le PHAR servait les commandes de la version précédente (constaté avec les alias de la décision 68). Le cache des classements et de la météo (`cache/pogo`) reste partagé entre versions |
| 72 | Commandes préfixées `app:` au lieu de `pogo:` | Préfixe par défaut d'une application Symfony ; le binaire reste `pogo` et les alias `pvp` / `weather` ne changent pas |
| 73 | `release.yml` appelle `ci.yml` (`workflow_call`) au lieu d'en recopier les checks | Annule la décision 54 : le tag rejoue toujours les checks, mais depuis une seule définition, qui ne peut plus diverger de celle du CI |
| 74 | Note de release = message du tag annoté (`--notes-from-tag`) | Les notes générées listent des PR, et le projet n'en ouvre pas : la note s'écrit avec le tag (`git tag -a`) et part avec le binaire, sans `gh` en local |
| 75 | La famille entière classée d'office : évolutions, puis la méga de chacune | On connaît la forme en main, pas celle qui combattra. Annule les décisions 31 et 42 : `--mega`, `--mega-x` et `--mega-y` disparaissent, la méga — X et Y comprises — s'affichant comme évolution de la forme tapée ; `--mega1` à `--mega4` ne choisissent plus que le niveau |
| 76 | Colonne de montée nommée d'après la forme tapée, pour les évolutions comme pour les mégas | Remplace « Pre-Mega CP » (décision 32) : évoluer et méga-évoluer gardent tous deux le niveau, donc c'est la même question — « jusqu'à combien je monte ce que j'ai en main ? » |
| 77 | Gain de CP de la méga (décision 53) affiché seulement à un pas de la méga | Depuis Tarsal, « +260 % » mesurerait surtout les évolutions ordinaires et n'apprendrait rien |
| 78 | Liens d'évolution « vers l'avant » seuls, un obscur suivant ceux de sa forme ordinaire | Les liens `parent` de pvpoke contredisent les `evolutions` à douze endroits, et certains obscurs n'ont aucun lien ; une évolution sans entrée obscure est écartée plutôt que remplacée par la forme ordinaire |
| 79 | Sous `--league`, une méga n'entre que dans les Mega Editions, les autres formes partout | Une Mega Edition admet une méga par équipe et classe aussi les Pokémon ordinaires (Noctali #29 en Mega Great) ; seule la méga est interdite en ligue standard |
| 80 | `excessive-parameter-list` relevé de 8 à 9 | `Species` gagne ses évolutions ; les sortir dans un port à part dédoublerait la lecture de `species.json` pour une relation qui appartient à l'espèce. Même raison que la décision 10 |
| 81 | Forme qui évolue encore masquée quand aucune ligue affichée ne la classe, forme tapée comprise | Demande de l'utilisateur, pour la place : Tarsal, Kirlia ou Chrysacier n'apportaient qu'un tableau de « unranked », et la colonne de montée nomme déjà la forme tapée. Les finales restent, et le test passe avant le calcul des spreads |
| 82 | Titre réduit au nom et au n° de dex, niveau de méga entre parenthèses | Demande de l'utilisateur : le chemin « Inkay → Malamar → Malamar (Mega) » se lisait mal, et la colonne de montée nomme déjà la forme en main. Le niveau de méga, lui, change le calcul : il se lit dans le titre (« Malamar (Mega, level 3) ») |
| 83 | Faute de frappe corrigée d'office vers le nom le plus proche, avec mention | Choix de l'utilisateur, entre corriger et seulement suggérer : à une ou deux lettres près le risque de viser le mauvais Pokémon est faible, et la ligne `Interpreted …` le rend visible avant toute lecture. `nearest()` est une méthode du port distincte de `search()` pour que le domaine sache qu'il a deviné ; la tolérance, elle, reste une politique de l'adapter, comme la normalisation (décision 20) |
| 84 | Plancher d'IV en entier unique (`--floor`), les sources n'étant que des alias | Demande de l'utilisateur : un nombre couvre ce qu'aucun alias ne nomme (`--floor=4`, capture boostée par la météo), et `egg` / `research` ne sont que d'autres noms de `raid`. Les trois IVs partagent le même plancher dans tous les cas du jeu, d'où un seul entier |
| 85 | Sous un plancher, rangs absolus conservés et rang dans le plancher ajouté à côté | Le rang absolu du meilleur spread possible est la réponse utile : il dit ce que la source coûte. Renuméroter ferait passer un #180 pour un #1 |
| 86 | `shadow` à 6 (raid obscur, alias `giovanni`), pas de `purified` | Choix de l'utilisateur : ce sont les obscurs qu'on vise en PvP. Le plancher des sbires Rocket diverge selon les sources (dittobase 1, Pokémon GO Hub aucun) et reste accessible par un nombre |
| 87 | PC (`--cp`) contre IVs : rejet si aucun spread ne le donne, rejet aussi s'il en reste plusieurs | Choix de l'utilisateur, qui recoupait ses IVs par le PC sur un autre outil. Classer chaque candidat noierait la réponse ; la liste des spreads possibles dit quel IV ajouter. Une seule inconnue suffit souvent, pas toujours : pour Ectoplasma 1/15/14 au PC 1478, `x/15/14` laisse deux spreads, `1/x/14` un seul |
| 88 | Le PC se compare à la forme en main, sur tous les niveaux jusqu'au plafond ; le niveau trouvé ne sert qu'à l'affichage | C'est la forme tapée que le joueur regarde. Le niveau n'entre pas dans le classement : celui-ci cherche déjà le meilleur niveau sous le plafond de la ligue. Le nombre de jokers n'est pas limité, la règle d'ambiguïté tranche |
| 89 | Joker `x` seul, ni `?` ni `*` ni `_` | `?` et `*` sont des globs : zsh refuse `1/?/14` (« no matches found ») sans guillemets |
| 90 | Inventaire en fichier JSON, pas en SQLite | Un PHAR n'embarque pas d'extension : `pdo_sqlite` manquait sur la machine de l'utilisateur. Quelques dizaines d'entrées se lisent et se réécrivent entières, et le joueur peut lire, sauvegarder ou corriger le fichier. Le port laisse changer d'adapter si le volume l'exige un jour |
| 91 | Inventaire sous `$XDG_DATA_HOME`, pas sous le cache ; même fichier PHAR et checkout | Ce sont les seules données saisies par le joueur : un cache se jette, elles non. Le chemin est lu à l'exécution et non au build du conteneur, qui figerait le `HOME` de la machine qui l'a compilé ; `POGO_INVENTORY` le remplace |
| 92 | `app:inventory:add` / `:list` / `:remove` avec alias, pas `inventory add` | `inventory add` serait une commande `inventory` à argument `add` : options et aide différentes par action dans une seule classe. Le `:` coûte un caractère, et `inv:add` marche par abréviation |
| 93 | Une entrée par ligue, chaque Mega Edition à part | Choix de l'utilisateur : l'inventaire reflète l'équipe de chaque ligue. Une entrée n'est comparée que dans sa ligue |
| 94 | Entrée classée comme la forme de sa famille la mieux classée dans sa ligue ; comparaison sur toute la famille | Choix de l'utilisateur : Draco vaut mieux que Dracolosse en Super, donc la dernière évolution n'est pas la bonne cible ; un Minidraco gardé pour devenir Draco doit compter face à un Draco. Le rang d'espèce tranche, sans option `--as` |
| 95 | Fichier d'inventaire illisible : erreur sans écriture ; forme inconnue : listée, signalée | À l'inverse du cache (copie périmée servie, fichier corrompu ignoré), rien d'autre ne détient ces données : les lire comme vides puis sauvegarder les effacerait |
| 96 | Commandes d'écriture `*Command` dans `Application/`, exclues du conteneur comme `*Query` | Ajouter ou retirer n'est pas une requête ; le suffixe dit qu'elles changent l'état. Elles restent des messages, pas des services |
| 97 | `app:stardust:info` (alias `stardust`), sans argument, sans port ni données | La vraie question est « où gagner ma poussière » : captures, Combat GO et baies côte à côte. Règles du jeu en quelques constantes, un service de domaine par source. Un nombre de défenseurs en argument ne servait à rien : l'arène pleine avec son cumul donne chaque taille d'arène sur ses premières lignes. Le handler ne prend pas de query, la réponse ne dépend de rien |
| 98 | Barèmes (capture 100 / 125 boostée ; série 3 600 + 7 200 + 3 600 ; fin de saison par rang ; baies 30 × rang de série sans plafond, 10 par défenseur) pris des relevés de l'utilisateur | Aucun fichier du jeu ne les expose ; isolés dans `CatchStardust`, `BattleLeagueStardust` et `BerryStardust` comme `MegaLevelTableBuilder` et `WmoConditions` le sont pour leurs tables |
| 99 | Pièce étoile × 1,5 sur chaque récompense, arrondi vers le bas par récompense, affichée partout en `total (+ gain)` | Choix de l'utilisateur : le gain se lit sans soustraire. La Pièce s'applique récompense par récompense, d'où l'arrondi sur chacune et non sur le total (100 captures boostées : 18 700, pas 18 750) |
| 100 | `app:data:build` masquée (`hidden`) et exclue du PHAR par `box.json` | Elle ne tourne que depuis un checkout (dev, puis la CI juste avant `box compile`) : dans l'archive en lecture seule elle ne pouvait qu'échouer. L'exclure retire aussi `Infrastructure/Build`, et la garde `PharEnvironment` de la commande disparaît. Préféré à `when@dev`, qui l'aurait liée à l'environnement plutôt qu'au checkout. `GameDataPaths` passe en `#[Autowire]` : une définition YAML nommerait une classe absente de l'archive et ferait échouer le conteneur |
| 101 | Un fichier d'inventaire par compte (`inventory/<compte>.json`), choisi par `--account` | Choix de l'utilisateur : un fichier corrompu ne touche qu'un compte, et chacun garde sa numérotation. Le format du fichier ne change pas ; `POGO_INVENTORY` nomme désormais un répertoire. Pas de migration : aucun inventaire n'existait encore |
| 102 | Réglages = défauts des options de commande, clé JSON `famille.option`, dans `$XDG_CONFIG_HOME` ; `app:settings:show/set` | Demande de l'utilisateur : chaque option potentiellement réglable, sans liste à tenir à jour. La clé se lit sur la ligne de commande, le fichier s'édite à la main. « settings » plutôt que « config », à sa demande |
| 103 | Pas de `--traded` | Retiré à la demande de l'utilisateur : `pvp.floor` / `inventory.floor` règlent le plancher habituel, `--floor` reste pour l'exception |
| 104 | Réglages appliqués par un écouteur `console.command` qui change le défaut de l'option, couche UI seule | Domaine et handlers ignorent les réglages ; changer la valeur de l'input ne tient pas, `Command::run()` le relie à nouveau. Les drapeaux restent hors réglage, faute de pouvoir les retirer en ligne de commande |
| 105 | Réglage vérifié à l'enregistrement, par nom d'option, avec le parseur de la commande | Demande de l'utilisateur : une faute doit tomber à `settings:set`, pas à la commande suivante. Le nom d'option suffit (`--floor` a le même sens partout) ; une option sans vérification connue est acceptée, pour ne pas bloquer une option nouvelle |
| 106 | `app:settings:wizard` plutôt qu'une question au premier usage de l'inventaire | Choix de l'utilisateur : lancé quand on veut, il ne surprend pas au milieu d'une commande, et c'est l'endroit pour expliquer qu'une option tapée l'emporte. Il n'écrit que des réglages existants ; sans compte réglé, l'inventaire le rappelle d'une ligne au lieu de demander. « wizard » plutôt que « setup », à sa demande |
| 107 | `laravel/prompts` pour les cases à cocher des ligues du wizard, avec repli sur `ChoiceQuestion` | Demande de l'utilisateur. Symfony Console n'a pas de sélection multiple aux flèches. Le paquet est maintenu, ne dépend que de `symfony/console` (déjà là) et ne sert qu'à `LeaguePicker`, en couche UI. Le repli couvre ce qu'il ne gère pas : pas de TTY, Windows (`stty`), et les tests — son `Prompt::fake()` exige Mockery, absent du projet |
| 108 | `--at=auto` : la position devinée par l'IP, défaut de l'option et réponse proposée par le wizard | Demande de l'utilisateur : une fois le lieu habituel réglé, il fallait pouvoir revenir à la position courante en déplacement. Une valeur de `--at` plutôt qu'un drapeau `--here` (essayé puis retiré) : une seule option, pas de conflit entre deux, et la même valeur sert de réglage (`weather.at: auto`) et de défaut visible dans l'aide. « auto » plutôt que « here », qui promettrait une précision de GPS ; seul le mot exact compte, « Autun » reste un lieu (`PlaceOption`) |
| 109 | `app:settings:set` cachée (`hidden`), mais gardée dans le PHAR | Choix de l'utilisateur : pour l'instant, on règle par le wizard, et à la main dans le fichier pour ce qu'il ne demande pas. Contrairement à `app:data:build` (décision 100), elle n'est pas retirée du PHAR : elle ne coûte rien, sert aux scripts, et revenir en arrière ne demandera que d'enlever `hidden`. Plus aucun message visible n'y renvoie |
| 110 | `app:inventory:add` sans `--cp` ni `--floor` ; `format` jamais réglable | Choix de l'utilisateur : l'inventaire ne sert qu'aux rangs, la déduction et le plancher restent à `app:pvp:rank`. Les IVs doivent être complets, avec un message propre à l'inventaire plutôt que celui qui renvoie à `--cp`. `format` est exclu des réglages pour toutes les commandes (`ConfigurableOptions::NEVER`) : la sortie se choisit à chaque exécution, un JSON par défaut surprendrait au terminal |
| 111 | Langue d'affichage en réglage global (`global.locale`), `cp` jamais réglable | Choix de l'utilisateur : c'est la langue de l'application, pas d'une commande. Une section `global` garde la forme `famille.option` du fichier ; seules les options listées dans `APPLICATION_WIDE` y vont. Un PC décrit un Pokémon, pas un défaut |
| 112 | Chaque commande nomme, sur une ligne, les réglages qu'elle a pris ; sur la sortie d'erreur | Demande de l'utilisateur : un résultat ne doit pas dépendre d'un défaut oublié. Seules les options laissées de côté sont citées (une option tapée l'emporte, la citer tromperait). La sortie d'erreur garde le JSON et les pipes propres ; `-q` la tait. Remplace l'affichage réservé à `-v` |
| 113 | Lecture et écriture des fichiers du joueur partagées dans `JsonDocumentFile`, sur `symfony/filesystem` (dépendance directe) ; contraintes Symfony en `^8.1`, la version tenue par `extra.symfony.require` | Inventaire et réglages recopiaient le même code ; `dumpFile` y gagne un nom temporaire aléatoire (deux exécutions ne se marchent plus dessus) et une vraie erreur de lecture. Revers assumés : un crash entre écriture et renommage peut laisser un fichier temporaire, et un lien symbolique est suivi plutôt que remplacé (un fichier gardé dans un dépôt de dotfiles y reste). Le dossier reste créé en 0775 quel que soit l'umask. Flex fixe déjà toute la famille Symfony à `8.1.*` : passer en 8.2 ne demandera que cette ligne |
| 114 | Complétion shell native de Symfony (`pogo completion zsh`) pour l'argument `pokemon`, en plus de la correction des fautes ; le mot complété est le nom replié | Demande de l'utilisateur. La saisie interactive `laravel/prompts` `search()` a été essayée puis retirée : trop lente à l'usage. La complétion native ne coûte aucune dépendance et marche depuis le PHAR (`_complete`, conteneur `prod`, ~0,3 s par Tab). Le nom replié (`melodelfe`) passe le filtre par préfixe du shell sans accents ni majuscules, et sans guillemets pour « Raichu (d'Alola) » ; le nom affiché sert de description. La correction des fautes reste : elle couvre ce qui est tapé sans Tab |
| 115 | Sous un plancher non nul, le meilleur spread sans plancher reste affiché, sur une ligne à part et pas en vert | Demande de l'utilisateur : comparer d'un coup d'œil ce qu'une source donne à ce qu'une capture sauvage donnerait. Préféré à un `--floor` répétable (envisagé puis écarté) : le plancher 0 est le seul point de comparaison voulu, une option de plus ne servirait pas. Pas de vert : la couleur dit « imbattable d'où vient le Pokémon », ce spread ne peut pas en venir |
| 116 | Plus de refus des IVs sous le plancher ; la déduction par le PC ignore le plancher | Choix de l'utilisateur, pour régler `pvp.floor` sur `best` sans taper `--floor=wild` à chaque capture sauvage. Le plancher dit ce qu'on peut chasser, pas d'où vient le Pokémon en main. Un repli sur `wild` limité aux planchers venus des réglages a été proposé puis écarté : une règle de plus pour un refus qui ne protégeait de rien |
| 117 | Le wizard demande le plancher par l'usage (« second account to reroll IVs », niveau d'amitié), pas par le nombre | Formulation de l'utilisateur : le joueur connaît son niveau d'amitié, pas le plancher qu'il donne. Un seul choix dans une liste, comme les cases à cocher des ligues (décision 107), à la demande de l'utilisateur : rien à taper ni à retenir. La réponse est l'alias de `--floor` (`best`), `no` retire le réglage ; `lucky` n'est pas proposé, un échange chanceux ne se prévoit pas |
| 118 | Le compte devient un réglage global (`global.account`), changé par `switch` | Le compte n'est pas une affaire de famille : la même personne joue le même compte quelle que soit la commande, comme la langue. `switch` évite de retenir la clé et liste les comptes qui ont un inventaire (port `InventoryRepository::accounts()`) |
| 119 | Une clé de réglage inconnue arrête la commande, sauf `settings:*` et `account:*` | Renommer `inventory.account` en `global.account` aurait sinon renvoyé l'inventaire sur `default` sans un mot. Une faute de frappe a le même effet. Les commandes qui écrivent les réglages tournent quand même, pour les corriger |
| 120 | Les renommages de réglages passent par une table de migrations tenue à la main (`SettingMigrations`), appliquée par `settings --fix` seulement | Issue #2. Deviner la nouvelle clé (distance d'édition, même option ailleurs) se tromperait sans le dire ; migrer en silence au démarrage écrirait dans le fichier du joueur sans qu'il l'ait demandé. Le plan est montré par l'erreur, puis appliqué tel quel, d'un seul calcul (`SettingsRepair`) |
| 121 | Une branche et une PR par issue, plus de commit direct sur `main` | Choix de l'utilisateur, après une release v3.2.0 qui a embarqué une feature pas prête, commitée sur `main` en attendant : il a fallu réécrire `main` et republier. `main` ne porte plus que ce qui est fusionné, et la CI (`ci.yml`, déjà sur `pull_request`) tourne avant la fusion |
| 122 | Retour au commit direct sur `main`, sans branche ni PR ; `Closes #N` dans le message du commit qui règle une issue | Choix de l'utilisateur, qui annule la décision 121 : il ne relit pas le code, une PR n'ajoutait qu'une étape. Le risque de la v3.2.0 se traite autrement : on ne commite sur `main` que du travail fini, et la release montre la liste des commits embarqués avant de taguer |
| 123 | `app:inventory:add <pokemon> <league> <ivs\|rang> [--cp]` : la ligue en argument, une seule ; un nombre seul est un rang ; `--cp` refuse un Pokémon trop monté pour sa forme retenue. Révise la décision 110 | Demande de l'utilisateur, qui note le rang dans le surnom en jeu et ne veut pas réévaluer le Pokémon. Un rang ne vaut que pour une ligue, et un Pokémon monté n'entre que dans une : le garder pour deux se fait en deux ajouts. Le rang est celui de la forme retenue par l'inventaire au plafond 50, le seul que `list` affiche ensuite ; le départage des égalités en fait un seul spread, et seuls les IVs sont écrits. `#12` a été écarté : le shell avale tout ce qui suit un `#` en début de mot, sans erreur. Le PC n'est pas gardé, il ne sert qu'à vérifier à l'ajout |
| 124 | `app:inventory:remove [pokemon] [league] [ivs\|rang]` comme `add`, chaque argument réduisant la recherche, choix aux flèches s'il en reste plusieurs ; le numéro d'entrée ne s'affiche plus | Demande de l'utilisateur, qui n'aimait pas retirer par un numéro : il désigne un Pokémon par ce qu'il lit en jeu, comme à l'ajout. Le filtrage est celui de `ListInventoryQuery`, que `remove` réutilise avant de retirer par l'id. Un seul résultat est retiré sans confirmation (le message le nomme, un ajout le rend) ; sans interaction, plusieurs résultats arrêtent la commande plutôt que d'en choisir un. Le numéro reste l'identifiant interne, dans le fichier et en JSON |
