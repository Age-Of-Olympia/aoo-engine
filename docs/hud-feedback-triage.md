# HUD feedback triage — retours joueurs (Google Doc, juillet 2026)

Source : doc "bugs nouvelle interface" (14vr0h8hBfTBdEyyhK14MeOBRAorFiupTQ0Y8xLk8rAA).
Branche de travail : `feat/hud-paper-panels` → merge dans `integration/hud-redesign` pour déploiement.

Légende taille : XS (< 30 min), S (heure), M (demi-journée), L (journée+).

## Lot 1 — Quick wins (styling / petits fixes)

| # | Item | Cause identifiée | Fix proposé | Taille |
|---|------|------------------|-------------|--------|
| 1.1 | Bulle d'aide équipement (HTML brut) | `src/View/EquipmentSlotsView.php:57-60` : `$caracs` non passé par `strip_tags` avant `htmlspecialchars` | `strip_tags($caracs)` avant concaténation | XS |
| 1.2 | « Points de réputation » illisible | `scripts/infos/reputation.php:15-37` : gradient or + `color:transparent` conçu pour fond sombre, illisible sur papier | Restyler pour le thème papier (encre foncée, accent doré discret) | S |
| 1.3 | Barre d'XP pas claire | `load_upgrades.php:35-42` + `css/hud.css:1496-1505` : pas de cadre visible | Ajouter bordure/cadre à `.hud-xp-progress .progress-bar` | XS |
| 1.4 | Classement : re-clic sur onglet actif ferme le panneau | `js/hud.js:1204-1211` `togglePanel` : même URL → fermeture | Si le clic vient d'un lien *dans* le panneau (onglets), recharger au lieu de fermer ; garder le toggle pour les entrées du rail | S |
| 1.5 | « Tout marquer comme lu » absent | Bouton seulement dans `LastPostsView.php:37` ; absent de `ForumHomeView` | Ajouter le bouton sur l'accueil forum du panneau (POST `api/forum/markAllAsRead.php` + refresh badges) | S |
| 1.6 | Onglet Quêtes | Pas d'entrée rail dédiée ; quêtes visibles via `logs.php?light` | Masquer la section quêtes de la page événements (décision : garder cachée jusqu'à nouvel ordre) | XS |
| 1.7 | Matricule absent de la fiche | `src/View/InfosSheetView.php:119-131` : pas de matricule | Ajouter `mat. N` discret sur la fiche personnage | XS |

## Lot 2 — Escapes simples vers l'ancienne interface (extension du routeur de panneau)

Le routeur est la whitelist `panelUrl()` dans `js/hud.js:958-1005`. Chaque item = mapper l'URL vers un fragment `load_*.php` + adapter la vue en mode fragment + remplacer les `document.location` par un refresh de panneau.

| # | Item | Point d'évasion | Fix proposé | Taille |
|---|------|-----------------|-------------|--------|
| 2.1 | Oublier un sort/comp | `js/forget_spells.js` : POST puis `document.location.reload()` | POST en AJAX puis recharger le contenu du panneau (`load_upgrades.php?spells`) | S |
| 2.2 | Équiper/déséquiper ferme la fenêtre | `js/inventory.js:129-146` : `document.location.reload()` | POST AJAX + re-render du panneau inventaire ; MAJ pills top-bar | M |
| 2.3 | + popup de confirmation équiper/déséquiper | Aucune confirmation actuellement | Dialog « Équiper [gladius] en [main1] (1 Ae) ? » / « Déséquiper [gladius] ? » | S |
| 2.4 | + icônes équiper ≠ consommer | `js/inventUi.js:27-71` : même bouton `use` | Icône/libellé distinct équiper vs utiliser/consommer | S |
| 2.5 | Artisanat sort de l'interface | `js/inventory.js:62,68` : `document.location = inventory.php…` après construction | Rester dans le panneau craft, refresh AJAX | S |
| 2.6 | Changer de mdp | `account.php?changePsw` hors whitelist (`AccountView.php:77`) | Mapper `?changePsw` (et `?changeMail`) dans `load_account.php` + formulaire en fragment | S |
| 2.7 | Portrait / avatar | `account.php?portraits|avatars` hors whitelist | Mapper en fragments panneau (galerie dans le panneau) | M |
| 2.8 | mdj / histoire depuis paramètres | `account.php?mdj|story` hors whitelist | **Proposition du doc retenue** : masquer du panneau Profil (mdj déjà géré dans le chat) ; déplacer « modifier son histoire » sur la fiche perso (en fragment) | S |
| 2.9 | Répondre à un message (forum/missives) | `js/forum_reply.js` : POST puis `document.location = forum.php?topic=…` | Formulaire de réponse dans le panneau topic + refresh du fil en AJAX ; idem liens `?edit=` | M |
| 2.10 | + bouton « pleine page » | Demande du doc | Ajouter sur les panneaux forum (et pnjs) un bouton ouvrant la vraie page complète | S |
| 2.11 | Collection de récompenses | Exclusion volontaire (`js/hud.js:963`) | Décision requise : garder pleine page (avec ouverture propre) ou paneliser | S–M |

## Lot 3 — Visibilité PV / dégâts / effets

| # | Item | État actuel | Fix proposé | Taille |
|---|------|-------------|-------------|--------|
| 3.1 | Attaque pas visible à la connexion | Pill PV neutre (`TopBarView.php:57-63`), pas d'état « endommagé » | PV en rouge (style `hud-pill--warn`) dès que PV < max ; option : mini-barre de dégâts dans la pill | S |
| 3.2 | Effets (adrénaline, eau, feu…) visibles dès la connexion | Effets seulement sur la fiche (`InfosSheetView.php:62-101`) | Afficher les icônes d'effets actifs dans la top-bar à côté des malus | M |
| 3.3 | Dégâts absents de la fiche | `InfosSheetView` sans PV ni voile de sang | Réutiliser `.hud-pv-lost` (voile) sur le portrait de la fiche + afficher PV | S |
| 3.4 | Dégâts peu visibles sur mobile | Voile réduit avec l'image | Renforcer le contraste / min-height du voile sur petits écrans | S |

## Lot 4 — Rafraîchissement & mobile

| # | Item | État actuel | Fix proposé | Taille |
|---|------|-------------|-------------|--------|
| 4.1 | Refresh mdj ne met pas à jour les persos | `#hud-feed-refresh` ne recharge que le feed (`js/hud.js:1655`) ; le mdj affiché ailleurs (observation/fiche) reste stale | Identifier les autres surfaces affichant le mdj et les rafraîchir aussi (ou invalider au refresh) | S |
| 4.2 | Pas de pull-to-refresh mobile | `body:has(#hud){overflow:hidden}` (`css/hud.css:67-76`) supprime le geste | Bouton « rafraîchir » explicite dans le drawer mobile (F5 forcé) ; pull-to-refresh custom = risqué avec le board | S |

## Lot 5 — Gros morceaux (décisions / design requis)

| # | Item | État actuel | Options | Taille |
|---|------|-------------|---------|--------|
| 5.1 | Marchands / instructeurs / worship | `observe.php:374,379,543` → `merchant.php`, `warschool.php`, `worship.php` pleine page | A) Paneliser (fragments `load_merchant.php` etc.) — gros chantier ; B) Garder pleine page mais avec retour propre vers le HUD | L |
| 5.2 | Icônes menu : PNG → icônes plates | `css/hud.css:293-330` PNG paper ; craft/banque déjà en rpg-awesome | Basculer les 6 entrées PNG vers des glyphes rpg-awesome (comme `ra-forging`) | S |
| 5.3 | Ergonomie fenêtre événements (ouvre à gauche) | `logs.php?light` passe par le panneau standard gauche | Décision : statu quo, ou ouvrir la page complète des événements côté droit / pleine page | ? |

## Décisions à prendre (utilisateur)

1. **Marchands (5.1)** : paneliser ou garder pleine page ? (impacte fortement le planning)
2. **Récompenses (2.11)** : pleine page assumée ou panneau ?
3. **Icônes (5.2)** : ok pour abandonner les PNG papier au profit de rpg-awesome partout ?
4. **PV rouges (3.1)** : simple texte rouge sous 100 %, ou vraie barre de dégâts dans la top-bar ?
5. **Événements (5.3)** : le panneau gauche convient-il finalement ?
