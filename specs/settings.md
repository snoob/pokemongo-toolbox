# Réglages — `app:settings:*`

```bash
app:settings:wizard                      # alias : wizard
app:settings:show [--all]                # alias : settings
app:settings:set pvp.floor best          # cachée (décision 109)
app:settings:set pvp.floor               # sans valeur : retire le réglage
```

```json
{"inventory": {"account": "snoob2555"}, "pvp": {"floor": "5"}, "weather": {"at": "Villemomble, France"}}
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
  `inventory.account`, `pvp.league`, `pvp.floor`, `weather.at` — avec la valeur actuelle
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
