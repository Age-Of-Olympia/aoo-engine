# Un parent pour les types — cadrage

Note de conception du 2026-07-31, à trancher avant le lot des plantes.

Suite de [design-resources-entities.md](design-resources-entities.md), dont
elle applique le même geste — un parent, des déclinaisons — non plus aux objets
posés sur la carte, mais au **catalogue qui les décrit**.

---

## 0. Le constat, en chiffres

`races` porte **41 colonnes pour 128 lignes**, réparties en cinq populations
que rien ne sépare qu'un couple de colonnes :

| `kind` | `structure_nature` | lignes | ce que c'est |
|---|---|---|---|
| structure | obstacle | 53 | murs, palissades |
| structure | ressource | 42 | arbres, pierres, tourbe |
| structure | decor | 16 | statues, tertres, figures |
| character | edifice | 16 | **races jouables** |
| structure | edifice | 1 | bâtiment à porte |

La quatrième ligne dit déjà le problème : seize races de personnages portent
`structure_nature = 'edifice'`, qui ne veut rien dire pour elles. Elles le
portent parce que la colonne est `NOT NULL` et qu'il fallait bien écrire
quelque chose.

Le même défaut se lit colonne par colonne :

- `harvest_item`, `harvest_exhaust`, `harvest_regrow` : vides pour 86 lignes
  sur 128 ;
- `playable`, `faction`, `plan`, `animateurId`, `portraitNextNumber`,
  `avatarNextNumber` : n'ont de sens que pour les 16 personnages ;
- `readable_from_afar`, `default_text` : n'en ont que pour les 112 structures ;
- les 16 CARACS (`a`, `mvt`, `cc`, `ct`, `agi`…) décrivent un personnage ; seul
  `pv` sert aussi à une structure, qui se frappe.

Aucune de ces colonnes n'est fautive. Ce qui l'est, c'est qu'elles vivent
toutes au même endroit et que **le code doit demander à chaque fois de quelle
population il parle**.

## 1. Le précédent est déjà dans le dépôt

Le côté OBJET a déjà reçu ce traitement : `players` est un tronc
`GameEntity` avec un discriminant `player_type`, décliné en `Structure`,
`Resource extends Structure`, `Scenery`, `Building`. Les données propres à une
famille vivent dans des satellites (`buildings`, `resources`,
`unique_objects`), et le service de pose est commun
(`EntityPlacementService`).

Le côté TYPE, lui, est resté plat. On a donc un moteur où l'objet posé sait ce
qu'il est, et où son type ne le sait pas.

## 2. Ce que le couple de colonnes coûte déjà

`TypeEditorFace` existe précisément pour compenser : son docblock dit qu'il
« remplace un booléen qui répondait "structure ?" en quarante-sept endroits ».
Il a fait le gros du travail, et c'est pourquoi le lot du rendement n'a coûté
que deux `if` — un dans le formulaire, un dans l'enregistrement.

Mais il compense sans résoudre. Chaque famille nouvelle rajoute :

1. une colonne nullable de plus sur le tronc commun,
2. un `if` dans le formulaire,
3. un `if` dans l'enregistrement,
4. un cas dans chaque écran qui affiche un type.

Les plantes arrivent avec au moins deux réglages propres (rendement, taux de
pousse). Les bâtiments ont leur porte et leur dialogue. Le décor a son rôle de
case. La facture est linéaire dans le nombre de familles, et on en ajoute une.

## 3. La proposition

Un tronc abstrait `EntityType`, discriminé, décliné en autant de classes que de
populations :

```
EntityType (abstrait, table `races`)
├── CharacterRace   — jouable, faction, plan de départ, CARACS
├── BuildingType    — porte, dialogue, inscription
├── SceneryType     — rôle de case (cover), figure découpée
└── HarvestableType — rendement, épuisement, repousse
    └── PlantType   — + taux de pousse, marchable (à venir)
```

**Le discriminant existe déjà, sous forme dérivée.** Les quatre valeurs sont
exactement les quatre clés de `TypeEditorFace` : `character`, `building`,
`scenery`, `resource`. La règle de dérivation est celle que
`TypeEditorFace::of()` applique aujourd'hui :

| `kind` | `structure_nature` | discriminant |
|---|---|---|
| character | *(peu importe)* | `character` |
| structure | decor | `scenery` |
| structure | ressource | `resource` |
| structure | edifice / obstacle | `building` |

Une colonne `type_kind` remplie une fois par cette règle, et le couple actuel
devient de la donnée d'affichage (`structure_nature` garde son sens
édifice/obstacle **à l'intérieur** de `BuildingType`).

## 4. Ce que l'héritage rend possible

Ce qui est aujourd'hui un `if` devient un appel :

```php
/* aujourd'hui */
$race->setHarvestItem($face->isResource() ? (string) ($_POST['harvest_item'] ?? '') : '');

/* après */
$type->applyForm($_POST);   // chaque classe sait ce qu'elle a à lire
```

Et surtout, `HarvestableType::yield()` n'existe que là où il veut dire quelque
chose. On ne peut plus demander son rendement à une race de nain — ce qui est
aujourd'hui possible, et rend `null`.

## 5. Stockage : table unique ou satellites ?

Deux formes, et je recommande la première.

**Table unique (STI Doctrine)** — `races` garde ses colonnes, le discriminant
choisit la classe. Les colonnes propres à une famille restent nullables en
base, mais **disparaissent du modèle PHP** des autres. Zéro migration de
données au-delà du discriminant, zéro jointure, et 128 lignes ne justifient
aucune optimisation de stockage.

**Satellites (CTI)** — `races` garde le commun, `harvestable_types`,
`character_races`… portent le reste. Physiquement propre, cohérent avec les
satellites du côté objet — mais c'est une migration de 41 colonnes pour un gain
qui n'est pas mesurable à cette volumétrie, et chaque lecture prend une
jointure.

**Recommandation : STI.** La douleur est dans le modèle et les écrans, pas dans
le disque. On peut passer aux satellites plus tard sans rien rejouer du travail
fait sur les classes — l'inverse n'est pas vrai.

## 6. Le chemin, par étapes

Chaque étape est déployable seule et ne casse rien derrière elle.

1. **Le discriminant.** Migration : colonne `type_kind`, remplie par la règle
   du §3, plus un test qui vérifie qu'aucune ligne ne reste sans famille.
   Rien ne le lit encore.
2. **Le tronc et les classes.** Doctrine : `EntityType` abstrait, quatre
   sous-classes, mêmes getters qu'aujourd'hui. `RaceService` reste la porte
   d'entrée unique et rend désormais des sous-classes.
3. **Les champs déménagent.** Les accesseurs propres à une famille quittent le
   tronc. C'est là que PHPStan travaille pour nous : tout appel devenu
   impossible se voit.
4. **Les écrans suivent.** `TypeEditorFace` fusionne avec les classes — c'est
   la même information, dite deux fois. Les `if` du formulaire et de
   l'enregistrement deviennent de la polymorphie.
5. **Les plantes arrivent comme sous-classe**, et ne coûtent qu'une classe.

## 7. Ce que ça ne fait pas

- **Pas de renommage de la table.** `races` reste `races`. Le nom est faux
  (un arbre n'est pas une race) mais il est écrit dans `players.race`, qui est
  une chaîne et non une clé étrangère : le renommage toucherait tout et
  n'apporterait rien qu'un meilleur nom. Le tronc PHP, lui, s'appelle
  `EntityType`.
- **Pas de changement de règle de jeu.** Aucun comportement ne bouge ; c'est un
  lot de structure, et il doit se relire comme tel.

## 7 bis. L'identité : le nom ou l'id ?

La question se pose d'elle-même dès qu'on veut **pouvoir renommer un type**.
Aujourd'hui `players.race` porte le NOM, et le nom est donc gravé partout.

Le symptôme est déjà visible : **cinq jointures** comparent des noms avec un
`COLLATE utf8mb4_general_ci` des deux côtés. On ne collationne pas une clé
étrangère ; on collationne une chaîne dont on n'est pas sûr.

**Ce que le passage à l'id apporte :**

- renommer un type devient un `UPDATE` d'une ligne, au lieu d'une cascade sur
  `players` et les tables de carte ;
- une vraie clé étrangère : plus de `race` orpheline pointant un type disparu ;
- les cinq jointures collationnées deviennent des jointures d'entiers.

**Ce qu'il n'apporte PAS, et c'est le piège :** le nom ne cesse pas d'être une
identité pour autant. Il l'est encore à trois endroits, et chacun a une bonne
raison.

1. **Les images.** `BuildingService::resolveAvatar()` fabrique
   `img/avatars/{nom}.webp`, et la palette de l'éditeur liste
   `img/walls/*.png` : le fichier EST le nom. Renommer un type sans toucher à
   ça, c'est perdre son sprite en silence.
2. **Les bundles.** `PlanExporter` porte des **clés naturelles**, et
   volontairement : « aucun id de base, le bundle est portable entre
   environnements ». Les ids diffèrent d'un environnement à l'autre — un
   bundle ne peut donc pas les porter, jamais.
3. **Les JSON de plan.** Les biomes nomment les types. C'est en voie
   d'extinction (ils ne servent plus qu'au versement), mais c'est encore lu.

**La forme juste est donc : id à l'intérieur, nom aux frontières.** Les
références internes deviennent des ids ; le nom reste l'étiquette humaine et
portable, traduite en exactement deux endroits — l'import/export et ce que
poste l'éditeur.

Et pour que le nom soit VRAIMENT libre, il faut de surcroît **découpler le
sprite du nom** : tant que l'image se déduit du nom, renommer casse l'affichage.
Le sprite doit devenir une propriété du type, comme le libellé.

**Séquencement.** Ce chantier est plus lourd que le précédent — de l'ordre de
183 sites touchent `race`, c'est un strangler, pas un lot. Il est indépendant
de l'héritage : les classes se posent très bien sur des références par nom.
Faire l'héritage d'abord (petit, rentable tout de suite avec les plantes), puis
l'id comme piste séparée — **sauf** si renommer des types devient un besoin
proche, auquel cas l'id passe devant, avec le découplage du sprite dans le même
lot.

## 8. Points durs

- **`players.race` est une chaîne.** L'identité d'un type est son nom, pas son
  id. Rien dans ce lot n'y touche, mais il faut le savoir : le discriminant ne
  devient pas la clé.
- **`RaceService` est la seule porte** (CLAUDE.md) — c'est ce qui rend le lot
  faisable. Toute lecture directe de `races` trouvée en chemin est à ramener
  par là AVANT de déplacer un champ.
- **Les étalons.** `RaceService` et les écrans d'admin ont des tests ; ils
  doivent passer inchangés à chaque étape, puisque rien du comportement ne
  bouge. Un étalon qu'il faut réécrire signale qu'on a changé autre chose que
  la structure.

## 9. Séquencement

**Avant le lot des plantes.** Les plantes sont la cinquième famille : les
convertir sur un tronc qui n'existe pas encore, c'est écrire la version
« colonnes nullables + `if` » puis la réécrire. Faites-le d'abord, et les
plantes coûtent une classe.
