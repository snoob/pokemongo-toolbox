# Classement PvP — `app:pvp:rank`

## Interface

```bash
app:pvp:rank ectoplasma          # nom FR
app:pvp:rank gengar              # nom EN
app:pvp:rank 94                  # n° de dex
app:pvp:rank ectoplasma 1/15/14  # avec IVs atk/def/sta
app:pvp:rank ectoplasma 1/x/14 --cp=1478  # IV inconnu déduit du PC
```

Options : `--league` (répétable : `great`, `ultra`, `master`, `mega-great`,
`mega-ultra`, `mega-master`), `--shadow`, `--mega1` à `--mega4`,
`--floor` (voir « Plancher d'IV »), `--cp` (PC affiché en jeu), `--best-buddy`
(niveau 51), `--format=text|json`, `--locale=fr|en`.

**Le PC contrôle les IVs, et complète ceux qu'on ignore.** `--cp` donne le PC lu dans le
jeu ; un `x` à la place d'un IV le laisse déduire (seul `x` est accepté : `?` et `*` sont des
jokers du shell et devraient être entre guillemets). Le niveau est
inconnu : `IvDeducer` essaie chaque niveau jusqu'au plafond pour chaque spread qui suit le
motif, soit au plus 4096 × 101 calculs (0,13 s). Trois issues :
aucun spread ne donne ce PC → erreur, code 2 ; plusieurs le donnent → erreur, code 2, et
liste des candidats avec leur niveau (dix au plus) ; un seul → classement normal, précédé
de `Deduced 1/15/14 from CP 1478 (level 19).` (ou `CP 1478 checks out for …` quand les
trois IVs étaient tapés) et de la clé `deduction` en JSON. Un joker sans `--cp`, ou
`--cp` sans IVs, est refusé.

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

**Une faute de frappe est corrigée, et la réponse le dit.** Quand ni le nom exact ni un
début de nom ne trouvent rien, on prend le nom le plus proche par distance de Levenshtein
(`levenshtein()` natif, `TypoTolerance`) : « flozizarre » donne Florizarre, avec
`Interpreted "flozizarre" as Florizarre.` avant les tableaux et `guessedFrom` en JSON.
La tolérance croît avec la longueur — 0 faute jusqu'à 3 lettres, 1 jusqu'à 5, 2 jusqu'à
9, 3 au-delà — et un numéro de Pokédex n'est jamais corrigé. Les noms entiers passent
avant les débuts de nom (« Giratima » atteint les deux Giratina par leur début) ; deux
noms à égalité sont listés comme un nom ambigu. La langue de la réponse suit le nom le
plus proche : « flozizarre » répond en français.

**Tab complète le nom dans le shell.** Une fois `pogo completion zsh` chargé (bash et fish
aussi), `pogo pvp melo<Tab>` propose les noms qui commencent par ce qui est tapé, dans les
deux langues, toutes formes comprises : `melodelfe` décrit « Mélodelfe »,
`raichudalola` « Raichu (d'Alola) ». Le mot inséré est le nom replié (`NameNormalizer`),
pas le nom affiché : le shell ne garde que ce qui commence par les lettres tapées, donc
« melo » n'atteindrait jamais « Mélodelfe », et un nom avec espace ou apostrophe
exigerait des guillemets. Le nom replié se résout comme le nom entier, et la réponse
parle sa langue. Rien n'est proposé sans au moins une lettre. La complétion ne remplace
pas la correction des fautes : elle aide pendant la frappe, la correction rattrape ce qui
est tapé sans Tab (commande collée, script, faute déjà faite). Les deux se complètent
(décision 114). Coût : un démarrage du conteneur `prod` par Tab, ~0,3 s.

**Méga et obscur ne se demandent pas par leur nom.** « Ectoplasma (Méga) » et « Gengar
(Shadow) » ne résolvent rien. La méga s'affiche d'elle-même, comme évolution de la forme
tapée (« Évolutions ») ; l'obscur se demande avec `--shadow`. Offrir les deux voies ferait deux
orthographes à connaître par langue pour le même résultat.

Les formes **sans drapeau** gardent leur nom comme seule voie d'accès : régionales
(`Raichu (d'Alola)`), Origine/Alternative, Totémique… Elles se combinent aux drapeaux :
`"Giratina (Alternative)" --shadow` donne bien la forme alternative obscure.

## Mégas et Mega Editions

Depuis la saison *Twilight Trails* (8 septembre 2026), les méga-évolutions sont admises
en combat de ligue, mais **uniquement dans les Mega Editions** — elles restent interdites
dans les ligues standard. Une Mega Edition admet une méga par équipe, le reste de
l'équipe étant des Pokémon ordinaires, classés eux aussi. Par défaut chaque forme reçoit
donc ses trois ligues : Mega Editions pour une méga, ligues standard sinon. Avec
`--league`, une méga n'est classée que dans les Mega Editions demandées — la classer en
Great League ne répondrait jamais que « non classé » — et les autres formes dans toutes
les ligues demandées.

**Seize mégas portent le tag `supermega`** : une attaque chargée supplémentaire,
exclusive, suffixée `_PLUS` dans les données et marquée `isMegaMove`. Le moveset les
affiche naturellement à trois attaques chargées, et une note le signale.

**Les niveaux de méga sont binaires, pas progressifs.** `--mega1` à `--mega4` choisissent
le niveau auquel les mégas de la famille sont classées, 3 par défaut (le niveau qu'on vise
en PvP). Mais d'après le game master,
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

**Une méga n'a pas d'option : elle suit la forme tapée.** `altaria` affiche Altaria puis
Méga-Altaria ; `dracaufeu` affiche ses deux mégas X et Y, sans rien à départager. Seule la
forme de base méga-évolue : une forme régionale qui partage le n° de dex d'une méga
(Flagadoss de Galar) ne l'atteint pas, et un obscur doit d'abord être purifié — ce qui
retire les IVs classés.

**CP de montée.** Une Mega Edition plafonne le CP de la **forme méga**, pas de la forme
de base. Le nombre affiché en jeu pendant la montée est donc celui de la forme de base,
plus bas : c'est la colonne de montée décrite dans « Évolutions », qui donne 916 CP pour Méga Altaria en
Mega Great. Un pas avant la méga seulement, elle ajoute ce que méga-évoluer rapporte à ce
niveau (+64 %).

**Une ligue sans plafond n'affiche ni CP de montée ni niveau.** Sans cap, il n'y a aucun
CP à viser, et tous les spreads atteignent le niveau plafond : le répéter à chaque ligne
ne dirait rien. En Master on lit donc « CP 3576 » et un tiret dans la colonne de montée.

Cette mécanique est **déduite des données de pvpoke**, pas lue dans une documentation :
en recalculant leurs mégas sous plafond, notre résultat tombe à 1,5 % du leur, l'écart
résiduel venant de ce que leur `stats` décrit leur build recommandé et non le stat
product maximal. Plafonner la forme de base à la place donnait 70 % d'écart — hypothèse
écartée.

## Évolutions

On connaît souvent la forme qu'on a en main, pas celle qui combattra. **La commande classe
donc la forme tapée et tout ce qu'elle peut devenir**, en profondeur d'abord, chaque forme
suivie de sa méga : `ralts` donne Gallame, Méga-Gallame, Gardevoir, Méga-Gardevoir —
un tableau par forme, titré de son seul nom et de son n° de dex (« Gallame — #475 ») :
la forme en main est déjà nommée par la colonne de montée. Le titre d'une méga précise le
niveau de méga auquel elle est classée (« Malamar (Méga, level 3) — #687 »), puisque ce
niveau déplace le CP où s'arrêter. Une forme finale n'affiche que sa méga, s'il y en a
une.

**Une forme qui évolue encore et qu'aucune ligue ne classe est masquée**, la forme tapée
comprise : Tarsal et Kirlia ne rempliraient qu'un tableau de « unranked », et la colonne
de montée des tableaux suivants répond déjà pour la forme en main. Celles qu'une ligue
classe restent (Bulbizarre, Herbizarre, Galifeu en Great). Les formes finales restent
toujours, y compris « non classé » : elles sont la réponse, et une forme finale tapée ne
laisserait rien à afficher. Une méga ne compte pas comme évolution suivante : Gardevoir
est finale. Le test ne lit que le
classement (une recherche en mémoire), donc une forme masquée ne coûte pas ses 4096
spreads.

**Évoluer garde les IVs et le niveau.** Le CP à atteindre avant d'évoluer est donc celui
de la forme en main, au niveau où l'évolution est classée. Il occupe la colonne de
montée, **nommée d'après la forme tapée** (« Ralts CP », en anglais comme le reste de
l'interface, le nom suivant `--locale`) : c'est le nombre qu'on lit à l'écran pendant
la montée. Une seule colonne pour les évolutions et les mégas, puisque c'est la même
question. La note de pied le rappelle une fois pour toute la famille.

**L'arbre vient des liens « vers l'avant » de pvpoke** (`family.evolutions`), seuls
conservés au build : les liens arrière (`parent`) les contredisent à une douzaine
d'endroits (Rattatac a pour parent le Rattata d'Alola alors que le Rattata ordinaire le
liste). Un lien vers une forme absente du catalogue est ignoré.

**Un obscur évolue en obscur, et n'a pas de méga.** pvpoke omet les liens de certains
obscurs (Évoli) : un obscur suit ceux de sa forme ordinaire. Une évolution sans entrée
obscure est écartée plutôt que remplacée par la forme ordinaire, qui répondrait pour un
Pokémon que l'obscur ne peut pas devenir.

Coût : chaque forme affichée classe les 4096 spreads dans chaque ligue. Mesuré à 1,7 s
pour la famille de Tarsal (4 formes affichées) et 2,5 s pour Évoli (9 formes).

## Sans IVs — rang d'espèce

Position de l'espèce dans le classement de chaque ligue, telle que publiée par pvpoke :
Great (cap 1500), Ultra (2500), Master (10000). On affiche le rang, le score et le
**moveset recommandé** (clé `moveset` du classement : l'attaque rapide, puis les
chargées).

Une attaque figurant dans les `eliteMoves` de l'espèce est marquée `*` : elle coûte un
TM Élite, ce qu'il vaut mieux savoir avant de viser le moveset. Une légende n'apparaît
que si au moins une attaque affichée l'est.

## Avec IVs — rang interne à l'espèce

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

## Plancher d'IV

L'origine du Pokémon fixe l'IV minimum de chacune des trois stats. `--floor` le prend
comme un entier, ou par un alias de source : `wild` 0, `good` 1, `great` 2, `ultra` 3,
`weather` 4, `best` 5, `shadow` et `giovanni` 6, `raid`, `egg` et
`research` 10, `lucky` 12. Défaut : `wild`, qui ne change rien au classement.

- Le classement des 4096 n'est **pas** refait : le meilleur spread possible
  (`IvRanking::bestFrom`) garde son rang absolu, puisque c'est ce que le plancher coûte
  (« le mieux qu'un échange Best Friend puisse donner est #180 / 4096 »).
- Les IVs donnés gardent aussi leur rang absolu, et gagnent leur rang parmi les spreads
  que le plancher laisse possibles (`withinFloor`, « #4 / 1331 »). Le texte ne l'affiche
  que sous un plancher non nul ; le JSON l'a toujours (`ivRankWithinFloor`, `ivFloor`),
  à `null` pour des IVs sous le plancher.
- **Le plancher 0 reste en vue.** Sous un plancher non nul, une ligne
  `0/15/15  best overall — CP …` suit celle du meilleur spread au-dessus du plancher :
  ce qu'une capture sauvage pourrait donner, pour juger si l'échange vaut la peine. Elle
  disparaît quand le plancher garde ce spread (15/15/15 en Master League). Le JSON l'a
  toujours (`bestIvWithoutFloor`, égal à `bestIv` sans plancher).
- **Des IVs sous le plancher sont classés quand même**, sans rang dans le plancher. Le
  plancher dit ce qu'on peut chasser, pas d'où vient le Pokémon en main : réglé une fois
  pour les échanges (`pvp.floor`), il ne doit pas obliger à taper `--floor=wild` pour une
  capture sauvage. Pour la même raison, la déduction par le PC ne filtre pas par plancher.
- Si aucun spread au-dessus du plancher ne tient sous le cap de CP, la ligue est
  `ineligible`, comme pour une espèce trop forte ; le texte dit alors « nothing above
  floor N fits under the cap » et garde le meilleur spread sans plancher.

Sources des valeurs : Bulbapedia et GamePress pour les échanges (Good 1, Great 2, Ultra 3,
Best 5, chanceux 12). `shadow` vise le raid obscur et Giovanni (6, dittobase et Pokémon
GO Hub), pas les sbires dont le plancher est incertain : ceux-là passent par un nombre.
Pas d'alias `purified`, à la demande de l'utilisateur.

## Tests attendus

- CP connus de référence (ex. : Ectoplasma 15/15/15 niv. 40) → valeurs exactes ;
- rang 1 en Great d'une espèce documentée (ex. : Azumarill 0/15/15-ish) ;
- résolution des trois formes d'identifiant vers la même espèce ;
- complétion : les noms dont la forme repliée commence par le texte tapé, quelle que soit
  la casse, toutes formes et les deux langues ; rien pour un texte vide ;
- les 4096 combos produisent 4096 rangs sans trou ni doublon de position.
