# Plan de test — Entités (bâtiments, objets uniques) & Instances d'objets

À dérouler sur le devcontainer, connecté **Cradek** (mdp `test`), sur
**arcadia** (le monde de dev). Tout est déjà en place : actions
accordées, matériaux fournis, démos posées. Cocher au fur et à mesure.

Stack concernée : !652 → !665 (base `integration/hud-redesign`).

---

## A. Bâtiments — combat et administration

1. **Voir la palissade de démo** — au chargement du damier, une
   palissade (sprite barricade) est en **(1,-1)**, adjacente à vous.
   ☐ Elle est visible comme n'importe quelle entité.
2. **L'observer** — cliquer sa case.
   ☐ Panneau : « Palissade », et SEULEMENT les actions **Attaquer** et
   **Réparer** (pas de Barbier, pas de vol — les autres actions sont
   masquées par leur garde TargetType).
3. **L'attaquer** — Attaquer, plusieurs fois si besoin.
   ☐ Jets de dés, dégâts, PV qui baissent ; 1 A par attaque ; du sang
   sur la case (comportement hérité, connu).
4. **La réparer** — action **Réparer** sur la palissade blessée.
   ☐ PV remontent de +F (clampé au max) ; Réparer refuse un personnage
   (le bouton n'apparaît d'ailleurs pas sur un joueur).
5. **La détruire** — attaquer jusqu'à 0 PV.
   ☐ « Vous détruisez la structure. » ; PAS de partage d'XP ni de
   compteur de kills ; dans **admin → Bâtiments**, son état passe à
   « Ruine », la ligne reste.
6. **Admin → Bâtiments** — menu latéral admin.
   ☐ Roster (type, état, PV, position, propriétaire) ; **Restaurer**
   remet PV au max + état « Construit » ; **Retirer** supprime ;
   **poser** une nouvelle palissade via le formulaire (case libre !).
6b. **Dialogue porté par un bâtiment** (buildings.dialog — ce qui était
   sur des PNJ peut passer sur des bâtiments).
   ☐ Admin → Bâtiments : colonne « Dialogue » (sélecteur par ligne +
   OK) et champ optionnel du formulaire de pose.
   ☐ Admin → Dialogues : la liste compte les bâtiments porteurs dans
   la colonne références ; supprimer un dialogue porté par un bâtiment
   est refusé (« détachez-les d'abord ») — même garde que les
   déclencheurs map_dialogs.
6c. **Édifices, porte Ouvert/Fermé et intégration HUD** — démo prête :
   « Taverne du Sanglier » #20000004 en (2,-2) arcadia (type édifice
   `taverne`, 150 PV) porte le dialogue `gaia`.
   ☐ En jeu (HUD) : cliquer la taverne → la zone de sélection montre
   la pastille « OUVERT · Construit · PV 100% » et un bouton
   « Parler » (comme « Marchander » chez les PNJ). Le bouton — ou le
   nom — ouvre la FICHE du bâtiment dans le panneau « Personnage »
   (qui sortait « error target id » sur les structures) : portrait,
   état, propriétaire, description, et la conversation FAÇON MARCHAND.
   ☐ PORTÉE : la conversation exige d'être sur une case ADJACENTE au
   bâtiment — sinon la fiche affiche « Il faut être directement à
   côté du bâtiment pour pouvoir parler au tenancier » (garde côté
   serveur, même mécanisme que le MDJ limité à la Perception).
   ☐ Admin → Bâtiments → Éditer : nom, DESCRIPTION (l'équivalent du
   message du jour — visible sur la carte et la fiche), dialogue,
   porte, propriétaire, faction. Le sélecteur de dialogue par ligne a
   déménagé dans cette page.
   ☐ L'endommager sous 50% des PV → « FERMÉ (endommagé) », le
   dialogue disparaît ; restaurée, elle rouvre.
   ☐ Admin → Bâtiments : bouton « Fermer » (édifices seulement) →
   « FERMÉ » en jeu, dialogue tu ; « Ouvrir » le rétablit. Ruine et
   construction ferment aussi d'office.
   ☐ La palissade (obstacle, races.structure_nature) n'a NI porte NI
   mention « Fermé » : seulement « Construit · PV x% ». Un mur n'est
   pas un édifice — son is_open signifiera un jour la passabilité
   (système à mutualiser avec les coffres).
   ☐ Admin → Races : sélecteur « Nature » (Édifice/Obstacle) sur les
   sortes Structure ; la nature voyage dans les bundles de races.
   ☐ Une structure ne s'affiche jamais « (inactif) » — l'inactivité
   est réservée aux joueurs réels.
6d. **Boutons d'action CONTEXTUELS** (action_conditions.display_context).
   ☐ Workbench (admin → Actions → Configuration) : chaque condition a
   une case « Contextuelle — le bouton ne s'affiche que si la
   condition passe » (+ badge « contextuelle » dans l'en-tête). Sans
   la case : comportement historique, le bouton s'affiche et
   l'exécuteur refuse au clic.
   ☐ Démo : la RequiresDistance (max 1) de « reparer » est marquée
   contextuelle — le bouton Réparer n'apparaît sur une structure que
   depuis une case ADJACENTE ; « attaquer » (non flaggée) s'affiche à
   toute distance et refuse au clic, comme avant.
   ☐ Le bouton « Parler » (navigation, pas une action) suit la même
   règle d'adjacence en dur — pas d'affordance qui mène à « il faut
   être à côté ».
6e. **Événement « Nouveau tour »** (TurnProcessingService — le moteur
   de tour extrait de la vue).
   ☐ Au tour suivant : la page « Nouveau Tour » s'affiche comme avant
   (mêmes lignes de récupération, usure comprise).
   ☐ Évènements : une ligne dorée « Nouveau tour — Xp +…, PV +… …
   Prochain tour le … » relisible après coup.
   ☐ VISIBILITÉ : un AUTRE joueur à portée ne voit PAS votre événement
   de tour (il est privé) ; la « Vue complète » admin (logs.php?admin)
   le montre comme tout le reste.
7. **Console `²`** — `building place palissade 4 4 arcadia` puis
   `building remove [id]`.
   ☐ Les deux marchent et le damier se rafraîchit.
8. **Admin → Objets → Recettes** — l'artisanat en panneau d'admin.
   ☐ Liste des recettes (ingrédients, résultats, races — « toutes »
   sans restriction) ; créer/éditer : nom, 5 emplacements d'ingrédients
   (objet × quantité), 2 de résultats, cases races ; supprimer avec
   confirmation. Modifier la recette mur_bois (12 bois), vérifier à
   l'Artisanat en jeu, remettre 15.
8b. **Admin → Objets** — nouvelle entrée du menu, ÉDITION COMPLÈTE.
   ☐ Catalogue complet listé (flags, élément, sort lié, usure) avec
   filtre ; Éditer un objet règle TOUT : description, prix,
   emplacement, type/sous-type, race, les 16 caracs, les spéciaux
   (esquive, PR, fixedF…), munitions, effets/interdits/extra (JSON
   validé), flags et usure — la base est la source de vérité
   (stats migrées des JSON, sans perte via `extra`).
   ☐ Modifier le CC du gladius (+1 → +2), sauver, observer sa case :
   les caracs en jeu suivent immédiatement ; remettre +1.
   ☐ En prod : admin → item-seed rejoue le seed pour les objets sans
   stats (92 en dev faute de JSON locaux).
8c. **Admin → Objets — création & bundles JSON** (cycle de vie complet,
   comme races/factions/dialogues/plans).
   ☐ « Nouvel objet » : nom technique (minuscules/chiffres/_-, unique)
   + tout le formulaire d'édition → l'objet créé est d'emblée sourcé
   en base (badge BDD) ; nom en doublon ou invalide → refus avec
   message, rien n'est écrit.
   ☐ Bouton « JSON » sur une ligne → télécharge le bundle de l'objet ;
   « Exporter tout (JSON) » → bundle des 122+ objets ; « Importer » →
   action-import.php, aperçu créations/mises à jour avant écriture.
   Un objet importé passe toujours `stats_in_db=1` (la base devient sa
   source), même si le payload ne portait pas la clé.
   ☐ Recettes : mêmes boutons JSON / Exporter tout / Importer ; les
   ingrédients, résultats et races voyagent par NOMS — importer une
   recette dont un objet n'existe pas ici est REJETÉ avec le message
   « importer d'abord le bundle d'objets », sans rien écrire.
8b. **Admin → Races** — éditer « palissade ».
   ☐ Sorte « Structure » affichée ; créer une nouvelle sorte structure
   (ex. tour, PV 200) → elle apparaît dans le formulaire de pose ;
   elle n'apparaît PAS dans la création de PNJ ni à l'inscription.

## B. La palissade, objet constructible (craft → porter → construire)

9. **La boucle objet constructible** — décision de revue : la palissade
   N'EST PAS un « type de bâtiment », c'est un OBJET du catalogue.
   ☐ **Artisanat** : la recette « palissade » (10 bois → 1 palissade)
   est disponible ; vous avez 18 bois — craftez-en une. L'objet
   palissade est dans l'inventaire : empilable, bancable, échangeable
   (vous en avez déjà une autre, fournie).
   ☐ **Construire DEPUIS L'INVENTAIRE, avec CHOIX DE LA CASE** :
   cliquer l'objet palissade (ou mur_bois) → « Construire (1 A) » →
   retour au damier en mode choix : le MASQUE du tutoriel (voile troué
   autour de vous, croix sur les cases non-praticables) n'éclaire que
   les cases libres adjacentes — cliquer l'une d'elles construit LÀ ;
   Échap ou Annuler pour sortir ; une case volée entre l'affichage et
   le clic est refusée SANS consommer l'objet.
   ☐ Sans Action restante : bouton grisé avec l'explication ; sans
   l'objet : il n'y a simplement pas de ligne à cliquer.
   ☐ Le panneau de votre case ne montre AUCUN bouton construire_* —
   l'objet est l'entrée unique.
10. **La boucle complète** — attaquer votre propre palissade, la
    réparer, la détruire.
    ☐ Tout fonctionne comme en A.

## C. Instances d'objets — équipement

11. **Inventaire** — ouvrir l'inventaire.
    ☐ Les ÉQUIPÉS sont triés EN TÊTE : bâton (instance, sa durabilité
    affichée sous les caracs), casque, flèches — puis le reste par nom
    (dont la pile bâton x4). Les totaux sont bons.
    ☐ Cliquer une ligne d'instance affiche son ÉTAT dans l'aperçu
    (« Durabilité 35/100 », « Brisé » le cas échéant) ; cliquer une
    pile n'affiche pas d'état.
12. **Bandeau d'équipement** — observer votre case (ou la fiche).
    ☐ Sous l'icône du bâton : **jauge orangée** (~35 % — la valeur vit
    au rythme des tours, l'usure du bâton est active en dev), tooltip
    « durabilité X/100 » ; casque : jauge neutre pleine.
13. **Déséquiper / rééquiper le casque** (100/100, vierge).
    ☐ Déséquipé, il **retourne dans la pile** (aller-retour
    invisible) ; rééquipé, tout est normal, caracs correctes.
14. **Déséquiper le bâton** (usé 37/100).
    ☐ Il reste **sa propre ligne** d'inventaire (il est usé, il ne
    peut pas redevenir une pile) ; le rééquiper reprend LA même
    instance (toujours 37/100).

## D. Usure au tour

15. **Attaquer** (la palissade, par ex.) avec le bâton équipé — le
    bâton est configuré en dev : déclencheur attack, −2/tour.
    ☐ Rien ne bouge immédiatement (l'usure est armée, pas appliquée).
16. **Passer le tour** — console `²` : `newturn 1` (voir `help
    newturn`), puis recharger : l'écran « Nouveau Tour » apparaît.
    ☐ Le récap affiche « Bâton de marche s'use (−2). » ; la jauge est
    à 35/100 ; dix attaques dans le même tour n'usent qu'une fois.
17. **Brisé** (optionnel, via SQL ou en enchaînant les tours) — à 0 :
    ☐ icône ternie + croix dans le bandeau, « BRISÉ » au tooltip, et
    ses caracs ne comptent plus (CC redescend).

## E. Objets uniques — le pont carte

18. **« Dette de Thétis »** — un gladius NOMMÉ, usé 55/100, est posé
    en **bourse** en **(2,0)**, à deux cases de vous (retour de revue :
    une instance au sol se comporte comme tout loot).
    ☐ Sprite **bourse** sur le damier, comme les autres ; cliquer la
    case liste « Au sol : « Dette de Thétis » (Gladius) — durabilité
    55/100 ».
19. **La ramasser en MARCHANT dessus** — pas d'action dédiée.
    ☐ Récap de ramassage ; la bourse disparaît ; votre inventaire
    gagne la ligne « Dette de Thétis » (Gladius) **avec ses 55/100**
    — l'identité a survécu à l'aller-retour.
20. **Console** — `objet place gladius 3 3 arcadia`.
    ☐ Une bourse apparaît en (3,3) ; marcher dessus ramasse le gladius
    (instance neuve, créateur tracé).
21. **Bourses au sol (objets NON instanciés)** — trois bourses
    map_items classiques sont posées près de vous : **1 bois en
    (-1,0)**, **8 pierres en (-1,-1)** et une **bourse mixte en (1,0)**
    (bois x3, pierre x2, cuir x5).
    ☐ Les trois s'affichent en **bourse** (sprite loot), objet seul,
    pile, ou mélange — la représentation héritée est intacte à côté
    des objets uniques ;
    ☐ CLIQUER une bourse liste son contenu dans le panneau (« Au
    sol : … » avec vignettes et quantités, les trois lignes pour la
    mixte) ; une case vide n'affiche rien ;
    ☐ marcher sur la case ramasse le contenu (comportement hérité de
    go.php), quantités correctes en inventaire ;
    ☐ **SA PROPRE case** : lâcher un objet (Lâcher dans l'inventaire)
    puis cliquer sa case → le panneau liste l'objet ET propose un
    bouton **Ramasser** (pas besoin de sortir/revenir) ; sur les autres
    cases, le rappel « marchez dessus » reste.

## E2. Structures de carte héritées — image « brisé »

21a. **Mur de bois CONSTRUCTIBLE (nouveau système)** — vous avez
    l'action « Construire un mur de bois » + 1 objet mur_bois (recette :
    15 bois à l'artisanat).
    ☐ Construire → l'ENTITÉ apparaît avec le sprite du mur ; elle
    s'ATTAQUE (melee) et se RÉPARE (action Réparer) comme la palissade ;
    détruite, elle passe au sprite **brisé** (mur_bois_broken) en état
    Ruine ; restaurée (admin), elle reprend son sprite.
21b. **Mur cassé LEGACY (ancien système)** — un `mur_bois` de carte
    frappé sous la moitié de ses PV est en **(0,-1)** : image brisée.
    ⚠ Un mur legacy ne s'attaque PAS par l'action Attaquer ni ne se
    répare — il se détruit par l'ICÔNE de destruction qui apparaît SUR
    la case après clic, en étant ADJACENT (1 A, arme de mêlée). C'est
    la frontière actuelle : les murs BÂTIS par les joueurs sont des
    entités complètes, les murs de carte historiques migreront avec le
    chantier murs→structures.
    ☐ Le CLIQUER ouvre la MÊME carte que la palissade (Ui::get_card) :
    nom « Mur bois — brisé », portrait avec voile de dégâts rouge,
    type Structure, état Destructible — le principe « posé = bourse,
    construit = entité » est visible côte à côte avec les bourses
    voisines.
    ☐ Continuer à le frapper (action destruction, arme de mêlée) le
    détruit à 100 dégâts ; un mur SANS image _broken (ou sans entrée
    WALLS_PV _broken) garde son image d'origine — repli vérifié.
21c. **Admin → Objets** : éditer `mur_bois`.
    ☐ Le panneau **Images** montre les quatre représentations (objet,
    vignette, carte, carte-brisé) avec « manquante » pour celles qui
    n'existent pas ; la liste affiche les mini-vignettes carte à côté
    du nom.

## F. Garde-fous (non-régression)

22. ☐ Se déplacer, attaquer un PNJ, se soigner, fouiller : comme avant.
23. ☐ Le tutoriel démarre et se déroule normalement (isolation intacte).
24. ☐ Munitions : équiper un carquois l'équipe EN BLOC (pas une
    instance par flèche) ; tirer consomme normalement.
25. ☐ Banque / marché / échanges : fonctionnent sur les piles ; les
    lignes d'instances ne s'y risquent pas encore (assumé, phase
    suivante).
26. ☐ `make test` : 846 verts ; `make phpstan` : OK.

---

## Restes connus / hors périmètre de ce plan

- Butin de mort : les instances équipées ne tombent pas encore (le
  drop unifié mort/destruction arrive avec l'extraction du bloc loot
  de `Player::death()`).
- Marché/banque d'instances (`instance_id` sur asks/bids) : phase
  suivante.
- Équilibrage : quels objets s'usent, à quel rythme, coûts de
  construction par type — contenu admin, tout est configurable.
- UI de pose au choix de la case pour `construire` (v1 : case libre
  adjacente automatique).
