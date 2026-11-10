# Inventaire — `app:inventory:*`

## Question

« Quels Pokémon je garde pour chaque ligue, ce que je tiens là vaut-il mieux, et
lesquels un échange peut-il encore améliorer ? » `app:pvp:rank` ne consulte pas encore
l'inventaire.

```bash
app:inventory:add <pokemon> <league> <ivs|rang> [--cp=…] [-s] [-a compte]
app:inventory:add minidraco great 0/15/15
app:inventory:add minidraco great 6 --cp=815      # rang 6, refusé si Draco dépasse 1500
app:inventory:list [-l great] [--format=json]
app:inventory:check [-l great …] [--floor=best] [--format=json]   # issue #1
app:inventory:remove [pokemon] [league] [ivs|rang] [-s] # comme add ; choix aux flèches
```

L'argument `pokemon` de `app:inventory:add` se résout comme celui de `app:pvp:rank`,
correction des fautes comprise, et se complète de la même façon avec Tab
(`specs/pvp-rank.md`, « Tab complète le nom dans le shell ») ; `league` se complète
aussi.

## Une ligue par ajout

La ligue est un **argument** obligatoire, une seule (décision 123). Monté, un Pokémon
n'entre que dans une ligue, à cause du PC ; le garder pour deux (un Minidraco pas encore
monté, ou Super et Mega Super qui plafonnent toutes deux à 1500) se fait en l'ajoutant
deux fois. `app:inventory:list` garde son `-l`, facultatif : c'est un filtre.

## IVs ou rang

Le troisième argument est **soit les trois IVs** (`0/15/15`, mêmes séparateurs que
`app:pvp:rank`), **soit un rang** : un nombre seul, sans séparateur, de 1 à 4096. Le
joueur qui note le rang dans le surnom du Pokémon n'a pas à le réévaluer. Pas de `#` :
en début de mot, le shell le prend pour un commentaire et avale la suite sans un mot.

Le rang est celui **de la forme que l'inventaire retient** dans la ligue (« Classer une
entrée »), au plafond 50 : le nombre qu'affiche ensuite `list`, et celui du tableau de
cette forme dans `app:pvp:rank` sans `--best-buddy`. Un Minidraco en Super se donne par
son rang en Draco. Les égalités de produit de stats étant départagées (les IVs les plus
hauts d'abord), un rang désigne un seul spread. On garde les IVs dans le fichier, jamais
le rang : une mise à jour des données ne fausse rien de ce qui est gardé. Un rang hors
du classement, ou une forme qui ne peut pas passer sous le plafond, est refusé.

La sortie de l'ajout montre les IVs retrouvés et la forme qui a servi : un rang pris
dans un autre outil, ou pour une autre forme, se voit tout de suite.

## Vérifier par le PC

`--cp` (facultatif) donne le PC affiché en jeu par la forme en main. Les IVs étant
connus, le PC donne le niveau (le plus bas s'il y en a deux, jusqu'au niveau 51). Évoluer
garde le niveau : si la forme retenue y dépasse le plafond de la ligue, l'ajout est
**refusé**, rien n'est écrit. Un Minidraco trop monté pour faire un Draco de Super ne
s'ajoute pas en Super. Aucun niveau ne donnant ce PC → refus aussi : les IVs ou le PC
sont faux, ce qui attrape au passage un rang mal noté. Sans plafond (Master), seule
cette cohérence est vérifiée. Une méga se compare à son PC au même niveau, sans les
niveaux qu'offre le dernier Mega Level, comme le classement de l'inventaire. Le PC n'est
pas gardé : il change à chaque montée.

## Comptes

Chaque compte du jeu a son inventaire, numéroté à part : un fichier
`inventory/<compte>.json`. `-a, --account` le choisit, sinon le réglage
`global.account` (que `switch` change), sinon le compte `default`. Le nom passe en minuscules (le jeu ignore la casse des noms de
dresseur) et se limite à ce qui peut nommer un fichier.

L'ajout ne prend pas de `--floor` (décision 110) : peser un plancher se fait avec
`app:pvp:rank`, et des IVs inconnus (`15/x/14`) n'y sont pas acceptés.

## Modèle

Une **entrée** = une forme (l'id porte l'obscur), des IVs complets, **une ligue**, une
date. Le même Pokémon gardé pour deux ligues fait deux entrées : chaque ligue est une
équipe, et chaque Mega Edition est une ligue à part entière. Chaque entrée porte un numéro, jamais
réutilisé après une suppression ; il reste dans le fichier et la sortie JSON, mais ne
s'affiche plus (décision 124) : le joueur désigne un Pokémon par ce qu'il lit en jeu. Même forme, mêmes IVs, même ligue → refusé (doublon).

## Classer une entrée

Une entrée est classée comme **la forme de sa famille que sa ligue classe le mieux**
(rang d'espèce de pvpoke), pas comme sa dernière évolution : un Minidraco gardé en Super
compte comme Draco, en Hyper comme Dracolosse. Une méga n'est candidate qu'en Mega
Edition. Puis ses IVs sont classés comme cette forme, au plafond 50. `InventoryRanker`
garde chaque classement de 4096 spreads en mémoire le temps d'une commande : une famille
ne se calcule qu'une fois par ligue.

## Lister

`list` groupe par ligue (dans l'ordre de `League::cases()`), puis trie chaque ligue par
**rang d'espèce** de la forme retenue (les meilleures espèces de la ligue d'abord), puis
par **rang d'IV** comme cette forme ; une forme non classée ou un spread qui ne passe pas
sous le plafond descend en bas de sa ligue. À rangs égaux (le plus souvent deux Pokémon
de la même famille, comptés comme la même forme), **l'ordre alphabétique** du nom affiché
départage, dans la langue d'affichage, sans compter accents ni majuscules. Ce dernier
départage se fait à l'affichage (`InventoryOrder`) : le handler ne connaît pas les noms.

## Retirer

`remove` prend **les arguments de `add`**, tous facultatifs, chacun réduisant la
recherche : Pokémon, ligue, IVs ou rang (lu comme à l'ajout, ce qui demande Pokémon et
ligue — l'ordre des arguments les impose). Un nom seul trouve aussi la forme obscure ;
`-s` ne garde que l'obscure. Une seule entrée trouvée → retirée, sans confirmation : le
message la nomme, et la rajouter suffit à défaire. Plusieurs → choix aux flèches
(`laravel/prompts`, tapé hors terminal comme le wizard), dans l'ordre de `list` ; sans
interaction (`-n`), la liste est affichée et rien n'est retiré (code 1). Rien → erreur.
Sans argument, le choix porte sur tout l'inventaire du compte.

Une entrée se décrit sans son numéro : « Dratini 0/15/15, Great League — IVs #6 as
Dragonair », au choix comme au message de retrait (`KeptPokemonLabel`).

## Comparer

À l'ajout, l'entrée est comparée aux autres de **sa ligue** qui peuvent devenir la même
forme (ou le sont déjà) : la meilleure n'est nommée que si elle la bat. Un Dracolosse
gardé ne bat pas un Minidraco en Super, puisqu'il ne peut plus être Draco.

## Stockage

Un fichier JSON versionné (`InventoryDocument`), lu et écrit par `JsonDocumentFile`,
commun aux réglages (décision 113) : écriture dans un fichier temporaire au nom aléatoire
puis renommage (`Filesystem::dumpFile`), dossier créé en 0775. Un fichier illisible est une **erreur**, jamais un inventaire vide : la
sauvegarde suivante effacerait ce que le joueur a gardé. Une forme que les données du
jeu ne connaissent plus reste listée, signalée, plutôt que de faire échouer la commande.

## Vérifier ce qu'un échange peut améliorer — `check`

Question (issue #1) : « Parmi ce que je garde, lesquels un échange peut-il encore
améliorer, avec quelle chance, et lesquels sont finis ? »

- **`--floor`** : le plancher de l'échange envisagé, comme `app:pvp:rank` (entier ou
  alias `best`, `lucky`…). Pas de raccourci : `-f` est `--format` partout. **`-l`**,
  répétable : les ligues à vérifier. Par défaut, les deux viennent de **`pvp.floor`** et
  **`pvp.league`**, ce que demande le wizard (décision 125, `specs/settings.md`). Sans
  plancher, ou plancher nul : erreur (code 2), la question n'a pas de sens.
- **Peser une entrée** : classée comme dans l'inventaire (forme retenue, plafond 50), elle
  est comparée à **chaque spread dont les trois IVs sont ≥ plancher** et qui passe sous
  le plafond. Un échange tire chaque IV uniformément du plancher à 15 : ces spreads sont
  équiprobables, et la part de ceux dont le stat product est **strictement** supérieur
  est la **chance qu'un échange fasse mieux** (`TradeOdds`, `IvRanking::tradeOdds`).
  Une égalité ne bat pas : les deux sont interchangeables en jeu, comme pour l'ordre du
  classement. Une capture sauvage sous le plancher peut être finie.
- **Sortie texte** : un tableau par ligue des entrées qu'un échange peut améliorer
  (Pokémon, IVs, forme retenue, rang d'IV, « 0.8 % (11 / 1331) », meilleur spread du
  plancher avec son rang), dans l'ordre de `list`. Puis, par ligue, une ligne qui nomme
  celles qu'aucun échange ne bat (« No trade can beat — Great League: … »), et une pour
  celles qui ne se pèsent pas : forme inconnue des données, forme hors plafond, plancher
  qui ne laisse rien sous le plafond. Inventaire vide pour ces ligues : un message, code 0.
- **JSON** : chaque entrée comme `list --format=json`, plus `beatenWithinFloor`,
  `spreadsWithinFloor` et `bestWithinFloor` (`iv`, `position`) ; `null` quand elle ne se
  pèse pas.

## Tests attendus

- Ajout : par IVs, par rang (lu comme la forme retenue : `0/15/15` est le rang 6 de Draco
  en Super), rang d'une forme qui ne passe pas sous le plafond refusé ; `--cp` qui laisse
  l'évolution sous le plafond accepté (Minidraco 0/15/15 à 815 PC : Draco à 1490),
  au-dessus refusé sans rien écrire (822 PC : 1502), PC qu'aucun niveau ne donne refusé,
  ligue sans plafond qui ne vérifie que la cohérence ; doublon refusé.
- Liste : par ligue, rang d'espèce puis rang d'IV ; à rangs égaux, l'ordre alphabétique
  dans la langue d'affichage (Haunter avant Raichu, Raichu avant Spectrum).
- Retrait : les trois arguments désignent une entrée ; plusieurs correspondances
  choisies (tapé hors terminal) ou, avec `-n`, listées sans rien retirer ; un nom seul
  trouve aussi l'obscur, `-s` seulement l'obscur ; rien ne correspond → erreur.
- Échange (`check`) : le meilleur spread du plancher n'est battu par rien ; une entrée sous
  le plancher est pesée contre les spreads du plancher (nombre, meilleur) ; une égalité
  ne bat pas ; plancher et ligues pris de `pvp.floor` / `pvp.league`, `--floor` tapé
  l'emporte ; plancher nul refusé ; forme inconnue non pesée ; inventaire vide : un
  message, code 0.
