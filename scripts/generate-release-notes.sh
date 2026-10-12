LAST_TAG=${CI_COMMIT_TAG:-$(git tag | sort -V | tail -1)}
PREV_TAG=$(git tag | sort -V | grep -x -B1 "$LAST_TAG" | head -1)

RELEASE_NOTES=$(git --no-pager log --no-merges $PREV_TAG..$LAST_TAG --pretty=format:"- %s")

echo "Release Notes for $LAST_TAG:"
echo "$RELEASE_NOTES"
