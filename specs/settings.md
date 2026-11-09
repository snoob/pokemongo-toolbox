# Réglages — `app:settings:*`

```bash
app:settings:wizard                      # alias : wizard
app:settings:show [--all]                # alias : settings
app:settings:show --fix                  # remet d'aplomb un fichier refusé (issue #2)
app:settings:set pvp.floor best          # cachée (décision 109)
app:settings:set pvp.floor               # sans valeur : retire le réglage
```

```json
{"global": {"account": "snoob2555"}, "pvp": {"floor": "5"}, "weather": {"at": "Villemomble, France"}}
```

**La clé JSON est l'option de la commande.** Un objet par famille (le milieu de
`app:pvp:rank`), une clé par option longue : `pvp.floor` est le défaut de `--floor` de
toutes les commandes `app:pvp:*`. Rien n'est déclaré option par option : toute option à
valeur d'une commande est réglable dès qu'elle existe, et `settings --all` les liste en
lisant les définitions des commandes.

- **Couche UI seulement.** Un réglage n'est que le défaut d'une option de ligne de
  commande : ni le domaine ni les handlers ne le voient. `ApplySettings` écoute
  `console.command` et remplace le **défaut** de chaque option réglée dans la définition
  de la commande. Pas `setOption()` : `Command::run()` relie l'input une seconde fois
  après l'événement et l'effacerait. Effet de bord utile : ce qui est tapé l'emporte sans
  rien détecter.
- **Pas de drapeaux** (`--shadow`, `--mega4`…) : réglés, on ne pourrait plus les retirer en
  ligne de commande. Ni les options de l'application (`--env`, `--verbose`), ni `--format`
  et `--cp`, qui tiennent à une exécution (`ConfigurableOptions::NEVER`).
- **`global.locale`** : la langue d'affichage se règle une fois pour toutes les commandes
  qui ont `--locale` ; une famille ne la règle pas seule (`pvp.locale` n'existe pas).
  L'entrée reste bilingue : l'index de recherche couvre les deux langues quel que soit
  l'affichage.
- **`global.account`** : de même pour le compte du jeu (décision 118). `app:account:switch`
  (alias `switch`) le change sans retenir sa clé : `switch snoob2`, ou sans nom un choix
  parmi les comptes qui ont un inventaire (`AccountPicker`, flèches dans un terminal,
  liste à taper ailleurs ; « Another account… » pour en taper un nouveau). Hors terminal
  et sans nom, il affiche le compte courant et la liste. Un compte sans inventaire est
  accepté, avec un rappel (faute de frappe possible).
- **Une clé qui ne remplit aucune option arrête toute commande** (décision 119) : faute de
  frappe ou clé d'une version antérieure (`inventory.account`). Seules `settings:*` et
  `account:*`, qui écrivent les réglages, tournent quand même, pour les corriger.
  L'erreur explique le problème avant toute correction (voir « `settings --fix` »).
- Une option répétable prend une liste ; une liste pour une option à valeur unique est
  une erreur. Chaîne ou liste vide = non réglé.
- La valeur est vérifiée à l'enregistrement par `SettingValues`, avec le parseur que les
  commandes utilisent pour cette option (`IvFloor`, `LeagueOption`, `Account`, `CpOption`,
  `text|json`, `fr|en`) ; une option sans vérification (`--at`, texte libre) est prise
  telle quelle. `settings` signale les valeurs fausses ou orphelines d'un fichier édité à
  la main (code 1). Chaque exécution nomme les réglages qu'elle a pris (« From your settings: … »). `pvp.league great ultra master` masque
  ainsi les Mega Editions de `pvp`, sans option dédiée.
- **`wizard`** sert d'installation et d'accueil : chaque question est précédée de ce
  qu'elle règle, des commandes qui s'en servent et de la façon de passer outre (un
  exemple en ligne de commande), et la fin propose les premières commandes à essayer —
  pour ne pas avoir à lire la doc avant de commencer. Il liste d'abord tous les réglages
  en place, puis pose cinq questions — `global.locale`,
  `global.account`, `pvp.league`, `pvp.floor`, `weather.at` — avec la valeur actuelle
  (ou une proposition : `default`, `great ultra master`, `no`) ; Entrée la garde, `-` la retire. Les
  ligues sont des cases à cocher (`LeaguePicker`, `laravel/prompts`) dans un terminal ;
  ailleurs (entrée fournie par un test ou un pipe, Windows), la même liste se tape, noms
  séparés par des virgules. Rien de coché = réglage retiré. Le plancher se demande par
  l'usage : « Do you use a second account to reroll IVs for PvP? At which friendship
  level? », un seul choix dans une liste (`RerollFriendshipPicker`, `select()` de
  `laravel/prompts`, même repli que les ligues). `good`, `great`, `ultra` ou `best` est
  enregistré tel quel (l'alias de `--floor`, plus lisible que le nombre), `no` retire le
  réglage. Un plancher écrit à la main que nulle amitié ne donne (`raid`) est proposé
  tel quel, pour qu'Entrée le garde. Pas de `lucky` : un échange chanceux ne se prévoit
  pas. Chaque réponse passe par `SettingValues`, le lieu par le port
  `PlaceSearch` (hors ligne, gardé tel quel). Rien n'est écrit avant la fin ; refusé hors
  terminal. Sans compte réglé, `inventory:add` et `:list` le rappellent (`AccountHint`).
- Fichier illisible : erreur, comme l'inventaire — retomber sur les défauts de l'outil
  rangerait des Pokémon dans un autre compte sans le dire.

## `settings --fix` — après un renommage d'option

« Mon fichier de réglages est refusé : comment je le remets d'aplomb sans perdre mes
valeurs ? » (issue #2). Hors périmètre : une valeur fausse sous une clé valide, que
`settings` signale déjà et qui ne bloque pas les commandes.

```
 [ERROR] Your settings file ~/.config/pokemongo-toolbox/settings.json holds keys this version no longer reads.

  inventory.account   renamed global.account in v3.2.0
  pvp.flor            no option has this name

 settings --fix will:
  - set global.account to snoob2555, the value of inventory.account
  - remove inventory.account
  - remove pvp.flor — its value "best" is lost; set it again under its right name

 Run: pogo settings --fix
```

- **Le problème s'explique avant toute correction** : l'erreur qui bloque une commande et
  `settings` sans option disent la même chose — chaque clé et pourquoi, chaque action que
  `--fix` fera, la commande à taper. `settings` affiche aussi le tableau et sort en code 1.
- **`--fix` applique ce plan, rien d'autre**, puis dit « Done: » suivi des mêmes actions
  au passé et affiche les réglages corrigés. Un fichier sain : « Nothing to fix », code
  0, fichier non réécrit. Pas de confirmation : le plan a été montré avant.
- **Table des migrations** (`SettingMigrations`, décision 120), tenue à la main, une ligne
  par changement : ancienne clé, nouvelle clé (aucune = option supprimée), version.
  - Renommage : la valeur passe telle quelle (liste comprise), l'ancienne clé est retirée.
  - Nouvelle clé déjà réglée : elle est gardée, l'ancienne retirée — « keep
    global.account = main, drop inventory.account = snoob2555 ».
  - Valeur refusée par la nouvelle option (`SettingValues`, ou une liste pour une option
    à valeur unique) : l'ancienne clé est retirée, le plan cite l'erreur et la valeur
    perdue.
  - Option supprimée, ou clé hors table (faute de frappe, édition à la main) : la clé est
    retirée, le plan cite la valeur perdue pour qu'elle puisse être réglée à nouveau.
- **Un seul calcul** (`SettingsRepair`) : le plan affiché par l'erreur, celui de
  `settings` et ce que `--fix` écrit en viennent. Le fichier corrigé est calculé avec le
  plan puis écrit en une fois (écriture atomique de `JsonDocumentFile`) ; un fichier
  illisible reste une erreur, `--fix` n'y touche pas.
- La commande proposée reprend le binaire tel qu'il a été lancé (`pogo`,
  `./pogo.phar`, `bin/console`).
