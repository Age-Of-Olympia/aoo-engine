# Plan — faire des ressources des entités

Plan d'exécution du cadrage [design-resources-entities.md](design-resources-entities.md).
Établi le 2026-07-27. Chaque lot est livrable seul, déployable seul, et laisse
le jeu jouable.

## Principes

1. **Rien n'est entifié tant que les trois corrections de performance ne sont
   pas en production.** C'est le seul verrou dur du dossier et il coûte une
   journée. Sans lui, chaque pas de joueur passe de 13,3 à 48,3 ms ; avec lui,
   il tombe à 2,6 ms — le jeu à 41 800 entités devient plus rapide qu'aujourd'hui
   à 14 223.
2. **Le comportement de jeu est gelé avant d'être migré**, bizarreries comprises.
   Les corrections d'équilibrage partent seules, dans leur propre commit, en le
   disant. Sinon aucune régression n'est distinguable d'un bug de migration.
3. **L'identité reste `players.id`.** Aucune table d'objet parallèle, aucun
   `entity_id` nullable : ce serait donner deux clés primaires à tout objet qui
   parle, c'est-à-dire reproduire la maladie que le chantier soigne.
4. **Migrations avant code, rétrocompatible seulement.** Vérifié : une vue à
   jointure accepte l'`UPDATE` des crons ; seuls trois sites d'écriture
   (`destroy.php:144`, `erase_case.php:41`, `TiledMapService::insertRow`)
   demandent une bascule préalable.
5. **Tout ce qui a besoin de `datas/` est une commande console, jamais une
   migration.** Le déploiement exécute les migrations depuis un checkout où
   `datas/` est absent — la seed des races a déjà été brûlée par là.
6. **Une seule fenêtre de double vérité à la fois.** Celle de `map_walls`
   (2026-07-20) doit être fermée avant qu'une autre ne s'ouvre durablement.
7. **Quand on absorbe le travail en cours de quelqu'un, on garde sa signature.**
   Reprendre une mécanique conçue ailleurs ne doit pas effacer qui l'a conçue :
   `git cherry-pick` quand le patch s'applique, `git commit --author=…` quand il
   faut le refaire. Le committer change, l'auteur non.

## Chemin critique

```
L0 ─┬─ L1 ── L2 ──┬── L3 ── L4 (décor)
    │             │
    └─ L0bis      └── L5 (autel) ── L6 (ressources) ── L7 (plantes, creuser)

                                                        L8 (découpe players) ─ hors chemin
                                                        L9 (tutoriel) ─────── en dernier
```

**La saison ouvre avec l'ensemble** (arbitrage du 2026-07-27). Une version
antérieure de ce plan coupait en deux — « L0 à L2 avant l'ouverture, le reste
après » — c'était une prudence de rédaction, pas une contrainte du projet.
Le chantier va au bout.

L'ordre reste celui du schéma, pour des raisons de dépendance et non de
calendrier :

- **L0 conditionne tout le reste** : sans l'index et le scope, chaque entité
  ajoutée alourdit le pas. Ce n'est pas négociable, c'est une journée.
- **L1 précède tout changement de comportement** : on gèle avant de déplacer,
  sinon une régression d'équilibrage devient indiscernable d'un bug de
  migration.
- **L5 précède L7** : rendre les plantes franchissables avant d'avoir absorbé
  l'autel efface un dieu du classement au premier pas (cf. le trigger #16206).
- **L8 est hors chemin** : le découpage de `players` se justifie par la clarté
  et la sécurité des identifiants, pas par la performance. Il peut arriver
  quand on veut.

**En dernier, quoi qu'il arrive** : L9, le tutoriel. Il sera adapté une fois le
reste stabilisé — le rattraper à chaque lot reviendrait à le refaire autant de
fois qu'il y a de lots.

Ce qui ne change pas avec le calendrier : les invariants de déploiement
(migrations avant code, rétrocompatible seulement), et la vérification de
réversibilité exigée avant L6 — `deploy_sql.sh` ne fait aucune sauvegarde, et
l'existence de `map_walls_archive` en vraie production n'a jamais été contrôlée.

---

## L0 — Les trois verrous de performance · 2 jours · aucun arbitrage

**But** : rendre l'entification possible. Aucun lien avec le modèle, aucune
décision à prendre, valeur immédiate pour tout le jeu.

1. **Réparer `View::get_coords_id` d'abord.** `Classes/Db.php:223`
   (`get_last_id`) fait `ORDER BY id DESC LIMIT 1` sur toute la table, pas
   `LAST_INSERT_ID()`. Remplacer par
   `INSERT … ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)` — recette déjà
   écrite dans `TutorialHelper.php:312`.
   **Ordre non négociable** : sans upsert idempotent, l'index UNIQUE de l'étape
   suivante transforme une course bénigne en `return null` (`View.php:964`)
   propagé dans `go.php`.
2. **`ALTER TABLE coords ADD UNIQUE KEY uk_pzxy (plan, z, x, y)`** — 0,28 s,
   +6,52 Mo, réversible par `DROP INDEX`. Contrôler l'absence de doublon sur la
   **vraie** production d'abord (zéro sur les 147 975 lignes de la réplique,
   mais rien ne le contraint).
3. **Scoper `refresh_players_svg`** : exclure l'inerte —
   `AND (player_type IS NULL OR player_type NOT IN ('resource','scenery'))`.
   **Liste noire, pas liste blanche.** Les bâtiments agiront (bâtiments de
   défense) et tiendront alors une session et un cache comme un personnage :
   une liste blanche d'acteurs deviendrait fausse ce jour-là, en silence. Le
   gain n'est donc pas une réduction du balayage actuel, mais l'arrêt de sa
   **croissance** — mesuré sur une fenêtre dense de `fort_turok` : 223 lignes
   aujourd'hui, et 213 de plus qu'on évite d'y ajouter à l'entification.
4. **Normaliser les collations des clés de jointure.** Mesuré : cinq jointures
   échouent aujourd'hui en « Illegal mix of collations » —
   `map_resources.name ↔ races.name`, `map_foregrounds.name ↔ races.name`,
   `resource_types.name ↔ races.name`, `players_actions.name ↔ actions.name`,
   `coords.plan ↔ races.plan`. La colonne `plan` seule ne suffit donc pas :
   c'est la jointure type ↔ instance qui bloque L6. Aligner le **catalogue**
   (`races.name/code/plan`, `resource_types.name`, `actions.name`, quelques
   dizaines de lignes) sur `utf8mb4_general_ci`, collation des données
   (`map_*`, `players`, `coords`) — et non l'inverse, qui toucherait 40 000
   lignes. Ne pas convertir les tables entières : `races.label` porte du
   français accentué et son insensibilité aux accents doit rester.

**Fin** : `observe.php` mesuré sous 1 ms de SQL par clic ; le balayage SVG
n'augmente plus avec le nombre d'occupants inertes ; les cinq jointures passent
sans `CONVERT`.

---

## L0bis — Bugs actifs, indépendants · 3 jours · aucun arbitrage

Chacun se livre seul. Aucun n'attend le reste du chantier.

- **`Classes/Player.php:1223`** — ajouter `player_id = ?` au `DELETE FROM
  players_bonus`. Aujourd'hui il balaie la table entière à chaque coup, chaque
  soin, chaque repos. À corriger **avant** de rendre 26 563 ressources
  destructibles : c'est le chemin qu'elles vont marteler.
- **`reparer`** — ajouter une condition « la cible est endommagée ». 3 XP par
  point d'action sans contrôle, c'est le meilleur rapport du jeu et il sera armé
  dès que l'action de destruction sera distribuée.
- **`id < 0` → `player_type`** sur les quatre sites (`TutorialMapInstance:291`
  et `:361`, `observe.php:260` et `:298`, `PlanAdminService:653`). Chaque session
  de tutoriel abandonnée laisse aujourd'hui un plan complet et 32 entités
  indestructibles (ERROR 1451 reproduit).
- **`PlanAdminService::countCharactersOnPlan`** — filtrer sur `player_type`.
  `praetorium_save` s'annonce à 663 joueurs, aucun réel.
- **`ViewService::generateResourceLayer`** — remplacer
  `LIKE '%mur%' OR LIKE '%arbre%'` par un filtre de catalogue, et lier le
  paramètre `$plan` au lieu de le concaténer. 74 % du parc est invisible sur la
  carte du monde.
- **`map load`** — accepter la clé `walls` dans le chargeur mono-fichier, et y
  ajouter le `DELETE` que seul le chemin multi-parties exécute. 5 535 murs des
  21 cartes archivées sont ignorés en silence, et un rechargement duplique.
- **`ALTER TABLE map_plants ADD player_id` + la FK manquante vers `coords`.**
  Plus petit prérequis du dossier, seul blocage strict pour « les joueurs peuvent
  planter ».
- **Table `entity_sequences`** remplaçant les deux `MAX+1` de
  `config/functions.php:105-168`, appelée **dans** la transaction de pose.
  Unifier au passage l'allocation des PNJ de tutoriel
  (`TutorialMapInstance:199` fait `-(time()+$i)` : deux sessions dans la même
  seconde se collisionnent).
- **Nettoyer `cimetiere_s2`** : `{wall: pierre1, ressource: "pierre1"}` désigne
  un objet qui n'existe pas — `fouiller` à côté de l'une des 29 ressources
  récoltables du plan est une erreur fatale aujourd'hui. Et poser la garde
  manquante dans `ResourceOutcomeInstruction`.

---

## L1 — Le filet · 1 semaine · aucun arbitrage

**But** : geler le comportement actuel *tel quel* pour que la migration soit
mesurable.

### Blocage préalable : une base fraîche n'est plus constructible

Mesuré le 2026-07-27. En chargeant `db/init_noupdates.sql` dans une base vide
puis en rejouant la chaîne, la migration s'arrête après 60 versions sur
`Unknown column 'grow_rate' in 'SET'`.

Cause : `Version20260717180000_ItemsFromJson` appelle du **code applicatif**
(`ItemStatsSeeder::seed()`), lequel écrit toutes les clés de
`Item::SPECIAL_KEYS` — dont `grow_rate`, colonne ajoutée six jours plus tard par
`Version20260723121000_ItemRatesFromConstants`. Rejouée depuis zéro, une
migration de juillet écrit donc une colonne qui n'existe pas encore.

C'est le risque classique d'une migration qui appelle du code vivant : elle ne
décrit pas l'état du schéma à sa date, elle décrit celui d'aujourd'hui. À
corriger avant L1 — le filet de tests suppose qu'on sache fabriquer une base
propre. Deux voies : figer la liste de colonnes dans la migration, ou déplacer
le seed vers une commande console (c'est de toute façon la règle du projet pour
tout ce qui lit `datas/`).

**Prérequis d'extraction** :
- Faire passer les trois tirages par `Classes\Dice` — il existe, il journalise
  dans `DiceLog`, il est déjà mocké par `ScriptedDice`
  (`ResourceOutcomeInstruction:61`, `ResourceService:129` et `:142`). Effet de
  bord souhaitable : les jets de récolte deviennent visibles dans le panneau de
  détails, comme les jets de combat, au lieu d'un `(1dN = x)` reconstruit à la
  main.
- Rendre `ResourceService` instanciable avec un fournisseur de biomes injectable
  (aujourd'hui 100 % statique, `new Db()` en interne, relit le JSON de plan deux
  fois par récolte).

**Tests étalons** — au sens de `docs/conventions-code.md` : une photographie du
comportement existant, figée AVANT de refactorer. Les classes gardent le suffixe
anglais des vingt-six qui existent déjà (`CreuserBaselineTest`…) ; c'est le
vocabulaire de prose qui reste français.

- `FouillerBaselineTest` — N=1, N=6, N=8, **la case à 1d16 de `fort_turok`
  (-22, 47)**, case mêlant deux rendements, case inerte, case à `damages=0`.
- `ExhaustCapCharacterizationTest` — **pinne** le plafond d'épuisement par le dé
  du dernier type parcouru, en le documentant comme bug gelé.
- `NeverExhaustCharacterizationTest` — pinne les 3 476 ressources sans
  `exhaust`/`regrow`. Sans lui, le passage au catalogue leur donne
  silencieusement l'épuisement.
- `BlockingSsotBaselineTest` — table de vérité occupant × verbe × les sept
  prédicats, plan **avec** et **sans** JSON, `player_visibility` à `false`.
**La spec Cypress est reportée à la toute fin de la saison** (arbitrage du
2026-07-27). Elle attend 121 coords et 41 murs quand la graine en produit 81 et
5+32, mais le tutoriel sera de toute façon adapté une fois le reste stabilisé :
le réparer maintenant, c'est le réparer deux fois. Il reste en `when: manual` +
`allow_failure` jusque-là.

**PHPStan sur le legacy : pas un lot, une conséquence.** L'extension du
périmètre à `Classes/` figurait ici ; l'arbitrage est qu'elle arrive d'elle-même
à mesure que le legacy migre vers `src/`, déjà analysé — c'est le strangler du
glossaire, et il n'y a pas de raison de payer un périmètre qui doit disparaître.

La mesure le confirme, et elle corrige au passage ce que ce plan laissait
craindre : tout `Classes/` au niveau 4 du projet ne produit que **153 erreurs**
(11 057 lignes ; `Player.php` en fait 2 640, pas les 51 000 qu'annonce
`CLAUDE.md`). Et sur les deux seuls fichiers que ce chantier réécrit —
`View.php` et `Item.php` — il n'y en a que **21**, dont la majorité ne sont pas
des défauts de code : PHPStan ne connaît ni `json()`, ni `ExitError()`, ni
`ITEMS_OPT`, ni `CARACS`, qui vivent dans `config/` et ne sont pas déclarés en
`scanFiles`.

Ce qui reste utile, et qui profite aussi à `src/` : **déclarer ces globales de
`config/` en `scanFiles`**. Le reste attend le strangler.

**Attention** : le tutoriel ne peut pas servir de filet — il valide des clics,
pas des gains. Voir L9, où il est traité en bloc et en dernier.

---

## L2 — Rendu et SSOT du blocage · 1 à 2 semaines · aucun changement de modèle

- **Branche `players` de l'UNION** : ajouter `player_type`, `avatar`, `race` ;
  supprimer `PlayerFactory::legacy()->get_data()` de la boucle ;
  `UNION` → `UNION ALL` (mesuré 7,6 → 6,6 ms, le DISTINCT ne déduplique rien).
- **Backfiller par commande console les 10 539 avatars vides de bâtiments.**
  `Classes/View.php:399-425` fait aujourd'hui un `UPDATE` + `purgeEntityCaches`
  **depuis la boucle de rendu** — une écriture dans un chemin de lecture, à
  supprimer avant d'ajouter 26 656 lignes susceptibles du même trou.
- **Supprimer `ObstacleCondition`** et ses 8 lignes de
  `action_condition_preconditions` : une requête et un Bresenham gaspillés par
  tir, technique et sort. Zéro comportement changé.
- **`TileOccupancyService` en lecture seule** + test étalon des prédicats
  *actuels* : on gèle la divergence, on ne la corrige pas encore.
- **`go.php` bascule dessus** — et c'est là, en un commit, un comportement
  changé et mesurable, que le bug du `if($planJson)` se corrige, avec la règle
  « bloquer, c'est être vu ».
- **Puis un prédicat par commit** : `lineOfFireReport`, `place()`,
  `get_coords_taken`, `is_free`.
- **`data-block` sur la case** : `js/view.js` et `js/blocked-tiles.js` cessent
  de déduire du DOM.

---

## L3 — L'emprise à vide · 1 semaine · **décision requise : multi-occupation**

Additif et réversible. Rien ne lit encore.

- Créer `entity_cells` et `race_footprint`.
- **Matérialiser à l'identique** : une ligne
  `(player_id, players.coords_id, piece=0, role='anchor'|'block')` pour chacune
  des 13 549 entités — 0,3 s. `players.coords_id` conservée, aucun lecteur
  changé.
- Backfiller `race_footprint` **depuis les données**, pas depuis les
  `convert.sh` (25 scripts d'un dépôt non versionné avec le moteur, déjà
  divergents sur trois familles ; et 35 familles n'en ont aucun).

**Attention, deux pièges mesurés** :

- **La composante 8-connexe ne suffit pas.** Elle produit 275 groupes, mais 34
  d'entre eux contiennent plusieurs exemplaires collés — la forêt de
  `banniere_velue` est 14 géants que la connexité fusionne en un objet de 29
  cases. Le compte réel est **≥ 354**. Il faut un critère supplémentaire :
  l'unicité de l'indice de morceau dans un groupe.
- **L'ancre « bas-gauche » est fausse pour 5 familles** (`unique_praetorium`
  dx=-2, `unique_fort_turok` et `unique_pyramide` dx=-1, `asteroide` dy=+1) :
  quand un coin est transparent il n'est pas posé, donc la boîte englobante des
  cases *posées* n'est pas la découpe. Les emprises **trouées** doivent être
  autorisées — la table le permet naturellement.

**Décision bloquante** : une case peut-elle appartenir à deux objets, et qui
gagne au rendu et au blocage ? 642 cases portent plusieurs décors ; sans règle,
elles sont ingérables à la migration.

---

## L4 — Le décor · 1 à 2 semaines · premier client de l'emprise

Dans l'ordre :

1. **Sortir `ombre`** — 8 353 lignes, 82 % de la table, devient une INTENSITÉ
   de case (`coords.shade`).

   **Ne pas dédoublonner.** La première version de ce plan disait « dédoublonner
   les 509 cases doublement assombries » : c'était une erreur, et elle aurait
   détruit du travail. `ombre` est un noir uni à 5,5 % d'opacité que les
   animateurs posent PLUSIEURS fois pour foncer — 7 104 cases à un calque, 319 à
   deux, 154 à trois, 31 à quatre, 5 à cinq. Ce sont des dégradés peints à la
   main, pas des doublons.

   L'empilement EST l'intensité, mais exprimée en répétant une ligne, ce
   qu'aucun code ne peut lire comme telle. Le rendu reste fidèle au pixel près :
   N calques d'opacité `a` donnent `1-(1-a)^N`, qu'un seul rectangle porte aussi
   bien que N images. Le geste de l'animateur ne change pas — même pinceau,
   re-cliquer fonce.

   La couleur viendra, si elle vient, en colonne supplémentaire avec le noir par
   défaut : aujourd'hui le seul outil EST du noir (`tile_colors` associe une
   couleur à un nom de décor, pour la carte du monde, pas à une case).
2. **Sortir les suivants** (55 lignes) vers un `players_followers` autoporteur.
   Ferme au passage le bug où `add_follower` adopte une ligne de décor existante
   que `delete_follower` supprime ensuite.
3. `map_foregrounds` tombe de 10 215 à **1 862 lignes** — la table devient
   lisible et l'ampleur réelle du sujet apparaît.
4. **Migrer les fragments** en entités `'scenery'` + emprises. Dériver les
   rôles : `block` là où un `forbidden` est co-localisé (185 lignes), `door` là
   où un `tp` est posé, `cover` ailleurs. Puis remonter le rôle majoritaire au
   catalogue et **signaler les divergences** — c'est la liste de travail des
   animateurs.

   **Règle de conversion : un objet cassé devient une entité ABÎMÉE.** Quand
   la migration rencontre un `*_broken` posé, elle ne crée pas un type
   « cassé » : elle pose une entité du TYPE DE BASE, blessée sous la moitié
   de ses points de vie. Tout le reste suit alors sans qu'on l'écrive —
   `BuildingService::refreshWoundSprite()` bascule le sprite sur la variante
   `_broken`, et `closureReason()` répond « endommagé » sous
   `CLOSED_BELOW_PV_PCT` (50 %), donc l'objet reste fermé comme il l'est
   aujourd'hui. L'apparence et le comportement sont conservés sans état
   nouveau, et l'objet devient réparable : c'est ce qu'un objet cassé doit
   être.

   Le détail qui compte : les PV RESTANTS d'une entité ne sont pas une
   colonne. `caracs->pv` est le maximum, tiré de `races.pv`, et les dégâts
   vivent dans `players_bonus`. « Poser sous la moitié » veut donc dire
   insérer une ligne de bonus négatif — et `putBonus()` est le seul chemin
   qui déclenche la bascule de sprite.

   Ce que cette règle touche aujourd'hui : **cinq `altar_broken`**, seuls
   `*_broken` posés sur la carte (rien dans `map_foregrounds`, rien en
   `players.race`). Les autels étant le sujet d'un lot à part, ces cinq-là
   s'y rattachent plutôt qu'ici — mais la règle, elle, vaut pour tout
   `*_broken` que la conversion rencontrera.
5. **Ajouter `entity_cells` à `place()`**, qui ne consulte aujourd'hui ni
   `map_foregrounds` ni `map_triggers` : sans ça les structures restent
   constructibles par-dessus (50 cases de fragment portent déjà une entité).
6. `observe.php` lit l'emprise : les structures parlent enfin, les 110 lignes
   `map_dialogs` redondantes disparaissent.
7. **Les DEUX éditeurs, avant l'étape 4 et non après.** Tant qu'ils posent des
   morceaux indépendants, ils fabriquent de la dette pendant qu'on la rembourse.

   - *Tiled* (externe) : supprimer `expandComposite`
     (`tools/tiled/aoo/aoo.js:897`), qui ré-éclate la tuile posée en morceaux
     `-NN` au moment du push — le commentaire l'assume : « un pull ultérieur
     ré-affiche les morceaux, ce qui revient au même visuellement ».
     Visuellement oui, structurellement l'objet meurt à la porte. Le push
     envoie `{x, y, name}` sur une couche `structures`, le serveur matérialise
     l'emprise depuis le catalogue.
   - *tiled* (maison, `tiled.php`) : même bascule, et elle est plus urgente.
     `scripts/tiled/erase_case.php` sait déjà démonter un BÂTIMENT par son
     service — « jamais par un DELETE brut », dit le commentaire — mais le
     décor part par `DELETE FROM <table> WHERE coords_id = ?`, **une case à la
     fois**. Effacer une case d'une taverne 3×3 laisse huit fragments
     orphelins : c'est ainsi qu'on en a fabriqué une trentaine. La pose doit
     écrire une entité et laisser le serveur étendre l'emprise ; l'effacement
     doit prendre l'objet entier depuis n'importe laquelle de ses cases.

8. **Le catalogue des découpes s'édite depuis l'admin**, comme les autres
   catalogues passés en base : `admin/footprints.php` + `-save.php`, sur le
   patron de `races.php`, `effects.php`, `items.php`, `resource-types.php`.
   C'est là que se prennent les décisions de décor, pas dans une migration :
   la découpe (w, h), le rôle de chaque morceau (`block` pour la base, `cover`
   pour la partie haute, `door` pour l'entrée), et l'arbitrage figure complète
   / demi-figure. Une migration fige, une page d'admin laisse la main aux
   animateurs.
9. Les ~32 fragments orphelins et groupes incomplets partent dans un rapport
   d'admin, un par un.

**Multi-z — tranché** : `porte_des_enfers` sur quatre étages devient **quatre
bâtiments distincts**, un par niveau. Il n'y a pas de destruction d'étage pour
l'instant, donc rien n'exige qu'ils se connaissent ; les lier serait une
complication sans emploi. Les 14 colonnes concernées suivent la même règle.

**Exemplaires tronqués — tranché** : la figure complète fait foi, et les 38
exemplaires incomplets partent dans un **rapport**. Pas de complétion
automatique : on regardera la liste avant de décider quoi en faire. Si une
demi-figure est voulue quelque part, elle deviendra un décor à part entière.

**`lac_thetis` et `triton_statue`** : reportés. Ce sont deux familles sur 68,
elles n'empêchent pas les 66 autres d'avancer.

### Ce que la dérivation a mesuré (26 juillet, copie de production)

Le catalogue est dérivable des données, et il l'a été — script rejouable, en
lecture seule :

- **66 familles sur 68** rendent une découpe complète, dont **8 figures
  trouées** (le géant pétrifié occupe 4 cases dans une boîte 3×3).
- **L'ancre est le premier morceau, pas le coin de la boîte englobante.** Une
  figure trouée n'a pas forcément de case au coin bas-gauche — c'est ce qui
  rendait l'ancre fausse pour cinq familles, et le problème disparaît avec ce
  choix, sans cas particulier.
- **38 exemplaires tronqués** sur 7 familles : 21 géants sans leurs pieds,
  9 `arbre_blanc`, etc. Avec la règle « la figure complète fait foi », ce sont
  des exemplaires à compléter — la liste de travail des animateurs.
- **2 familles à trancher** : `lac_thetis` (les suffixes `-04`/`-05` sont deux
  VARIANTES de lac, pas les moitiés d'une figure) et `triton_statue` (deux
  conventions de nommage mélangées dans la même famille).
- **Aucune famille de décor n'existe dans `races`** : le catalogue ne peut donc
  pas être semé avant que L4 n'ait créé les entités `'scenery'`. La dérivation
  est prête, l'écriture appartient à ce lot.

Le mécanisme actuel, lui, ne couvre qu'une minorité du décor :
`TileCatalogService::buildComposites` lit le DISQUE, exige que tous les
morceaux existent sous `base-NN.png`, et écarte la famille entière au premier
manquant. Or **442 fichiers de morceaux sont nommés `_NN` contre 120 en
`-NN`** — et `unique_fort_turok` porte `_02`…`_16`, donc il n'a aucune tuile
composite et se peint morceau par morceau. Mettre le catalogue en base rend
ces conventions caduques : l'indice de morceau devient une colonne.

---

## L5 — L'autel et le contrôle de plan · 1 semaine · **absorbe !491**

Ce lot ne refactore pas seulement l'autel : il **livre la mécanique de !491**
(`Draft: Update altars`, branche `feature/altars-update`, 5 commits de fin mai).
Cette MR n'est pas un correctif, c'est un **nouveau mécanisme de jeu**, et il est
complémentaire du nôtre.

### La mécanique à conserver

- Construire un autel coûte **50 PF** en plus de l'action.
- Un dieu **contrôle un plan** quand il est le seul à y avoir un autel
  (`GROUP BY plan HAVING COUNT(DISTINCT godId) = 1`).
- Le classement de la Foi gagne une colonne « Plans contrôlés » et une ligne
  nommant le dieu qui en contrôle le plus — avec les cas « aucun » et « égalité ».
- Détruire un autel le retire du décompte.

C'est un contrôle territorial des dieux par les autels. Rien dans le modèle cible
ne s'y oppose ; tout y est plus simple.

### L'autel sans dieu, et la consécration — arbitrage rendu

**Décision** : un autel peut exister sans dieu et être **déifié ensuite**. Des
joueurs peuvent poser des autels nus, qu'un dieu vient occuper plus tard. Les 10
autels orphelins ne sont donc plus un cas à liquider : ce sont les premières
cibles de la consécration.

Cela **inverse une règle de !491** et déplace deux choses :

- `RequiresGodAffiliationCondition` **quitte `construire` et passe à
  `consacrer`**. La MR interdisait de bâtir sans dieu (`exit('error no god')`) ;
  poser un autel nu devient au contraire un geste de jeu légitime.
- **Le coût de 50 PF suit** — proposition, à confirmer : facturer 50 PF pour un
  autel inerte n'a pas de sens, alors que c'est le prix naturel de la
  consécration. Bâtir reste au tarif d'une structure ; consacrer coûte 50 PF et
  exige un dieu.

`consacrer` est de toute façon une **capacité perdue en juillet** à rétablir :
une ligne d'`actions` sur `PlaceStructureOutcomeInstruction`. L'autel nu porte
simplement `godId = 0` sur son entité — valeur par défaut, aucun schéma à
toucher.

**L'autel nu est NEUTRE** (tranché le 2026-07-27). La requête de contrôle de
!491 compte `COUNT(DISTINCT godId)` : un `godId = 0` y compterait comme une
valeur distincte et ferait perdre le contrôle du plan au dieu qui y règne. Le
décompte doit donc porter `WHERE godId != 0`. Un autel non consacré n'ôte le
contrôle à personne et n'en donne à personne — il attend son dieu. Pas de
mécanique de profanation.

### Pourquoi l'implémentation ne peut pas être mergée telle quelle

- **Elle modifie `build.php`, qui n'existe plus.** Le fichier a été supprimé sur
  `integration/hud-redesign` : la pose passe désormais par l'action `construire`
  (id 161) et `PlaceStructureOutcomeInstruction`. La MR part d'une base
  antérieure aux travaux de juillet et vise `staging`.
- **Elle crée une troisième adresse pour l'autel.** Après `map_resources` +
  `map_triggers`, elle ajoute une table `altars(coords_id, godId, plan)`,
  maintenue à la main dans `build.php` et `destroy.php`, sans lien entre les
  trois. C'est exactement le défaut que ce chantier soigne — et le motif est
  pourtant bon : le classement a besoin du couple (dieu, plan), et
  `map_triggers` n'a pas de plan.
- **Deux sources pour un même fait dans la même requête** : le `WHERE` garde
  `EXISTS (SELECT 1 FROM map_triggers …)` pendant que le `JOIN` lit `altars`.
- **Aucun backfill.** Les 16 autels existants n'auraient pas de ligne `altars` :
  au déploiement, ils resteraient comptés par le `EXISTS` mais afficheraient
  « 0 plan contrôlé ». La mécanique naîtrait vide.
- **Le plan est dénormalisé en chaîne à la construction** : il se périme au
  renommage de plan. Dérivé des coordonnées, il ne peut pas.
- Détails : `$item->id == 3` codé en dur quatre fois ; le SQL passe par
  `db/updates/*.sql`, le second système de migrations, hors Doctrine ;
  `exit('error pf')` au lieu d'un message de condition.

### Comment on la rend, dans le modèle cible

Tout est déjà là, et **`altars` redevient une requête au lieu d'une table** :

| Besoin de !491 | Dans le modèle cible |
|---|---|
| « vénérer un dieu » | `RequiresGodAffiliationCondition` — **existe déjà**, message compris ; portée par `consacrer`, pas par `construire` |
| « coûte 50 PF » | coût de l'action `consacrer` |
| enregistrer (dieu, plan) | l'entité porte le dieu, `entity_cells` porte le plan |
| « plans contrôlés » | `GROUP BY` sur les entités `race='altar'` **consacrées**, jointes à leur emprise |
| retirer à la destruction | `ON DELETE CASCADE`, plus aucun nettoyage à la main |
| le trigger `altar` | supprimé — l'entité **est** l'autel |
| déifier un autel nu | `consacrer` sur une entité `race='altar'` à `godId = 0` |

Reprendre en revanche telles quelles la **règle de contrôle** (un seul dieu sur
le plan), la **présentation** du classement (colonne + ligne de synthèse, avec
les cas « aucun » et « égalité ») et le **coût de 50 PF** : c'est la valeur de
la MR, et elle est bonne.

### Le refactor lui-même

L'autel tombe dans le modèle sans une ligne de schéma : 1 entité `race='altar'`
(déjà au catalogue `races`, id 104, pv 25), le dieu sur `players.godId` de
l'autel, le bâtisseur sur `buildings.owner_id` (= les 11
`map_resources.player_id`, qui sont **tous** des autels).

`FoiView:43` devient
`EXISTS (SELECT 1 FROM players a WHERE a.race='altar' AND a.godId = g.id)`.
« Vénérer » devient une action normale. `worship.php` meurt avec son 500 sur
14 217 des 14 233 triggers (`:18` ne filtre pas `name='altar'`, `:30` passe
`params` à `PlayerFactory::legacy(int)` **avant** le contrôle de distance).
L'insensibilité au passage s'obtient par **suppression** de
`scripts/map/triggers/altar.php`, pas par une garde.

**Prérequis dans l'ordre** : réparer l'export/copie de la couche `buildings`
(absente d'`AUTHORABLE_LAYERS`, donc `copyPlan` perd les autels devenus
entités) ; créer l'action `venerer` **avant** la bascule, parce qu'
`observe.php:308` court-circuite `ResourceCardView` dès qu'une entité occupe la
case ; retourner `WallsConversionBaselineTest`.

**Cas à traiter à part** : le trigger #16206 (Thétis, `lac_thetis_s2`) ne repose
sur aucune ressource `altar` — sa case porte `jungle1` et `herbe3`. C'est le seul
autel d'un dieu classé (3 fidèles, 213 pf), et `altar.php` le supprime
inconditionnellement au passage. **Ce lot doit donc précéder « les plantes sont
franchissables »**, faute de quoi le dieu disparaît au premier pas, sans trace.

**Backfill obligatoire, absent de !491** : les 24 autels existants doivent entrer
dans la mécanique de contrôle au moment de la bascule, sinon elle naît vide. Les
10 sans dieu entrent comme autels **nus, consacrables** (cf. arbitrage ci-dessus).

Reste un cas isolé, distinct : le trigger #19250 (`params = -14` → Griffith, de
race `animal`, sur une case nue) ne désigne pas un dieu. Ce n'est pas un autel
sans dieu, c'est un pointeur cassé : suppression sèche.

### Reprendre le travail sans effacer son auteur

Les 5 commits sont d'un même auteur, et la répartition décide de la méthode :

| Commit | Fichiers | Transposable ? |
|---|---|---|
| `951363a5` Début update altars | `build.php` | non — fichier supprimé |
| `e3084f98` Ajoute le trigger à la construction | `build.php` | non |
| `a8066d4a` Retire le trigger à la destruction | `destroy.php` | non — le fichier meurt en L6 |
| `24750803` Contrôle des plans dans le classement | `FoiView.php` (+42), `db/*`, `build.php`, `destroy.php` | **partiellement** — c'est le cœur |
| `10bdf971` Contrainte si pas de Dieu | `build.php` | non — et la règle est inversée |

**Aucun commit n'est cherry-pickable tel quel** : le seul qui porte la mécanique
mélange `FoiView` avec `build.php`. La méthode est donc :

```bash
git cherry-pick -n 24750803          # applique sans committer
# ne garder que les hunks FoiView.php ; écarter build.php, destroy.php,
# db/updates/*.sql (second système de migrations) et init_noupdates.sql
git commit --author="oubould <oubould@protonmail.com>"
```

Puis, pour ce qui doit être réécrit (la pose et la destruction, qui passent
désormais par le moteur d'actions), un commit par pièce, chacun avec
`--author` sur l'auteur d'origine quand il porte sa mécanique — et sous notre
signature quand c'est du travail neuf. Le committer change, l'auteur non.

**À faire avec lui, pas à sa place** : la branche `feature/altars-update` vise
`staging` et part d'une base de mai. Le chemin propre est de reprendre la
mécanique dans ce lot, sur `integration/hud-redesign`, et de fermer !491 en le
disant — pas de tenter un rebase sur un fichier supprimé.

---

## L6 — Les ressources · 1 à 2 semaines · le cœur

1. **Écrire les 41 lignes de catalogue** via `admin/races.php` (48 types posés
   − 4 déjà en `races` − 3 `unique_*`). Un après-midi.
2. **Créer `race_harvest`, clé sur (plan, race_id)** — 12 types ont un rendement
   dépendant du plan, un catalogue clé sur le seul type ne peut pas représenter
   le monde. Y verser les `biomes[]` **par commande console**, jamais par
   migration. Deux fichiers de plan sont du JSON invalide : la commande s'arrête
   dessus, elle n'invente pas de défaut.
3. **INSERT ensembliste** des 26 656 entités `'resource'` + emprises (0,34 s).
4. **Fenêtre de compat par vue à jointure** : les crons et les `UPDATE` passent
   (vérifié) ; les trois sites d'`INSERT`/`DELETE` basculent côté code **avant**.
5. Filtre sur la branche `resources` de l'UNION pour éviter le double dessin.
6. `ResourceService::findResourcesAround` lit l'occupant filtré sur *l'état*, au
   lieu de `damages=-1` croisé avec les clés `biomes[]` du plan.
7. **La course à la récolte se ferme** par le `SELECT … FOR UPDATE` de `place()`.
8. **Le butin** : `race_default_items` recopié à la pose, et **versé au sol** à
   la destruction au lieu d'être purgé — `BuildingService::deleteEntityRows:53`
   et `vanish:786` cessent de supprimer `players_items` ;
   `PlayerService:230` porte déjà le commentaire à l'emplacement exact.
9. `destroy.php` et `js/observe_destroy.js` meurent.
10. **Corriger le plafond d'épuisement seul, dans son propre commit, en le
    disant.**

**Vérifier avant d'engager** : `map_walls_archive` existe-t-elle en **vraie**
production ? `deploy_sql.sh` ne fait aucun `mysqldump` et ne pose aucun drapeau
de maintenance ; sans archive, la marche arrière d'une conversion de 26 656
lignes est la restauration d'une sauvegarde d'hébergeur.

---

## L7 — Les plantes et `creuser` · 1 semaine · capacités neuves

- Les 809 plantes rejoignent le modèle avec `respawn='spawner'` et le lien
  **explicite** instance → semoir (aujourd'hui implicite : même `coords_id`).
  Ce lien rend visible ce qui ne l'est pas : **648 plantes sur 809 n'ont aucun
  semoir**, et `lotus_noir` (12 plantes, zéro semoir) disparaît du monde à la
  douzième récolte.
- Supprimer la récolte au passage (`go.php:149` + `scripts/map/plants.php`) et
  la promesse « Se récolte en marchant sur la case » d'`observe.php:158`.
- **Action `planter`** (ItemPick sur `type=graine` + PlaceLayer sur la couche
  plants), qui ferme la fenêtre « la graine traîne au sol et se fait ramasser par
  le premier passant ». `growTo`/`growZMin` sortent des JSON vers `race_harvest`.
- **`creuser`** : la distribuer — elle existe (action 164, type `dig`, test
  étalon compris) mais `players_actions` en compte **zéro** ligne et aucune
  `race_starter_actions` ne la porte. Remplacer les `$_POST['digX']/['digY']`
  fabriqués par `go.php:235` par un BuildSitePick, remonter la confirmation
  « pas de Pioche » (18 lignes de JS inline) dans l'aperçu de coût, sortir la
  pierre en dur (`DigTunnelOutcomeInstruction:84`) vers le rendement du type.
  `go.php` cesse de creuser et se contente de **refuser** : la règle « à z<0,
  pas de sol = obstacle » rejoint le SSOT. **C'est exactement la formulation de
  la réserve sur la case spéciale, et elle ne demande aucune donnée nouvelle.**

---

## L8 — Découper `players` · 2 à 3 semaines · hors chemin critique

`accounts` d'abord (379 lignes concernées) : valeur propre et indépendante — les
identifiants cessent de vivre dans la table que traversent le rendu de carte et
six requêtes de blocage. Puis `characters` (19 colonnes, et la totalité des ~30
sites `UPDATE players SET`, tous concentrés dans `Classes/Player.php`).

Recette rétrocompatible : créer + miroir par déclencheur, basculer les lectures,
basculer les écritures et retirer les colonnes — **trois déploiements par
table**.

**Ne pas en faire un prérequis** : mesuré, la largeur de `players` ne coûte que
+6 Mo à 40 879 lignes. Ce lot se justifie par la clarté du modèle et la sécurité
des identifiants de compte, pas par la performance.

---

## L9 — Le tutoriel, à la toute fin de la saison

Rien avant que le reste ne soit stabilisé. Le tutoriel sera **adapté**, pas
rattrapé : le réparer au fil des lots, c'est le réparer autant de fois qu'il y a
de lots.

Ce qui l'attend à ce moment-là, déjà recensé :

- la spec Cypress attend 121 coords et 41 murs quand la graine en produit 81 et
  5+32 ; une fois d'accord, retirer `when: manual` et `allow_failure` ;
- `ActionStep::validateActionUsed:37` valide une CHAÎNE envoyée par le
  navigateur, pas un gain : l'étape `use_fouiller` avance même quand la
  condition a refusé. C'est pour ça que le tutoriel ne peut pas servir de filet
  au reste du chantier ;
- le catalogue `2.0.0-craft` enchaîne 7 étapes bâties sur une récolte
  impossible — les deux `pierre1` du plan tutoriel sont à `damages = 0` ;
- les plans d'instance sont clonés par session, et leurs ressources avec :
  c'est le seul monde reproductible du jeu (81 coords, 5 ressources, 32
  entités, 2 PNJ), donc le décor de test naturel de la migration.

---

## Ce qu'on ne fait pas

- **Pas de table d'objet parallèle** (`map_occupants` / `map_structures`) avec
  `entity_id` nullable.
- **Pas de troisième catalogue** (`structure_types`) : `resource_types` et
  `races kind='structure'` se contredisent déjà sur 11 noms.
- **Pas de renommage de `players` en `entities`**, même par vue de compat : ça
  ouvrirait une seconde fenêtre de double vérité alors que celle de `map_walls`
  n'est pas fermée. Le nom est laid ; il attendra.
- **Pas de clé de case calculée** au niveau global (0,013 % du gain une fois
  l'index posé, et elle rend l'historique *faux* quand un plan est recréé sous
  le même nom). Uniquement la dénormalisation `(plan,z,x,y)` sur `entity_cells`.
- **Pas de récolte ciblée** : `fouiller` reste une action de zone sur le 3×3.
- **Pas de correction d'équilibrage dans le lot de migration.**
- **Pas de butin dérivé des recettes de craft** (`craft_recipes` compte 2 lignes).
- **Pas de promesse « un seul système de récolte »** tant que les semoirs ne sont
  pas traités.
- **Pas de conservation de `map_plants.params`** : 0 ligne sur 809 l'utilise,
  `tile_info.php:23` la remplace par NULL, et elle est pourtant déclarée
  `paramsInKey: true` dans le diff Tiled.

## Hors périmètre, mais du même SSOT

Les ~9 000 `forbidden` nus (l'espace négatif des plans) deviennent un attribut de
praticabilité de la case — chantier distinct. Question corollaire jamais posée :
**un `forbidden` arrête-t-il la flèche ?** Aujourd'hui non — `lineOfFireReport`
ignore `map_triggers`, donc une falaise interdite laisse passer les tirs.

Et `View::get_coords_taken` reste à réécrire : plein-plan par construction,
25,5 ms aujourd'hui, encore 4,6 ms **avec** l'index.
