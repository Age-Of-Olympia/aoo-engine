# La saison prochaine — ce qui change pour les admins et animateurs

L'esprit général : **le contenu du jeu passe des fichiers JSON à la base**, édité par des pages d'admin cohérentes : plus besoin de toucher aux fichiers ni à la base à la main.

## Le tableau de bord refondu

- **Menu réorganisé en groupes** (Tutoriel, Cartes, Actions, Personnages, Dialogues, Races, Effets, Factions, Bâtiments, Objets), avec un lien
  « Retour au jeu » épinglé en bas de la barre.
- **Composants partagés partout** : mêmes tableaux (recherche + pagination), mêmes formulaires, mêmes messages flash — chaque page se comporte pareil.
- **Contrôle d'accès par page** : chaque entrée du menu a un niveau (admin / superadmin), ajustable depuis la page Contrôle d'accès. Les droits sont validés sur l'humain connecté, pas sur le personnage incarné.

## Les catalogues passent en base

- **Effets** : le catalogue complet (icônes, comportements — postures, vol, coûts, blocages) vit en base et s'édite sur la page Effets. Plus aucun comportement codé « par nom » ; un wiki des effets se génère depuis le catalogue (ce comportement est à généraliser pour que Finn n'ait plus à tout faire à la main <3>) ; export/import par bundles JSON.
- **Objets** : page Objets complète (stats migrées du JSON, flags, durabilité max par objet), création d'objets, bundles export/import objets + recettes. Les consommables s'éditent au catalogue.
- **Recettes d'artisanat** : panneau dédié dans le groupe Objets, c'était une simple liste déjà très utile, maintenant c'est un panneau d'édition
- **Factions** : entités en base (rôles compris), pages Liste / Membres, export/import JSON. La faction de départ d'une race devient un select validé sur ce catalogue.
- **Dialogues** : liste et édition.
- **Page d'accueil** : sections éditoriales, chroniques et galerie s'éditent depuis l'admin ; le message du jour se change depuis le HUD.

## Actions : le workbench

- **Configuration complète d'une action** on avait : conditions, outcomes, instructions, paramètres typés, simulation de résolution. On gagne un "contexte" : si on affiche le bouton d'action ou non, typiqueemnt le bouton pour parler à un bâtiment ne doit s'afficher que collé à lui.
- **Nouveautés du catalogue** : condition TargetType (cible personnage ou structure), condition RequiresItem, instructions PlaceStructure et PlaceLayer (routes), contexte d'affichage des boutons, défauts par type, import/export.
- **La catégorie d'une action s'édite** désormais au workbench (datalist des catégories en usage) : plus figée à la création.

## Personnages

- **Fiche d'édition d'un personnage** depuis la liste : renommer, téléporter (plan validé), régler les vitalités restantes (PV, PM, MVT, A, Ae), rendre le tour disponible. Pensé pour l'animation : blesser, soigner, déplacer sans une requête SQLet plus intuitivement que via la console.
- **La liste des personnages** se filtre par nom/matricule, type (joueur/PNJ), statut et désormais par race.
- **Avatars & portraits** : inventaire par type et par race avec diagnostics (dimensions hors canon, miniature absente, fichier disparu), upload redimensionné à l'upload, suppression gardée tant que l'image est utilisée, aperçu en taille réelle d'un clic, et liste des joueurs utilisant chaque image.

## Cartes et plans

- **Page Plans** : création (vierge ou clonage), configuration par cartes thématiques, niveaux Z, validation du JSON, export bundle restaurable.
- **Opérations lourdes sous double validation** — confirm() ET le code du plan sur lequel on travaille, vérifié côté serveur :
  - **supprimer un plan** (bilan préalable : joueurs bloquent toujours, PNJ/logs exigent le forçage) ;
  - **renommer un plan** : coords, respawn des factions, plan des races, catalogue tutoriel, téléporteurs entrants et fichiers suivent : pas de lien cassé ;
  - **vider les cases d'un plan** en gardant sa configuration ;
  - **supprimer une ligne de niveau (z)** — refusée tant qu'une entité occupe le niveau.
- **Tuiles & images** : inventaire par couche avec renommage propagé aux cartes, déplacement entre couches, suppression gardée, et liste des positions de chaque tuile.
- **Bâtiments** : page dédiée — pose (avec coordonnée Z), restauration, retrait ; bouton de migration des anciens objets « structure » vers le système d'entités.

## À savoir en jeu (côté animation)

- **Les bâtiments sont des entités** (lignes `players` de type building) : PV, combat, portes, dialogues portés. Les murs posés par les joueurs ne sont plus des `map_walls`.
- **Le tour est un moteur extrait et relisible** : le récap écrit dans les événements fait foi (XP, PI, usure…).
- **Consommation d'objet** : le détail des effets part en `hiddenText` des événements (visible du seul consommateur) ; les effets cachés (poison) restent muets.
- **L'artisanat est en sommeil** derrière la constante `CRAFT_ENABLED` (route et code intacts) — il reviendra porté par un bâtiment.
