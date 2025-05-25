#!/bin/bash

# Récupérer le dernier tag et le tag précédent
LAST_TAG=$(git tag --sort=-committerdate | sort -V | tail -1)
PREV_TAG=$(git tag --sort=-committerdate | sort -V | tail -2 | head -1)

# Générer les release notes
RELEASE_NOTES=$(git --no-pager log $PREV_TAG...$LAST_TAG --pretty=format:"- %s")

# Afficher les release notes
echo "Release Notes for $LAST_TAG:"
echo "$RELEASE_NOTES"