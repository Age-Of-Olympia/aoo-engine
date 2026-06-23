<?php

/**
 * Host -> logical-environment resolver.
 *
 * Single source of truth shared by:
 *  - admin/deploy.php  : resolve the deploy target docroot, the matching source
 *                        checkout (~/deploy/<env>) and the branch that env expects.
 *  - config.php        : per-environment session isolation (cookie name + storage).
 *
 * Background: the test server has been retired. A single o2switch account now
 * hosts three environments as separate subdomains, so isolation is by docroot,
 * not by Linux account anymore. Everything that used to differ "per server"
 * now has to be derived from the request host.
 *
 *   prod          age-of-olympia.net               ~/public_html               (main, tag)
 *   test/staging  test.age-of-olympia.net          ~/test.age-of-olympia.net   (staging)
 *   experimental  experimental.age-of-olympia.net  ~/experimental...           (saison-3)
 *
 * No absolute server paths are committed here: the checkout dir is derived from
 * the account HOME at runtime (HOME/deploy/<env>).
 */

if (!function_exists('aoo_deploy_env')) {

    /**
     * Resolve the environment descriptor for a given request host.
     *
     * @return array{env:string,branch:?string,is_prod:bool,session_name:string}
     */
    function aoo_deploy_env(?string $host): array
    {
        $host = strtolower(trim((string) $host));

        // Drop a port suffix (php -S localhost:80) and a leading "www." so prod
        // resolves with or without it.
        if (($pos = strpos($host, ':')) !== false) {
            $host = substr($host, 0, $pos);
        }
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        $targets = [
            'age-of-olympia.net' => [
                'env'          => 'prod',
                'branch'       => 'main',
                'is_prod'      => true,
                'session_name' => 'AOO_PROD',
            ],
            'test.age-of-olympia.net' => [
                'env'          => 'test',
                'branch'       => 'staging',
                'is_prod'      => false,
                'session_name' => 'AOO_TEST',
            ],
            'experimental.age-of-olympia.net' => [
                'env'          => 'experimental',
                // Default branch for experimental. CI deploys pass the chosen
                // branch explicitly (EXPERIMENTAL_BRANCH); this default only
                // applies to manual deploys and can be overridden server-side
                // with the AOO_EXPERIMENTAL_BRANCH env var.
                'branch'       => getenv('AOO_EXPERIMENTAL_BRANCH') ?: 'saison-3',
                'is_prod'      => false,
                'session_name' => 'AOO_EXP',
            ],
        ];

        if (isset($targets[$host])) {
            return $targets[$host];
        }

        // Unknown host (localhost, devcontainer, CI, php -S): a safe local
        // default with no production powers and a distinct session name.
        return [
            'env'          => 'local',
            'branch'       => null,
            'is_prod'      => false,
            'session_name' => 'AOO_LOCAL',
        ];
    }
}

if (!function_exists('aoo_app_background')) {

    /**
     * Resolve the body background image for the current environment.
     *
     * prod always uses bg.jpeg. Non-prod envs (test, experimental, local) get a
     * distinct image so it's obvious at a glance you're not on prod: it prefers
     * bg_<env>.jpeg, then the generic bg_test.jpeg, then falls back to bg.jpeg.
     * Each candidate is existence-checked against the docroot so we never point
     * at a missing file.
     *
     * @return string Absolute URL path of the background image.
     */
    function aoo_app_background(?string $host = null): string
    {
        $dir     = '/img/ui/bg';
        $default = $dir . '/bg.jpeg';

        $env = aoo_deploy_env($host ?? ($_SERVER['HTTP_HOST'] ?? null));
        if ($env['is_prod']) {
            return $default;
        }

        $docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        foreach (["{$dir}/bg_{$env['env']}.jpeg", "{$dir}/bg_test.jpeg"] as $candidate) {
            if ($docroot !== '' && is_file($docroot . $candidate)) {
                return $candidate;
            }
        }

        return $default;
    }
}
