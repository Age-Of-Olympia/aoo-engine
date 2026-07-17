# Revue de code — chantier personnages / bâtiments / objets (2026-07-18)

Revue clean code / DRY / KISS / SOLID sur l'ensemble du chantier
(base `4bcbc4dc` → `feat/building-dialogs`, 116 fichiers, ~8 800
lignes) : cinq passes par domaine (entités/STI/races, nouveaux
services + import/export, intégration legacy, système d'actions,
admin/frontend/tests), chaque constat vérifié sur le code avant
d'être retenu. ~65 constats, dont 6 de sévérité haute — tous les
correctifs de correction sont dans la MR `fix/entities-items-review`.

## Corrigé dans cette MR

### Correction (sévérité haute)

1. **Destruction d'un objet unique = mort de personnage** —
   `PlayerService::ProcessTargetDeath` ne branchait que
   `player_type='building'` ; un objet unique attaquable tombé à 0 PV
   partait dans le chemin de mort des personnages (partage d'XP,
   compteurs de kills, respawn). Corrigé via `EntityCategory` (toute la
   branche structure) ; la destruction d'un unique retire l'entité et
   fait tomber l'instance enveloppée BRISÉE au sol
   (`UniqueObjectService::destroyToGround`).
2. **Duplication d'objets au dépôt** — `Player::drop()` posait la
   bourse au sol AVANT de décrémenter la pile, sans vérifier le retour
   de `add_item()` : déposer un objet possédé uniquement en instance
   créait la pile à partir de rien. Ordre inversé + retour vérifié ;
   les boutons Jeter/Artisanat sont masqués sur les lignes d'instance
   tant que le flux `dropAt` n'est pas câblé dans l'inventaire.
3. **Mur gratuit via build.php** — la garde `get_n()` (devenue
   inclusive des instances) laissait passer un joueur ne possédant
   qu'une instance ; le mur se posait sans rien consommer. Garde et
   affichage passés en pile-seule + décrément vérifié avant la pose.
4. **`placeInstance` sans garde de localisation** — LEFT JOIN sur le
   lien de possession : une instance déjà au sol ou déjà enveloppée
   pouvait gagner une seconde localisation. INNER JOIN + index UNIQUE
   `unique_objects.item_instance_id` (migration 20260718130000).
5. **`BuildingService::place`/`remove` sans transaction ni contrôle
   d'occupation** — la paire players+buildings est désormais
   transactionnelle, la case doit être libre (entité ou mur → refus),
   le démontage est tout-ou-rien. Effet de bord utile : les fixtures
   de test fantômes qui squattaient des cases sont maintenant
   détectées au lieu d'empiler des structures.
6. **Résolution de case dupliquée** — la validation
   adjacente-et-libre vivait en DOUBLE dans `BuildSiteCondition` et
   `PlaceStructureOutcomeInstruction` (dérive possible + relecture du
   POST après paiement). Extraite dans `BuildSitePick` (source
   unique) ; la condition dépose la case validée sur le
   `ConditionObject`, l'outcome consomme CE résultat. Le bruit non
   numérique (`buildX=abc` → case (0,0)) est refusé.

### Correction (moyenne)

- `ItemInstanceService::collectAt` : deux marcheurs simultanés ne se
  disputent plus une instance (DELETE compté avant l'INSERT).
- `Player::equip()` : la disponibilité est vérifiée AVANT de libérer
  les emplacements — plus de déséquipement fantôme quand le chemin
  instance échoue ensuite (`hasEquippableUnit`, miroir exact de
  `equipCatalogItem`).
- `DropWeapon` : branche sur le résultat de la démotion, plus sur une
  exception — une arme DÉTRUITE ne se re-matérialise plus en pile
  neuve au sol.
- `RequiresItem`/`RequiresAmmo` : `toRemove` remis à zéro en tête de
  `check()` — deux occurrences sur une même action ne se contaminent
  plus (consommation d'un coût non satisfait).
- `EntityCategory::fromPlayerType` : un discriminateur inconnu lève
  désormais (`ValueError`) au lieu de passer silencieusement toutes
  les portes « character ».
- Importeurs objets/recettes : un échec d'écriture REMONTE (comme le
  reste de la famille) au lieu de produire un rapport contradictoire ;
  noms de recettes normalisés en minuscules (collision `Potion` /
  `potion` sous collation insensible).
- `action.php` : la garde anti-cible-déplacée exige à nouveau les
  coordonnées pour toute vraie cible (drapeau `selfDefaulted`).
- Admin recettes : le formulaire d'édition affiche toujours au moins
  une ligne de plus que l'existant (le remplace-tout ne tronque plus
  une recette > 5 ingrédients) ; création avec contrôle d'unicité et
  `insert_id` (plus de re-SELECT par nom) ; suppression d'un id
  inconnu signalée.
- Admin bâtiments : X/Y obligatoires à la pose (plus de pose (0,0)
  sur POST malformé) ; `confirm()` des suppressions échappé via
  `json_encode` (une apostrophe dans un nom libre cassait la garde
  JS) — pareil côté recettes.
- Liens d'export `?name=` encodés (`urlencode`) sur objets et
  recettes.
- `build_picker.js` : la demande `pendingBuild` ne survit plus à une
  page inapte (plus de picker qui se ré-arme des jours après) et la
  charge utile est validée (`?v=` bumpé).

### DRY / structure

- `ItemInstanceService::label()` : libellé d'instance (nom
  personnalisé échappé, sinon catalogue capitalisé) — source unique,
  consommée par `collectAt` et `WearService`.
- `ItemStatsSeeder::STRING_KEYS` : les colonnes texte déclarées à côté
  de `SCALAR_KEYS`, consommées par `ItemImporter`.
- `Db::insertId()` ; `GroundLootService` restaure les coords du
  joueur en `finally` ; `countBuildingDialogReferences` en COUNT
  direct.
- Harnais de test : `requireBuildingsOrSkip()`, `itemOrSkip($name)`,
  `placeStructure($type, $x, $y)` dans `LegacyPlayerFixtureTestCase` —
  cinq copies de guards/fixtures suppprimées dans les classes
  golden-master, ainsi qu'un tearDown redondant.

## Backlog (constats retenus, non corrigés ici)

Par ordre de valeur estimée :

1. **Exécuteur : coût payé quand l'outcome échoue** — `applyCosts()`
   court dès que les conditions passent ; si `PlaceStructure` échoue
   (case volée dans la fenêtre concurrente), l'objet est consommé sans
   structure. La fenêtre est désormais millimétrique (BuildSite valide
   juste avant, place() verrouille), mais le vrai fix est un mécanisme
   de non-paiement/remboursement au niveau exécuteur.
2. ~~Base commune d'importeurs DBAL~~ — FAIT (ménage n°2) :
   `AbstractDbalImporter`, ItemImporter/RecipeImporter dessus
   (DialogImporter, EM/mysqli, reste à aligner un jour).
3. ~~observe.php : listing de bourse → service + vue~~ — FAIT
   (ménage n°2) : le contrôleur est réduit au routage (352 lignes au
   lieu de 937) — EntityCardView / WallCardView / TileDialogView /
   GroundLootView (+ `GroundLootService::listAt`, repli d'icône
   unifié, bouton Ramasser délégué dans js/observe.js), sortie
   prouvée identique par diff HTTP sur 8 cas.
4. **Équiper UNE instance précise** — les lignes d'inventaire
   d'instance portent maintenant `data-instance-id`/id DOM `i{id}`,
   mais le flux use/equip vise toujours le catalogue
   (`equipCatalogItem` prend la plus ancienne) ; câbler l'id
   d'instance de bout en bout.
5. **Démontage d'entité partagé** — la séquence delete
   composants/logs/players + purge caches existe en trois
   exemplaires (BuildingService::remove, UniqueObjectService::
   takeInstance/destroyToGround) ; extraire un helper.
6. **Injection de dépendances dans BuildingService** — RaceService/
   FactionService/DialogService instanciés dans les méthodes ;
   paramètres de constructeur optionnels comme les importeurs.
7. **`NewTurnView` : l'usure s'applique dans le rendu** — déplacer
   `applyNewTurnWear` dans le chemin de commit du tour et ne passer à
   la vue que le récap.
8. **Divers** : sortes de races via `EntityCategory::options()` dans
   admin/races.php ; seuil « brisé » en constante partagée
   (`isBroken()`) ; type d'objet `constructible` découvrable dans le
   formulaire admin (datalist) et centralisé ; `RecipeExporter::
   exportAll` en une requête ; munitions validées au save ; scoper le
   `down()` de 20260716150000 ; retirer la condition TargetType morte
   des seeds construire ; `map_items_instances` dans la vue damier
   sous `ONLY_FULL_GROUP_BY` (MIN + une seule bourse par case) ;
   propriétés camelCase sur les satellites ; `item-seed` en flash.

## Vérification

- Suite complète : 855 tests verts (2 skips habituels), PHPStan
  propre.
- Fixtures fantômes purgées de la base dev (14 lignes `Gm*`
  d'anciennes exécutions interrompues — détectées par le nouveau
  contrôle d'occupation de `place()`).
