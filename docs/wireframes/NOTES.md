# AoO — Refonte UI/UX · Notes pour devs & assistants IA

Document compagnon des wireframes statiques de `/docs/wireframes/`. À garder à jour
quand les wireframes évoluent.

## Vue d'ensemble

Refonte complète de l'interface d'Age of Olympia, organisée autour d'un HUD
permanent : barre haute (identité + caracs), rail vertical gauche (navigation),
damier central carré (joueur toujours au centre), chat à droite, zone basse en
trois colonnes (minimap · sélection · actions).

Le clic sur les tuiles **conserve le comportement actuel** (`observe.php`
alimente la zone Sélection). La refonte est principalement CSS / HTML — pas de
réécriture du back-end.

## Lexique

- **A** — Actions (1 A = 1 action standard ; toutes les actions du jeu coûtent 1 A)
- **MVT** — points de mouvement
- **PV** — points de vie
- **PM** — points de magie (sorts)
- **PF** — points de foi (techniques sacrées / prière)
- **EN** — énergie (stamina / fatigue)
- **damier** — vue map central (toujours carrée, joueur au centre)
- **fiche personnage** — panneau ouvert au clic sur le player chip

## Zones du HUD

| Zone | Rôle | Source actuelle |
|---|---|---|
| Player chip (haut-gauche) | Identité du joueur · clic → fiche perso | `CaracsPanelRenderer` + données joueur |
| Pills A / MVT / PV / PM / PF / EN | Caracs persistantes | `CaracsPanelRenderer` |
| Bandeau lieu (haut-droite) | Olympia · lieu courant · coords | `players.coords`, `plans/*.json` |
| Bouton "Changer de personnage" | Switch entre persos d'un même compte | (mécanisme existant) |
| Bouton "Classements" | Ouvre Classements en panneau | `classements.php` |
| Bouton "Aide" | Help contextuel | `infos.php` |
| Rail (gauche) | Navigation 8 items (cf. ci-dessous) | Pages existantes restylées |
| Damier central | Vue de jeu, joueur au centre, perception carrée | `MainView` (inchangé) |
| Chat & événements (droite) | Onglets Général / Faction / Privé / Événements | "Messages du jour" actuel + `players_logs` |
| Minimap (bas-gauche) | Vue d'orientation ou tactique | Réutilise l'existant — pas de nouveau code |
| Sélection (bas-centre) | Lieu / ressource / joueur sélectionné | `observe.php` (inchangé) |
| Actions (bas-droite) | Grille 4 colonnes, jusqu'à 16 actions | Ancien `#ui-card` reformaté |

## Rail de navigation (7 items + Paramètres en bas)

1. **Personnage** (fiche perso — accès Faction, École de guerre, Sorts/Techniques, etc.)
2. **Carte** (monde / locale — vue map dédiée distincte du damier permanent)
3. **Inventaire / Banque** (fusionnés en une seule page)
4. **Craft**
5. **Caractéristiques** (détail des caracs avancées)
6. **Sorts / Techniques**
7. **Forum**

Et en bas du rail :
- **Paramètres**

Ne sont **pas** dans le rail :
- Damier permanent — toujours visible au centre, pas de "page" séparée
- Classements — accessible depuis la top bar
- Marchand — accessible quand on est sur une case de marchand (action contextuelle)
- Outils admin — visible uniquement pour les admins, à part

## Damier (carte centrale)

- Toujours **carré** (11×11 par défaut) : perception égale dans toutes les directions
- Joueur **toujours au centre** (5, 5)
- Quand un panneau s'ouvre depuis la gauche, le damier se **re-centre automatiquement**
  dans l'espace visible restant (entre panneau et chat)
- Clic sur une case → `observe.php` met à jour la zone Sélection
- Clic sur un joueur (même non-adjacent) → état "joueur sélectionné" avec actions PvP
- Le tutoriel s'affiche en overlay sur cette même vue, pas d'écran dédié

## Zone Sélection — trois états

### État neutre (rien de sélectionné)

Affiche les infos du **lieu courant** :
- Nom (ex. Eryn Dolen)
- Description (ex. "La forêt dans laquelle les Elfes ont établi leur ville")
- Section "Événements" — texte libre que les admins peuvent renseigner pour faire
  vivre le lieu (ex. "Visa accordé à Puck", "Tout le monde est attendu au marché")

### Ressource sélectionnée (ex. arbre)

- Portrait + nom + meta (Arbre · Récoltable)
- Description
- Stats : ressource produite, état (Récoltable / Épuisé / …), position

### Autre joueur sélectionné

- Portrait avec **dégradé rouge montant du bas** proportionnel aux blessures
  (CSS : `[data-wound]::after` avec `--wound-pct`)
- Nom + race + rang + position
- Faction
- Ligne d'**équipement** visible (arme, armure, bottes, casque, amulette…)
- **Pas** de PV chiffré ni de "Statut" ni de "Dernier vu"

Le titre de la zone reflète la sélection : "Sélection — Eryn Dolen", "Sélection — Grand chêne", "Sélection — Thyrias".

## Actions — règle générale

- Toute action "réelle" coûte **1 A**
- Certains sorts ajoutent un coût en **PM** (ex. Boule de Magma : `1 A · 4 PM`)
- Les actions sociales ou d'UI (Échanger, Parler, Voir profil) ne coûtent rien (`0`)
- Grille 4 colonnes pour caser jusqu'à ~16 actions sans scroll
- Action principale (la plus probable) mise en avant en bordeaux/or
- Pas d'Observer / Examiner sur un joueur (ces actions s'appliquent au lieu ou à un objet)

## Chat & événements

Composant **persistant** à droite du damier (toujours visible, même quand un
panneau est ouvert).

Promu depuis l'ancien "messages du jour".

Quatre onglets :
1. **Général**
2. **Événements** (badge non-lus) — historique perso = ce qui était dans `players_logs`

Auteur en couleur-race (nain : ocre, elfe : vert, HS : rouge, humain : bleu).
Messages système en italique + bordure gauche bleue.

## Sous-pages en panneau glissant

Pattern unifié : toute sous-page (Fiche personnage, Inventaire, Banque, Craft,
Sorts/Techniques, Classements, Forums, Paramètres, Journal d'événements) s'ouvre
dans un conteneur `.subpage-panel` qui glisse **depuis la gauche** (côté du
rail).

- Largeur : 460 px
- Position : `left: 64px` (après le rail), du top-bar (56 px) jusqu'en bas
- Le damier se re-centre automatiquement (CSS : `.panel-open .hud-map { padding-left: 460px }`)
- Le chat reste visible à droite — toujours

### Stratégie de migration

Chaque `*.php` de sous-page apprend à répondre en "mode panneau" via
`?panel=1` qui renvoie seulement le HTML du body sans le chrome global. Migration
page-par-page :

1. Inventaire (le plus gros impact UX)
2. Fiche personnage (déclenche faction / war school / sorts)
3. Journal d'événements (relie au chat onglet Événements)
4. Banque, Craft, Marchand
5. Forums, Classements
6. Paramètres

La page reste accessible en plein-écran (fallback navigation directe) pendant
toute la migration.

## Mobile

Sur mobile (~390 × 780) :
- Top bar **bordeaux** avec player chip compact + 5 mini-pills (A · MVT · PV · PM · PF) en icône+chiffre seulement
- **Damier carré** occupant ~50% de la hauteur
- **Pastille chat overlay** flottante en haut du damier (dernier message + badge)
- **Carrousel** à 3 panneaux à la place des 3 zones du bas desktop : minimap ↔ sélection+actions (défaut) ↔ grille d'actions complète
- **Drawer gauche** déclenché par un bouton hamburger en haut-gauche du top bar — slide-in
  depuis la gauche, contient les 8 items du rail + Changer de personnage / Classements / Aide
- **Tab bar bordeaux** : 4 raccourcis seulement — Sac · Personnage · Chat · Sorts
  (pas de bouton "Plus" : tout passe par le drawer)

Les sous-pages s'ouvrent en **bottom-sheet** (~85 % de hauteur) couvrant le
damier mais laissant **les pills A/MVT/PV/PM/PF toujours visibles** au-dessus.

Mêmes décisions que desktop : panneau / sheet = même HTML, le chrome change.
La navigation mobile mirrore donc le rail desktop (mêmes 8 items, même ordre).

## Composants à développer (net-new)

- Chat persistant front (WS ou polling)
- Carrousel mobile (CSS scroll-snap + JS pour les dots)
- `panel-router` qui charge la sous-page demandée en AJAX
- `player-card view` — agrège caracs + accès Faction, École, Sorts, etc.
- Petit système d'événements de lieu (admin → DB → onglet Événements + carte lieu)

## Composants réutilisés

- Police `goudy.ttf` et `rpg-awesome` déjà présents
- Fonds `bg.jpeg` (pierre claire), `marbre.jpeg` (marbre), `perchment.gif` (parchemin)
- Couleurs AoO existantes : bordeaux `#810303`, or `#b55300` / `#efd477`, bleu `#0072ff`
- `observe.php` (zone Sélection)
- Vues map actuelles (local + image monde) pour la minimap
- `players_logs` pour l'onglet Événements

## Cache-busting

Penser à incrémenter `?v=YYYYMMDD` sur les fichiers JS/CSS modifiés.

## Capture des wireframes

Un petit script `screenshot.js` est livré dans ce dossier pour capturer
automatiquement chaque wireframe (et chacun de ses états URL-anchor) dans
les bonnes dimensions de viewport. Utile pour partager rapidement l'état
courant du design.

### Prérequis

- Apache local servant `/var/www/html` (par défaut dans le dev container)
- `puppeteer` installé sous `node_modules/` (déjà présent dans le repo)

### Exécution

```bash
# Depuis la racine du projet
node docs/wireframes/screenshot.js

# Sortie par défaut : /tmp/wf-shots/
# (volatile, non commité)

# Personnaliser la sortie ou l'URL de base :
node docs/wireframes/screenshot.js --out=/tmp/my-shots
node docs/wireframes/screenshot.js --base=http://localhost:9000/docs/wireframes/
```

Le script capture neuf vues : la page d'index, l'écran principal desktop
(état neutre / arbre / autre joueur via les hashs `#s-none`, `#s-tree`,
`#s-player`), le panneau desktop dans ses trois états (`#p-pcard`,
`#p-inv`, `#p-log`), et les deux écrans mobile.

## Voie de développement

| Sujet | Décision |
|---|---|
| Style général | Layout HUD permanent, joueur au centre du damier carré |
| Clic sur tuile | Comportement actuel conservé (`observe.php`) — zéro changement back-end côté logique |
| Combat | Pas d'écran dédié — combat depuis le damier, clic sur cible → actions PvP dans Sélection |
| Sous-pages | Panneau glissant (desktop, depuis la gauche) · bottom-sheet (mobile) · même HTML |
| Priorité de design | Mobile-first, puis extension desktop |
| Top bar | Player chip · pills A/MVT/PV/PM/PF · plan/lieu · changer-perso / classements / aide |
| Terminologie | "A" pour Actions, "PM" pour magie, "PF" pour foi |
| Tour suivant | Horaire informatif (HH:MM), pas de countdown |
| Player chip → fiche | Clic = fiche perso en panneau avec accès Faction / École / Sorts / Logs / Équipement / Hauts faits |
| Chat | Promu depuis "messages du jour" — toujours visible · onglet Événements remplace l'icône Journal |
| Navigation | 8 items dans le rail · tab bar 5 items sur mobile |
| Damier | Carré 11×11, joueur au centre, perception égale toutes directions |
| Damier + panneau | Re-centrage auto dans l'espace visible restant |
| Tutorial | Overlay sur le HUD réel, pas de wireframe dédié |
