# Conventions de code & glossaire

Décisions d'équipe (revue du 2026-07-18) — la référence quand un choix
de nommage ou de structure se pose. Complète CLAUDE.md (qui reste la
référence outillage/process).

## Langue

- **Identifiants en ANGLAIS** : classes, méthodes, colonnes, clés de
  config (`NpcAdminService`, `WearService`, `display_context`…).
- **Chaînes visibles en FRANÇAIS** : UI du jeu, admin, messages
  d'erreur joueurs, flashs.
- Les commentaires/docblocks peuvent être en français (le pourquoi
  s'adresse à l'équipe) — l'anglais hérité n'est pas retraduit.
- `wear` = **usure** (wear and tear), c'est le terme anglais correct ;
  ne pas renommer en « usage » — `usage` est déjà l'un des
  déclencheurs d'usure (utilisation).

## Vocabulaire entités

- La table `players` contient TOUTES les entités du monde (personnages,
  PNJ, bâtiments, objets uniques) — nom hérité, on ne la renomme pas.
- Le code NEUF dit **entity** (`GameEntity`, `PlayerFactory::
  gameEntity`, `getNextEntityId`…) ; le legacy garde `Player` jusqu'à
  son remplacement progressif (strangler). Renommer opportunément les
  variables trompeuses quand on touche au code, pas de big-bang.

## Sources uniques (DRY)

- **Caracs** : `App\Enum\Caracs::KEYS` — jamais de liste littérale
  `['a', 'mvt', …]`. La constante globale `CARACS` garde les libellés
  UI ; l'égalité des clés est épinglée par
  `tests/Various/CaracsSingleSourceTest`.
- **Types d'objets câblés** : `Classes\Item::TYPE_CONSTRUCTIBLE` /
  `TYPE_STRUCTURE`.
- **Catégories d'entités** : `App\Enum\EntityCategory`
  (character/structure) — tout `player_type` se mappe par
  `fromPlayerType()`, jamais de comparaison littérale éparpillée.
- **Règle d'ouverture d'un bâtiment** :
  `BuildingService::closureReason()` — unique.

## Pages d'admin

- Pattern : la page **rend** (GET), son compagnon `-save.php` **mute**
  (POST, CSRF, PRG + flash), accès via `layout.php`
  (AdminMenuAccessService).
- HTML : passer par les composants de `admin/helpers.php` —
  `renderSelectOptions()` / `formSelect()` / `formField()` /
  `renderTable()`… — plutôt que concaténer le même markup dans chaque
  page. Faire GROSSIR cette bibliothèque au besoin ; on ne prendra un
  moteur de templates que si elle ne suffit plus.
- Toute donnée échappée par `e()` (ENT_QUOTES) ; texte libre dans un
  `onclick`/`onsubmit` : passer par `json_encode` (une apostrophe casse
  la garde sinon).
- Toute entité de configuration a le cycle complet : liste + édition +
  **création** + export/import de bundles JSON (registres ImportExport,
  clé naturelle `name`).

## Glossaire

| Terme | Sens |
|---|---|
| **Test étalon** (golden master) | Photographie du comportement existant, figée par un test AVANT de refactorer : le refactoring doit laisser le comportement identique au bit près. Groupe PHPUnit `entities-golden-master`. |
| **Strangler** | Remplacement progressif d'un god class (Classes/Player) : le neuf pousse autour du vieux, méthode par méthode, jusqu'à l'étouffer — jamais de réécriture big-bang. |
| **STI** | Single Table Inheritance : une table (`players`), un discriminateur (`player_type`), plusieurs classes (Character/Structure…). |
| **Satellite** | Table 1:1 qui complète une ligne `players` pour un type d'entité (`buildings`, `unique_objects`) — le « component pattern ». |
| **Pseudo-race** | Ligne `races` non jouable portant les stats de base d'un type de structure (PV max d'une palissade). |
| **Bundle** | Export/import JSON d'une entité de configuration (race, faction, dialogue, objet, recette…) par clé naturelle `name` — le véhicule des données entre environnements. |
| **Seed** | Migration ou page admin qui recopie les JSON legacy vers la base (une fois) — la base devient la source de vérité. |
| **Bourse** | Présentation « posé au sol » d'un objet (sprite de loot, ramassé en marchant) — l'un des deux états-monde, l'autre étant « construit » (entité). |
| **Instance** | Ligne `item_instances` : un exemplaire individualisé d'un objet (usure, nom, créateur) — né par promotion paresseuse depuis la pile. |
| **Édifice / Obstacle** | Natures d'un type de structure (`races.structure_nature`) : l'édifice a une porte (Ouvert/Fermé, dialogue) ; l'obstacle est un mur construit (is_open = future passabilité). |
| **Contexte d'affichage** | `action_conditions.display_context` : condition évaluée AU RENDU — le bouton d'action n'apparaît que si elle passe. |
