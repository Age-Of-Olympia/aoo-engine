#!/bin/bash
#
# Shared guards for the deploy scripts. SOURCED, not executed.
#
# admin/deploy.php exports DOCROOT, SRC and EXPECT_BRANCH before running each
# deploy_*.sh. These helpers fail closed (copy nothing) if anything about the
# target environment looks wrong — that is what makes a single account safe now
# that prod, test and experimental share one Linux home.

aoo_die() { echo "DEPLOY ABORT: $*" >&2; exit 1; }

# Composer binary. Prefer one on PATH; fall back to ~/bin/composer (where the
# o2switch prod account historically keeps it). Override by exporting COMPOSER.
: "${COMPOSER:=$(command -v composer || echo "$HOME/bin/composer")}"

# DOCROOT = the docroot of the calling subdomain (deploy target).
# SRC     = the per-env source checkout dir (~/deploy/<env>).
aoo_assert_env() {
    [ -n "${DOCROOT:-}" ] || aoo_die "DOCROOT not set"
    [ -n "${SRC:-}" ]     || aoo_die "SRC not set"
    [ -d "$DOCROOT" ]     || aoo_die "DOCROOT '$DOCROOT' is not a directory"
    [ -d "$SRC" ]         || aoo_die "source dir '$SRC' missing"
}

# Bring the engine checkout to the right code before deploying. When the caller
# requested a specific branch (CHECKOUT_BRANCH, e.g. the experimental env
# tracking a configurable branch) switch to it; otherwise just pull the branch
# the checkout is already on. Run this BEFORE aoo_assert_branch.
aoo_update_checkout() {
    cd "$SRC/aoo-engine" || aoo_die "cannot enter '$SRC/aoo-engine'"
    local branch="${CHECKOUT_BRANCH:-}"
    [ -n "$branch" ] || branch="$(git rev-parse --abbrev-ref HEAD)"

    # Detached HEAD (prod deploying a tag): deploy exactly what is checked out,
    # don't touch the remote.
    if [ "$branch" = "HEAD" ]; then
        git log --oneline -1
        return 0
    fi

    # ONE remote read, then fast-forward locally. Doing a fetch immediately
    # followed by a second network op (git pull also fetches) tripped GitLab's
    # SSH connection limit on the shared host — a single fetch + local merge
    # avoids the second connection.
    git fetch origin "$branch" || aoo_die "fetch of '$branch' failed"
    git checkout "$branch" || aoo_die "checkout of '$branch' failed"
    git merge --ff-only FETCH_HEAD || aoo_die "fast-forward of '$branch' failed"
    git log --oneline -1
}

# Refuse to deploy unless the engine checkout is on the branch this env expects.
# This is the guard that prevents cross-env clobbering: a test-env deploy can
# never copy a 'main' checkout into the test docroot, and vice versa.
# EXPECT_BRANCH empty (prod, which deploys from a tag/detached HEAD) skips it.
aoo_assert_branch() {
    [ -d "$SRC/aoo-engine" ] || aoo_die "engine checkout '$SRC/aoo-engine' missing"
    [ -n "${EXPECT_BRANCH:-}" ] || return 0
    local actual
    actual="$(git -C "$SRC/aoo-engine" rev-parse --abbrev-ref HEAD 2>/dev/null)"
    [ "$actual" = "$EXPECT_BRANCH" ] || \
        aoo_die "branch mismatch: checkout on '$actual', this env expects '$EXPECT_BRANCH'"
}
