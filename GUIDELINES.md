# GUIDELINES — pokemongo-toolbox

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
| Classements PvP par ligue | pvpoke (JSON, GitHub) | `.../src/data/rankings/{all,mega}/overall/rankings-{1500,2500,10000}.json` |
| Stats de base, attaques, tags, `eliteMoves` | pvpoke gamemaster | `.../src/data/gamemaster.json` |
| Moveset recommandé par ligue | déjà dans les fichiers de classement (clé `moveset`) | — |
| Noms d'attaques (fr) | PokéAPI, **au build** | `.../data/v2/csv/moves.csv` + `move_names.csv` |
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
l'exécution, derrière un cache disque (`var/cache/pogo/` depuis un checkout,
`~/.cache/pokemongo-toolbox/` depuis le PHAR) avec un TTL de 24 h. Aucune
commande métier ne fait d'appel HTTP direct : elle demande la donnée à un *port* (§6),
dont l'implémentation décide de servir le cache ou de rafraîchir. Si la source est
injoignable, la copie périmée est servie plutôt que d'échouer.

Pour forcer une mise à jour : supprimer le répertoire de cache (classements) ou relancer
`pogo:data:build` (données de jeu).

Deux fichiers sont **générés puis commités** par `pogo:data:build`, et lus sans réseau :

- `data/species.json` — 1742 formes : identifiant, n° de dex, stats de base, slugs de
  forme. **Aucun nom** : ils vivent dans les catalogues de traduction ;
- `data/cpm.json` — 101 niveaux (1 → 51 par pas de 0,5), demi-niveaux déjà interpolés ;
- `translations/pokemon.{fr,en}.xlf` — 1025 noms par langue, clé = n° de dex ;
- `translations/pokemon_form.en.xlf` — 119 libellés de forme, clé = slug (`shadow`, `mega_x`) ;
- `data/moves.json` — 351 attaques : puissance, énergie, drapeau `mega` (15 exclusives) ;
- `data/mega-levels.json` — progression des niveaux de méga ;
- `translations/pokemon_move.{fr,en}.xlf` — 351 attaques en anglais, 346 en français.

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

Options : `--league` (répétable : `great`, `ultra`, `master`, `mega-great`,
`mega-ultra`, `mega-master`), `--shadow`, `--mega`,
`--best-buddy` (niveau 51), `--format=text|json`, `--locale=fr|en`.

Un nom saisi dans **l'une ou l'autre langue** résout : l'index de recherche contient les
noms de toutes les locales embarquées, indépendamment de la langue d'affichage. Les
formes aussi : « Raichu (d'Alola) » et « Raichu (Alolan) » désignent la même espèce.

**La langue de la réponse est celle de la question.** Sans `--locale`, demander
« Ectoplasma » répond en français, « Gengar » ou « 94 » en anglais — qui est le défaut,
car c'est la langue du méta et des noms d'attaques. 165 des 1025 espèces s'écrivent
pareil dans les deux langues (Pikachu, les Nidoran…) : deviner y serait un pile ou face,
donc le défaut s'applique et `--locale=fr` reste là pour trancher.

Le coût de cette détection est nul : deux lectures dans un catalogue déjà chargé
(0,26 s avec ou sans, mesuré). C'est pourquoi elle est préférée à un drapeau `--fr`, qui
ferait doublon avec `--locale` tout en obligeant à le taper à chaque fois.

Sortie d'erreur : identifiant inconnu → code 1 ; identifiant ambigu → liste des formes
candidates et code 1 ; IVs invalides → code 2 (`Command::INVALID`).

La résolution de l'identifiant est **insensible à la casse et aux accents**
(`ectoplasma` = `Ectoplasma`). Un nom ambigu liste les candidats au lieu de deviner.

**Une forme couverte par un drapeau ne se demande pas par son nom.** « Ectoplasma (Méga) »
et « Gengar (Shadow) » ne résolvent rien : on écrit `ectoplasma --mega` ou
`ectoplasma --shadow`. Offrir les deux voies ferait deux orthographes à connaître par
langue pour le même résultat.

Les formes **sans drapeau** gardent leur nom comme seule voie d'accès : régionales
(`Raichu (d'Alola)`), Origine/Alternative, Totémique… Elles se combinent aux drapeaux :
`"Giratina (Alternative)" --shadow` donne bien la forme alternative obscure.

### 4.1 bis — Mégas et Mega Editions

Depuis la saison *Twilight Trails* (8 septembre 2026), les méga-évolutions sont admises
en combat de ligue, mais **uniquement dans les Mega Editions** — elles restent interdites
dans les ligues standard. Par défaut la commande choisit donc les trois ligues qui
correspondent à l'espèce : Mega Editions pour une méga, ligues standard sinon. Classer
une méga en Great League ne répondrait jamais que « non classé ».

**Seize mégas portent le tag `supermega`** : une attaque chargée supplémentaire,
exclusive, suffixée `_PLUS` dans les données et marquée `isMegaMove`. Le moveset les
affiche naturellement à trois attaques chargées, et une note le signale.

**Les niveaux de méga sont binaires, pas progressifs.** `--mega1` à `--mega4` existent,
`--mega` valant `--mega3` (le niveau qu'on vise en PvP). Mais d'après le game master,
relevé sur les 139 templates `MEGA_EVOLUTION_LEVEL_*` :

| | niv. 1 | niv. 2 | niv. 3 | niv. 4 |
|---|---|---|---|---|
| `selfCpBoostAdditionalLevel` | absent | absent | absent | **2** |
| `sameTypeAttackBoost` | 1,3 | 1,3 | 1,3 | 1,3 |
| Rechargement | 7 j | 5 j | 3 j | 1 j |
| Bonbons | 1 | 1 | 2 | 3 |

Donc **seul le niveau 4 change le CP**, et `--mega2` affiche légitimement la même cible
que `--mega3`. Le `sameTypeAttackBoost` ne varie à aucun niveau et s'applique aux
**coéquipiers en raid**, pas à la méga.

**En revanche le niveau multiplie la puissance de base de l'attaque exclusive** —
×1,0 / ×1,1 / ×1,2 / ×1,3 du niveau 1 au niveau 4. C'est la seule progression qui se voie
en combat. Le pied de tableau l'affiche en **valeur de base + bonus** (« Rafale Psy+,
puissance 60 + 12 ») plutôt qu'en multiplicateur : on lit le gain sans faire la
multiplication de tête.

L'attaque est reconnue par le drapeau `isMegaMove` du jeu, pas par le « + » de son nom.
Sa puissance et son coût en énergie vivent dans `data/moves.json`, généré au build — la
même donnée servira à comparer le DPE d'une `_PLUS` à sa version de base.

⚠️ **Cette valeur est la seule du projet qu'aucun fichier amont ne confirme.** La liste
exhaustive des clés de `MEGA_EVOLUTION_LEVEL_*` n'en contient aucune trace ; elle vient
de [pokemongo.fandom.com](https://pokemongo.fandom.com/wiki/Mega_Evolution#Additional_Charged_Attacks),
qui cite une analyse Silph Road. Elle est donc écrite en constante documentée dans
`MegaLevelTableBuilder`, pas déduite des données — à revérifier si le méta bouge.

**On demande une méga avec `--mega`**, pas en tapant son nom de forme : `altaria --mega`
plutôt que `"Altaria (Mega)"`. Trois espèces ont deux mégas (Dracaufeu, Raichu, Mewtwo
X/Y). `--mega-x` et `--mega-y` départagent ces trois-là, et le message d'ambiguïté les
suggère : sans eux, le seul recours serait de taper l'identifiant interne
(`charizard_mega_x`), que l'utilisateur n'a pas à connaître. Demander une variante
implique la méga, donc `--mega-x` seul suffit. Aucune forme n'étant à la fois méga et
obscure, `--mega` l'emporte sur `--shadow`.

**CP de montée.** Une Mega Edition plafonne le CP de la **forme méga**, pas de la forme
de base. Le nombre affiché en jeu pendant la montée est donc celui de la forme de base,
plus bas — il a sa propre colonne « Pre-Mega CP », qui n'apparaît que lorsqu'une méga est
à l'écran : 916 CP pour Méga Altaria en Mega Great. Elle suit la colonne des IVs ligne
par ligne — tes IVs d'abord, le spread imbattable en dessous **en vert**.

**Une ligue sans plafond n'affiche ni « Pre-Mega CP » ni niveau.** Sans cap, il n'y a aucun
CP à viser, et tous les spreads atteignent le niveau plafond : le répéter à chaque ligne
ne dirait rien. En Master on lit donc « CP 3576 » et un tiret dans la colonne hors méga.

Cette mécanique est **déduite des données de pvpoke**, pas lue dans une documentation :
en recalculant leurs mégas sous plafond, notre résultat tombe à 1,5 % du leur, l'écart
résiduel venant de ce que leur `stats` décrit leur build recommandé et non le stat
product maximal. Plafonner la forme de base à la place donnait 70 % d'écart — hypothèse
écartée.

---

### 4.2 Sans IVs — rang d'espèce

Position de l'espèce dans le classement de chaque ligue, telle que publiée par pvpoke :
Great (cap 1500), Ultra (2500), Master (10000). On affiche le rang, le score et le
**moveset recommandé** (clé `moveset` du classement : l'attaque rapide, puis les
chargées).

Une attaque figurant dans les `eliteMoves` de l'espèce est marquée `*` : elle coûte un
TM Élite, ce qu'il vaut mieux savoir avant de viser le moveset. Une légende n'apparaît
que si au moins une attaque affichée l'est.

### 4.3 Avec IVs — rang interne à l'espèce

Calcul local, sans réseau :

1. Pour chacune des **4096** combinaisons d'IV (atk, def, sta ∈ [0,15]) :
   - trouver le **niveau maximal** (pas de 0,5 ; plafond 50, ou 51 en best buddy)
     tel que `CP ≤ cap` de la ligue ;
   - `CP = max(10, floor( (base_atk+iva) × (base_def+ivd)^0.5 × (base_sta+ivs)^0.5 × CPM² / 10 ))` ;
   - `statProduct = (base_atk+iva)×CPM × (base_def+ivd)×CPM × floor((base_sta+ivs)×CPM)`.
2. Trier par `statProduct` décroissant, **puis par total d'IVs décroissant**. Les
   égalités parfaites sont fréquentes (au plafond, 15/15/15 et 15/15/14 plafonnent aux
   mêmes PV, donc au même stat product au bit près) : les spreads sont alors
   interchangeables en jeu, et c'est celui que le joueur reconnaît qui l'emporte.
3. Sortie par ligue : le **rang** des IVs demandés avec `% du rang 1`, CP et niveau,
   **et le meilleur spread de la ligue** — celui qu'il faut chercher. Ce dernier est
   calculé même sans IVs fournis, et masqué en sortie texte quand les IVs demandés sont
   déjà le rang 1, puisque la ligne ne ferait que se répéter. En `--format=json` il est
   toujours présent : un consommateur machine ne veut pas d'un champ conditionnel.

En Master League le meilleur est 15/15/15 : la donnée dit d'elle-même qu'une ligue sans
plafond ne récompense pas la recherche de spreads, sans avoir à l'expliquer en prose.

Les demi-niveaux ne sont pas dans la table PokeMiners : ils s'interpolent par
`CPM(L+0.5) = sqrt( (CPM(L)² + CPM(L+1)²) / 2 )`.

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
3. Une feature = domaine + cas d'usage + adapters + tests, dans le même commit.
4. **Aligner la version de PHP au bootstrap** : `composer.json` déclare encore
   `"php": ">=8.4"` alors que le projet tourne en 8.5. Passer à `">=8.5"` (et régénérer
   le `platform` du `composer.lock`) en même temps que l'ajout des dépendances, pour que
   la contrainte, le `mago.toml` et le runtime disent la même chose.
5. Dépendances : toujours `composer require`, jamais d'édition manuelle de `composer.json`
   (Flex écrit les recettes dans `config/packages/`).
6. Les caches d'exécution ne sont **jamais** commités ; les données générées stables
   (`data/*.json`, `translations/*.xlf`) le sont.

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