# Conversion des map_walls restants en entités — cadrage

Décision du 2026-07-19 : « tous les murs doivent se comporter pareil »
et `destroy.php` disparaît (« ok to delete »). Les murs POSÉS par les
joueurs sont déjà des entités bâtiment ; ce chantier convertit les
murs DE CARTE (`map_walls`) restants pour que `attaquer`, la mort par
disparition (vanish), les sprites blessés et la ligne de tir couvrent
tout — un seul système.

## Périmètre : ce qui se convertit, ce qui reste

### Se convertissent : les OBSTACLES et le DÉCOR

Tout `map_walls` non récoltable (`damages >= 0`) : murs, piliers,
statues, cocotiers/arbres décoratifs, coffres, tonneaux, pancartes…
Census local (à REJOUER SUR PROD avant le go — la requête est dans ce
doc) : 126 murs, dont ~110 obstacles/décor répartis sur arcadia (79),
banque_des_lutins (39), gaia/gaia2/enfers (3).

Atout majeur : la migration WallsToStructures a déjà créé les
pseudo-races structure de la plupart des types (41 races structure en
base : murs, statues, coffres, pilier, tonneau, totems…). La
conversion des noms sans race en crée à la volée (même défauts que
`StructureConversionService` : PV depuis `WALLS_PV`, obstacle,
bloque les tirs).

### Restent des map_walls : les RESSOURCES récoltables

`damages = -1` (récoltable) et `-2` (épuisé) — arbres, pierres à
`fouiller`. Ce sont des ressources, pas des combattants : leur cycle
(fouille → épuisé → regrow par le cron `refresh_resources` +
biomes des plans) est un système à part entière et fonctionne. Les
convertir n'apporterait rien et casserait `ResourceService`. Décision :
`map_walls` DEVIENT la table des ressources, et rien d'autre.

### Cas à trancher (questions ouvertes)

1. **Coffres** (`coffre_* `, PV 1 dans WALLS_PV) : convertis, ils
   deviennent « attaquables » (on tue un coffre d'un coup). S'ils
   portent un butin ou un dialogue un jour, l'entité est le bon
   véhicule — mais faut-il les exclure en attendant ?
2. **Plans de tutoriel** : `TutorialMapInstance` CLONE les map_walls
   par session (murs d'enceinte des plans `tut_*`). Les convertir
   multiplierait les entités par session. Proposition : EXCLURE les
   plans `tutorial`/`tut_*` de la conversion — leurs murs restent des
   map_walls (le tutoriel ne se bat pas contre les murs).
3. **`gaia` (le mur nommé « gaia »)** et autres noms exotiques :
   inventaire au dry-run, arbitrage à la main.

## Mapping de conversion

| map_walls | entité |
|---|---|
| `name` (ex. `pilier`) | `players.race` = race structure du même nom (créée si absente, PV = `WALLS_PV[name]`, sinon défaut 10) |
| `name` en `*_broken` | race de BASE + blessure `players_bonus pv = -(ceil(max/2))` — le sprite brisé suit la règle ≤ 50 % (refreshWoundSprite) |
| image | **copiée telle quelle : `img/walls/{name}.png`** — le mur s'affichait avec, le chemin est du DONNÉ, pas du résolu. AUCUN accès disque dans la migration (leçon « Mu » : une migration déployée tourne sans img/) |
| `pvmax`/`damages` | blessure initiale `players_bonus` si le mur était entamé |
| propriétaire / faction | aucun (`owner_id` NULL, faction '') — décor du monde |
| id / display_id | plage bâtiment (`getNextEntityId('building')`) |

## Mécanique de conversion

1. **Migration Doctrine** (idempotente, backward-compat,
   `--no-all-or-nothing`) — PURE SQL, zéro filesystem :
   - crée les races structure manquantes (INSERT IGNORE, PV WALLS_PV
     recopiés en dur dans la migration — pas de lecture de datas/) ;
   - pour chaque map_walls du périmètre : INSERT players (type
     building, avatar copié `img/walls/{name}.png`) + buildings
     (state built) + blessure éventuelle ; DELETE le map_walls ;
   - les lignes converties sont d'abord COPIÉES dans
     `map_walls_archive` (rollback froid : réinsérer, supprimer les
     entités créées — le champ `players.id` créé est journalisé dans
     l'archive).
2. **Post-déploiement** : `building repair-avatars` (console admin) —
   facultatif, promeut les visuels dédiés `img/avatars/*.webp` s'il y
   en a ; le self-heal du rendu (!706) couvre le reste.
3. **Nettoyage du code** (même MR ou MR suivante) :
   - `destroy.php` + `js/observe_destroy.js` : SURVIVENT pour le seul
     AUTEL (destructible, mécanique d'influence en cours de refonte) —
     ils meurent avec la conversion des autels, pas avant : les
     supprimer aujourd'hui rendrait les autels indestructibles en
     silence. Pour tout le reste, `attaquer` fait foi ;
   - `WallCardView` ne garde que la carte des RESSOURCES (récoltable /
     épuisé) et de l'autel ;
   - `go.php` : le blocage map_walls reste (ressources infranchissables ?
     — aujourd'hui un arbre récoltable bloque le pas : inchangé) ;
   - ligne de tir (`lineOfFireReport`) : déjà les deux mondes — les
     map_walls restants (ressources) continuent d'arrêter les flèches ;
   - couche murs des cartes (`ViewService::generateWallLayer`) : ne
     dessine plus que les ressources ; les obstacles convertis relèvent
     du calque bâtiments (!705) ;
   - `WALLS_PV` : réduit aux ressources (les PV des obstacles vivent
     dans leurs races) ;
   - éditeur Tiled (`scripts/tiled/*`) : poser un « mur » d'obstacle
     pose désormais une entité (ou l'éditeur garde map_walls et un cron
     de conversion ramasse ? — à trancher avec l'équipe carte).

## Ce que ça débloque

- Un seul chemin de dégâts/mort/sprites pour tout ce qui se détruit.
- Les murs de carte gagnent : portes potentielles, dialogues portés,
  réparation par `reparer`, factions de quartier (avant-postes).
- `destroy.php` (163 lignes parallèles au moteur) disparaît.

## Risques

- **Volume prod inconnu** : census obligatoire avant go
  (`SELECT c.plan, w.name, COUNT(*) … GROUP BY`) ; si un plan porte
  des milliers de murs d'enceinte, l'impact damier/SVG et requêtes
  d'occupation est à mesurer (les entités bloquent par la jointure
  players, déjà exercée par les 160+ bâtiments actuels).
- **Éditeur Tiled** : le flux de pose des obstacles change de table —
  coordination avec l'équipe carte.
- **Sauvegarde** : `map_walls_archive` + dump SQL avant migration
  (procédure standard déploiement).

## Estimation

M–L : 3–5 jours (migration + nettoyage + golden masters « un pilier
converti s'attaque et disparaît » + passe Tiled), PUIS du temps de
playtest — d'où l'intérêt de lancer tôt dans la fenêtre S3 (merge
staging en septembre).
