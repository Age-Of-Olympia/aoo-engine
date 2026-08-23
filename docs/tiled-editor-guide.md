# Guide : éditer les cartes du jeu avec Tiled

Guide pratique pour les admins/MJ. La partie [Architecture &
décisions](#architecture--décisions) en fin de document sert de référence
technique (le pourquoi, ce qui reste, le déploiement).

## Mise en route (une fois)

1. **Installer Tiled** : https://www.mapeditor.org/download (dans le
   devcontainer : `~/opt/tiled/tiled-headless`).
2. **Installer l'extension** : télécharger la
   [dernière release](https://gitlab.com/age-of-olympia/aoo-tiled-extension/-/releases/permalink/latest/downloads/aoo-tiled-extension.zip),
   puis dans Tiled : Édition → Préférences → Plugins → « Ouvrir le
   dossier d'extensions », y extraire le zip (dossier `aoo`) et
   redémarrer Tiled. (Pour développer l'extension : cloner
   https://gitlab.com/age-of-olympia/aoo-tiled-extension et lier le
   clone sous le nom `aoo` à la place du dossier.)
3. **Configurer** : **Fichier → AoO : Configuration…** — renseigner les
   instances (`nom=url`, séparées par des virgules). Écrit `config.json`
   tout seul, pas besoin d'éditer un fichier à la main. Les images de
   tuiles se rapatrient toutes seules au pull, dans un magasin par
   instance (`images/<instance>/`) ; le dossier d'appoint (`gameDir`,
   chemin absolu d'un dossier contenant `img/` — typiquement un checkout
   du dépôt moteur) est optionnel, réservé aux développeurs.
4. **Ouvrir le projet** : Fichier → Ouvrir un projet… →
   `aoo.tiled-project` du clone. C'est lui qui apporte les classes
   typées des déclencheurs.

**Se maintenir à jour** : dézipper la nouvelle release par-dessus le
dossier `aoo` (config, session, cartes et images sont conservés), puis
re-puller les plans ouverts. L'extension annonce sa version à chaque
appel : une instance qui en exige une plus récente refuse la requête en
disant quoi télécharger, plutôt que de la laisser parler un protocole
qu'elle ne connaît plus. La version installée s'affiche dans le titre de
la fenêtre de configuration et au chargement, dans la Console.

Les actions AoO vivent en bas du menu **Fichier** :

| Action | Rôle |
|---|---|
| AoO : Configuration… | dossier du dépôt + instances (écrit `config.json`) |
| AoO : Connexion / changer d'instance… | formulaire compte + instance (local, test… ou « adresse personnalisée » pour saisir une URL à la volée) |
| AoO : Pull un plan du jeu… | télécharge un plan (liste déroulante des plans avec leurs niveaux z ; niveau z optionnel, vide = tous) |
| AoO : Push la carte vers le jeu… | applique la carte ouverte à son instance d'origine |
| AoO : Générer le monde… | pull tous les plans et écrit un `.world` (vue d'ensemble) |

Le reste du cycle de vie se gère dans **l'admin du jeu** : création,
clonage et configuration des plans (fond, ambiance, biomes…) sur la page
**Plans**, inventaire et gestion des images de tuiles sur la page
**Tuiles & images** (l'`img/` du dépôt n'est pas versionné : récupérer
l'art via le dépôt d'assets). L'extension se concentre sur l'édition du
contenu des couches.

### Le monde (tous les plans en un espace)

**Fichier → AoO : Générer le monde…** pull tous les plans de l'instance et
écrit un fichier `.world` (`maps/<instance>/<instance>.world` dans le dossier
de l'extension) qui
les dispose côte à côte : chaque territoire à sa position (`x`/`y`) de la
carte du monde, et les donjons hors grille (atteints par un `tp`) sous leur
plan d'entrée. Charger via **Carte → Charger le monde…** (*Load World*) : on
voit et édite alors les plans voisins ensemble, et on traverse leurs bords.

C'est une **vue d'ensemble** — chaque plan garde ses coordonnées locales, ce
n'est pas une grille continue (les vrais déplacements passent toujours par
les `tp`). Le push reste par plan, inchangé.

## L'interface en 30 secondes

- **Centre** : la carte. Molette = zoom, clic molette (ou barre espace) +
  glisser = déplacer la vue. La caméra est libre — plus besoin de téléporter
  un personnage.
- **À droite, en haut — Calques** : la pile de couches. *Tout se joue là* :
  on édite **la couche sélectionnée**, et rien d'autre. L'œil règle la
  visibilité, le cadenas le verrouillage.
- **À droite, en bas — Tilesets** : la palette. Un onglet par couche du jeu
  (`aoo-tiles`, `aoo-resources`…) contenant **toutes** les images disponibles,
  pas seulement celles déjà posées.
- **À gauche — Propriétés** : les détails de l'élément sélectionné (pour
  les déclencheurs : leurs champs typés).

## Les couches d'un plan

Chaque niveau z est un groupe `z=0`, `z=-1`… contenant, de bas en haut :

| Couche | Contenu | Type |
|---|---|---|
| `tiles` | sol (biomes, routes posées au sol…) | tuiles |
| `routes`, `plants` | routes, plantes | tuiles |
| `resources` | ressources récoltables (arbres, pierres…), autels, `unique_*` — bloquants | tuiles |
| `elements` | décor animé/temporaire | tuiles |
| `buildings` | **entités bâtiment** (murs d'enceinte, statues, coffres…) — une tuile = une entité | tuiles |
| `foregrounds` | décor d'avant-plan | tuiles |
| `triggers` | déclencheurs invisibles (tp, forbidden…) | objets |
| `dialogs` | points de dialogue | objets |
| `xxx (joueurs)` 🔒 | constructions des joueurs — intouchables | tuiles |

Règle d'or : **la tuile doit venir du tileset de sa couche** (un arbre
d'`aoo-resources` se pose sur `resources`). L'extension le signale **dès le
geste**, et le push refuse d'envoyer quoi que ce soit tant qu'une tuile est
ailleurs que chez elle — compte et première coordonnée dans le message. Une
couche qui n'apparaît pas dans le panneau signale une extension plus ancienne
qu'elle : mettre à jour et re-puller.

Depuis la conversion des murs en entités, les **obstacles et le décor**
(murs d'enceinte, statues, coffres…) sont des **bâtiments** : ils se posent
sur la couche `buildings`, pas sur `resources`. La palette `aoo-resources` ne
propose donc que ce qui s'y pose encore — les ressources récoltables, les
autels et les types `unique_*` (tout, sur les plans de tutoriel, dont les murs
sont clonés par session) — et le serveur refuse un push qui réintroduirait un
obstacle dans `resources`.

### Les couches `resources` et `plants` (entités)

Elles se peignent comme des couches de tuiles, mais chaque tuile est une
**entité**, comme sur `buildings` :

- le pull montre ce que le jeu tient réellement debout sur le niveau ;
- au push, une tuile ajoutée devient une ressource posée (récoltable), une
  tuile effacée la retire du plateau, et une tuile **inchangée garde son
  entité** — son id et son état : une ressource épuisée par les joueurs ne
  repousse pas parce qu'on a poussé la carte ;
- un type absent du catalogue n'est pas posé : le rapport le dit (⚠) sans
  faire échouer le push ;
- le push ne compare **que le niveau poussé** : les autres z ne bougent pas.

### La couche `buildings` (entités)

Elle se peint **comme une couche de tuiles** (pinceau, pot, gomme,
copier-coller), mais chaque tuile est une **entité** du jeu :

- la palette `aoo-buildings` liste les types de structure du catalogue
  (les mêmes que admin → Bâtiments), sprite compris ;
- au push, chaque tuile ajoutée devient une entité (PV de son type,
  attaquable) et chaque tuile effacée démonte la sienne ; une tuile
  inchangée conserve son entité — id, blessures, tout ;
- une pose sur une **case occupée** (joueur, autre entité, mur, élément
  non constructible) est refusée case par case : le push réussit, le
  rapport liste les refus (⚠), re-puller pour recoller la carte au réel ;
- seul le **décor** est éditable : les bâtiments à propriétaire, de
  faction, en chantier ou en ruine arrivent dans la couche verrouillée
  « buildings (joueurs) », intouchables — ils se gèrent dans
  admin → Bâtiments (propriétaire, faction et dialogue s'y règlent aussi).

## Peindre des tuiles

1. Cliquer la couche cible dans **Calques** (ex. `resources`).
2. Choisir une tuile dans l'onglet du tileset correspondant.
3. Outils principaux (raccourcis) :
   - **B — tampon** : peindre tuile par tuile, ou en traits.
   - **F — pot de peinture** : remplir une zone contiguë.
   - **E — gomme** : effacer (= suppression en base au push).
   - **R — sélection rectangulaire**, puis **Ctrl+C / Ctrl+V** : copier-coller
     des zones entières.
4. **Capturer une structure** : avec le tampon (B), **clic droit + glisser**
   sur la carte capture la zone sous le curseur comme pinceau — idéal pour
   dupliquer un bâtiment.
5. **Ctrl+Z** annule, sans limite, jusqu'au push.

### Copier des zones sur plusieurs couches à la fois

Les outils opèrent sur **les couches sélectionnées** — et on peut en
sélectionner plusieurs (**Ctrl+clic** dans le panneau Calques) :

- couches `tiles` + `resources` + `foregrounds` sélectionnées → **R**, tracer la
  zone, **Ctrl+C**, **Ctrl+V** : l'aperçu suit la souris, un clic dépose le
  tout, chaque contenu sur sa couche ;
- même principe pour le tampon : capture au clic droit avec plusieurs
  couches sélectionnées = **tampon multi-couches** (sol + murs + décor en un
  seul pinceau) ;
- les bons tampons se conservent : panneau **Affichage → Tile Stamps** →
  enregistrer (pointer le dossier des tampons sur `stamps/` du dépôt de
  l'extension pour les partager via git) ;
- pour dupliquer un niveau entier : tout sélectionner (**Ctrl+A**) avec les
  couches voulues, copier, sélectionner les couches de destination (autre
  groupe z ou autre carte), coller.

Limites : les **objets** (triggers, dialogs) ne suivent pas le copier-coller
de tuiles — les dupliquer à part (outil flèche, sélection, Ctrl+C/V ou
Ctrl+D) ; et les couches verrouillées « (joueurs) » refusent le collage,
c'est voulu.

### Les structures multi-tuiles (olympia, porte des enfers…)

Les grandes images historiquement découpées en morceaux (`olympia-00` à
`-03`…) existent dans la palette **en une seule grande tuile** (l'image
entière, à la fin du tileset de leur couche). La poser = **un clic**, ancré
par sa case bas-gauche ; au push, elle est automatiquement ré-éclatée en
morceaux individuels pour le jeu. Un pull ultérieur ré-affiche les morceaux
séparés — c'est normal, le rendu est identique.

Convention pour créer une nouvelle structure : déposer l'image entière dans
`img/<couche>/<base>/<base>.png` (dimensions multiples de 50) et ses
morceaux `<base>-NN.png` (numérotés ligne par ligne depuis le coin
haut-gauche) à la racine de `img/<couche>/`, puis re-puller. Les tuiles de
sol (`tiles`) restent strictement 50x50 : pas de structures sur cette
couche, le décor multi-tuiles vit en `foregrounds`/`elements`.

### Les propriétés du plan (nom, fond, visibilité, biomes…)

La configuration du plan est éditable directement : chaque clé apparaît
en **propriété de la carte** préfixée `aooPlan_` — `name`, `shortName`,
`season` (numéro de saison ; vide = toutes les saisons), `x`/`y`
(position sur la carte du monde), `player_visibility` (`true`/`false`),
`pnj`, `size`, `bg`, `mask`, `scrollingMask`, `verticalScrolling`,
`biomes` (JSON des ressources). Une valeur vide = clé retirée. Tout est
validé et appliqué au push ; le rapport remonte le bilan de santé du
plan (`PlanJsonValidator` : biomes en doublon, ressources inconnues…).

> **Revenir aux propriétés de la carte** : le panneau Propriétés suit ce
> qui est sélectionné (objet, calque…). Pour retrouver les `aooPlan_*`
> globales, menu **Carte → Propriétés de la carte…** (ou cliquer dans une
> zone vide du panneau Propriétés). Pour les propriétés d'un niveau z, clic
> sur le groupe `z=N` dans le panneau Calques.

#### La configuration d'un niveau z

Chaque niveau z est un **groupe de calques** (`z=0`, `z=-1`…) : ses
propriétés (panneau Propriétés en sélectionnant le groupe) portent la config
de ce niveau, préfixée `aooZ_` :

- **`aooZ_name`** : le nom affiché du niveau (« Surface », « Sous-sol »…).
- **`aooZ_mapUnavailable`** : `true` si le niveau n'a pas de carte
  (les bornes sont alors ignorées).
- **`aooZ_bounds`** : les bornes visibles du niveau —
  - `auto` (défaut) : recalculées sur l'étendue réelle du contenu à chaque
    push, aucune maintenance ;
  - `minX,maxX,minY,maxY` : bornes fixes imposées (ex. `-5,10,-5,10`).
  Après un push en `auto`, un re-pull montre les bornes calculées (elles
  restent modifiables, et `auto` les refait coller au contenu).

Tout est écrit dans `z_levels` du JSON de plan au push.

#### Le fond, l'ambiance et les biomes (admin → Plans)

Le fond (`bg`, l'image affichée sous les tuiles), le masque animé (`mask`,
l'effet d'ambiance météo qui défile par-dessus) et les biomes (`biomes`,
quels murs sont récoltables et ce qu'ils donnent) sont des clés du JSON de
plan : leur **édition assistée vit dans l'admin du jeu, page Plans**
(catalogue des fonds, validation des biomes).

Côté Tiled, ces clés restent visibles et modifiables à la main dans les
propriétés `aooPlan_*` (cas avancés — elles sont réécrites au push, avec le
bilan de santé `PlanJsonValidator` dans le rapport), et le pull ajoute un
**aperçu** du fond et du masque : deux calques image verrouillés « fond
(aperçu) » / « masque (aperçu) », purement visuels et ignorés au push. Le
rendu exact reste celui du jeu.

### Le pinceau Terrain (transitions automatiques)

L'idée : au lieu de choisir des tuiles, on peint des **étiquettes de
terrain** ("ici c'est du désert") et Tiled choisit lui-même la bonne tuile —
pleine ou de transition — selon les voisines.

Particularité à comprendre : notre set « Biomes » est de type *corner*, donc
**le pinceau peint les coins entre les tuiles, pas les tuiles**. Le curseur
s'aimante sur les intersections (un losange en surbrillance couvre les 4
tuiles autour) ; peindre un coin remplace ces 4 tuiles par celles dont le
coin correspond. Une tuile ne devient « pleine » que quand ses 4 coins
portent le même terrain — d'où le mode d'emploi :

1. Sélectionner la couche `tiles` (dans le bon groupe z).
2. Onglet **Terrain Sets** → « Biomes » → cliquer un terrain (ex. *desert*) —
   l'outil Pinceau Terrain s'active tout seul (sinon touche **T**).
3. Sur la carte, **glisser sur une zone** (pas un clic isolé) : l'intérieur
   devient du désert plein, le pourtour reçoit automatiquement les tuiles de
   fondu. Un seul clic ne pose qu'un coin = juste une tache de transition.
4. Pour revenir en arrière : repeindre avec le terrain d'origine (ou Ctrl+Z).

Toutes les paires de biomes du set ont leurs tuiles de fondu (générées via
`php tools/tiled/generate_transitions.php --all tiles`). Sur la carte du
jeu, ces tuiles prennent automatiquement un mélange des couleurs des deux
biomes (`ColorService::colorFor`). Après ajout d'un nouveau biome au set,
relancer la génération `--all` puis re-puller.

## Les déclencheurs et dialogues (objets)

Sélectionner la couche `triggers`, puis :

- **Sélectionner/déplacer** : outil de sélection d'objets (première icône
  de la barre d'outils objets), puis glisser.
- **Créer** : outil « Insérer un rectangle », tracer ~une case, puis dans
  **Propriétés** choisir la **Classe** (`tp`, `need`, `forbidden`, `rez`…).
  Pas besoin de nom.
- **Configurer** : les champs typés apparaissent sous la classe :
  - `tp` : x, y, z, plan de destination — laisser `x`/`y`/`z` tels quels
    pour conserver la position courante du joueur (ex. descendre d'un
    niveau : seulement z=-1 et le plan) ;
  - `need` : conditions `item:nom:n,spell:nom_du_sort` ;
  - `question` : identifiant du dialogue.
- **Supprimer** : sélectionner l'objet, touche Suppr.

## Les niveaux z (étages)

Un plan multi-niveaux arrive entier : un groupe par z, seul le plus haut
visible. Pour travailler un sous-sol :

1. Œil du groupe `z=0` → masquer (ou réduire son **opacité** à ~30 % pour
   voir à travers — parfait pour aligner les escaliers entre étages).
2. Œil du groupe `z=-1` → afficher, puis sélectionner une couche *dans* ce
   groupe.

Le push envoie tous les niveaux d'un coup, chacun avec son propre contrôle
de conflit.

## Pousser vers le jeu

**Fichier → AoO : Push…** — la confirmation rappelle le plan et l'instance
cible (celle d'où la carte a été pullée, toujours). Le rapport liste par
niveau : insérés / supprimés / conservés / protégés.

Garanties :
- les constructions des joueurs et les objets au sol ne sont jamais touchés ;
- l'état runtime (ressources épuisées, éléments temporaires) survit sur les
  cases non modifiées ;
- si quelqu'un d'autre a modifié le plan depuis votre pull → **409**, rien
  n'est écrit : re-puller, refaire l'édition (ou la re-coller), re-pusher.

## Dépannage rapide

| Symptôme | Cause / remède |
|---|---|
| Le pinceau ne pose rien | Mauvaise couche sélectionnée (souvent une couche d'objets) — cliquer la couche de tuiles voulue |
| « Tuile sans propriété aooName » au push | Tuile posée depuis un tileset étranger à la carte — utiliser les onglets `aoo-*` de cette carte |
| « Le plan a changé depuis le pull » (409) | Édition concurrente — re-puller puis rejouer l'édition |
| Étiquettes de texte sur la carte | Affichage → « Show Object Names » (désactivé par défaut sur les cartes pullées) |
| Le formulaire de connexion n'apparaît pas | Un jeton valide est en cache (`session.json`) — passer par « AoO : Connexion… » pour changer de compte ou d'instance |
| Une couche est grisée/inéditable | C'est une couche « (joueurs) » verrouillée : constructions des joueurs, volontairement intouchables |
| Nouvelles images dans `img/` invisibles | Re-puller le plan (les tilesets sont reconstruits au pull) |
| Une couche attendue n'apparaît pas (`buildings`…) | L'extension est plus ancienne que la couche : mettre à jour, redémarrer Tiled, re-puller |
| « Tuiles sur la mauvaise couche » au push | Une palette a servi sur la couche d'une autre (un mur `aoo-buildings` posé sur `resources`) : le message donne le compte et la première coordonnée. Ctrl+Z, sélectionner la couche du nom de la palette, repeindre. Rien n'est parti au jeu |
| Tuiles présentes dans la palette mais sans image | Le magasin d'images n'a pas l'art : re-puller (la synchronisation tourne au pull). Si le pull annonce « les *undefined* images sont déjà toutes présentes », l'extension date d'avant la v0.4.0 — mettre à jour |
| « Extension Tiled trop ancienne » (426) | L'instance exige une version plus récente : dézipper la dernière release par-dessus le dossier `aoo`, redémarrer Tiled, re-puller. Côté serveur, la barre se règle dans le tableau de bord admin (Options générales) |

---

# Architecture & décisions

Référence technique de l'intégration Tiled (remplace l'ancien
`tiled-editor-plan.md`). Le détail par étape vit dans l'historique git ;
cette section garde le **pourquoi**, ce qui **reste** et le **déploiement**.

## Principe

L'éditeur Tiled est branché sur le jeu via son **API de scripting** (extension
`tools/tiled/aoo/aoo.js`), tout passe par **HTTP** — une instance = une URL de
base. Le modèle de données du jeu est conservé : `coords` (x, y, z, plan) +
tables de couches `map_*`. Flux **pull → édition → push**, pas de synchro
temps réel.

Emplacements :
- Extension + projet : dépôt dédié
  https://gitlab.com/age-of-olympia/aoo-tiled-extension (`aoo.js`,
  `aoo.tiled-project`, `stamps/` ; `config.json`/`session.json` locaux
  gitignorés, `.exemple` fournis ; cartes pullées dans `maps/<instance>/`).
- Endpoints : `api/admin/map/{auth,export,import,plans,world,terrains}.php`
  (socle commun `_common.php`). La création/configuration des plans et la
  gestion des images passent par l'admin du jeu (pages Plans et
  Tuiles & images).
- Services : `TiledMapService` (diff transactionnel `map_*`),
  `PlanConfigService` (JSON de plan), `TileCatalogService` (scan `img/`),
  `TiledAuthService` (jetons), plus `ColorService`/`PlanJsonValidator` existants.
- Version de l'extension : elle l'annonce en en-tête `X-AoO-Tiled-Version`,
  `_common.php` refuse (426) en dessous de la barre — une extension d'un
  autre âge parlerait un protocole changé et se tromperait en silence. La
  barre est un **réglage** (`TiledExtensionService`, `admin_settings`),
  pas une constante : publier une release de l'extension ne demande ni
  commit ni déploiement, seulement de la relever depuis le tableau de
  bord (Options générales).
- Secret : `config/tiled_constants.php` (gitignoré). Sets de terrain :
  `tools/tiled/terrains.json` par instance (état runtime, servi à
  l'extension par `terrains.php` au pull — une instance sans l'endpoint se
  pull sans pinceau Terrain).

## Décisions structurantes

- **Import par diff sur clé d'identité** (x, y, name[, params]) : les lignes
  inchangées sont conservées telles quelles, donc leur **état runtime**
  (`damages` des murs, `endTime` des éléments) survit à un push. Import
  transactionnel.
- **Lignes des joueurs intouchables** : toute ligne `player_id` non nul est
  montrée (couches verrouillées « (joueurs) ») mais jamais modifiée ni
  poussée — protection portée par une **propriété**, pas par le nom de couche
  (résiste au renommage/déverrouillage). `map_items` (objets au sol) jamais
  concerné.
- **Couche `buildings` = entités** : pas de table `map_buildings` — les
  lignes sont les entités bâtiment du niveau. Même diff (x, y, type), mais
  pose par `BuildingService::place()` et retrait par `remove()`, **hors de
  la transaction `map_*`** (autre connexion) et après elle ; une pose
  refusée (case occupée) est signalée sans condamner le push, et la
  version post-push se recalcule sur l'état réel. Seul le décor (sans
  propriétaire ni faction, état `built`) est diffable.
- **Contrôle de version optimiste** : empreinte du contenu authoré calculée au
  pull (colonnes runtime et lignes joueurs exclues), vérifiée au push → **409**
  si le plan a bougé entre-temps.
- **Auth par compte du jeu** (`isAdmin`, lui-même ou un PNJ possédé), jeton
  HMAC sans état, droits revérifiés à chaque requête ; pare-feu réutilisé de
  `login.php`. **Push verrouillé sur l'instance d'origine** de la carte.
- **Axe y** : le jeu monte, Tiled descend → `tiledY = -gameY`.
- **Config de plan hors transaction** : les propriétés du JSON de plan sont
  validées **avant** la transaction (aucun 400 après le commit des couches),
  écrites via `Classes\Json` (inerte en simulation).
- **`exits` et `war` retirés** : sous-systèmes jamais terminés ni utilisés
  (aucune donnée en base).

## Reste à faire

- **Monde** : cases dimensionnées par plan plutôt qu'au plus grand (`enfers`,
  421 tuiles, gonfle l'espacement) ; validation des liens `tp` cassés
  (destination inexistante / hors bornes) remontée comme le bilan de santé.
- **Biomes** : édition assistée dans l'admin (page Plans). À valider en
  jeu : la clé d'identité des plantes inclut `params`.
- **Aperçu** : bg/mask affichés en calques image ; on pourrait aussi poser
  l'image de fond de la carte.
- **Industrialisation** : export périodique des plans en `.tmj` versionnés
  (diff/review, sauvegarde) ; décommissionnement progressif de `tiled.php`
  (gardé pour les retouches rapides in-game).

## Déploiement (à ne pas oublier)

- `img/` **n'est pas versionné** : reporter dans la source d'assets déployée
  les tuiles de transition générées (`img/tiles/trans_*`), l'arbre sacré
  déplacé en `img/foregrounds/`, et le retrait de `img/triggers/{enter,exit}.png`.
- Relever la version minimale de l'extension (tableau de bord → Options
  générales) **après** la publication de la release correspondante,
  jamais avant : la barre ferme la porte à tout le monde tant que le zip
  n'est pas téléchargeable. Réglage par instance : l'expérimental peut
  exiger plus récent que la prod.
- Pour viser une **instance déployée** (test suit `staging`), y créer
  `config/tiled_constants.php` avec son propre secret (`openssl rand -hex 32`) ;
  secret vide/absent = endpoints désactivés.
