# Tampons (stamps) partagés

Bibliothèque de structures préfabriquées pour l'éditeur Tiled, partagée
via git : bâtiments, formations, décors récurrents à poser en un geste.

## Utiliser cette bibliothèque

1. Ouvrir le panneau **Tampons** : menu **Affichage → Vues et barres
   d'outils → Tampons** (*View → Views and Toolbars → Tile Stamps*).
2. Dans ce panneau, bouton **« Définir le dossier des tampons »**
   (*Set Stamps Folder*) → pointer sur ce répertoire du dépôt :
   `<dépôt>/tools/tiled/stamps`.

Les tampons enregistrés atterrissent alors ici et sont versionnés — tous les
admins les partagent.

## Créer un tampon

1. Sélectionner une ou plusieurs couches dans le panneau Calques
   (Ctrl+clic pour plusieurs — sol + murs + décor d'un coup).
2. Outil tampon (**B**), **clic droit + glisser** sur la carte pour capturer
   la zone.
3. Panneau **Tampons** → enregistrer ; le `.stamp` apparaît ici.

Les tampons capturent les tuiles des tilesets `aoo-*` : ils se re-posent tels
quels sur n'importe quel plan pullé (mêmes tilesets reconstruits au pull).
