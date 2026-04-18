echo "\nsource /usr/share/bash-completion/completions/git" >> ~/.bashrc
./scripts/composer-setup.sh
mkdir -p /var/www/html/bin; mv composer.phar /var/www/html/bin/composer
composer install
composer dump-autoload
npm ci

# Install glab (GitLab CLI) to ~/.local/bin if missing — pin to a known version
GLAB_VERSION="1.92.1"
if ! command -v glab >/dev/null 2>&1; then
    echo "Installing glab ${GLAB_VERSION}..."
    mkdir -p ~/.local/bin /tmp/glab-install
    if curl -sL --fail \
        -o /tmp/glab-install/glab.tar.gz \
        "https://gitlab.com/gitlab-org/cli/-/releases/v${GLAB_VERSION}/downloads/glab_${GLAB_VERSION}_linux_amd64.tar.gz" \
        && tar -xzf /tmp/glab-install/glab.tar.gz -C /tmp/glab-install \
        && install -m 0755 /tmp/glab-install/bin/glab ~/.local/bin/glab; then
        echo "glab $(~/.local/bin/glab --version)"
    else
        echo "Warning: glab install failed (non-fatal — see https://gitlab.com/gitlab-org/cli for manual install)"
    fi
    rm -rf /tmp/glab-install
fi