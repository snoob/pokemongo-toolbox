# Météo du jeu — `app:weather:forecast`

## Question

« Quand allumer l'Encens d'aventure du jour ? » Sa prise rare, ce sont les trois oiseaux
de Galar (annonce de Niantic : le game master décrit l'objet, 15 min, livré à minuit, mais
ne porte aucune table d'apparition). La météo ne change pas — à notre connaissance — la
probabilité de les voir ; elle change **ce qu'on peut capturer**.

## Le boost joue dans les deux sens

Une rencontre sauvage boostée a chaque IV ≥ 4 (`guaranteedIndividualValues` du game
master). Or les meilleurs spreads de ligues plafonnées des trois oiseaux ont tous une
attaque de 0 à 3 : impossibles en boosté. Le bon créneau est donc celui qui laisse le plus
d'oiseaux **non** boostés. La commande coche (✔, en vert) chaque oiseau boosté, met un tiret aux autres, et
surligne en vert l'heure qui en booste au moins un (décision 70) : le vert veut toujours
dire « boost ».

Mesuré en octobre 2026, le meilleur spread encore accessible en boosté atteint 98,6 à
99,9 % du rang 1 selon l'oiseau et la ligue : le boost est une préférence, pas un
interdit. La commande ne l'affiche plus (décision 69), mais `app:pvp:rank` donne les
spreads de chaque oiseau.

Artikodin (Psy/Vol) est boosté par Venteux ; Électhor (Combat/Vol) par Venteux et
Nuageux ; Sulfura (Ténèbres/Vol) par Venteux et Brouillard. Ces associations ne sont pas
écrites dans le code : elles découlent des types (`species.json`) et des affinités
(`weather.json`).

## Prévoir la météo du jeu

Le jeu lit les prévisions horaires d'**AccuWeather**, qui n'a plus d'offre gratuite. La
source retenue est donc **Open-Meteo** (gratuit, sans clé, rafales incluses) : un autre
modèle, donc une heure peut différer de la météo du jeu — la commande le dit.

Niantic ne publie aucune traduction prévision → météo du jeu. La communauté en a établi
une pour AccuWeather (thornleaf/pgo-weatherbot, un an de relevés à Lindsay, Ontario) ;
celle de `WmoConditions` est la **nôtre**, tracée par analogie : chaque ciel sec garde
son nom, la précipitation l'emporte sur le nuage, et seul un ciel sec devient Venteux,
au-delà de 24 km/h de vent **ou** 35 km/h de rafales (seuils du README de
pgo-weatherbot). Elle n'a été comparée au jeu par personne : comme le multiplicateur
d'attaque méga ([pvp-rank.md](pvp-rank.md#mégas-et-mega-editions)), c'est une valeur qu'aucun fichier du jeu ne confirme, isolée
dans une classe avec sa provenance — à recaler sur des relevés en jeu dès qu'on en a. Un
code hors table WMO reste `null`, et une heure inconnue n'a pas de verdict.

## Où

`--at="Ville, Pays"` passe le nom au géocodeur d'Open-Meteo (qui ne cherche que par nom)
et la partie après la virgule départage les homonymes, par nom de pays ou code ISO. Sans
`--at`, la position est devinée par IP (ipinfo) : précise à la ville au mieux, fausse
derrière un VPN ou en 4G — le lieu trouvé est affiché en tête. Les coordonnées sont
arrondies à ~100 m pour que le cache serve.
