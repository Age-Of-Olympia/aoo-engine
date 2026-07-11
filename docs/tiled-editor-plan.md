# Plan : éditeur de cartes via Tiled (mapeditor.org)

## Contexte

L'éditeur actuel (`tiled.php` + `js/tiled.js`) est limité par sa structure :
caméra ancrée sur le personnage admin (navigation = téléportation), un
aller-retour AJAX par tuile posée avec rechargement complet de la vue, outil
« zone » rudimentaire (rectangle d'un seul type de tuile), pas de
sélection/copier-coller, pas d'annulation, pas de préfabriqués.

Le modèle de données, lui, est sain : `coords` (x, y, z, plan) + tables de
couches (`map_tiles`, `map_walls`, `map_triggers`, `map_foregrounds`,
`map_elements`, `map_plants`, `map_routes`, `map_items`, `map_dialogs`).

**Décision** : au lieu de réécrire un éditeur web, brancher le vrai éditeur
[Tiled](https://www.mapeditor.org) sur le jeu via son API de scripting
JavaScript (extensions). Tiled apporte d'office : cartes infinies (coordonnées
négatives natives), calques, sélection/copier-coller, tampons (structures
complètes), annulation, tilesets « collection d'images » (nos PNG individuels
dans `img/` fonctionnent tels quels, pas d'atlas à générer).

Le moteur de script de Tiled est un `QQmlEngine` (vérifié dans
`src/tiled/scriptmanager.cpp` du dépôt mapeditor/tiled) : l'environnement QML
fournit `XMLHttpRequest`, donc une extension peut appeler directement les
endpoints PHP du jeu. Flux : **pull → édition → push** (pas de synchro
temps réel, ce qui est le comportement voulu : préparer, relire, appliquer).

## Correspondance des données

| Côté jeu | Côté Tiled |
|---|---|
| plan (`coords.plan`) | une carte `.tmj` (infinite map) |
| `map_tiles` | calque de tuiles `tiles` |
| `map_walls` (+ `damages`) | calque de tuiles `walls`, propriété `damages` |
| `map_foregrounds` | calque de tuiles `foregrounds` |
| `map_plants`, `map_routes`, `map_elements` | calques de tuiles homonymes |
| `map_triggers` (+ `params`) | calque d'objets `triggers` (params par instance) |
| `map_dialogs` | calque d'objets `dialogs` |
| `map_items` | **non authorable** : état runtime, jamais écrasé à l'import |
| PNG individuels `img/{tiles,walls,…}` | tilesets « image collection » `.tsj` générés |

Règles de sécurité à l'import (côté serveur, `TiledMapService`) :
- ne jamais toucher `map_items` ni les entrées avec `player_id` non nul
  (montrées dans l'éditeur en couches verrouillées « xxx (joueurs) »,
  ignorées au push) ;
- diff par clé d'identité (x, y, name[, params]) : les lignes inchangées
  sont conservées telles quelles, donc leur état runtime (`damages` des murs,
  `endTime` des éléments) survit à l'import ;
- import transactionnel (tout ou rien) + contrôle de version optimiste :
  l'empreinte du contenu authoré calculée au pull doit correspondre à l'état
  courant, sinon 409 (les colonnes runtime et lignes joueurs sont exclues de
  l'empreinte pour éviter les faux conflits).

## Authentification et instances

Les endpoints `api/admin/map/*` sont réservés aux comptes du jeu possédant
l'option `isAdmin` (le compte lui-même ou l'un de ses PNJ) : l'extension se
connecte via `auth.php` (nom ou matricule + mot de passe) et reçoit un jeton
HMAC signé, sans état, valable 30 jours (`TiledAuthService`). Le secret de
signature (`TILED_HMAC_SECRET`) vit dans `config/tiled_constants.php`,
gitignoré ; vide = endpoints désactivés. Les droits admin sont revérifiés à
chaque requête : retirer `isAdmin` invalide immédiatement les jetons.

L'extension gère plusieurs **instances** (tout passe par HTTP, une instance
= une URL de base ; chaque déploiement a sa propre base derrière son
hostname, cf. `config/deploy_targets.php`) : `config.json` les déclare
(local, test, experimental, prod), l'action « AoO : Connexion / changer
d'instance… » ouvre le formulaire (liste déroulante + identifiants). Jetons
mis en cache par instance dans `session.json` (gitignoré), cartes pullées
rangées par instance (`tools/tiled/maps/<instance>/`), et chaque carte est
**verrouillée sur son instance d'origine** au push : une carte pullée de
test ne peut pas partir vers prod par accident.

⚠ Pour viser une instance déployée (test…), il faut que cette MR y soit
déployée (test suit la branche `staging`) et qu'un
`config/tiled_constants.php` avec son propre secret existe sur ce serveur.

## Phases

### Phase 0 — Spike de faisabilité (faite)

1. Installer Tiled dans le devcontainer (AppImage extraite, exécution sous
   `xvfb-run` comme Cypress) — permet des tests automatisés headless via
   `tiled --evaluate script.js`.
2. **Test décisif** : `XMLHttpRequest` depuis un script Tiled vers
   `http://localhost` (Apache du conteneur). Si KO → repli : format custom
   `.tmj` + import via commande externe (mêmes endpoints serveur).
3. Endpoint lecture seule `api/admin/map/export.php?plan=X` (gate par token
   admin dans un fichier de config gitignoré, pattern `db_constants.php`).
4. Extension minimale « AoO / Pull » : XHR → construction de la carte en
   mémoire (calques + tilesets collection d'images) → ouverture dans Tiled.
5. Validation : ouvrir `arcadia` dans Tiled, sauvegarder en `.tmj`, rendre en
   PNG (tmxrasterizer) pour vérification visuelle.

**Critère de sortie** : un plan réel du jeu visible et éditable dans Tiled.

### Phase 1 — Push (écriture) (faite)

- Endpoint `api/admin/map/import.php` : reçoit les calques, écrit en
  transaction, applique les règles de sécurité ci-dessus.
- Action « AoO : Push la carte vers le jeu… » : sérialise la carte ouverte,
  POST, affiche le rapport (insérés/supprimés/conservés/protégés) et met à
  jour la version locale.
- Contrôle de concurrence : version du plan renvoyée au pull, vérifiée au
  push (409 si le plan a changé entre-temps).
- Authentification par compte admin du jeu (voir section ci-dessus) au lieu
  du jeton statique initial du spike.
- Gestion des niveaux z : un (plan, z) par carte (« plan:z » au pull),
  arcadia a par exemple un sous-sol en z=-1.

### Phase 2 — Confort d'édition (partiellement faite)

Fait :
- Vue multi-niveaux : tous les z d'un plan dans une carte (groupe « z=N »
  par niveau, version d'édition par groupe, push par niveau).
- Catalogue complet : les tilesets contiennent toutes les images de `img/`
  par couche, pas seulement les tuiles déjà posées — palette entière, plans
  neufs remplissables.
- Liste des plans existants au pull (`plans.php`) et création d'un plan
  vierge depuis l'éditeur (`create.php`, action « Nouveau plan »). Le JSON
  de plan (`datas/private/plans/<plan>.json` — fond, biomes,
  player_visibility) reste à créer à la main si besoin.
- Sets de terrain (onglet « Terrain Sets ») : construits au pull depuis
  `tools/tiled/aoo/terrains.json` (déclaratif : couleurs + tuiles pleines ou
  wangId explicites). Set « Biomes » de départ sur les tuiles de sol. Pure
  métadonnée d'éditeur : le push est inchangé.

- Art de transition : `tools/tiled/generate_transitions.php <couche> <A> <B>`
  (ou `--all <couche>` pour toutes les paires) génère les 14 tuiles de fondu
  par paire de biomes (GD, interpolation bilinéaire des coins) et déclare
  leurs wangId dans `terrains.json` — le pinceau Terrain fait de vraies
  transitions douces entre tous les biomes du set (21 paires générées).
  La carte du jeu colore ces tuiles en mélangeant les couleurs des deux
  biomes (`ColorService::colorFor`, testé unitairement).
  ⚠ `img/` n'est pas versionné : reporter les PNG générés dans la source
  d'assets déployée. Les tuiles générées sont remplaçables par de l'art
  dessiné à la main (mêmes noms).

Reste :
- Bibliothèque de structures : tampons Tiled partagés dans le dépôt
  (`tools/tiled/stamps/`).

### Phase 2b — Spécificités du jeu (faite pour les triggers/dialogs)

Les cas particuliers de l'ancien éditeur sont de vrais objets typés :

- **Projet Tiled** `tools/tiled/aoo/aoo.tiled-project` : une classe par type
  de déclencheur (couleur + champs typés dans le panneau Propriétés). À
  ouvrir dans Tiled : *Fichier → Ouvrir un projet…* — il référence aussi le
  dossier des cartes pullées.
- **Codecs** params ↔ champs typés dans l'extension (`AoO.PARAM_CODECS`),
  alignés sur les consommateurs du jeu (`scripts/map/triggers/*.php`) :
  - `tp` : champs x, y, z, plan (positionnel « x,y,z,plan » ; segment non
    numérique = conserver la valeur courante du joueur) ;
  - `need` : champ conditions (« item:nom:n,spell:nom ») ;
  - `question` (dialogs) : champ dialog.
- Un objet créé à la main avec seulement une classe (sans nom) est poussé
  sous le nom de sa classe ; les objets sans codec gardent leur propriété
  `params` brute.

| Cas | Données | État |
|---|---|---|
| Déclencheurs `tp` | params `x,y,z,plan` | ✅ champs typés |
| Déclencheurs `need` | params `item:name:n,spell:...` | ✅ champ conditions |
| `forbidden`, `rez`, `altar` | sans params | ✅ classes colorées |
| `enter`, `exit` | voyage inter-plans | 🗑 supprimés avec le système d'exits (jamais utilisé) |
| Dialogs `question` | params = id de dialogue | ✅ champ dialog |
| Murs ressources | `damages` -1/-2, WALLS_PV | défaut -1 à l'insertion — OK ; l'état de récolte (-2 épuisé) est du **runtime**, non authorable depuis Tiled (préservé sur les lignes conservées ; l'outil faux de `tiled.php` reste l'outil de retouche en jeu) |
| Éléments temporaires | `endTime` | ✅ rien à faire : insertion sans endTime = défaut 0 = permanent (le cron `delete_elements` ne supprime que `endTime != 0`) ; préservé sur lignes conservées |
| Plantes | params (croissance) | params dans la clé d'identité — à valider en jeu |

### Phase 2c — Propriétés de plan (faite)

Toutes les clés du JSON de plan lues par le code sont éditables depuis Tiled :

| Clé | Rôle | État Tiled |
|---|---|---|
| `name`, `shortName`, `x`, `y`, `pnj`, `size` | nom/position/gardien/taille | ✅ propriétés de carte `aooPlan_*` |
| `player_visibility` | masquer les autres joueurs | ✅ `aooPlan_player_visibility` |
| `bg` | fond de la vue | ✅ `aooPlan_bg` + action « Fond / ambiance » |
| `mask`, `scrollingMask`, `verticalScrolling` | masque animé (météo) | ✅ `aooPlan_*` (+ action pour bg/mask) |
| `biomes` | ressources : mur → ressource, exhaust/regrow | ✅ `aooPlan_biomes` (JSON édité en propriété) |
| `z_levels` | nom + bornes visibles + MapUnavailable par niveau | ✅ propriétés du groupe `aooZ_*` ; bornes `auto` (recalculées) ou imposées |
| `war`, `exits` | — | 🗑 morts-nés, code retiré |
| `id`, `fromCoords` | injectés au runtime par map.php | n/a (jamais stockés) |

Validation : chaque push passe par `PlanJsonValidator` (enrichi côté staging)
et remonte son bilan (biomes en doublon, ressources inconnues, niveaux z non
déclarés…).

### Reste à faire

- À valider en jeu : la clé d'identité des plantes inclut `params`.
- **Industrialisation** (phase 3) : export périodique des plans en `.tmj`
  versionnés dans git (diff/review, sauvegarde) ; décommissionnement
  progressif de `tiled.php` (gardé pour les retouches rapides in-game).

#### Phase 3 — Monde Tiled (multi-plans en un espace)

Un **World** Tiled (fichier `.world`) assemble plusieurs cartes en un
espace navigable : positions (x, y) par carte, on édite les territoires
voisins ensemble et on traverse leurs bords. Idée : une action
« AoO : Générer le monde » qui écrit un `<instance>.world` (par niveau z)
positionnant chaque plan pullé, à partir de **deux sources complémentaires** :

- **`x` / `y` du plan** (la grille olympia) : position absolue des
  territoires présents sur la carte du monde — décalage pixel = `x/y × pas`.
- **Déclencheurs `tp`** (params `x,y,z,plan`) : le graphe de connexions
  entre plans. Sert à placer les plans **hors grille** (donjons comme
  `nidhogg`, qui n'existent que comme destination d'un `tp`) à côté de leur
  entrée, et à **valider les liens** (tp vers un plan inexistant, ou hors des
  bornes visibles du niveau visé → signalé, comme le bilan de santé actuel).

Nuances : layout = vue d'ensemble (chaque plan garde son origine locale, ce
n'est pas une grille continue) ; un `.world` par z (les niveaux ne se
superposent pas à plat) ; purement éditeur, le push reste par carte.

Fait récemment : édition assistée des biomes (action « Biomes… »,
formulaire `wall:ressource:exhaust:regrow`), aperçu du fond/masque
(calques image verrouillés au pull), bibliothèque de tampons partagés
(`tools/tiled/stamps/`).

### Déploiement (à ne pas oublier)

- `img/` n'est pas versionné : les assets **générés/déplacés** par ce travail
  doivent être reportés dans la source d'assets déployée — les ~294 tuiles de
  transition (`img/tiles/trans_*`) et l'arbre sacré déplacé en
  `img/foregrounds/` ; les images `enter.png`/`exit.png` retirées de
  `img/triggers/`.
- Pour viser une instance déployée (test…), cette MR doit y être déployée
  (test suit `staging`) et un `config/tiled_constants.php` avec son propre
  secret doit exister sur ce serveur.

## Emplacements

- Extension Tiled : `tools/tiled/aoo/aoo.js` (+ `config.json` et
  `session.json` locaux, gitignorés, `.exemple` fournis)
- Projet Tiled (types d'objets, dossier des cartes) : `tools/tiled/aoo/aoo.tiled-project`
- Endpoints : `api/admin/map/auth.php`, `export.php`, `import.php`
- Services : `src/Service/TiledMapService.php`, `src/Service/TiledAuthService.php`
- Secret HMAC : `config/tiled_constants.php` (gitignoré, `.exemple` fourni)
- Cartes pullées : `tools/tiled/maps/*.tmj` (gitignoré, généré)
