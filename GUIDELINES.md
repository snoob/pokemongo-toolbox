# GUIDELINES — pokemon-crawler

Référentiel des décisions et conventions du projet. À lire avant d'écrire du code ;
toute décision structurante nouvelle s'ajoute au §9.

---

## 1. Produit

**Toolbox Pokémon GO en ligne de commande**, à usage personnel.

Pas un Pokédex généraliste : les données du jeu principal (PokéAPI stats, types, etc.)
ne s'appliquent **pas** à Pokémon GO, qui a ses propres stats de base, ses propres
attaques et ses propres formules. Seule exception tolérée : PokéAPI comme source de
**traduction des noms** (voir §3).

Périmètre à date : classements PvP. Les raids, events et dex perso viendront après,
chacun avec sa propre source dédiée.

---

## 2. Invocation

Le cœur métier vit dans des **commandes Symfony**, préfixées `pogo:` :

```bash
php bin/console pogo:pvp:rank <pokemon> [ivs]
```

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
| Classements PvP par ligue | pvpoke (JSON, GitHub) | `raw.githubusercontent.com/pvpoke/pvpoke/master/src/data/rankings/all/overall/rankings-{1500,2500,10000}.json` |
| Stats de base, moves, tags | pvpoke gamemaster | `.../src/data/gamemaster.json` |
| Table des CP Multipliers | PokeMiners game_master | `raw.githubusercontent.com/PokeMiners/game_masters/master/latest/latest.json` → `PLAYER_LEVEL_SETTINGS` |
| Noms d'espèces (fr, en) | PokéAPI, **au build** | `raw.githubusercontent.com/PokeAPI/pokeapi/master/data/v2/csv/pokemon_species_names.csv` (un seul fichier, 12 langues) |

### Pourquoi pas pokemongohub / stadiumgaming

- **pokemongohub** (`db.pokemongohub.net/pokemon/65`) affiche Alakazam en Great League
  `#919`. `rankings-1500.json` de pvpoke place Alakazam en position **919**. Hub est un
  *affichage* des données pvpoke : scraper le HTML apporterait zéro information
  supplémentaire, pour une fragilité maximale.
- **stadiumgaming rank-checker** ne détient aucune donnée propriétaire : le rang IV
  interne à l'espèce est un calcul déterministe (§4.3) qu'on fait nous-mêmes, plus vite,
  hors-ligne, et avec les cas que le site ne couvre pas (ombres, best buddy, niveau 51).

### Cache

Le projet a **deux étages de données**, et un seul se rafraîchit tout seul.

**Étage build — manuel.** `pogo:data:build` va chercher les trois sources amont et écrit
les fichiers commités listés plus bas. Il n'est **jamais** déclenché automatiquement et
il n'existe **aucune détection de péremption** : on le relance après une mise à jour du
jeu. Au runtime ces fichiers sont lus sur disque, sans réseau — c'est ce qui rend la
toolbox utilisable hors-ligne et reproductible d'une machine à l'autre.

**Étage runtime — automatique.** Seuls les **classements** sortent sur le réseau à
l'exécution, derrière un cache disque sous `var/pogo/` avec un TTL de 24 h. Aucune
commande métier ne fait d'appel HTTP direct : elle demande la donnée à un *port* (§6),
dont l'implémentation décide de servir le cache ou de rafraîchir. Si la source est
injoignable, la copie périmée est servie plutôt que d'échouer.

Pour forcer une mise à jour : supprimer `var/pogo/` (classements) ou relancer
`pogo:data:build` (données de jeu).

Deux fichiers sont **générés puis commités** par `pogo:data:build`, et lus sans réseau :

- `data/species.json` — 1742 formes : identifiant, n° de dex, stats de base, slugs de
  forme. **Aucun nom** : ils vivent dans les catalogues de traduction ;
- `data/cpm.json` — 101 niveaux (1 → 51 par pas de 0,5), demi-niveaux déjà interpolés ;
- `translations/pokemon.{fr,en}.xlf` — 1025 noms par langue, clé = n° de dex ;
- `translations/pokemon_form.en.xlf` — 119 libellés de forme, clé = slug (`shadow`, `mega_x`).

Une exception : **`translations/pokemon_form.fr.xlf` est tenu à la main** et n'est jamais
réécrit par `pogo:data:build`. « Shadow », « Mega » ou « Alolan » n'existent pas dans le
Pokédex, donc aucune source ne les traduit. Ce qui n'y figure pas retombe sur l'anglais
via `framework.translator.fallbacks`.

Ce sont des données quasi-immuables : elles ne bougent qu'avec une mise à jour du jeu.
Seuls les **classements** sont téléchargés à l'exécution, derrière le cache.

---

## 4. Feature 1 — `pogo:pvp:rank`

### 4.1 Interface

```bash
pogo:pvp:rank ectoplasma          # nom FR
pogo:pvp:rank gengar              # nom EN
pogo:pvp:rank 94                  # n° de dex
pogo:pvp:rank ectoplasma 1/15/14  # avec IVs atk/def/sta
```

Options : `--league=great|ultra|master` (répétable, défaut : les trois), `--shadow`,
`--best-buddy` (niveau 51), `--format=text|json`, `--locale=fr|en`.

Un nom saisi dans **l'une ou l'autre langue** résout : l'index de recherche contient les
noms de toutes les locales embarquées, indépendamment de la langue d'affichage. Les
formes aussi : « Raichu (d'Alola) » et « Raichu (Alolan) » désignent la même espèce.

Sortie d'erreur : identifiant inconnu → code 1 ; identifiant ambigu → liste des formes
candidates et code 1 ; IVs invalides → code 2 (`Command::INVALID`).

La résolution de l'identifiant est **insensible à la casse et aux accents**
(`ectoplasma` = `Ectoplasma`). Un nom ambigu (plusieurs formes : Alolan, Galarian,
Mega…) liste les candidats au lieu de deviner.

### 4.2 Sans IVs — rang d'espèce

Position de l'espèce dans le classement de chaque ligue, telle que publiée par pvpoke :
Great (cap 1500), Ultra (2500), Master (10000). On affiche le rang, le score, et les
moves recommandés (`moves.fastMoves` / `chargedMoves` triés par `uses`).

### 4.3 Avec IVs — rang interne à l'espèce

Calcul local, sans réseau :

1. Pour chacune des **4096** combinaisons d'IV (atk, def, sta ∈ [0,15]) :
   - trouver le **niveau maximal** (pas de 0,5 ; plafond 50, ou 51 en best buddy)
     tel que `CP ≤ cap` de la ligue ;
   - `CP = max(10, floor( (base_atk+iva) × (base_def+ivd)^0.5 × (base_sta+ivs)^0.5 × CPM² / 10 ))` ;
   - `statProduct = (base_atk+iva)×CPM × (base_def+ivd)×CPM × floor((base_sta+ivs)×CPM)`.
2. Trier par `statProduct` décroissant → le rang 1 est le meilleur spread.
3. Sortie pour les IVs demandés : **rang**, `% du rang 1`, CP et niveau atteints.

Les demi-niveaux ne sont pas dans la table PokeMiners : ils s'interpolent par
`CPM(L+0.5) = sqrt( (CPM(L)² + CPM(L+1)²) / 2 )`.

En Master League (pas de cap), le classement se réduit à l'ordre des IVs bruts au
niveau plafond — on l'affiche quand même pour cohérence, mais sans laisser croire à un
arbitrage.

### 4.4 Tests attendus

- CP connus de référence (ex. : Ectoplasma 15/15/15 niv. 40) → valeurs exactes ;
- rang 1 en Great d'une espèce documentée (ex. : Azumarill 0/15/15-ish) ;
- résolution des trois formes d'identifiant vers la même espèce ;
- les 4096 combos produisent 4096 rangs sans trou ni doublon de position.

---

## 5. Architecture — DDD

Découpage DDD en quatre couches. Le modèle métier est le centre ; tout le reste gravite
autour et lui est remplaçable.

```
src/
  Domain/                 # modèle métier pur — zéro dépendance externe
    Pokemon/
      Model/              # Species, PokemonName, DexNumber, IvSpread
      Port/               # SpeciesCatalog (interface)
      Exception/          # SpeciesNotFound, AmbiguousSpecies, InvalidIv
    Pvp/
      Model/              # League, Cp, StatProduct, SpeciesRank, RankedSpread
      Service/            # CpCalculator, IvRankCalculator — calcul pur, sans état
      Port/               # PvpRankingCatalog, CpMultiplierTable (interfaces)
  Application/            # cas d'usage, un par intention
    Pvp/RankPokemon/      # RankPokemonQuery, RankPokemonHandler, RankPokemonResult
  Infrastructure/         # adapters — seule couche qui connaît le réseau et le disque
    Pvpoke/               # PvpokeRankingCatalog
    PokeMiners/           # PokeMinersCpMultiplierTable
    Catalog/              # JsonFileSpeciesCatalog (data/names.json)
    Cache/                # décorateurs de cache disque
  UI/Cli/Command/         # #[AsCommand] : parse, délègue, affiche
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
- Nommage : anglais pour le code et les identifiants ; français accepté dans les messages
  CLI destinés à l'utilisateur.
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
3. Une feature = domaine + cas d'usage + adapters + tests, dans le même commit.
4. **Aligner la version de PHP au bootstrap** : `composer.json` déclare encore
   `"php": ">=8.4"` alors que le projet tourne en 8.5. Passer à `">=8.5"` (et régénérer
   le `platform` du `composer.lock`) en même temps que l'ajout des dépendances, pour que
   la contrainte, le `mago.toml` et le runtime disent la même chose.
5. Dépendances : toujours `composer require`, jamais d'édition manuelle de `composer.json`
   (Flex écrit les recettes dans `config/packages/`).
6. Les données crawlées (`var/pogo/`) ne sont **jamais** commitées ; les données générées
   stables (`data/names.json`) le sont.

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
