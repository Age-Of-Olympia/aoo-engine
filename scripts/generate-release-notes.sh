#!/bin/bash

# Récupérer le dernier tag et le tag précédent
LAST_TAG=$(git tag --sort=-committerdate | tail -1)
PREV_TAG=$(git tag --sort=-committerdate | tail -2 | head -1)

# Générer les release notes
RELEASE_NOTES=$(git log $PREV_TAG...$LAST_TAG --pretty=format:"- %s")

# Afficher les release notes
echo "Release Notes for $LAST_TAG:"
echo "$RELEASE_NOTES"