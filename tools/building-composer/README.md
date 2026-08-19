# Building composer

Compose des sprites de bâtiments pour la carte à partir de trois ingrédients :
une **forme de base** (gabarit 3D projeté), une **façade** et un **toit**.
Le moteur s'occupe de toute la géométrie — perspective dimétrique, éclairage
des faces, débord de toit, découpe en tuiles de 50 px — pour que les surfaces
restent des rectangles plats, faciles à peindre. Pas de sol dans le sprite :
le terrain vient des cases de la carte.

## Interface graphique

Avec le devcontainer démarré :
**http://localhost:9000/tools/building-composer/gui.php**

Aperçu en direct (zoom ×2/×4/×6 sur damier de transparence), tous les réglages
du CLI, et « Enregistrer » qui écrit `out/<nom>.png` plus les tuiles 50×50.
Le sélecteur « Aperçu » bascule entre brut (instantané, par défaut) et peint
(fidèle mais ~3 s) ; les enregistrements sont toujours peints.
Depuis un réseau privé la page est libre (atelier local) ; sur un hôte
public (experimental), l'auth admin du jeu prend le relais — superadmin,
le niveau par défaut des pages non listées. Le déploiement ne copie que
`tools/building-composer/`, le reste de `tools/` reste local.

## Lancer l'outil en ligne de commande

L'outil tourne dans le devcontainer (PHP + GD) :

```bash
docker exec PHP-AOO4-Local php /var/www/html/tools/building-composer/compose.php list
docker exec PHP-AOO4-Local php /var/www/html/tools/building-composer/compose.php sheet
docker exec PHP-AOO4-Local php /var/www/html/tools/building-composer/compose.php blueprint all
docker exec PHP-AOO4-Local php /var/www/html/tools/building-composer/compose.php \
    build maison_2x2 --facade colombage --roof slate --shape gable --name ma_maison
```

`build` écrit `out/<name>.png` (l'image composée — utilisable telle quelle
comme sprite de **bâtiment**, convention `img/walls/`, dont la vignette
avatar se déduit) et `out/<name>_NN.png` (les tuiles 50×50 prêtes pour un
**foreground** dans `img/foregrounds/`, même convention que le `convert.sh`
historique). Un même bâtiment composé sert donc aux deux usages.

Options de `build` :
`--facade stone|plaster|wood|colombage|columns|darkstone|logs|swamp`,
`--roof tiles|thatch|slate|flat`, `--shape gable|hip|flat|temple|banque|attique`
(temple = fronton grec pleine largeur, toit plat légèrement enfoncé derrière,
parapet sur les quatre côtés ; banque = même toit-parapet mais fronton modeste
centré sur l'entrée ; attique = pignon rectangulaire pleine largeur ; temple
se marie avec `--facade columns`, qui supprime les fenêtres — la colonnade
est l'ouverture), `--brut` (désactive la passe peinture, voir plus bas),
`--windows simple|arche|hautes|aucune`, `--label TEXTE`
(plaque sombre sur l'attique, lettres blanches ; deux lignes avec `LIGNE1|LIGNE2`,
la lisibilité est meilleure sur une seule ligne courte ; 💰 dessine une pièce d'or,
les autres emojis sont ignorés),
`--door simple|double|arche|aucune` et `--door-pos gauche|centre|droite`
(une banque = `--door arche --door-pos centre`), `--mirror` (orientation
inversée), `--seed N` (variation), `--facade-img fichier.png` et
`--roof-img fichier.png` (textures peintes à la main, voir ci-dessous).
Les fenêtres s'écartent d'elles-mêmes de l'emprise de la porte.

## Formes disponibles

| Forme | Emprise | Canevas | Niveaux | Toit par défaut |
|---|---|---|---|---|
| `hutte_1x1` | 1×1 | 50×50 | 1 | pignon |
| `longere_2x1` | 2×1 | 100×100* | 1 | pignon |
| `maison_2x2` | 2×2 | 100×100 | 2 | 4 pans |
| `tour_1x1` | 1×1 | 50×100* | 3 | 4 pans |
| `halle_2x2` | 2×2 | 100×100 | 1 | pignon |

\* le canevas déborde d'une rangée vers le nord : le sprite recouvre la case
au-dessus, comme `hutte_pilotis`.

Ajouter une forme = une ligne dans la constante `FORMS` de `compose.php`.
Rien ne peut déborder du canevas : le débord de toit est compris dans le
calcul de largeur, et un bâtiment trop haut est réduit plutôt que coupé.
Après toute retouche de `FORMS` ou de la projection, vérifier avec :

```bash
docker exec PHP-AOO4-Local php /var/www/html/tools/building-composer/check_fit.php
```

## Passe peinture

Chaque `build` se termine par une passe « peinture » (`postpass.php`, requis par
`compose.php`), appliquée sur le canevas de travail avant la réduction et la
découpe en tuiles : palette calée sur les peintures de référence déposées dans
`out/refs/` (à défaut, repli sur les bâtiments peints du jeu,
`img/foregrounds/*_olympienne_*.png` ; rien trouvé = pas de calage, le reste
s'applique), dégradé
vertical de lumière, occlusion ambiante le long des arêtes, grain, ombre de
pied de mur et contour irrégulier. `--brut` la désactive. Les murs reçoivent
aussi un habillage procédural (lierre au pied, fissures), remplacé par les
tampons peints déposés dans `parts/stamps/` quand il y en a (peints ~2× plus
larges que hauts, comme les façades — la projection les resserre).
`postpass.php compare` génère
`out/compare.png` : brut / peint / référence peinte. En CLI autonome,
`postpass.php run in.png out.png` applique la passe à un PNG déjà réduit.

## Circuit graphiste

Les textures procédurales servent de bouche-trou. Pour de vraies surfaces :

1. `blueprint all` génère dans `blueprints/` la perspective annotée de chaque
   forme (façade en bleu, pignon en vert, toit en rouge) et dans
   `parts/templates/` un canevas **plat** par surface, avec les repères de
   cases (traits bleus) et d'étages (traits rouges).
2. Le graphiste peint par-dessus un canevas — à plat, sans se soucier de la
   perspective — et enregistre son PNG (portes et fenêtres comprises, ou non).
3. Déposer le PNG dans `parts/` : il apparaît dans les listes « peinte » de
   l'interface. En CLI : `build <forme> --facade-img parts/fichier.png
   --roof-img parts/toit.png`. Le moteur projette les peintures sur les
   faces, applique l'éclairage et découpe les tuiles.

Une même texture peut habiller plusieurs formes : elle est remise à l'échelle
de chaque face. Peindre large (~2× la hauteur utile) : la projection écrase
l'horizontal.

## Limites connues

- Le rendu est plus « propre » que les bâtiments peints existants
  (`maison_olympienne`, `auberge_olympienne`) : le charme viendra des textures
  peintes, pas du moteur.
- Pas encore d'annexes (auvent, escalier, cheminée, balcon) ni de pilotis ;
  c'est l'étape suivante naturelle si le principe est validé.
- GD refuse les matrices affines à déterminant négatif : `pasteQuad` retourne
  la texture et réancre le quadrilatère quand l'axe v monte à l'écran. Ne pas
  « simplifier » ce détour.
