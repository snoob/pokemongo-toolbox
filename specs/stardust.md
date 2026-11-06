# Poussière par source — `app:stardust:info`

## Question

« Combien de poussière me rapporte chaque source, avec et sans Pièce étoile ? »

```bash
app:stardust:info                # alias : stardust
app:stardust:info --format=json
```

Aucun argument : la réponse est la même pour tout le monde, un tableau par source.

## Règles

- **Captures** : 100 par capture, 125 sous boost météo ; affichées pour 1, 10, 50 et 100.
  Les formes évoluées capturées dans la nature rapportent plus et sont laissées de côté.
- **Combat GO** : une série paie 3 600 à sa première victoire, 7 200 à sa cinquième et
  3 600 à sa fin ; les victoires intermédiaires ne donnent pas de poussière. Série à une
  victoire : 7 200 ; à cinq : 14 400. Fin de saison par rang : 20 → 90 000, As → 110 000,
  Vétéran → 134 000, Expert → 164 000, Légende → 200 000.
- **Baies en arène** : une baie rapporte 30 poussières fois son rang dans la série, sans
  plafond : 30, 60, … 300 pour le premier défenseur. Un défenseur prend 10 baies ; passer
  vite au suivant garde la série (330 … 600 pour le deuxième), donc chaque défenseur paie
  plus que le précédent : 1 650, 4 650, 7 650… Arène pleine (6) : 54 900. L'arène est
  toujours montrée pleine, avec le cumul défenseur par défenseur.
- **Pièce étoile** : ajoute la moitié à chaque récompense, partout, fin de saison
  comprise. L'arrondi se fait par récompense, vers le bas : une capture boostée paie 187,
  pas 187,5.

**Provenance : relevés de l'utilisateur en jeu**, aucun fichier du jeu ne les donne. Ils
vivent dans `CatchStardust`, `BattleLeagueStardust` et `BerryStardust` (constantes
commentées) ; à recaler si les montants observés divergent. L'arrondi de la Pièce étoile
sur 125 n'a pas encore été vérifié en jeu.
