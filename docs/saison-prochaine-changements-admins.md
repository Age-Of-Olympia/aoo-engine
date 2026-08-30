# La saison prochaine — ce qui change pour les admins et animateurs

L'esprit général : **le contenu du jeu passe des fichiers JSON à la base**, édité par des pages d'admin cohérentes : plus besoin de toucher aux fichiers ni à la base à la main.

## Le tableau de bord refondu

- **Menu en quatre sections** : Le monde (Cartes, Bâtiments, Décors, Ressources, Plantes, Éléments, Dialogues), Les règles (Races, Objets, Actions, Factions), Les joueurs (Personnages, Contrôle d'accès), Outils (Tutoriel, Wiki, Crons). Les groupes se replient, et il n'y a plus qu'une seule barre de défilement. Le lien « Retour au jeu » reste épinglé en bas.
- **Composants partagés partout** : mêmes tableaux (recherche + pagination), mêmes formulaires, mêmes messages flash — chaque page se comporte pareil.
- **Contrôle d'accès par page** : chaque entrée du menu a un niveau (admin / superadmin), ajustable depuis la page Contrôle d'accès. Les droits sont validés sur l'humain connecté, pas sur le personnage incarné.
- **Les réglages du monde sont sur le tableau de bord** : plan principal, plan des morts, saison courante, intensité d'ombre par défaut.
- **Page Crons** : la liste des tâches planifiées et un bouton pour en rejouer une à la main, sans attendre l'heure.
- **Page Wiki** : les fiches DokuWiki se génèrent depuis le jeu — actions, passifs, effets, objets, races et types de bâtiments. C'est la généralisation demandée : plus rien à recopier à la main.

## Les catalogues passent en base

- **Effets** : le catalogue complet (icônes, comportements — postures, vol, coûts, blocages) vit en base et s'édite sur la page Effets. Plus aucun comportement codé « par nom » ; export/import par bundles JSON. Les durées s'écrivent maintenant en tours, avec une écriture pour l'effet sans fin ; les libellés indiquent quand une valeur multiplie au lieu d'ajouter ; le sélecteur d'icône est le même que celui des actions.
- **Objets** : page Objets complète (stats migrées du JSON, flags, durabilité max par objet), création d'objets, bundles export/import objets + recettes. Les consommables s'éditent au catalogue. La fiche est découpée en sections, avec sélecteur de type, graine, signalement des incohérences et badge « hors type ». S'y ajoutent le drapeau Magique, le type « objet de quête », la liste des détenteurs, l'import d'images, la suppression, une section contenance, et la durée des effets que l'objet applique.
- **Recettes d'artisanat** : panneau d'édition dédié dans le groupe Objets, avec deux niveaux de recettes : la recette de base, et celle qui nomme l'atelier où elle se fabrique.
- **Races** : page de gestion (caractéristiques, actions autocomplétées, flag « cachée »), et un rééquilibrage des caractéristiques.
- **Factions** : entités en base (rôles compris), pages Liste / Membres, export/import JSON. La faction de départ d'une race devient un select validé sur ce catalogue.
- **Dialogues** : liste et édition ; un dialogue déclare sa nature et sa portée.
- **Page d'accueil** : sections éditoriales, chroniques et galerie s'éditent depuis l'admin ; le message du jour se change depuis le HUD. Le portail met « Jouer » en avant et ouvre les CGU.

## Actions : le workbench

- **Configuration complète d'une action** on avait : conditions, outcomes, instructions, paramètres typés, simulation de résolution. On gagne un "contexte" : si on affiche le bouton d'action ou non, typiqueemnt le bouton pour parler à un bâtiment ne doit s'afficher que collé à lui.
- **Nouveautés du catalogue** : condition TargetType (cible personnage ou structure), condition RequiresItem, instructions PlaceStructure et PlaceLayer (routes), contexte d'affichage des boutons, défauts par type, import/export.
- **La catégorie d'une action s'édite** désormais au workbench (datalist des catégories en usage) : plus figée à la création.
- **Le type d'une action se rebascule** depuis le workbench : une action créée dans la mauvaise famille se corrige sans passer par la base.
- **Une condition dit si elle bloque** la tentative ou si elle la laisse échouer, et une condition neuve naît bloquante.
- **Workbench des passifs** à côté de celui des actions, et les pré-requis des actions et passifs existants sont renseignés.
- **Défauts par type** : préconditions, instructions, XP et journal se règlent une fois pour tout un type d'action.
- **Réparer ne s'applique qu'à ce qui se répare**, c'est le type qui le dit ; un objet brisé ne se répare plus.
- **Le déclencheur de téléportation accepte une condition d'accès.**

## Compétences, sorts et caractéristiques

- **La M se scinde en deux caractéristiques** : Puissance (les dégâts magiques) et Résistance (ce qui les encaisse). Races, objets, effets, actions et passifs ont été rebasculés, les améliorations en M ont été supprimées et les Pi dépensés dessus ont été remboursés.
- **Un objet marqué Magique frappe en Pui** et applique les effets qu'il porte.
- **Rééquilibrage** des caractéristiques de races, du saut d'attaque et de Puissance de la nature.
- **Compétences, sorts et passifs portent des pré-requis** : un arbre par défaut, plus des pré-requis explicites (ce qu'il faut avoir, ce qui est incompatible), vérifiés à l'achat. Le bouton d'achat de l'école de guerre reflète l'éligibilité. Attention : ces pré-requis ne s'éditent pas encore depuis l'admin, ils se posent en migration.
- **Les sorts sont limités par des emplacements**, ouverts par des passifs dédiés — ces passifs restent à créer au catalogue.
- **Niveaux maximum** : 4 pour les compétences, 5 pour les sorts.
- **Page de réassignation** à l'école de guerre : l'onglet est toujours visible, la présence sur place est exigée, et un rang se rachète au prix du rang.

## Personnages

- **Fiche d'édition d'un personnage** depuis la liste : renommer, téléporter (plan validé), régler les vitalités restantes (PV, PM, MVT, A, Ae), rendre le tour disponible. Pensé pour l'animation : blesser, soigner, déplacer sans une requête SQLet plus intuitivement que via la console.
- **Effets portés** : on pose et on lève un effet depuis la fiche, et on en règle la durée et la valeur.
- **Progression** : XP, PI et rangs se gèrent depuis la fiche, les PNJ décorrélés.
- **L'inventaire s'édite** sur la fiche.
- **La fiche garde sa position d'écran** après chaque action.
- **La liste des personnages** se filtre par nom/matricule, type (joueur/PNJ), statut et désormais par race.
- **Avatars & portraits** : inventaire par type et par race avec diagnostics (dimensions hors canon, miniature absente, fichier disparu), upload redimensionné à l'upload, suppression gardée tant que l'image est utilisée, aperçu en taille réelle d'un clic, liste des joueurs utilisant chaque image, et déplacement d'un avatar vers une autre race.
- **Les vieilles requêtes SQL de dépannage ne marchent plus** : le compte, le tour et la progression ne vivent plus sur la ligne du personnage. Tout se fait depuis la fiche.

## Cartes et plans

- **Page Plans** : création (vierge ou clonage — le clonage emporte les bâtiments), configuration par cartes thématiques, niveaux Z, validation du JSON, export bundle restaurable. La configuration ne vit plus dans des fichiers.
- **Saisons** : une colonne saison sur les plans, une saison courante réglable, et une page de renommage qui retire le suffixe de saison des plans en cours en le laissant aux archives.
- **Le plan principal et le plan des morts se choisissent dans l'admin**, ils ne sont plus en dur.
- **Un niveau de plan peut interdire la pose des coffres.**
- **Les ombres sont une intensité de case** : le défaut se règle au tableau de bord, chaque plan peut le dévier.
- **Opérations lourdes sous double validation** — confirm() ET le code du plan sur lequel on travaille, vérifié côté serveur :
  - **supprimer un plan** (bilan préalable : joueurs bloquent toujours, PNJ/logs exigent le forçage) ;
  - **renommer un plan** : coords, respawn des factions, plan des races, catalogue tutoriel, téléporteurs entrants et fichiers suivent : pas de lien cassé ;
  - **vider les cases d'un plan** en gardant sa configuration ;
  - **supprimer une ligne de niveau (z)** — refusée tant qu'une entité occupe le niveau.
- **Tuiles & images** : inventaire par couche avec renommage propagé aux cartes, déplacement entre couches, suppression gardée, et liste des positions de chaque tuile. La palette de couleurs de carte s'édite en admin, avec la vignette de la tuile en regard. Transitions de terrain et captures d'écran ont leur page.
- **Bâtiments** : page Posés (pose avec coordonnée Z, restauration, retrait), page Types, page Images (vignettes des races et des types, stock d'images, sprite hérité copiable dans le stock), et une reprise des dialogues de case hérités.
- **Décors** : deux pages neuves, Types et Formes, où la découpe et le passage se règlent en voyant le décor.
- **Ressources** : pages Types récoltables et Rendements. Le rendement est porté par le type, le plan ne fait que le dévier, et une dérogation ne dit que ce qu'elle change. Une ressource épuisée reste sur la carte et porte son état. Ce qu'un type rend se choisit dans une liste, ne se tape plus.
- **Plantes** : leur propre groupe de types, séparé des ressources.
- **Éléments** : catalogue des types d'éléments avec ce qu'ils appliquent, page Posés, et un flag « constructible par-dessus ». Les durées s'écrivent en tours.

## L'éditeur de cartes (Tiled)

- **L'extension a son propre dépôt** et se livre en zip versionné ; une extension trop vieille pour le serveur le dit, au lieu de casser en silence.
- **Images et terrains viennent du serveur** : plus de dossier d'assets à recopier à la main.
- **Un décor se pose, s'inspecte et se retire comme un seul objet** : le curseur montre la figure entière avant la pose, et la palette n'offre que des objets entiers. L'emprise suit le pinceau.
- **La gomme peut passer outre la protection**, en le disant.
- **Les couches de ressources et de plantes suivent la conversion en entités** ; l'import compare au lieu de remplacer.
- **Les grosses cartes s'envoient sans échouer**, et une erreur serveur revient lisible au lieu de se perdre.
- **Composeur de bâtiments** : formes, façades, toits et enseignes assemblés hors du jeu, pour donner des planches au graphiste.
- **Un joueur ne bâtit pas au travers d'un décor, un animateur si.**

## Bâtiments, chantiers et factions

- **Les bâtiments sont des entités** : PV, combat, portes, dialogues portés. Les murs posés par les joueurs ne sont plus des murs de carte.
- **Un édifice prend de la place** : 2×2 par défaut, réglable au type. La vue, la ligne de tir, l'observation et la sélection lisent toute l'emprise, plus seulement sa case d'ancrage.
- **Chaque type porte son comportement** de construction et de destruction, et le travail de construction se règle au type : construire ouvre un chantier, travailler l'achève, et l'avancement se lit au tableau de bord. Un chantier laisse passer les flèches, pas les gens.
- **L'échoppe, la banque et l'école de guerre sont devenues des bâtiments** et ouvrent leurs comptoirs sur la carte.
- **Coffres et serrures** : un coffre connaît sa contenance en lignes, sa serrure se tourne au pied de la chose, l'écran du contenant a deux volets, et un coffre brisé répand son butin avant de s'effacer. Poser un coffre demande une banque sur le plan.
- **Portes** : la porte en bois est au catalogue et se construit ; ouverte elle laisse passer la flèche, fermée elle barre le passage.
- **Autels** : ce sont des entités ; « consacrer » et « vénérer » sont au catalogue d'actions, et le classement de foi lit les autels et le contrôle des plans.
- **Factions** : une échelle de rangs qui fait la hiérarchie, un rang à deux noms, le sommet qui règle la structure et peut nommer un égal, la gestion des membres qui suit les rangs, un journal de la maison avec ses lecteurs, et le panneau qui montre les murs de la faction. Les rangs gouvernent les biens.
- **Bâtiments jouables** : un type déclaré jouable prend ses tours et progresse ; on prend les commandes depuis le panneau de faction, et on en sort par un bouton standard en haut à droite.
- **L'inscription d'un bâtiment est son message du jour** : un bâtiment neuf se tait tant qu'on ne lui a rien écrit.

## À savoir en jeu (côté animation)

- **Le tour est un moteur extrait et relisible** : le récap écrit dans les événements fait foi (XP, PI, usure…). La page « Nouveau Tour » passe sur la feuille d'annonce, et un personnage tué voit un écran « Vous êtes mort » à la connexion.
- **Consommation d'objet** : le détail des effets n'est visible que du consommateur ; les effets cachés (poison) restent muets.
- **L'artisanat est de retour** : l'interface est revenue, la fabrication passe par le moteur d'actions, et l'atelier est un bâtiment du monde.
- **L'usure de l'équipement** : frapper, encaisser et mourir usent ce qu'on porte, et le récap de tour le dit.
- **Le damier s'édite depuis le HUD** et se redessine sans rechargement. Le clic droit du damier est réservé aux admins, et les coordonnées se copient au format de la console.
- **Plus rien à purger ni à relancer à la main** après un déploiement ou un reseed, côté carte et données de personnage.
- **Balayage quotidien des instances de tutoriel à l'abandon.**
- **Correctif de session** : la réponse d'un joueur n'est plus servie à un autre.

## En cours, pas encore livré

- **La décrépitude des constructions** : les bâtiments s'entretiennent en servant, les routes en étant empruntées, les murs par la seule réparation ; ce que l'éditeur a posé ne se dégrade pas ; la décrépitude se joue au tour, pas en passe nocturne. Les décisions sont prises, rien n'est codé.
- **Les nouvelles compétences, sorts et passifs de la saison 3** : le lot est écrit mais pas encore fusionné.
- **Reste de chantier** : les dernières ressources et le décor encore hors du système d'entités, dont le compte se lit au tableau de bord.
