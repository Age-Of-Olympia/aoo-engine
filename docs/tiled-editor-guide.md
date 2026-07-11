# Guide : éditer les cartes du jeu avec Tiled

Guide pratique pour les admins/MJ. Pour l'architecture et les décisions,
voir [tiled-editor-plan.md](tiled-editor-plan.md).

## Mise en route (une fois)

1. **Installer Tiled** : https://www.mapeditor.org/download (dans le
   devcontainer : `~/opt/tiled/tiled-headless`).
2. **Lier l'extension** : Édition → Préférences → Plugins → « Ouvrir le
   dossier d'extensions », y créer un lien vers `tools/tiled/aoo` du dépôt.
3. **Configurer** : copier `tools/tiled/aoo/config.json.exemple` en
   `config.json`, renseigner `gameDir` (chemin absolu du dépôt) et les
   instances utiles.
4. **Ouvrir le projet** : Fichier → Ouvrir un projet… →
   `tools/tiled/aoo/aoo.tiled-project`. C'est lui qui apporte les classes
   typées des déclencheurs et référence le dossier des cartes pullées.

Les actions AoO vivent en bas du menu **Fichier** :

| Action | Rôle |
|---|---|
| AoO : Connexion / changer d'instance… | formulaire compte + instance (local, test… ou « adresse personnalisée » pour saisir une URL à la volée) |
| AoO : Pull un plan du jeu… | télécharge un plan (liste des plans affichée) |
| AoO : Push la carte vers le jeu… | applique la carte ouverte à son instance d'origine |
| AoO : Nouveau plan dans le jeu… | crée un plan vierge et l'ouvre |

## L'interface en 30 secondes

- **Centre** : la carte. Molette = zoom, clic molette (ou barre espace) +
  glisser = déplacer la vue. La caméra est libre — plus besoin de téléporter
  un personnage.
- **À droite, en haut — Calques** : la pile de couches. *Tout se joue là* :
  on édite **la couche sélectionnée**, et rien d'autre. L'œil règle la
  visibilité, le cadenas le verrouillage.
- **À droite, en bas — Tilesets** : la palette. Un onglet par couche du jeu
  (`aoo-tiles`, `aoo-walls`…) contenant **toutes** les images disponibles,
  pas seulement celles déjà posées.
- **À gauche — Propriétés** : les détails de l'élément sélectionné (pour
  les déclencheurs : leurs champs typés).

## Les couches d'un plan

Chaque niveau z est un groupe `z=0`, `z=-1`… contenant, de bas en haut :

| Couche | Contenu | Type |
|---|---|---|
| `tiles` | sol (biomes, routes posées au sol…) | tuiles |
| `routes`, `plants` | routes, plantes | tuiles |
| `walls` | murs et ressources (arbres, pierres) — bloquants | tuiles |
| `elements` | décor animé/temporaire | tuiles |
| `foregrounds` | décor d'avant-plan | tuiles |
| `triggers` | déclencheurs invisibles (tp, forbidden…) | objets |
| `dialogs` | points de dialogue | objets |
| `xxx (joueurs)` 🔒 | constructions des joueurs — intouchables | tuiles |

Règle d'or : **la tuile doit venir du tileset de sa couche** (un arbre
d'`aoo-walls` se pose sur `walls`). Sinon le push refuse avec un message
clair.

## Peindre des tuiles

1. Cliquer la couche cible dans **Calques** (ex. `walls`).
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

- couches `tiles` + `walls` + `foregrounds` sélectionnées → **R**, tracer la
  zone, **Ctrl+C**, **Ctrl+V** : l'aperçu suit la souris, un clic dépose le
  tout, chaque contenu sur sa couche ;
- même principe pour le tampon : capture au clic droit avec plusieurs
  couches sélectionnées = **tampon multi-couches** (sol + murs + décor en un
  seul pinceau) ;
- les bons tampons se conservent : panneau **Affichage → Tile Stamps** →
  enregistrer (pointer le dossier des tampons sur `tools/tiled/stamps/` pour
  les partager via git) ;
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

Le JSON de plan est éditable directement : chaque clé apparaît en
**propriété de la carte** préfixée `aooPlan_` (panneau Propriétés quand
aucun élément n'est sélectionné) — `name`, `shortName`, `x`/`y` (position
sur la carte du monde), `player_visibility` (`true`/`false`),
`pnj`, `size`, `bg`, `mask`, `scrollingMask`, `verticalScrolling`,
`biomes` (JSON des ressources). Une valeur vide = clé retirée. Tout est
validé et appliqué au push ; le rapport remonte le bilan de santé du
plan (`PlanJsonValidator` : biomes en doublon, ressources inconnues…).

Raccourci pour le fond et le masque animé : **Fichier → AoO : Fond /
ambiance du plan…** liste les grandes images disponibles (celles justement
absentes de la palette).

Les **bornes visibles des niveaux z** (`z_levels`) ne se maintiennent plus
à la main : à chaque push, elles sont recalées sur l'étendue réelle du
niveau (sauf niveau marqué `MapUnavailable`).

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
