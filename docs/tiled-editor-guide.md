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
| AoO : Connexion / changer d'instance… | formulaire compte + instance (local, test…) |
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
   dupliquer un bâtiment. (La capture ne prend que la couche active.)
5. **Ctrl+Z** annule, sans limite, jusqu'au push.

### Le pinceau Terrain (transitions automatiques)

Onglet **Terrain Sets** (à côté des Tilesets) → set « Biomes » : choisir un
terrain, puis peindre sur la couche `tiles` — Tiled pose tout seul les
tuiles de transition (fondus générés). Seules les paires générées via
`tools/tiled/generate_transitions.php` ont de vraies transitions douces ;
les autres biomes se posent en bords francs.

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
