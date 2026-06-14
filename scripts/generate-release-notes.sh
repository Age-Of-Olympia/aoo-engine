#!/bin/bash
set -euo pipefail

# Récupérer le dernier tag et le tag précédent (tri sémantique)
LAST_TAG=$(git tag --sort=-v:refname | head -1)
PREV_TAG=$(git tag --sort=-v:refname | head -2 | tail -1)

# Générer les release notes (commits du tag précédent jusqu'au dernier tag)
RELEASE_NOTES=$(git --no-pager log "${PREV_TAG}..${LAST_TAG}" --no-merges --pretty=format:"- %s")

# Garde-fou : un job vert ne doit jamais produire des notes vides.
# Cela arrive typiquement quand le clone est superficiel (git depth) et que le
# tag précédent n'est pas atteignable -> définir GIT_DEPTH: 0 sur le job.
if [ -z "$RELEASE_NOTES" ]; then
    echo "ERROR: no commits found between '${PREV_TAG}' and '${LAST_TAG}'." >&2
    echo "Is the clone shallow? Ensure the previous tag is reachable (GIT_DEPTH: 0)." >&2
    exit 1
fi

# Afficher les release notes
echo "Release Notes for $LAST_TAG:"
echo "$RELEASE_NOTES"
