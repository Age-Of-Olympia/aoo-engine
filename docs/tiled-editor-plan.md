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

## Authentification

Les endpoints `api/admin/map/*` sont réservés aux comptes du jeu possédant
l'option `isAdmin` : l'extension se connecte via `auth.php` (nom ou matricule
+ mot de passe) et reçoit un jeton HMAC signé, sans état, valable 30 jours
(`TiledAuthService`). Le secret de signature (`TILED_HMAC_SECRET`) vit dans
`config/tiled_constants.php`, gitignoré ; vide = endpoints désactivés. Les
droits admin sont revérifiés à chaque requête : retirer `isAdmin` invalide
immédiatement les jetons du joueur. Le jeton est mis en cache côté extension
dans `tools/tiled/aoo/session.json` (gitignoré).

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

### Phase 2 — Confort d'édition

- Script de génération des tilesets `.tsj` (une collection par répertoire
  d'images, propriétés par tuile : nom, type, params par défaut).
- Conventions triggers/dialogs en calque d'objets avec formulaire de
  propriétés.
- Bibliothèque de structures : les tampons Tiled (stamps) sauvegardés dans le
  dépôt (`tools/tiled/stamps/`) pour partager les préfabriqués entre admins.
- Dialogue de connexion (URL du jeu + token) via la classe `Dialog` de Tiled,
  config mémorisée.

### Phase 3 — Industrialisation

- Documentation admin (`docs/tiled-editor-guide.md`) : installation de Tiled,
  dépôt des extensions, workflow pull/édition/push.
- Export périodique des plans en `.tmj` versionnés dans git (diff/review des
  cartes, sauvegarde).
- Décommissionnement progressif de `tiled.php` (gardé pour les retouches
  rapides in-game tant qu'utile).

## Emplacements

- Extension Tiled : `tools/tiled/aoo/aoo.js` (+ `config.json` et
  `session.json` locaux, gitignorés, `.exemple` fournis)
- Endpoints : `api/admin/map/auth.php`, `export.php`, `import.php`
- Services : `src/Service/TiledMapService.php`, `src/Service/TiledAuthService.php`
- Secret HMAC : `config/tiled_constants.php` (gitignoré, `.exemple` fourni)
- Cartes pullées : `tools/tiled/maps/*.tmj` (gitignoré, généré)
