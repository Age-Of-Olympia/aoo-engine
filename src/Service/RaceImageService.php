<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\Race;
use App\Enum\ImageType;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * Inventaire et gestion des avatars et portraits de race (img/avatars/<race>,
 * img/portraits/<race>) pour le panneau admin « Avatars & portraits » :
 * lister chaque image avec ses diagnostics (dimensions hors canon, portrait
 * sans miniature, image choisie par des joueurs mais absente du disque) et
 * son nombre d'utilisateurs (players.avatar / players.portrait stockent le
 * chemin complet), ajouter (redimensionnée au canon + miniature, numérotée
 * par le compteur de la race) et supprimer avec garde-fou.
 *
 * L'API historique /api/account/upload_image_api.php reste le canal des
 * flux joueur (scripts/account) ; ce service est le pendant panneau admin.
 */
class RaceImageService
{
    private ?EntityManagerInterface $entityManager;
    private string $root;

    public function __construct(?EntityManagerInterface $entityManager = null, ?string $root = null)
    {
        $this->entityManager = $entityManager;
        $this->root = $root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2));
    }

    /**
     * Races ayant un dossier d'images ou une fiche en base, filtrables par
     * sorte — la même séparation Races / Types de bâtiments que l'admin
     * (races.php × structure-types.php) : 'character', 'structure', ou null
     * pour tout. Un dossier orphelin (sans fiche) compte comme personnage.
     *
     * @return list<string>
     */
    public function raceNames(ImageType $type, ?string $kind = null): array
    {
        $kinds = [];
        foreach ($this->em()->getRepository(Race::class)->findAll() as $race) {
            $kinds[$race->getName()] = $race->isStructureKind() ? 'structure' : 'character';
        }

        $names = array_fill_keys(array_keys($kinds), true);
        foreach (glob($this->baseDir($type) . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $names[basename($dir)] = true;
        }

        $names = array_keys($names);
        if ($kind !== null) {
            $names = array_values(array_filter(
                $names,
                static fn (string $name): bool => ($kinds[$name] ?? 'character') === $kind
            ));
        }
        sort($names);
        return $names;
    }

    /**
     * Première image du stock (ordre naturel, miniatures exclues) — LA
     * vignette des listes d'admin Races et Types, et le sprite par défaut
     * des structures posées (BuildingService::resolveAvatar) : le même
     * visuel partout. Chemin relatif au docroot, ou null si stock vide.
     */
    public function firstImagePath(ImageType $type, string $race): ?string
    {
        $this->assertRace($race);
        $dir = $this->raceDir($type, $race);

        $files = [];
        foreach (is_dir($dir) ? scandir($dir) : [] as $fileName) {
            if (preg_match('/\.(png|jpe?g|webp|gif)$/i', $fileName) && !str_contains($fileName, '_mini.')) {
                $files[] = $fileName;
            }
        }
        if ($files === []) {
            return null;
        }
        sort($files, SORT_NATURAL);

        return $this->relativeDir($type, $race) . '/' . $files[0];
    }

    /**
     * Inventaire d'un (type, race) : une entrée par image principale (les
     * miniatures _mini sont rattachées à leur portrait), plus les chemins
     * choisis par des joueurs dont le fichier a disparu.
     *
     * @return list<array{file: string, width: int, height: int, usage: int,
     *                    hasMini: bool, problems: list<string>, missing: bool}>
     */
    public function inventory(ImageType $type, string $race): array
    {
        $this->assertRace($race);
        $dir = $this->raceDir($type, $race);
        [$canonWidth, $canonHeight] = $type->dimensions();

        $files = [];
        $minis = [];
        foreach (is_dir($dir) ? scandir($dir) : [] as $fileName) {
            if (!preg_match('/\.(png|jpe?g|webp|gif)$/i', $fileName)) {
                continue;
            }
            if (str_contains($fileName, '_mini.')) {
                $minis[preg_replace('/_mini\./', '.', $fileName)] = true;
                continue;
            }
            $files[] = $fileName;
        }
        sort($files, SORT_NATURAL);

        $usage = $this->usageByPath($type);
        $prefix = $this->relativeDir($type, $race) . '/';

        $entries = [];
        foreach ($files as $fileName) {
            $size = @getimagesize($dir . '/' . $fileName);
            $problems = [];
            if (!$size) {
                $problems[] = 'image illisible';
            } elseif ($size[0] !== $canonWidth || $size[1] !== $canonHeight) {
                $problems[] = "dimensions {$size[0]}×{$size[1]} — canon {$canonWidth}×{$canonHeight}";
            }
            if ($type === ImageType::PORTRAIT && !isset($minis[$fileName])) {
                $problems[] = 'miniature _mini absente (listes de sélection)';
            }

            $entries[] = [
                'file'     => $fileName,
                'width'    => $size[0] ?? 0,
                'height'   => $size[1] ?? 0,
                'usage'    => $usage[$prefix . $fileName] ?? 0,
                'hasMini'  => isset($minis[$fileName]),
                'problems' => $problems,
                'missing'  => false,
            ];
        }

        // Chemins choisis par des joueurs dont le fichier a disparu
        $existing = array_fill_keys($files, true);
        foreach ($usage as $path => $count) {
            if (str_starts_with($path, $prefix) && !isset($existing[substr($path, strlen($prefix))])) {
                $entries[] = [
                    'file' => substr($path, strlen($prefix)), 'width' => 0, 'height' => 0,
                    'usage' => $count, 'hasMini' => false,
                    'problems' => ["choisie par {$count} joueur(s) mais absente du disque — image cassée en jeu"],
                    'missing' => true,
                ];
            }
        }

        return $entries;
    }

    /**
     * Ajoute une image : redimensionnée aux dimensions canoniques du type
     * (+ miniature pour les portraits), numérotée par le compteur de la
     * race — même contrat que l'API d'upload historique.
     *
     * @return string nom de fichier créé
     */
    public function upload(ImageType $type, string $raceName, string $tmpPath): string
    {
        $race = $this->em()->getRepository(Race::class)->findOneBy(['name' => $raceName]);
        if ($race === null) {
            throw new RuntimeException("Race inconnue : {$raceName}.");
        }
        if (!is_file($tmpPath) || filesize($tmpPath) > TileCatalogService::IMAGE_MAX_BYTES) {
            throw new RuntimeException('Fichier absent ou trop volumineux (max 4 Mo).');
        }

        $dir = $this->raceDir($type, $raceName);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer ' . $dir);
        }

        $number = $type === ImageType::PORTRAIT ? $race->getPortraitNextNumber() : $race->getAvatarNextNumber();
        $info = @getimagesize($tmpPath);
        $extension = self::outputExtension(is_array($info) ? (string) $info['mime'] : '');
        $fileName = $type->buildFilename($number, $extension);

        [$width, $height] = $type->dimensions();
        $this->resize($tmpPath, $dir . '/' . $fileName, $width, $height);

        if ($miniDims = $type->miniDimensions()) {
            $this->resize($tmpPath, $dir . '/' . $type->buildMiniFilename($number, $extension), $miniDims[0], $miniDims[1]);
        }

        $type === ImageType::PORTRAIT ? $race->incrementPortraitNextNumber() : $race->incrementAvatarNextNumber();
        $this->em()->persist($race);
        $this->em()->flush();

        return $fileName;
    }

    /**
     * Adopte dans le stock une image HÉRITÉE (sprite de mur du même nom,
     * webp dédié — bouton « Copier dans le stock » de Bâtiments → Images) :
     * copie VERBATIM, numérotée par le compteur — pas le pipeline upload,
     * dont le redimensionnement dégraderait un sprite de plateau déjà au
     * bon format.
     *
     * @param string $sourceRelPath chemin relatif au docroot (img/…)
     * @return string nom de fichier créé
     */
    public function adopt(ImageType $type, string $raceName, string $sourceRelPath): string
    {
        $race = $this->em()->getRepository(Race::class)->findOneBy(['name' => $raceName]);
        if ($race === null) {
            throw new RuntimeException("Race inconnue : {$raceName}.");
        }
        if (str_contains($sourceRelPath, '..')
            || !preg_match('#^img/[a-zA-Z0-9_/.-]+\.(png|jpe?g|webp|gif)$#', $sourceRelPath)
            || !is_file($this->root . '/' . $sourceRelPath)) {
            throw new RuntimeException('Image source introuvable : ' . $sourceRelPath);
        }

        $dir = $this->raceDir($type, $raceName);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer ' . $dir);
        }

        $number = $type === ImageType::PORTRAIT ? $race->getPortraitNextNumber() : $race->getAvatarNextNumber();
        $fileName = $number . '.' . strtolower(pathinfo($sourceRelPath, PATHINFO_EXTENSION));
        if (!copy($this->root . '/' . $sourceRelPath, $dir . '/' . $fileName)) {
            throw new RuntimeException('Copie impossible : ' . $dir . '/' . $fileName);
        }

        $type === ImageType::PORTRAIT ? $race->incrementPortraitNextNumber() : $race->incrementAvatarNextNumber();
        $this->em()->persist($race);
        $this->em()->flush();

        return $fileName;
    }

    /**
     * Déplace une image (et sa miniature) vers une autre race, renumérotée
     * par le compteur de la race cible — même contrat que Tuiles & images :
     * refus tant que des joueurs l'utilisent (leur players.avatar/portrait
     * pointerait vers un fichier disparu), le popover « Joueurs » dit qui
     * changer d'abord.
     *
     * @return string nom de fichier créé dans la race cible
     */
    public function move(ImageType $type, string $fromRace, string $fileName, string $toRace): string
    {
        $this->assertRace($fromRace);
        if ($toRace === $fromRace) {
            throw new RuntimeException('Race cible identique à la race actuelle.');
        }
        $race = $this->em()->getRepository(Race::class)->findOneBy(['name' => $toRace]);
        if ($race === null) {
            throw new RuntimeException("Race inconnue : {$toRace}.");
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+\.(png|jpe?g|webp|gif)$/i', $fileName) || str_contains($fileName, '..')) {
            throw new RuntimeException('Nom de fichier invalide.');
        }

        $sourceDir = $this->raceDir($type, $fromRace);
        if (!is_file($sourceDir . '/' . $fileName)) {
            throw new RuntimeException("Image inconnue : {$fileName}.");
        }

        $path = $this->relativeDir($type, $fromRace) . '/' . $fileName;
        $usage = $this->usageByPath($type)[$path] ?? 0;
        if ($usage > 0) {
            throw new RuntimeException("« {$fileName} » est l'image de {$usage} joueur(s) — changez-les d'abord.");
        }

        $targetDir = $this->raceDir($type, $toRace);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Impossible de créer ' . $targetDir);
        }

        $number = $type === ImageType::PORTRAIT ? $race->getPortraitNextNumber() : $race->getAvatarNextNumber();
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $newName = $number . '.' . $extension;
        if (!rename($sourceDir . '/' . $fileName, $targetDir . '/' . $newName)) {
            throw new RuntimeException('Déplacement impossible : ' . $fileName);
        }
        $mini = preg_replace('/\.([a-zA-Z]+)$/', '_mini.$1', $fileName);
        if (is_string($mini) && is_file($sourceDir . '/' . $mini)) {
            rename($sourceDir . '/' . $mini, $targetDir . '/' . $number . '_mini.' . $extension);
        }

        $type === ImageType::PORTRAIT ? $race->incrementPortraitNextNumber() : $race->incrementAvatarNextNumber();
        $this->em()->persist($race);
        $this->em()->flush();

        return $newName;
    }

    /**
     * Supprime une image (et sa miniature). Refus tant que des joueurs
     * l'ont comme avatar/portrait : ils se retrouveraient avec une image
     * cassée en jeu.
     */
    public function delete(ImageType $type, string $race, string $fileName): void
    {
        $this->assertRace($race);
        if (!preg_match('/^[a-zA-Z0-9_.-]+\.(png|jpe?g|webp|gif)$/i', $fileName) || str_contains($fileName, '..')) {
            throw new RuntimeException('Nom de fichier invalide.');
        }

        $dir = $this->raceDir($type, $race);
        if (!is_file($dir . '/' . $fileName)) {
            throw new RuntimeException("Image inconnue : {$fileName}.");
        }

        $path = $this->relativeDir($type, $race) . '/' . $fileName;
        $usage = $this->usageByPath($type)[$path] ?? 0;
        if ($usage > 0) {
            throw new RuntimeException("« {$fileName} » est l'image de {$usage} joueur(s) — impossible de la supprimer.");
        }

        if (!unlink($dir . '/' . $fileName)) {
            throw new RuntimeException('Suppression impossible : ' . $fileName);
        }
        $mini = preg_replace('/\.([a-zA-Z]+)$/', '_mini.$1', $fileName);
        if (is_string($mini) && is_file($dir . '/' . $mini)) {
            unlink($dir . '/' . $mini);
        }
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string, int> chemin relatif (players.avatar/portrait) => joueurs */
    /**
     * Qui utilise quelle image — pour le popover « Joueurs » de la page
     * d'admin (le compte seul ne dit pas QUI changer avant suppression).
     *
     * @return array<string, list<string>> chemin relatif => « Nom (mat.X) »
     */
    public function usersByPath(ImageType $type): array
    {
        $column = $type === ImageType::PORTRAIT ? 'portrait' : 'avatar';
        $rows = $this->em()->getConnection()->fetchAllAssociative(
            "SELECT {$column} AS path, id, name FROM players WHERE {$column} != '' ORDER BY name"
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['path']][] = $row['name'] . ' (mat.' . $row['id'] . ')';
        }

        return $map;
    }

    private function usageByPath(ImageType $type): array
    {
        $column = $type === ImageType::PORTRAIT ? 'portrait' : 'avatar';
        $rows = $this->em()->getConnection()->fetchAllKeyValue(
            "SELECT {$column}, COUNT(*) FROM players WHERE {$column} != '' GROUP BY {$column}"
        );
        return array_map('intval', $rows);
    }

    /**
     * Extension de sortie selon le format source : jpeg reste jpeg ;
     * png, webp et gif sortent en png — le seul format de sortie à canal
     * alpha dont GD dispose partout (pas d'imagewebp ici), un ré-encodage
     * jpeg aplatirait la transparence sur fond noir. Le gif perd de toute
     * façon son animation au redimensionnement.
     */
    private static function outputExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpeg',
            'image/png', 'image/webp', 'image/gif' => 'png',
            default => throw new RuntimeException('Image illisible (png, jpeg, webp ou gif attendu).'),
        };
    }

    /** Redimensionne vers les dimensions exactes ; le format d'écriture suit l'extension de destination. */
    private function resize(string $sourcePath, string $destinationPath, int $width, int $height): void
    {
        $info = @getimagesize($sourcePath);
        $source = match ($info['mime'] ?? '') {
            'image/png'  => imagecreatefrompng($sourcePath),
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            'image/gif'  => imagecreatefromgif($sourcePath),
            default      => false,
        };
        if (!$source) {
            throw new RuntimeException('Image illisible (png, jpeg, webp ou gif attendu).');
        }
        if (!imageistruecolor($source)) {
            imagepalettetotruecolor($source);
        }

        $destination = imagecreatetruecolor($width, $height);
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        $written = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION)) === 'png'
            ? imagepng($destination, $destinationPath)
            : imagejpeg($destination, $destinationPath, 90);
        if (!$written) {
            throw new RuntimeException('Écriture impossible : ' . $destinationPath);
        }
    }

    private function baseDir(ImageType $type): string
    {
        return $type === ImageType::PORTRAIT ? $this->root . '/img/portraits' : $this->root . '/img/avatars';
    }

    private function raceDir(ImageType $type, string $race): string
    {
        return $type->uploadDirectory($race, $this->root);
    }

    private function relativeDir(ImageType $type, string $race): string
    {
        return ($type === ImageType::PORTRAIT ? 'img/portraits/' : 'img/avatars/') . $race;
    }

    private function assertRace(string $race): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $race)) {
            throw new RuntimeException('Nom de race invalide : ' . $race);
        }
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManager ??= EntityManagerFactory::getEntityManager();
    }
}
