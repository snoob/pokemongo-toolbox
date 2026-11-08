# Inventaire — `app:inventory:*`

## Question

« Quels Pokémon je garde pour chaque ligue, et ce que je tiens là vaut-il mieux ? »
Première itération : l'inventaire seul. `app:pvp:rank` ne le consulte pas encore.

```bash
app:inventory:add minidraco 0/15/15 -l great -l ultra   # une entrée par ligue
app:inventory:list [-l great] [--format=json]
app:inventory:remove 3                                   # numéro affiché par list
app:inventory:add azurill 5/15/14 -l great -a snoob2555
```

L'argument `pokemon` de `app:inventory:add` se résout comme celui de `app:pvp:rank`,
correction des fautes comprise, et se complète de la même façon avec Tab
(`specs/pvp-rank.md`, « Tab complète le nom dans le shell »).

## Comptes

Chaque compte du jeu a son inventaire, numéroté à part : un fichier
`inventory/<compte>.json`. `-a, --account` le choisit, sinon le réglage
`global.account` (que `switch` change), sinon le compte `default`. Le nom passe en minuscules (le jeu ignore la casse des noms de
dresseur) et se limite à ce qui peut nommer un fichier.

L'ajout prend trois IVs connus, sans `--cp` ni `--floor` (décision 110) : l'inventaire
ne s'intéresse qu'aux rangs. Déduire des IVs ou peser un plancher se fait avec
`app:pvp:rank`.

## Modèle

Une **entrée** = une forme (l'id porte l'obscur), des IVs complets, **une ligue**, une
date. Le même Pokémon gardé pour deux ligues fait deux entrées : chaque ligue est une
équipe, et chaque Mega Edition est une ligue à part entière. Les numéros ne sont jamais
réutilisés après une suppression. Même forme, mêmes IVs, même ligue → refusé (doublon),
tout ou rien quand plusieurs ligues sont données.

## Classer une entrée

Une entrée est classée comme **la forme de sa famille que sa ligue classe le mieux**
(rang d'espèce de pvpoke), pas comme sa dernière évolution : un Minidraco gardé en Super
compte comme Draco, en Hyper comme Dracolosse. Une méga n'est candidate qu'en Mega
Edition. Puis ses IVs sont classés comme cette forme, au plafond 50. `InventoryRanker`
garde chaque classement de 4096 spreads en mémoire le temps d'une commande : une famille
ne se calcule qu'une fois par ligue.

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
