<?php

namespace Tests\Various;

use PHPUnit\Framework\TestCase;

/**
 * Les classes référencées EN DUR dans la couche legacy existent.
 *
 * Les contrôleurs racine, scripts/, api/ et admin/ sont hors de portée de
 * PHPStan et des tests unitaires : un espace de noms mal orthographié
 * (`\App\Action\ActionFactory` pour `\App\Factory\ActionFactory`, go.php)
 * n'explose qu'en jeu, au premier joueur qui emprunte le chemin — ici une
 * descente sans escalier en Arcadia, en Fatal error.
 *
 * Le garde tokenise chaque fichier et vérifie que toute référence
 * pleinement qualifiée vers App\ ou Classes\ s'autocharge. Les chaînes et
 * commentaires ne produisent pas de T_NAME_FULLY_QUALIFIED : pas de faux
 * positifs à exclure.
 */
class LegacyControllerClassReferencesTest extends TestCase
{
    public function testFullyQualifiedReferencesResolve(): void
    {
        $root = dirname(__DIR__, 2);

        $files = glob($root . '/*.php') ?: [];
        foreach (['scripts', 'api', 'admin'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = (string) $file;
                }
            }
        }
        $this->assertNotEmpty($files);

        $unknown = [];

        foreach ($files as $file) {
            foreach (token_get_all((string) file_get_contents($file)) as $token) {
                if (!is_array($token) || $token[0] !== T_NAME_FULLY_QUALIFIED) {
                    continue;
                }

                $name = ltrim($token[1], '\\');
                if (!str_starts_with($name, 'App\\') && !str_starts_with($name, 'Classes\\')) {
                    continue;
                }

                if (
                    !class_exists($name) && !interface_exists($name)
                    && !trait_exists($name) && !enum_exists($name)
                ) {
                    $unknown[$name][] = substr($file, strlen($root) + 1) . ':' . $token[2];
                }
            }
        }

        $this->assertSame(
            [],
            $unknown,
            "Références pleinement qualifiées introuvables (espace de noms mal écrit ?) :\n"
                . print_r($unknown, true)
        );
    }
}
