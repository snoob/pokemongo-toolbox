# Specs

Une spec par feature : ce que la commande fait et pourquoi, du point de vue du joueur.
Ce qui vaut pour toutes les features (architecture, conventions, outillage) reste dans
[`GUIDELINES.md`](../GUIDELINES.md) ; « décision N » renvoie à son journal (§12).

| Spec | Commande |
|---|---|
| [pvp-rank.md](pvp-rank.md) | `app:pvp:rank` |
| [weather-forecast.md](weather-forecast.md) | `app:weather:forecast` |
| [inventory.md](inventory.md) | `app:inventory:*` |
| [settings.md](settings.md) | `app:settings:*` |
| [stardust.md](stardust.md) | `app:stardust:info` |

## Cycle

1. Une nouvelle feature commence par sa spec, `specs/<feature>.md`, relue avant d'écrire
   du code.
2. Un changement de comportement modifie la spec dans le même commit que le code.
3. Une décision structurante prise en chemin s'ajoute au journal de `GUIDELINES.md`, et
   la spec y renvoie par son numéro.

## Squelette

```markdown
# <Nom> — `app:<famille>:<action>`

## Question

« La question que le joueur se pose. » Ce qui est hors périmètre pour cette itération.

## Interface

Exemples de ligne de commande, options, sortie texte et `--format=json`, codes de sortie.

## Règles

Le comportement attendu, cas limites compris.

## Provenance

D'où viennent les données et les valeurs, surtout celles qu'aucun fichier du jeu ne donne.

## Tests attendus

Les cas qui prouvent que la feature fait ce que dit la spec.
```
