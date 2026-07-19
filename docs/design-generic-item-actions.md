# Actions génériques paramétrées par l'objet — cadrage

Décision de cadrage du 2026-07-19 : remplacer les familles d'actions
« une ligne par objet » par UNE action générique qui reçoit l'objet à
l'exécution. Deux cibles : `construire` (aujourd'hui ~39 lignes
`construire_<type>`) et `consommer` (aujourd'hui hors du système
d'actions). Ce document fixe le constat, la mécanique visée, la
migration et les risques — l'implémentation est un chantier séparé.

## Constat

### Construire : 39 actions jumelles

Chaque constructible a sa ligne `construire_<nom>` (seed
`Version20260719190000_WallsToStructures::createBuildAction`, et
`StructureConversionService::createBuildAction` pour les retardataires).
Toutes portent exactement le même squelette :

- conditions : `TargetType {character}`, `RequiresItem {item: <id>, n: 1,
  consume: true}`, `BuildSite {}`, `RequiresTraitValue {a: 1}` ;
- outcome `construction` → instruction `placestructure {type: <nom>}`.

Seuls deux paramètres varient : l'id d'objet (RequiresItem) et le type
(PlaceStructure) — et les deux se déduisent du même nom d'objet.

Point clé : ces actions ne sont PAS accordées aux joueurs
(`players_actions` ne les contient pas) et n'apparaissent pas au panneau
de case. Elles sont émises par l'INVENTAIRE : `Ui.php:432` pose
`data-build-action="construire_<nom>"` sur la ligne d'objet,
`js/build_picker.js` POSTe `{action: construire_<nom>, buildX, buildY}`
vers action.php. Le lien objet→action est donc déjà une convention de
nommage — la ligne par objet n'apporte aucune information.

### Consommer : hors du système d'actions

« Utiliser » un consommable ne passe pas par action.php :
`inventory.php` → `InventoryService::useItem`, qui vérifie et décompte
le 1 A À LA MAIN (`getRemaining('a')` + `putBonus`), applique les bonus
colonne par colonne et journalise. Conséquences : pas de conditions
composables, pas de simulation, coût dupliqué hors de
`RequiresTraitValue`, effets non éditables comme des outcomes.

### Le canal de paramètre d'exécution existe déjà

`BuildSitePick` lit `$_POST['buildX']/['buildY']` directement, la
condition `BuildSite` valide et dépose le résultat sur
`ConditionObject::setBuildCoords()`, l'instruction lit
`getBuildCoords()`. C'est exactement le patron à généraliser pour
l'objet.

## Cible

### Mécanique commune : l'objet comme paramètre d'exécution

1. Le client POSTe `itemId` avec l'action (`action.php` ne lit rien de
   plus : comme `buildX`, le champ est lu par la condition).
2. Nouvelle condition `ItemPick` (miroir de `BuildSite`) : lit
   `$_POST['itemId']`, vérifie que l'acteur possède l'objet et que
   l'objet est admissible pour l'action (constructible / consommable),
   dépose l'`Item` sur `ConditionObject::setPickedItem()`.
3. `RequiresItem` : le paramètre statique `item` devient OPTIONNEL — en
   son absence, la condition consomme l'objet déposé par `ItemPick`.
   Les actions existantes à `item` statique (quêtes, coûts en matériaux)
   restent intactes.
4. `PlaceStructure` / `PlaceLayer` : le paramètre statique `type` /
   `name` devient optionnel — en son absence, dérivé de l'objet déposé
   (le nom d'objet EST le type de structure, convention déjà utilisée
   par les sprites et les pseudo-races).

### Action `construire` (une ligne)

Conditions `TargetType {character}` + `ItemPick {constructible}` +
`RequiresItem {n:1, consume:true}` + `BuildSite` +
`RequiresTraitValue {a:1}` ; outcome → `placestructure` sans `type`.
Côté UI : `Ui.php` émet `data-build-action="construire"` +
`data-item-id`, `build_picker.js` ajoute `itemId` au POST. Rien d'autre
ne change (le geste joueur est identique).

### Action `consommer` (une ligne, et un vrai gain)

Conditions `TargetType {character}` + `ItemPick {consommable}` +
`RequiresItem {n:1, consume:true}` + `RequiresTraitValue {a:1}` ;
outcome → nouvelle instruction `applyconsumable` qui applique la charge
de l'objet (pv/pm/mvt/pr/pf/malus/effets — le corps actuel de la
branche consommable de `InventoryService::useItem`, déplacé). Le clic
« Utiliser » d'un consommable POSTe action.php au lieu d'inventory.php.

Gains : coût en A exprimé comme partout (`RequiresTraitValue`),
conditions composables (un consommable réservé à une race, un lieu, un
état…), simulation du workbench, journalisation uniforme (le récap
« a consommé X — Effet : … » déjà en place se branche sur le log
d'action), et l'admin règle le comportement en données.

## Migration

1. Migration Doctrine (idempotente, backward-compat, `--no-all-or-nothing`) :
   crée `construire` et `consommer` + leurs conditions/outcomes ;
   supprime les `construire_*` et leurs satellites (action_conditions,
   action_outcomes, outcome_instructions) — elles ne sont référencées ni
   par `players_actions` ni par le contenu, seule l'UI les nomme.
2. `StructureConversionService` : ne crée plus d'action — convertir un
   objet se réduit à la pseudo-race + le type constructible.
3. UI : `Ui.php` (data-item-id), `build_picker.js`, `inventory.js`
   (consommables → action.php) + bump des versions d'assets.
4. `InventoryService::useItem` : la branche consommable devient un appel
   du moteur d'actions (ou disparaît au profit du POST direct) ; la
   branche équipement ne bouge pas.
5. Golden masters : caractériser AVANT (construction complète +
   consommation complète), rejouer APRÈS — mêmes effets, mêmes logs.

## Risques & points ouverts

- **Tutoriel** : les validations `action_used` par nom d'action — si une
  étape référence un `construire_*`, la renommer vers `construire`.
- **Bundles export/import** : les exports contenant des `construire_*`
  ré-importés recréeraient les lignes supprimées ; l'import doit les
  convertir (ou les refuser proprement).
- **Sécurité du paramètre** : `ItemPick` doit valider possession ET
  admissibilité côté serveur (un `itemId` arbitraire ne doit permettre
  ni de construire l'inconstructible, ni de consommer l'or).
- **Instances** : `RequiresItem` consomme sur la pile ; pour un
  consommable à instances (durabilité) le décompte doit rester aligné
  avec le comportement actuel de `useItem` (instance précise cliquée).
- **`display_name`/`text` par type** perdus (l'action générique a un
  seul libellé) : le libellé du bouton vient déjà de l'objet dans
  l'inventaire — vérifier qu'aucun autre affichage ne dépendait du
  libellé par type.

## Estimation

Chantier M : 2–3 jours dev (mécanique ItemPick + les deux actions + UI
+ migration + golden masters), à faire AVANT l'ouverture de la Saison 3
tant que les `construire_*` n'existent qu'en préproduction — après, la
migration devra tourner en prod.
