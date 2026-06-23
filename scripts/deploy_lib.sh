#!/bin/bash
#
# Shared guards for the deploy scripts. SOURCED, not executed.
#
# admin/deploy.php exports DOCROOT, SRC and EXPECT_BRANCH before running each
# deploy_*.sh. These helpers fail closed (copy nothing) if anything about the
# target environment looks wrong — that is what makes a single account safe now
# that prod, test and experimental share one Linux home.

aoo_die() { echo "DEPLOY ABORT: $*" >&2; exit 1; }

# DOCROOT = the docroot of the calling subdomain (deploy target).
# SRC     = the per-env source checkout dir (~/deploy/<env>).
aoo_assert_env() {
    [ -n "${DOCROOT:-}" ] || aoo_die "DOCROOT not set"
    [ -n "${SRC:-}" ]     || aoo_die "SRC not set"
    [ -d "$DOCROOT" ]     || aoo_die "DOCROOT '$DOCROOT' is not a directory"
    [ -d "$SRC" ]         || aoo_die "source dir '$SRC' missing"
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
