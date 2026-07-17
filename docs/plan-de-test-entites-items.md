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
7. **Console `²`** — `building place palissade 4 4 arcadia` puis
   `building remove [id]`.
   ☐ Les deux marchent et le damier se rafraîchit.
8. **Admin → Races** — éditer « palissade ».
   ☐ Sorte « Structure » affichée ; créer une nouvelle sorte structure
   (ex. tour, PV 200) → elle apparaît dans le formulaire de pose ;
   elle n'apparaît PAS dans la création de PNJ ni à l'inscription.

## B. Construire par l'action (10 bois + 1 A)

9. **Sa propre case** — cliquer votre case : l'action **« Construire
   une palissade »** est là (vous avez ~11 bois restants après mes
   tests ; console `item bois 20` au besoin — vérifier la syntaxe avec
   `help item`).
   ☐ Avec ≥ 10 bois : succès, −10 bois, −1 A, une VRAIE palissade
   apparaît sur une case libre adjacente, à VOTRE nom (admin →
   Bâtiments : propriétaire Cradek, faction reprise).
   ☐ Avec < 10 bois : bloquée, « Il vous faut 10 × Bois… », rien
   dépensé.
10. **La boucle complète** — attaquer votre propre palissade, la
    réparer, la détruire.
    ☐ Tout fonctionne comme en A.

## C. Instances d'objets — équipement

11. **Inventaire** — ouvrir l'inventaire.
    ☐ Votre bâton de marche : une ligne équipée (l'instance) + une
    pile x4 ; le casque : sa ligne d'instance équipée. Les totaux
    sont bons.
12. **Bandeau d'équipement** — observer votre case (ou la fiche).
    ☐ Sous l'icône du bâton : **jauge orangée à 37 %**, tooltip
    « durabilité 37/100 » ; casque : jauge neutre pleine.
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
    en **(2,0)**, à deux cases de vous.
    ☐ Visible sur le damier (sprite gladius) ; l'observer montre
    « Dette de Thétis » avec Attaquer + **Ramasser**.
19. **Le ramasser** — venir adjacent, action Ramasser (gratuite).
    ☐ « Vous ramassez Dette de Thétis. » ; l'objet disparaît de la
    carte ; votre inventaire gagne une ligne gladius **avec ses
    55/100** — l'identité a survécu.
20. **Console** — `objet place gladius 3 3 arcadia`.
    ☐ Un gladius neuf apparaît en (3,3), ramassable, attaquable
    (25 PV — le détruire le laisse en entité à 0 PV, boucle de
    destruction d'objets à affiner en équilibrage).
21. **Bourses au sol (objets NON instanciés)** — deux piles map_items
    classiques sont posées près de vous : **1 bois en (-1,0)** et
    **8 pierres en (-1,-1)**.
    ☐ Les deux s'affichent en **bourse** (sprite loot), objet seul
    comme pile — la représentation héritée est intacte à côté des
    objets uniques ;
    ☐ marcher sur la case ramasse le contenu (comportement hérité de
    go.php), quantités correctes en inventaire (le bois s'ajoute à
    votre pile existante).

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
