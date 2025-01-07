#!/bin/bash

# Redimensionner tous les fichiers .jpeg et .jpg dans le répertoire courant, sauf ceux déjà suffixés par _mini
for file in *.jpeg *.jpg; do
    if [[ -f "$file" && "$file" != *_mini.jpeg ]]; then
        # Extraire le nom de fichier sans extension
        filename="${file%.*}"
        # Redimensionner l'image et l'enregistrer avec le suffixe _mini.jpeg
        convert "$file" -resize 50x "${filename}_mini.jpeg"
        echo "Redimensionné et enregistré: ${filename}_mini.jpeg"
    fi
done

echo "Redimensionnement terminé."
