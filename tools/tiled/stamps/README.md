# Tampons (stamps) partagés

Bibliothèque de structures préfabriquées pour l'éditeur Tiled, partagée
via git : bâtiments, formations, décors récurrents à poser en un geste.

## Utiliser cette bibliothèque

Tiled range les tampons dans un dossier global (préférence, pas par projet) :

1. Édition → Préférences → onglet **Général** (ou **Général/Interface**
   selon la version) → **Dossier des tampons** (« Stamps directory »).
2. Pointer ce dossier sur `tools/tiled/aoo/../stamps` du dépôt, c'est-à-dire
   ce répertoire : `<dépôt>/tools/tiled/stamps`.

Les tampons enregistrés (panneau **Tampons** / *Tile Stamps*) atterrissent
alors ici et sont versionnés — tous les admins les partagent.

## Créer un tampon

1. Sélectionner une ou plusieurs couches dans le panneau Calques
   (Ctrl+clic pour plusieurs — sol + murs + décor d'un coup).
2. Outil tampon (**B**), **clic droit + glisser** sur la carte pour capturer
   la zone.
3. Panneau **Tampons** → enregistrer ; le `.stamp` apparaît ici.

Les tampons capturent les tuiles des tilesets `aoo-*` : ils se re-posent tels
quels sur n'importe quel plan pullé (mêmes tilesets reconstruits au pull).
