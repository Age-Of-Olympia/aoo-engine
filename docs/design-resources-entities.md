# Les ressources deviennent des entités — cadrage

Version 2 du 2026-07-27. La version 1 (2026-07-26) concluait *contre*
l'entification et *pour* un registre parallèle. Les arbitrages du lead et
une seconde passe de mesures ont invalidé ses trois arguments porteurs.
Ce document les corrige et fixe le modèle cible. Le déroulé opérationnel
est dans [plan-ressources-entites.md](plan-ressources-entites.md).

Suite de [design-walls-to-entities.md](design-walls-to-entities.md).

Mesures sur la réplique de production `aoo4_census` et sur bancs montés à
l'échelle. **Avertissement** : `aoo4_census` n'a aucun index (pas même de
PK sur `coords`) et lui manque `players_logs`, `doctrine_migration_versions`
et les tables `players_*`. Valable pour les volumétries, invalide pour toute
latence mesurée directement dessus.

---

## 0. Ce que la version 1 avait faux

Cinq corrections, dont trois renversent sa conclusion.

**« La vue de compat serait à jointure, donc non modifiable, donc les crons
casseraient : l'invariant migrations-avant-code interdit l'entification. »**
→ **Faux, vérifié en conteneur.** Une vue à jointure MariaDB accepte
l'`UPDATE` tant qu'il ne touche qu'une seule table sous-jacente. La forme
exacte de `ResourceService::exhaustResources()` (`UPDATE map_resources SET
damages=-2 WHERE id IN (…)`) passe à travers une vue
`resources_sat JOIN players`. Seuls échouent le `DELETE` (ERROR 1395) et
l'`INSERT` multi-table (ERROR 1393) — et il n'existe que **trois sites**
concernés dans tout le dépôt : `destroy.php:144` (voué à mourir),
`scripts/tiled/erase_case.php:41`, `TiledMapService::insertRow`. C'était
l'argument qui fermait la porte ; il n'existe pas.

**« Les 13 546 murs convertis n'ont rien apporté : 0 dialogue, 0 état, le
même texte partout. »** → **Faux.** La conversion date du 2026-07-19, les
dialogues portés par les objets ont été livrés quelques jours plus tard :
compter le contenu à J+7 mesure l'adoption par les animateurs, pas la
capacité du modèle. Et les capacités sont *câblées*, pas inutilisées —
`races.blocks_projectiles`, les PV entamés en `players_bonus` lus par
`StructureSheetView:48`, `BuildingService::refreshWoundSprite:675`,
`markDestroyed:655`, `PlayerService::processStructureDestruction:222`.
**Cet argument ne doit plus être rejoué.**

**« Le modèle manquant a pourri les données. »** → **Mauvais cadrage.** Les
ressources sans rendement sont majoritairement *voulues* : `arbre3` est
récoltable 4 135 fois et décoratif 99 fois, et c'est un bouton d'éditeur qui
en décide case par case (`scripts/tiled/tile_harvest_mode.php`, cycle
0 → -1 → -2 → 0). Le défaut n'est pas la corruption, c'est que **le modèle
ne sait pas distinguer « décoratif » de « mal configuré »** — donc personne
ne le peut, ni l'admin, ni un test, ni un rapport.

**« Collision de `display_id` = confusion d'identité. »** → **Faux.** Aucune
requête du dépôt ne route par `display_id` ; `infos.php:11` prend
`targetId` = `players.id`. La course des allocateurs `MAX+1` est réelle mais
la conséquence est cosmétique.

**« Densité 520 ressources en vue, l'hydratation par ligne est le blocage. »**
→ **Affaibli.** 520 est un pire cas géographique. La charge réelle mesurée
aux 262 positions effectivement occupées : 65 lignes aujourd'hui, 109 après
entification, soit 4 → 7 ms. L'hydratation est à corriger, elle n'a jamais
été le verrou. **Le verrou est `refresh_players_svg`** (13,3 → 48,3 ms sans
index, 2,6 ms avec index + scope).

Et une correction de comptage que j'avais moi-même propagée : les fragments
multi-cases ne sont pas 831 sur 27 familles. Il y a **trois** conventions de
nommage — `nom-NN` (831), `nom_NN` (546), et **sans séparateur** :
`Colosse_geant00`, `foret_malade00`, `triton_statue6` (85 lignes, 9 familles,
invisibles aux deux premiers regex). Total ≈ **1 422 lignes, 71 familles**.

---

## 1. Le vrai diagnostic : un objet, plusieurs adresses

La maladie de ce moteur n'est pas « trop d'entités ». C'est **« un objet,
plusieurs adresses »**. Le dossier la documente six fois, chiffres à l'appui :

| Objet | Adresses | Symptôme mesuré |
|---|---|---|
| Autel | `map_resources` + `map_triggers` | 10 autels sans dieu, 2 triggers sans autel, 4 cases à deux autels |
| Taverne d'Olympia | 4 tables | 9 décors + 4 `tp` vers **3 destinations différentes** + 2 `forbidden` + 5 herbes récoltables sur le toit |
| Suivants | `players_followers` + `map_foregrounds` | `add_follower` *adopte* une ligne de décor existante, `delete_follower` la supprime |
| Dialogues | `map_dialogs` par case | 335 lignes pour 225 params → 110 copies, deux déjà divergentes |
| Cocotier | `resource_types` + instances | `cocotier1` récoltable, `cocotier2/3` solides, 87 instances toutes à `-1` |
| Décor multi-cases | N lignes anonymes | 1 422 fragments, ≥ 354 objets, aucun lien entre les morceaux |

C'est pourquoi **créer une sixième adresse d'identité (un registre parallèle
avec `entity_id` nullable) serait une erreur de diagnostic** : tout objet qui
parle, se détruit ou porte un inventaire finirait avec deux clés primaires, et
chaque bout de code devrait savoir laquelle il tient.

L'identité de ce qui est posé sur une case existe déjà : `players.id`.

Le lead a raison sur le fond : **le problème n'était pas l'entité, c'était les
39 colonnes.** Après découpage, `players` n'est plus « un compte » — c'est dix
colonnes d'identité, de position et de présentation.

---

## 2. Ce que le modèle ne sait pas dire

Trois choses, et elles expliquent chacune une classe de bugs.

**« Cet objet occupe plusieurs cases. »** Le composite existe chez l'artiste
(une image découpée par `convert.sh`), dans l'éditeur (`TileCatalogService::
buildComposites()` redéduit la forme en divisant l'image entière par 50) et
dans le client Tiled (`AoO.addCompositeTiles`). Il était **détruit à
l'écriture** : le client ré-éclatait la tuile en N lignes `{x, y, name}`, et
l'objet mourait à la porte — il n'arrivait au serveur que des morceaux que
plus rien ne reliait.

**Corrigé** : le push envoie la tuile telle quelle, marquée `composite`, et
`TiledMapService::spreadComposites()` la découpe côté serveur avant le diff,
puis en fait une entité. Une ligne sans le drapeau passe inchangée, de sorte
qu'un greffon pas encore mis à jour continue de fonctionner.

Conséquences : la forme du monde dépend d'un script shell d'un dépôt non
versionné avec le moteur, et elle a **déjà dérivé** — `geant_petrifie` porte
deux formes sous un même nom (paires verticales sur 43 poses, horizontales sur
29 lignes), `asteroide` est posé en largeur 8 contre 3×3 déclaré, `dart` (5×5)
est rejeté de la palette faute de 8 PNG de coin transparents.

**« Cet exemplaire est décoratif. »** Propriété d'instance aujourd'hui, portée
par une sentinelle sur `damages` et pilotée par un bouton d'éditeur. Le modèle
cible doit **garder un état d'instance** — la mettre uniquement sur le type ne
répond pas à l'arbitrage.

**« Cet arbre donne du bois *ici*. »** Le rendement est une propriété du couple
**(plan, type)**, pas du type : `jungle1` donne `epine` sur six plans et
`tourbe` sur `jungle_sauvage` ; `pierre_noire*` donne `pierre_noire` sur dix
plans et `cendre` sur deux ; `arbre_petrifie*` donne `bois` ou `bois_petrifie`.
Les taux divergent aussi (`rocher_desert*` : exhaust 20 ou 75). Un catalogue
clé sur le seul type **ne peut pas représenter le monde actuel**.

---

## 3. Le modèle cible

Quatre pièces, **aucune nouvelle adresse d'identité**.

### 3.1 Identité — `players`, réduite

La table reste, ramenée au groupe `GameEntity` (les 25 colonnes `Character`
sont à zéro sur les 13 549 bâtiments, sans exception) :

```
players(id, player_type, display_id, name, coords_id, race,
        avatar, portrait, text, registerTime, visible)
```

Sortent en satellites :

```
accounts   (player_id, psw, mail, plain_mail, email_bonus,
            lastLoginTime, deletion_asked)              -- 379 lignes
characters (player_id, xp, pi, pr, pf, malus, energie, godId, rank,
            bonus_points, story, quest, faction, factionRole,
            secretFaction, secretFactionRole)           -- 674 lignes
turns      (player_id, nextTurnTime, nextTurnRescheduled,
            lastActionTime, antiBerserkTime, lastTravelTime)
```

**Trois satellites et non deux** : la temporalité de tour est séparée de la
progression de personnage, parce qu'un **bâtiment de défense agira** — il lui
faut un tour, sans lui faire porter ni XP, ni foi, ni faction. `turns` est donc
peuplé pour tout ce qui agit ; `characters` pour les seuls personnages.

`player_type` gagne `'resource'` et `'scenery'`. Deux valeurs, coût nul —
elles servent uniquement à **scoper** (`refresh_players_svg`, hydratation,
carte du monde). Le comportement vient du catalogue, jamais du discriminant.

**Attention à la ligne de partage.** Elle ne passe pas entre « joueurs » et
« structures », mais entre **ce qui agit** et **ce qui est inerte**. Les
bâtiments agiront (bâtiments de défense) : ils tiendront une session, un tour,
un cache, comme un personnage. Seuls `resource` et `scenery` sont inertes par
construction. Tout filtre de scoping doit donc **exclure l'inerte**, jamais
« garder les acteurs connus » — une liste blanche d'acteurs devient fausse au
premier bâtiment qui agit, et échoue en silence.

Ne **pas** passer Doctrine en `InheritanceType('JOINED')` : `Building` et
`UniqueObject` n'ont aucun champ propre et on poserait une jointure sur le
chemin le plus chaud. `accounts`/`characters` sont des satellites au même
titre — patron déjà prescrit par `Structure.php:20-22`.

### 3.2 Emprise — `entity_cells`, la pièce qui manquait

```sql
entity_cells(
  player_id INT NOT NULL,        -- FK players ON DELETE CASCADE
  coords_id INT NOT NULL,        -- FK coords
  plan VARCHAR(50), z SMALLINT, x INT, y INT,   -- dénormalisé : chemin chaud
  piece SMALLINT NOT NULL DEFAULT 0,            -- index du morceau de sprite
  role  VARCHAR(16) NOT NULL,                   -- part|block|cover
  PRIMARY KEY (player_id, coords_id),
  KEY k_coords (coords_id, player_id),
  KEY k_hot (plan, z, x, y)
)
```

Invariant : toute entité posée tient une case à `players.coords_id`. **La
colonne `players.coords_id` est conservée** comme origine — aucun des 337
sites `coords_id` du dépôt ne casse — et `entity_cells` devient la SSOT de
l'occupation.

Les rôles :

- `block` — bloque le pas ;
- `cover` — **marchable, dessinée au-dessus du joueur** : c'est « la portion
  haute où l'on va se cacher derrière ». C'est le cas particulier d'affichage
  assumé : la branche `players` de l'UNION émet
  `CASE role WHEN 'cover' THEN 100 ELSE 98 END AS tableOrder`, et le mécanisme
  `<use>` de `Classes/View.php:486` existe déjà.

  **Un ordre de dessin, et rien d'autre.** Le personnage est bien caché — mais
  à l'affichage seulement : le sprite passe devant lui, et là s'arrête l'effet.
  Le moteur ne cache rien. L'occupant reste listé à l'observation, reste visé
  par les tirs, et suit pour le pas les règles ordinaires. Faute de quoi il
  serait inatteignable : on passerait derrière l'arrière d'un bâtiment pour
  devenir invulnérable. Quand la ligne de tir lira `entity_cells` (aujourd'hui
  `BuildingService::lineOfFireReport()` ne joint que `players.coords_id`),
  seules les cases `block` feront écran ; `cover` et `part` laisseront passer
  les projectiles comme elles laissent passer le pas ;
- `part` — appartient à l'entité et ne prétend rien de plus : c'est le type
  qui tranche le passage. C'est le rôle par défaut.

Deux rôles annoncés par les premières versions de ce plan n'ont PAS été
retenus.

`door` devait « remplacer les `tp` recopiés à la main ». Il ne le peut pas :
la destination d'un téléporteur vit dans `map_triggers.params`, et un rôle de
seize caractères ne saurait la porter — encore moins les trois destinations de
la taverne d'Olympia. Une porte vers un autre plan garde donc son déclencheur,
qui seul sait où elle mène. Une porte qui ne fait que barrer le chemin sur le
même plan n'a besoin de rien d'autre que du passage, donc de `block`.

Ce que `door` aurait dit en plus — « cette case est une entrée » — est de
toute façon déjà lisible : le déclencheur est posé dessus. Et si une porte
doit un jour se fermer, ce n'est pas la case qui le dira mais l'état de
l'entité, que `BuildingService::closureReason()` connaît déjà.

`open` n'a jamais été défini nulle part, ni écrit, ni lu. Il aurait dit
« cette case ne bloque pas, quoi que fasse son type » — percer une porte
cochère dans un mur. Le besoin est plausible ; il n'existe pas aujourd'hui, et
aucune case ne sait donc ouvrir un passage dans un type qui bloque. Le jour où
quelqu'un en a besoin, le rôle revient AVEC une définition et un écrivain.

`anchor` a existé le temps d'un lot : il marquait la case d'origine, une
POSITION dans une colonne de natures, et il doublait `players.coords_id` —
d'où la mécanique de dérive qu'il a fallu écrire pour surveiller la copie.
L'origine reste `players.coords_id`.

Le rôle par défaut vient du catalogue et reste **surchargeable case par case** —
l'admin voit alors que la case *diverge* de son type (les 6 géants sur 43 dont
la base ne bloque pas, l'herbe sur le toit de la taverne).

#### Le comportement partagé tombe de la jointure

C'est l'intérêt principal de l'emprise, et il ne demande aucun code de
partage. Aujourd'hui `observe.php` résout une case en entité par
`p.coords_id = c.id` — **huit occurrences** —, si bien que seule la case
d'ancrage d'une taverne 3×3 trouve le bâtiment ; les huit autres ne trouvent
rien. D'où les **335 lignes `map_dialogs`**, des dialogues recopiés à la main
case par case pour compenser.

Passer cette jointure par `entity_cells` suffit : toute case de l'emprise rend
le même `player_id`, donc la même fiche, le même dialogue, les mêmes PV, les
mêmes actions. Les recopies deviennent du bruit à supprimer.

Une nuance qui compte : « toutes les cases ouvrent le même dialogue » oui,
« toutes les cases se comportent pareil » non. C'est à cela que servent les
rôles — une taverne dont le toit est franchissable et la porte unique, c'est
une seule entité, un seul dialogue, trois comportements de case.

#### Les bâtiments sont concernés au même titre que le décor

Un bâtiment EST une entité : 13 549 lignes `player_type='building'`, qui ont
déjà leur ancre. Ce qui leur manque, c'est une découpe de plus d'une case.

Les gros bâtiments, eux, sont encore du décor — **280 cases en `unique_*`**
(taverne, fort, praetorium, pyramide) que la conversion des murs avait
délibérément écartées. Décor et bâtiment sont donc le même sujet, et se
règlent par la même table.

#### Un seul catalogue de ce qui se pose sur une case

`races.kind` distingue déjà `character` (22) de `structure` (64). Les
ressources, elles, vivent dans une AUTRE table — même idée, mécanisme
différent — et c'est ce qui produit le désordre mesuré le 28 juillet :

- **47 noms vivent dans les deux catalogues** (`altar`, `cocotier1`,
  `mur_pierre`…). Pour `mur_pierre` : 6 433 entités posées, 0 ressource — la
  ligne de `resource_types` est un vestige de la conversion des murs ;
- joindre `resource_types.name` à `map_resources.name` **échoue** — « Illegal
  mix of collations ». Les deux moitiés du même sujet ne se parlent pas ;
- sur 105 types de ressource : 44 réellement posés, **42 devenus des
  structures**, 19 sans pose ni race ;
- **4 ressources posées n'ont aucun type** (`glaise3`, `arbre7`, et deux
  `unique_*` mal rangés).

**Cible : un seul catalogue**, `races`, avec `kind` ∈ `character` |
`structure` | `resource` | `scenery`. `races.name` étant déjà UNIQUE,
l'unicité des noms devient une contrainte de base et non une convention
qu'on espère respecter.

**Arbitrages rendus (28 juillet) :**

- **Un nom, un objet.** Les trois familles de décor en collision — `tonneau`
  (50 poses de décor face à 151 obstacles), `enclume` (9), `centaure` (7) —
  ne sont pas renommées : elles **fusionnent avec le type obstacle**. La
  traversabilité se règle. Attention : `races.blocks_passage` est un réglage
  par TYPE ; le cas par cas passe par `entity_cells.role`, que la conversion
  doit donc poser (`open` pour les tonneaux couchés) et pas seulement
  fusionner les noms.
- **« Cassé » est un état, pas un type.** Les 18 entrées `*_broken` de
  `resource_types` ne sont posées nulle part et décrivent l'apparence abîmée
  d'un objet, que `BuildingService` dérive déjà du type et des PV. Retirées.
  **Les images restent** — c'est le sprite que la bascule va chercher.
  `altar_broken`, lui, est POSÉ cinq fois et relève du lot des autels.
- **`pierre_precieuse`** (500 PV, posée nulle part) devient un décor comme
  les autres, avec un inventaire par défaut qui pourra tomber à sa
  destruction — donc `race_default_items`, à créer.

#### Les deux éditeurs, et l'admin

Trois surfaces à traiter, pas une :

- ~~**Tiled** (externe) ré-éclate la tuile posée en morceaux au push~~ —
  fait : la tuile part entière, le serveur la découpe ;
- **tiled** (maison) efface le décor case par case
  (`DELETE FROM <table> WHERE coords_id = ?`), alors qu'il sait déjà démonter
  un bâtiment par son service. C'est de là que viennent les fragments
  orphelins ;
- **l'admin** doit porter le catalogue des découpes, comme il porte déjà les
  races, les effets, les objets et les types de ressource. Découpe, rôle de
  chaque morceau, arbitrage figure complète / demi-figure : ce sont des
  décisions de décor, elles appartiennent à une page d'administration et non à
  une migration.

Coût mesuré du JOIN sur le chemin chaud (banc à 40 879 entités, fenêtre p=12 la
plus dense de `fort_turok`, index posé, 200 répétitions) : **2,17 → 2,62 ms.
+0,45 ms par rendu de damier.** Non-sujet.

**Réserve ouverte, non résolue : le multi-z.** `porte_des_enfers` est posée aux
mêmes (x,y) sur z = -3, -2, -1 et 0 ; `glinthil_tribute` sur deux z. Quatorze
colonnes (x,y) portent la même famille sur 2 à 4 niveaux. Un regroupement par
(famille, plan, z) les coupe en quatre objets. À trancher avant l'emprise du
décor — pas avant celle des ressources, qui sont mono-case.

### 3.3 Catalogue — `races`, seul

**Pas de table `structure_types` séparée.** `resource_types` et
`races kind='structure'` décrivent déjà la même chose sur 41 noms communs et se
contredisent sur 11 (races dit `blocks_projectiles=0`, `lineOfFireReport` bloque
sur toute ligne `map_resources`). Un troisième catalogue institutionnaliserait la
divergence.

`races` porte déjà `kind`, `structure_nature`, `blocks_passage`,
`blocks_projectiles`, `readable_from_afar`, `bleeds`, `wound_color`,
`default_text` et les 16 CARACS dont `pv`. `structure_nature` s'étend et devient
le porteur des deux familles :
`'edifice' | 'obstacle' | 'solide' | 'plante' | 'decor'`.

Trois satellites de catalogue :

```
race_footprint    (race_id, w, h, roles)
race_harvest      (plan, race_id, yield_item_id, dice, exhaust, regrow,
                   on_exhaust ENUM('delete','keep'),
                   respawn ENUM('in_place','spawner'),
                   plantable_from_item_id NULL)
race_default_items(race_id, item_id, n)
```

`race_harvest` est **clé sur (plan, race_id)** — c'est la correction imposée
par le §2 : 12 types ont un rendement dépendant du plan. Le plan `NULL` sert de
défaut, la ligne par plan surcharge.

`race_default_items` est l'inventaire par défaut, recopié dans `players_items`
à la pose puis libre — patron exact de `races.default_text → players.text`
(`Race.php:73`). C'est lui qui porte le butin de destruction. **Ne pas** le
dériver des recettes de craft : `craft_recipes` compte 2 lignes.

**Attention, la clé n'est pas le nom.** `tonneau` existe en
`img/foregrounds/tonneau.png` (50 poses de décor traversable) **et** en
`img/walls/tonneau.png` (`resource_types.tonneau = 5 PV`). Et il existe un
**cinquième référentiel par nom** que personne n'avait listé : `tile_colors`
(51 lignes), qui cherche le même nom successivement dans
`['tiles','resources','elements','foregrounds']`. Le catalogue doit être clé sur
l'archétype, pas sur la chaîne.

Meurt : `resource_types.pv`, l'INT à trois sémantiques.

### 3.4 Satellites — tous déjà là

`buildings`, `unique_objects`, `players_items` (le butin), `players_bonus` (les
PV entamés), `players_effects`, `players_logs.target_id` (le log « vous récoltez
*ce* filon », aujourd'hui impossible), `players_kills`, `players_assists`,
`players_actions`. Sur les 38 FK vers `players.id` : 13 ont un sens pour une
structure, 3 désignent un auteur de pose, 22 restent vides — comme aujourd'hui
pour les bâtiments.

`map_dialogs` devient `entity_dialogs(player_id, …)` : les 110 lignes
copiées-collées disparaissent, et la divergence déjà survenue devient
inexprimable.

---

## 4. Les deux familles de récoltables

Elles se disent sans inventer une colonne :

| | `blocks_passage` | `structure_nature` | `on_exhaust` | `respawn` | `pv` | `race_default_items` |
|---|---|---|---|---|---|---|
| **Plante** | 0 | `plante` | `delete` | `spawner` | NULL | — |
| **Solide** | 1 | `solide` | `keep` | `in_place` | > 0 | le butin |
| **Décoratif** | 1 | `decor` | — | — | — | — |

Le décoratif devient **exprimable**, et « mal configuré » devient
**détectable** : un type `solide`/`plante` sans ligne `race_harvest` est un
rapport d'admin, plus une ressource muette.

Le moteur de récolte ne change pas : `fouiller` reste une action de zone sur
le 3×3, l'agrégation par rendement et le dé `1dN` sont conservés. Ce qui change
est ce qu'il lit — l'état d'un occupant au catalogue, au lieu de `damages=-1`
croisé avec les clés `biomes[]` d'un JSON gitignoré.

**Deux découvertes qui doivent être gelées avant d'y toucher :**

- **3 476 ressources (13 % du parc) ne s'épuisent jamais.** 41 des 373 entrées
  de biome n'ont ni `exhaust` ni `regrow` ; `null > random_int(1,100)` est
  toujours faux. Concentrées sur `praetorium` (herbe, 1 293), `eryn_dolen`
  (arbre, 744), `cimes_geantes` (508), `fort_turok` (430). C'est une ferme
  infinie en production. Sortir les taux vers le catalogue leur donnerait
  silencieusement l'épuisement : **c'est un changement d'équilibrage, pas une
  migration.**
- **648 plantes sur 809 n'ont aucun semoir** (`map_triggers name='grow'`), et
  `lotus_noir` (12 plantes, zéro semoir) disparaît du monde à la douzième
  récolte. Les faire entrer dans le modèle sans traiter le semoir donnerait 809
  ressources non renouvelables qui ont l'air renouvelables.

---

## 5. SSOT du blocage

Sept prédicats aujourd'hui, **quatre réponses différentes** sur le même plan.
Mesuré sur `fort_turok_s2` (2 584 cases) : `go.php` 1 050 cases bloquées,
`View::get_coords_taken` 1 120, `BuildingService::place` 1 234,
`lineOfFireReport` 1 028 — aucun n'est un sur-ensemble d'un autre, **206 cases
de désaccord (8 % du plan)**. Les trois prédicats clients déduisent du DOM et se
contredisent entre eux (`js/view.js:346` exclut `[data-passable]`,
`js/blocked-tiles.js:43` non).

`App\Service\Map\TileOccupancyService` : une lecture de la case, trois verdicts
— `step()`, `projectile()`, `build()`. Il lit **deux sources distinctes** : la
praticabilité de la case (`forbidden`, « pas de sol à z<0 ») et le rôle des
occupants (`entity_cells.role` joint à `races.blocks_*`).

Règle structurante : **bloquer, c'est être vu.** Le service se construit sur le
même filtre de visibilité que `Classes/View.php:237-401`. Le bug de `go.php:67`
(2 819 entités sur 20 plans sans JSON ne bloquent plus) disparaît alors **par
construction**. Le correctif naïf — sortir la sous-requête du `if` —
produirait un mur invisible, puisque `View.php:392` *cache* les joueurs réels
de ces plans tout en dessinant les structures.

`ObstacleCondition` doit être supprimée, mais **pas parce qu'elle est morte** :
`action_condition_preconditions` la déclare précondition de six types de
condition (`Version20260622170000:32`) et `ActionExecutorService::
checkWithPreconditions:212` l'exécute. Elle lance donc réellement
`View::get_walls_between` — une requête plus un Bresenham — **à chaque tir,
technique et sort**, puis jette la réponse.

---

## 6. Ce qui sort du modèle d'occupation

C'est le plus gros gain de volume, et il ne coûte presque rien.

- **`ombre` : 8 353 lignes, 82 % de `map_foregrounds`.** Un carré noir uniforme
  à alpha 15, dessiné deux fois, dont **509 cases en double** (deux à trois fois
  trop sombres, sans qu'aucun écran ne le dise). Ce n'est pas un objet : c'est
  une propriété d'éclairage de la case.
- **Les suivants : 55 lignes** (`marchand` 33, `instructeur` 22). Attribut de
  rendu du joueur suivi, pas occupant. `players_followers` devient autoporteur.
- **`forbidden` : 12 187 lignes dont ~9 000 ne portent rien** (temple_s2
  850/860, lac_thetis z=-2 276/276). Ce n'est pas un occupant, c'est la
  praticabilité de la case — l'espace négatif des plans. Seules ses **185**
  lignes posées sous un fragment deviennent `role='block'`.
- **`map_tiles` à z<0** : ce n'est pas du décor, c'est le sol. Son *absence*
  est le roc plein (`go.php:170`). Seule règle de blocage du jeu qui s'exprime
  par une absence ; le SSOT doit l'accepter telle quelle. C'est aussi la forme
  exacte de la réserve du lead sur `creuser`.

Après ces trois retraits, `map_foregrounds` tombe de 10 215 à **1 862 lignes**.
Le décor n'est pas un chantier de volume : c'est un petit chantier de modèle.

---

## 7. Coût résiduel, mesuré

**Volume.** 13 549 déjà là + 26 656 ressources + ~760 décors + 809 plantes
≈ **41 800 objets**, ~42 900 lignes d'emprise. Migration des ressources :
INSERT ensembliste **0,34 s**. Matérialisation d'`entity_cells` à l'identique :
0,3 s. Disque : +6 Mo de données, +4 Mo d'index sur `players`, +6,52 Mo pour
l'index `coords`. À surveiller : 41 800 inodes dans un répertoire de cache plat
sur mutualisé.

**Latence.** Les trois chemins qui comptent :

| Chemin | Aujourd'hui | Entifié sans rien faire | Entifié + index + scope |
|---|---|---|---|
| Rendu du damier (p=12, cas dense) | 2,17 ms | — | 2,62 ms |
| `refresh_players_svg` (à chaque pas) | 13,3 ms | 48,3 ms | **~13,3 ms, stable** |
| `observe.php` (par clic) | 249,6 ms | — | **0,629 ms** |

Nuance sur le balayage SVG, corrigée le 2026-07-27 : le scope **exclut l'inerte**
(`resource`, `scenery`) au lieu de garder une liste blanche d'acteurs, parce que
les bâtiments agiront. Il ne fait donc pas *baisser* le coût actuel, il l'empêche
de **croître** — mesuré sur une fenêtre dense de `fort_turok` : 223 lignes
balayées aujourd'hui, 213 de plus qu'on évite d'y ajouter. Descendre sous les
13 ms supposerait de sortir aussi les bâtiments, ce qui est désormais exclu.

À cette réserve près, le jeu à 41 800 entités reste plus rapide qu'aujourd'hui à
14 223 — à condition que l'index `coords(plan,z,x,y)` et ce scope soient posés
**avant**. C'est le seul verrou dur du dossier, et il coûte une journée.

`observe.php` seul justifie l'index : 249,6 ms de SQL par clic de case
aujourd'hui, neuf balayages complets de couche. C'est le plus gros gain isolé du
dossier et il ne dépend d'aucun arbitrage.

**Travail.** 41 lignes de catalogue (48 types posés − 4 déjà en `races` − 3
`unique_*`), dans une table qui a déjà toutes les colonnes et une page d'admin :
un après-midi. 60 références à `map_resources` dans 25 fichiers hors migrations,
**dont aucun n'est analysé par PHPStan** (`Classes/`, la racine et `scripts/`
sont hors périmètre). Côté décor il n'y a rien à réécrire : `observe.php`
n'ouvre **jamais** `map_foregrounds`, aucun sélecteur
`data-table="foregrounds"` n'existe côté client — les 275+ structures du monde
sont muettes *par construction*.

**Ce que ça ne résout pas.** `View::get_coords_taken` balaie le plan entier,
trois fois en UNION, sans borne : 25,5 ms aujourd'hui, encore 4,6 ms *avec*
l'index. Structurellement plein-plan, à réécrire.

---

## 8. La clé de case calculée — réponse

**Non au niveau global, oui au niveau local.**

Une fois l'index posé, la résolution `(plan,z,x,y) → coords_id` ne pèse plus que
**0,032 ms sur les 249,6 ms** d'`observe.php`, soit 0,013 % du problème.

Trois options écartées, une retenue :

- **Rendre `coords.id` calculable** : arithmétiquement mort — 34 bits
  nécessaires, 31 disponibles dans un INT signé.
- **Clé composite (plan,z,x,y) propagée** : +66 % par table enfant sur 13
  tables, et elle détruit la seule propriété précieuse du substitut — renommer
  un plan est *une* requête aujourd'hui.
- **Clé entière calculée en BIGINT** : elle butte sur un usage réel supporté par
  le dépôt — `PlanAdminService::purgePlanRows()` sait supprimer un plan, et le
  nom redevient immédiatement réutilisable. Avec un substitut, l'historique
  détaché devient « sans lieu » ; avec une clé calculée il reste *valide* et
  désigne le **nouveau** plan. L'historique ne devient pas invisible, il devient
  **faux**.
- **Retenu** : dénormaliser `(plan,z,x,y)` sur `entity_cells`. C'est le chemin
  chaud du rendu, ça supprime la jointure `coords` sans toucher au reste du
  schéma.

---

## 9. Bugs actifs découverts, indépendants de ce chantier

Aucun n'attend un arbitrage. Certains sont graves.

1. **`Classes/Player.php:1223` — `DELETE FROM players_bonus WHERE name IN
   ("pm","pv") AND n >= 0`, sans `player_id`.** Exécuté à chaque coup porté,
   chaque soin, chaque repos, chaque coût de mouvement — `putBonus` a 55
   appelants. Balayage complet de la table (la clé primaire est
   `(player_id, name)`, il n'y a aucun index sur `name` seul).

   **Ce n'est pas un bug de comportement**, contrairement à ce que disait la
   première version de ce document. `players_bonus.n` pour `pv`/`pm` est un
   *déficit* : une ligne à `n >= 0` équivaut à l'absence de ligne, et
   `putBonus` plafonne le soin au déficit (`:1174`), donc `n` ne peut pas
   devenir positif par le jeu. Le DELETE global ne supprimait que des lignes
   déjà vides de sens.

   Ce qui était vrai : un coût qui grandit avec le nombre d'entités blessées —
   soit exactement ce que l'entification multiplie — et un effet de bord
   unique, `admin/player-edit.php:98` calculant `n = wanted - max` sans
   plafond, une vitalité fixée au-dessus du maximum étant reprise par la
   première action venue. Corrigé en scopant sur `player_id`, forme déjà
   utilisée par `applyUnequipItemBonus`.
2. **`reparer` (action 92) donne 3 XP par point d'action sans vérifier que la
   cible est endommagée**, et `HealingOutcomeInstruction` retourne toujours un
   succès. Meilleur rapport XP/A du jeu, contre n'importe lequel des 13 546
   bâtiments, indéfiniment — dès que l'action est distribuée. Le lot
   « destruction par le moteur d'actions » la distribuera nécessairement.
3. **`map load` a perdu les murs des 21 cartes archivées.** Elles portent la clé
   `walls` ; le chargeur mono-fichier n'itère plus que `resources`. **5 535
   lignes** ignorées en silence (fort_turok 2 619, praetorium 1 424). Et ce
   chemin n'exécute aucun `DELETE` avant l'`INSERT` : recharger duplique.
4. **La carte du monde ne dessine que `LIKE '%mur%' OR LIKE '%arbre%'`**
   (`ViewService::generateResourceLayer`). Depuis la conversion, `%mur%` ne
   matche plus rien : **74 % du parc** (pierre, herbe, jungle, pierre noire,
   rochers, cocotiers, minerais) n'apparaît nulle part. SQL concaténé au passage.
5. **`PlanAdminService` compte les bâtiments comme des joueurs**
   (`SUM(p.id > 0)`) : `praetorium_save` s'annonce à 663 joueurs, aucun réel. Et
   la suppression de plan est cassée — `deleteNpcsOnPlan` ne prend que
   `id < 0`, puis `DELETE FROM coords` lève 1451 et annule tout le purge.
6. **`TutorialMapInstance::deleteInstance` supprime par `id < 0`** : chaque
   session abandonnée laisse un plan complet et 32 entités indestructibles.
7. **`BuildingService::place()` ignore `map_foregrounds` et `map_triggers`** :
   on peut bâtir au milieu de la taverne. 50 cases de fragment portent déjà une
   entité, 73 une route, 183 une ressource récoltable.
8. **`map_plants` n'a ni `player_id` ni FK vers `coords`** : c'est le plus petit
   prérequis du dossier, et le seul strictement bloquant pour « les joueurs
   peuvent planter ».
9. **`cimetiere_s2` déclare `{wall: pierre1, ressource: "pierre1"}`** — un
   rendement qui n'existe pas comme objet. `ResourceOutcomeInstruction` appelle
   `get_data()` sur le `false` de `get_item_by_name`. 29 ressources récoltables
   sur ce plan : `fouiller` à côté est une erreur fatale, aujourd'hui.

---

## 10. Ce qui reste à trancher

Par ordre d'urgence dans le plan. **Tranché le 2026-07-27** : un autel peut
exister sans dieu et être déifié ensuite — les 10 autels orphelins ne sont pas à
supprimer, ce sont les premières cibles de l'action `consacrer`. `consacrer`
porte alors la condition « vénérer un dieu » et le coût de 50 PF, que !491 avait
mis sur la construction.

1. ~~Multi-occupation~~ — **tranché le 2026-07-27 : oui, et c'est une capacité à
   préserver.** Empiler des personnages et des choses sur une case sert aux
   animateurs et à l'administration ; c'est peu utilisé, mais nécessaire. Le
   monde le fait déjà (174 cases à 2-4 ressources, 66 cases à plusieurs entités,
   642 à plusieurs décors) et ce n'est donc pas une dérive à résorber.

   Ce n'est pas qu'une commodité d'animation : c'est ce dont **les plantes**
   auront besoin — une plante marchable partage forcément sa case avec ce qui
   pousse dessus ou autour — et ce que réclament les cas particuliers du type
   **mur factice**, une structure qui a l'air d'arrêter et qu'on traverse.

   Le mur factice a d'ailleurs déjà son mécanisme : `races.blocks_passage = 0`,
   la « structure passable » que `go.php` sait déjà exclure du blocage. Aucun
   type ne l'utilise aujourd'hui — c'est une capacité en attente d'usage, pas du
   code mort à retirer.

   **Le cas à ne pas casser en L4 : décor + déclencheur.** Un téléporteur est
   invisible par lui-même ; ce qui le signale au joueur est un DÉCOR posé sur
   la même case. Mesuré sur les 1 746 déclencheurs `tp` de production :
   9 escaliers vers le bas, 9 vers le haut, 9 échelles, 14 portes des enfers,
   plus les entrées de lieux (mine, carrière, tourbière, chantier, fort).
   En face, **2 entités** et 4 ressources seulement.

   Ces marqueurs étant des décors, `BuildingService::place()` ne les gouverne
   pas aujourd'hui — la superposition passe donc sous le radar. Le jour où L4
   entifie les décors, elle y passera : **toute règle du type « on ne bâtit pas
   sur un déclencheur » interdirait de poser un escalier sur un téléporteur**,
   c'est-à-dire le geste même qui lui donne son sens.

   C'est aussi pourquoi la divergence épinglée par le test étalon — `place()`
   ignore les déclencheurs quand `isVacant()` les compte — ne doit PAS être
   « corrigée » par réflexe de cohérence. Elle est du bon côté.

   Conséquences pour l'emprise : la PK `(player_id, coords_id)` l'autorise
   nativement, et `BuildingService::place()` — qui refuse aujourd'hui de poser
   sur une case occupée — devra distinguer « refuser au joueur » de « permettre
   à l'animateur », au lieu d'interdire tout court. Reste à écrire **qui gagne
   au rendu** (le `tableOrder` de l'UNION décide déjà, il faut l'assumer) et
   **ce qui bloque** quand plusieurs occupants se contredisent : la règle
   naturelle est que le plus restrictif l'emporte, un seul occupant bloquant
   suffisant à bloquer — le mur factice étant précisément l'exception qui ne
   bloque rien.
2. **Les 2 148 lignes à `damages=0`** sur un type récoltable — décor voulu, ou
   mal configuré ? À trancher **par type**. Cas visible : les deux `pierre1` du
   plan tutoriel sont à 0, et 7 étapes du tutoriel artisanat sont bâties dessus.
3. **Les 3 476 ressources inépuisables** — on leur donne l'épuisement (changement
   d'équilibrage assumé et annoncé) ou on grave l'exception au catalogue ?
4. **Les 648 plantes sans semoir** — on pose les semoirs, ou on assume une
   ressource définitivement épuisable ? Dans ce cas il faut le dire au joueur.
5. **Équilibrage de la destruction** — quels PV pour un arbre ? Quelle arme (la
   règle « une arme de mêlée, pas les poings » n'a pas de sens pour un arbre ;
   une hache, oui) ? Quel butin — le rendement de récolte, l'objet constructible
   qui l'a posé, ou un stock déclaré ? Les trois sont exprimables ; c'est le sens
   narratif qui manque.
6. ~~La profanation~~ — **tranché** : l'autel nu est neutre. Le décompte du
   contrôle territorial porte `WHERE godId != 0` ; un autel non consacré n'ôte
   le contrôle à personne et n'en donne à personne.
6. **Coût de la cueillette** — faire entrer les plantes dans le moteur de zone
   les fait passer de 0 à 1 PA. On l'assume, ou on garde un drapeau
   `harvest_on_walk` ?
7. **Le multi-z du décor** (`porte_des_enfers` sur quatre étages) — un objet ou
   quatre ?
8. **La taverne d'Olympia** — on redresse (et on modifie une carte que les
   joueurs connaissent, trajets compris) ou on gèle l'existant ?
9. **Le cocotier** — une espèce à trois sprites, ou trois types dont deux
   fausses saisies ? La réponse ferme la fenêtre de compat du 2026-07-20
   (`coco.json → growTo: table:"walls"` en est la dernière écriture).
10. **~32 arbitrages humains** sur les fragments orphelins et groupes incomplets :
    aucune trace ne permet de distinguer « décor volontairement tronqué » de
    « casse d'édition par `erase_case.php` ».
11. **PHPStan** — étend-on le périmètre à `Classes/`, la racine et `scripts/`,
    même au niveau 0-1 ? C'est exactement le périmètre à réécrire.
12. **Réversibilité** — `deploy_sql.sh` ne fait aucun `mysqldump` et ne pose
    aucun drapeau de maintenance. `map_walls_archive` existe dans `aoo4` mais son
    existence en **vraie** production n'a jamais été vérifiée.
