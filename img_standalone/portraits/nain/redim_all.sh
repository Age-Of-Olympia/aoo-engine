#!/bin/bash

# Répertoire contenant les images
directory="."

# Taille cible
target_width=210
target_height=330

# Parcourir tous les fichiers .jpeg dans le répertoire
for file in "$directory"/*.jpeg; do
    if [ -f "$file" ]; then
        # Obtenir la largeur et la hauteur de l'image
        dimensions=$(identify -format "%wx%h" "$file")
        width=$(echo $dimensions | cut -d'x' -f1)
        height=$(echo $dimensions | cut -d'x' -f2)

        # Vérifier si l'image est plus grande que les dimensions cibles
        if [ "$width" -gt "$target_width" ] || [ "$height" -gt "$target_height" ]; then
            # Redimensionner l'image
            convert "$file" -resize ${target_width}x${target_height}\> "$file"
            echo "Redimensionné: $file"
        else
            echo "Aucune redimension nécessaire: $file"
        fi
    fi
done

echo "Redimensionnement terminé."

