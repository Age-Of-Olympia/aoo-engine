<?php

namespace Tests\Various;

use PHPUnit\Framework\TestCase;

/**
 * Aucune requête ne colle une collation sur une colonne pour la comparer.
 *
 * L'idiome `a COLLATE utf8mb4_general_ci = b COLLATE utf8mb4_general_ci` était
 * partout : il réconcilie deux collations utf8mb4 différentes, ce qui est le cas
 * en développement. Mais imposer une collation utf8mb4 à une colonne **latin1**
 * n'est pas un rapprochement, c'est une ERREUR — et les serveurs anciens gardent
 * de telles colonnes.
 *
 * Une migration est morte là-dessus en déploiement, sur un environnement dont
 * personne ne relit le jeu de caractères table par table. `CONVERT(x USING
 * utf8mb4)` des deux côtés compare dans le même jeu, quel que soit celui des
 * colonnes, et rend le même résultat quand elles sont déjà en utf8mb4.
 *
 * Ce cas garde l'idiome, pas la migration : le fautif se réécrit sans effort et
 * revient donc tout seul.
 */
class SqlDoesNotPinACollationTest extends TestCase
{
    /** Déclarer un jeu de caractères reste permis ; le plaquer pour comparer, non. */
    private const DECLARATIONS = '/CHARACTER SET|SET NAMES|COLLATE utf8mb4_bin/i';

    public function testNoQueryPinsACollationOnAColumn(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $file) {
            foreach (file($file) ?: [] as $no => $line) {
                if (!str_contains($line, 'COLLATE utf8mb4')) {
                    continue;
                }

                if (preg_match(self::DECLARATIONS, $line)) {
                    continue;
                }

                /* Un commentaire qui NOMME l'idiome pour le proscrire ne doit
                 * pas le déclencher — sans quoi expliquer la règle la viole. */
                if (preg_match('#^\s*(\*|//|/\*|\#)#', $line)) {
                    continue;
                }

                $short = substr($file, strlen(dirname(__DIR__, 2)) + 1);
                $offenders[] = $short . ':' . ($no + 1) . ' → ' . trim($line);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Ces comparaisons échoueront sur une colonne latin1.\n"
            . "Écrire CONVERT(a USING utf8mb4) = CONVERT(b USING utf8mb4).\n"
            . implode("\n", $offenders)
        );
    }

    /** @return iterable<string> */
    private function phpFiles(): iterable
    {
        $root = dirname(__DIR__, 2);

        foreach (['src', 'Classes', 'api', 'admin', 'scripts', 'tests'] as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                continue;
            }

            $tree = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($tree as $file) {
                /* Ce fichier cite l'idiome pour le décrire : s'auto-signaler
                 * ferait échouer la garde sur sa propre définition. */
                if ($file->getRealPath() === __FILE__) {
                    continue;
                }

                if ($file->isFile() && $file->getExtension() === 'php') {
                    yield $file->getPathname();
                }
            }
        }
    }
}
