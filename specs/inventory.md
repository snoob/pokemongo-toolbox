# Inventaire — `app:inventory:*`

## Question

« Quels Pokémon je garde pour chaque ligue, et ce que je tiens là vaut-il mieux ? »
Première itération : l'inventaire seul. `app:pvp:rank` ne le consulte pas encore.

```bash
app:inventory:add <pokemon> <league> <ivs|rang> [--cp=…] [-s] [-a compte]
app:inventory:add minidraco great 0/15/15
app:inventory:add minidraco great 6 --cp=815      # rang 6, refusé si Draco dépasse 1500
app:inventory:list [-l great] [--format=json]
app:inventory:remove 3                              # numéro affiché par list
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
équipe, et chaque Mega Edition est une ligue à part entière. Les numéros ne sont jamais
réutilisés après une suppression. Même forme, mêmes IVs, même ligue → refusé (doublon).

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
